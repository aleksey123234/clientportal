<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Services\CifEligibility;
use App\Services\CifPdfGenerator;
use App\Services\CifProgress;
use App\Services\CifTimeline;
use App\Services\CifValidation;
use App\Services\CifVisibility;
use App\Services\Csrf;
use App\Support\Log;
use App\Support\SecureDownload;

/**
 * CIF (Client Information Form) controller.
 *
 * Finish validation: CifValidation. PDF generation stays here (ARCHITECTURE backlog).
 *
 * @see src/Services/CifValidation.php
 * @see src/views/cif/cif-page.php
 */
class CifController extends BaseController
{
    public function __construct()
    {
        require_once __DIR__ . '/../config/service_colors.php';
    }

    /* ================================================================
     *  Main entry point
     * ================================================================ */
    public function index(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        // Handle POST (AJAX save or PDF generate)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? $_GET['action'] ?? '';
            if ($action === 'generate') {
                $this->generatePdfs($userId);
                return;
            }
            $this->saveResponses($userId);
            return;
        }

        // Handle GET actions (download)
        $action = $_GET['action'] ?? '';
        if ($action === 'download') {
            $this->downloadPdf($userId);
            return;
        }

        // GET — load everything and render
        $questionSchema = require __DIR__ . '/../config/cif_questions.php';
        $userForms      = $this->getUserForms($userId);
        $responseRow    = $this->loadResponseRow($userId);
        $savedData      = $responseRow['data'];
        $completedAt    = $responseRow['completed_at'];

        // Pre-populate from Profile data where CIF fields are still empty
        $profileDefaults = $this->loadProfileDefaults($userId);
        foreach ($profileDefaults as $key => $val) {
            if (is_array($val)) {
                // For table-type fields (addresses), only set if no saved data at all
                if (empty($savedData[$key])) {
                    $savedData[$key] = $val;
                }
            } elseif (!isset($savedData[$key]) || $savedData[$key] === '') {
                $savedData[$key] = $val;
            }
        }

        $progress       = $this->calculateProgress($questionSchema, $userForms, $savedData);
        $serviceColors  = SERVICE_COLORS;
        $generatedPdfs  = $this->loadGeneratedPdfs($userId);
        $splitCitizenship = CifEligibility::isSplitCitizenshipUi($userForms);
        $csrfToken      = Csrf::ensureToken();

        $pageTitle  = 'Client Information Form';
        $activePage = 'cif';

        ob_start();
        require __DIR__ . '/../views/cif/cif-page.php';
        $content = ob_get_clean();

        require __DIR__ . '/../views/layouts/main.php';
    }

    /* ================================================================
     *  Determine which form types apply to this user
     * ================================================================ */
    private function getUserForms(int $userId): array
    {
        $sql = "SELECT DISTINCT sc.service_type
                FROM user_services us
                JOIN service_costs sc ON sc.id = us.service_cost_id
                WHERE us.user_id = ? AND sc.is_extra = 0
                ORDER BY sc.service_type";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([$userId]);
        $types = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Exclusion rules:
        // If has criminal-rehab → skip trp
        // If has pardon → skip expunging
        $hasCR     = in_array('criminal-rehab', $types, true);
        $hasPardon = in_array('pardon', $types, true);

        $filtered = [];
        foreach ($types as $t) {
            if ($t === 'trp' && $hasCR) continue;
            if ($t === 'expunging' && $hasPardon) continue;
            if ($t === 'nexus') continue; // NEXUS has no CIF form
            $filtered[] = $t;
        }

        // Prefer Waiver / CR first in badge order
        usort($filtered, function ($a, $b) {
            $rank = [
                'waiver' => 0,
                'waiver-renewal' => 1,
                'criminal-rehab' => 2,
                'trp' => 3,
                'pardon' => 4,
                'expunging' => 5,
            ];
            return ($rank[$a] ?? 50) <=> ($rank[$b] ?? 50);
        });

        return $filtered;
    }

    /* ================================================================
     *  Pre-populate CIF fields from Profile data
     *  Maps known Profile fields → CIF question keys
     * ================================================================ */
    private function loadProfileDefaults(int $userId): array
    {
        $defaults = [];

        // User table: first_name, middle_name, last_name
        $stmt = $this->db()->prepare('SELECT first_name, middle_name, last_name FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($user) {
            $defaults['first_name']   = $user['first_name'] ?? '';
            $defaults['middle_names'] = $user['middle_name'] ?? '';
            $defaults['last_name']    = $user['last_name'] ?? '';
        }

        // Client profile: dob, gender
        $stmt = $this->db()->prepare('SELECT dob FROM client_profiles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $profile = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($profile) {
            $defaults['dob'] = $profile['dob'] ?? '';
        }

        // Primary phone
        $stmt = $this->db()->prepare(
            'SELECT number FROM client_phones WHERE user_id = ? AND is_main = 1 LIMIT 1'
        );
        $stmt->execute([$userId]);
        $phone = $stmt->fetchColumn();
        if ($phone) {
            $defaults['primary_phone'] = $phone;
        }

        // Living address → first row of addresses table only
        $stmt = $this->db()->prepare(
            'SELECT street, unit, city, province_state, postal_code, country
             FROM client_addresses WHERE user_id = ? AND address_type = ? LIMIT 1'
        );
        $stmt->execute([$userId, 'living']);
        $addr = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($addr) {
            $street = trim(($addr['unit'] ? $addr['unit'] . '-' : '') . ($addr['street'] ?? ''));
            $defaults['addresses'] = [[
                'street'  => $street,
                'city'    => $addr['city'] ?? '',
                'prov'    => $addr['province_state'] ?? '',
                'country' => $addr['country'] ?? '',
                'from'    => '',
                'to'      => 'Present',
            ]];
        }

        return $defaults;
    }

    /* ================================================================
     *  Load / Save responses
     * ================================================================ */

    /**
     * @return array{data: array, completed_at: ?string}
     */
    private function loadResponseRow(int $userId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT response_data, progress, completed_at FROM cif_responses WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return ['data' => [], 'completed_at' => null];
        }

        $data = json_decode((string) ($row['response_data'] ?? ''), true);
        if (!is_array($data)) {
            $data = [];
        }
        $data = CifTimeline::migrateResponseData($data);

        $val = $row['completed_at'] ?? null;
        $completedAt = ($val !== null && $val !== '') ? (string) $val : null;

        return ['data' => $data, 'completed_at' => $completedAt];
    }

    private function saveResponses(int $userId): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }
        if (!is_array($input) || empty($input['data'])) {
            Response::jsonFail('Invalid data');
        }

        if (!Csrf::validate(isset($input[Csrf::FIELD]) ? (string) $input[Csrf::FIELD] : null)) {
            Response::jsonFail('Security token mismatch.');
        }

        $responseData = CifTimeline::migrateResponseData($input['data']);
        $questionSchema = require __DIR__ . '/../config/cif_questions.php';
        $userForms = $this->getUserForms($userId);

        if (!CifEligibility::isSplitCitizenshipUi($userForms)) {
            unset($responseData['green_card']);
        }

        $isFinish = !empty($input['finish']);
        if ($isFinish) {
            $errors = CifValidation::validateForFinish($questionSchema, $userForms, $responseData);
            if (!empty($errors)) {
                $elig = CifEligibility::validate($userForms, $responseData);
                $extra = ['errors' => $errors];
                $eligKeys = CifEligibility::keys($elig);
                if (!empty($eligKeys)) {
                    $extra['eligibility_keys'] = $eligKeys;
                }
                Response::jsonFail($errors[0], 400, $extra);
            }
        }

        $progress = $this->calculateProgress($questionSchema, $userForms, $responseData);
        $completedAt = CifProgress::completedAtForSave($progress);

        $json = json_encode($responseData, JSON_UNESCAPED_UNICODE);

        // UPSERT mirrors CifProgress::nextCompletedAt (see unit tests).
        $sql = "INSERT INTO cif_responses (user_id, response_data, progress, completed_at)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    response_data = VALUES(response_data),
                    progress      = VALUES(progress),
                    completed_at  = IF(
                        VALUES(progress) >= 100,
                        COALESCE(completed_at, VALUES(completed_at)),
                        completed_at
                    )";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([$userId, $json, $progress, $completedAt]);

        Response::jsonOk(['progress' => $progress]);
    }

    /* ================================================================
     *  Calculate progress percentage
     * ================================================================ */
    private function calculateProgress(array $schema, array $userForms, array $data): int
    {
        $total = 0;
        $filled = 0;

        foreach ($schema['sections'] as $section) {
            foreach ($section['questions'] as $q) {
                $applicable = array_intersect($q['forms'], $userForms);
                if (empty($applicable)) continue;
                if (!CifVisibility::isVisible($q, $data, $userForms)) continue;

                $required = !empty($q['required']);
                if (!empty($q['required_when'])) {
                    $required = CifVisibility::rulesMatch($q['required_when'], $data);
                }
                if (!$required) continue;

                $total++;
                $val = $data[$q['key']] ?? null;
                if ($q['type'] === 'table') {
                    if (is_array($val) && count($val) > 0) {
                        $hasData = false;
                        foreach ($val as $row) {
                            foreach ($row as $cell) {
                                if (!empty($cell)) {
                                    $hasData = true;
                                    break 2;
                                }
                            }
                        }
                        if ($hasData) $filled++;
                    }
                } else {
                    if ($val !== null && $val !== '' && $val !== []) {
                        $filled++;
                    }
                }
            }
        }

        return $total > 0 ? (int) round(($filled / $total) * 100) : 100;
    }

    /* ================================================================
     *  Load generated PDFs list
     * ================================================================ */
    private function loadGeneratedPdfs(int $userId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT * FROM cif_generated_pdfs WHERE user_id = ? ORDER BY generated_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /* ================================================================
     *  Generate filled PDF forms
     * ================================================================ */
    private function generatePdfs(int $userId): void
    {
        $body = file_get_contents('php://input');
        $json = json_decode($body, true);
        $csrfToken = null;
        if (is_array($json) && isset($json[Csrf::FIELD])) {
            $csrfToken = (string) $json[Csrf::FIELD];
        } else {
            $csrfToken = isset($_POST[Csrf::FIELD]) ? (string) $_POST[Csrf::FIELD] : null;
        }
        if (!Csrf::validate($csrfToken)) {
            Response::jsonFail('Security token mismatch.');
        }

        $savedData = $this->loadResponseRow($userId)['data'];
        if (empty($savedData)) {
            Response::jsonFail('No saved data found. Please save the form first.');
        }

        $userForms = $this->getUserForms($userId);
        $questionSchema = require __DIR__ . '/../config/cif_questions.php';

        $stmt = $this->db()->prepare('SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        $clientName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        if ($clientName === '') {
            $clientName = 'Client #' . $userId;
        }

        $uploadBase = $_ENV['UPLOAD_BASE_PATH'] ?? (realpath(__DIR__ . '/../../storage') ?: __DIR__ . '/../../storage');
        $outputDir = rtrim($uploadBase, '/\\') . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . 'cif';

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        foreach (glob($outputDir . DIRECTORY_SEPARATOR . '*.pdf') as $oldFile) {
            @unlink($oldFile);
        }

        $this->db()->prepare("DELETE FROM cif_generated_pdfs WHERE user_id = ?")->execute([$userId]);

        $generated = [];

        foreach ($userForms as $formType) {
            try {
                $fileName = 'CIF_' . str_replace('-', '_', $formType) . '_' . $userId . '.pdf';
                $outputPath = $outputDir . DIRECTORY_SEPARATOR . $fileName;

                $pdf = new CifPdfGenerator($formType, $clientName);
                $pdf->generate($formType, $savedData, $questionSchema, $outputPath);

                $pageCount = 1;
                try {
                    $counter = new \setasign\Fpdi\Fpdi();
                    $pageCount = $counter->setSourceFile($outputPath);
                } catch (\Exception $e) {
                    // Fallback — page count stays 1
                }

                $relPath = $userId . '/cif/' . $fileName;
                $stmt = $this->db()->prepare(
                    "INSERT INTO cif_generated_pdfs (user_id, form_type, file_path, page_count)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([$userId, $formType, $relPath, $pageCount]);

                $generated[] = [
                    'form' => $formType,
                    'file' => $fileName,
                    'pages' => $pageCount,
                    'id' => (int) $this->db()->lastInsertId(),
                ];
            } catch (\Exception $e) {
                Log::app()->error("CIF PDF generation error for $formType: " . $e->getMessage());
                $generated[] = ['form' => $formType, 'error' => $e->getMessage()];
            }
        }

        Response::jsonOk(['generated' => $generated]);
    }

    /* ================================================================
     *  Download a generated CIF PDF
     *  GET /cif?action=download&id={pdfId}
     * ================================================================ */
    private function downloadPdf(int $userId): void
    {
        $pdfId = (int) ($_GET['id'] ?? 0);
        if (!$pdfId) {
            http_response_code(400);
            echo 'Missing PDF id.';
            return;
        }

        $stmt = $this->db()->prepare(
            "SELECT * FROM cif_generated_pdfs WHERE id = ? AND user_id = ? LIMIT 1"
        );
        $stmt->execute([$pdfId, $userId]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$record) {
            http_response_code(404);
            echo 'PDF not found.';
            return;
        }

        $uploadBase = $_ENV['UPLOAD_BASE_PATH'] ?? (realpath(__DIR__ . '/../../storage') ?: __DIR__ . '/../../storage');
        $absPath = SecureDownload::resolveUnderBase((string) $uploadBase, (string) ($record['file_path'] ?? ''));

        if ($absPath === null) {
            http_response_code(404);
            echo 'File not found on disk.';
            return;
        }

        $fileName = basename((string) $record['file_path']);
        SecureDownload::sendFile($absPath, 'application/pdf', $fileName, 'inline');
    }
}