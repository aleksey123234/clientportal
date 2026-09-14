<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Services\Csrf;
use App\Services\DocumentCatalog;
use App\Services\DocumentUploadService;
use App\Services\EmailService;
use App\Services\ServiceEmailCta;
use App\Support\Html;
use App\Support\HtmlSanitizer;
use App\Support\Log;
use App\Support\SecureDownload;

/**
 * Documents page controller.
 *
 * Routes:
 *   GET  /documents          — show page with service tabs, doc lists
 *   POST /documents          — upload a file or delete a recently-uploaded file
 *
 * @see src/Services/DocumentCatalog.php
 * @see src/Services/DocumentUploadService.php
 * @see src/views/documents/documents-page.php
 */
class DocumentsController extends BaseController
{
    public function index(): void
    {
        Csrf::ensureToken();

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $error = null;
        $saved = false;

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $uri = strtok($_SERVER['REQUEST_URI'], '?');
            if (rtrim($uri, '/') === '/documents/download') {
                $this->handleDownload($userId);
                return;
            }
            if (rtrim($uri, '/') === '/documents/email') {
                $this->handleEmailView($userId);
                return;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST[Csrf::FIELD] ?? null)) {
                $error = 'Invalid request. Please try again.';
            } else {
                $action = $_POST['action'] ?? '';

                switch ($action) {
                    case 'upload':
                        [$saved, $error] = $this->handleUpload($userId);
                        break;

                    case 'delete':
                        [$saved, $error] = $this->handleDelete($userId);
                        break;
                }

                if ($saved) {
                    $_SESSION['doc_flash_success'] = true;
                    Response::redirect('/documents');
                }
                if ($error) {
                    $_SESSION['doc_flash_error'] = $error;
                    Response::redirect('/documents');
                }
            }
        }

        if (!empty($_SESSION['doc_flash_success'])) {
            $saved = true;
            unset($_SESSION['doc_flash_success']);
        }
        if (!empty($_SESSION['doc_flash_error'])) {
            $error = $_SESSION['doc_flash_error'];
            unset($_SESSION['doc_flash_error']);
        }

        $stmt = $this->db()->prepare(
            'SELECT * FROM documents WHERE user_id = ? ORDER BY service_type, doc_key, uploaded_at DESC'
        );
        $stmt->execute([$userId]);
        $allDocs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $uploaded = [];
        foreach ($allDocs as $doc) {
            $uploaded[$doc['service_type']][$doc['doc_key']][] = $doc;
        }

        $inboxEmails = $this->loadInboxEmails($userId);

        $services = DocumentCatalog::services();
        $pageTitle = 'Documents / Inbox';
        $activePage = 'documents';

        $content = $this->render(
            __DIR__ . '/../views/documents/documents-page.php',
            compact('pageTitle', 'activePage', 'services', 'uploaded', 'error', 'saved', 'inboxEmails')
        );

        require __DIR__ . '/../views/layouts/main.php';
    }

    /** @return array{0: bool, 1: ?string} */
    private function handleUpload(int $userId): array
    {
        $result = DocumentUploadService::store(
            $this->db(),
            $userId,
            trim($_POST['service_type'] ?? ''),
            trim($_POST['doc_key'] ?? ''),
            $_FILES['doc_file'] ?? [],
            trim($_POST['file_name'] ?? '')
        );

        if (!$result['ok']) {
            return [false, $result['error']];
        }

        try {
            $appName = $_ENV['APP_NAME'] ?? 'Client Portal';
            $userName = $_SESSION['user_name'] ?? 'Client';
            $svcLabel = DocumentCatalog::SERVICES[$result['service_type']]['label'] ?? $result['service_type'];
            $title = Html::e($result['title'] ?? '');
            $safeUser = Html::e((string) $userName);
            $safeSvc = Html::e((string) $svcLabel);
            EmailService::notifyUser(
                $this->db(),
                $userId,
                "Document Uploaded — {$appName}",
                "<p>Hi <strong>{$safeUser}</strong>,</p>
                 <p>Your document <strong>{$title}</strong> for <em>{$safeSvc}</em> has been uploaded successfully.</p>
                 <p style='color:#888;font-size:13px;'>If you did not perform this action, please contact support.</p>",
                'documents'
            );
        } catch (\Throwable $e) {
            Log::app()->error('Document upload notification error: ' . $e->getMessage());
        }

        return [true, null];
    }

    /** @return array{0: bool, 1: ?string} */
    private function handleDelete(int $userId): array
    {
        $docId = (int) ($_POST['doc_id'] ?? 0);
        if (!$docId) {
            return [false, 'Invalid document.'];
        }

        $stmt = $this->db()->prepare(
            'SELECT * FROM documents WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$docId, $userId]);
        $doc = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$doc) {
            return [false, 'Document not found.'];
        }

        $uploadedAt = strtotime($doc['uploaded_at']);
        if (time() - $uploadedAt > 600) {
            return [false, 'Delete window expired. Documents can only be removed within 10 minutes of upload.'];
        }

        if (file_exists($doc['file_path'])) {
            unlink($doc['file_path']);
        }

        $this->db()->prepare('DELETE FROM documents WHERE id = ?')->execute([$docId]);

        return [true, null];
    }

    private function handleDownload(int $userId): void
    {
        $docId = (int) ($_GET['id'] ?? 0);
        if (!$docId) {
            http_response_code(400);
            echo 'Invalid document ID.';
            exit;
        }

        $stmt = $this->db()->prepare(
            'SELECT * FROM documents WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$docId, $userId]);
        $doc = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$doc) {
            http_response_code(404);
            echo 'Document not found.';
            exit;
        }

        $uploadBase = (string) ($_ENV['UPLOAD_BASE_PATH'] ?? getenv('UPLOAD_BASE_PATH') ?: '');
        $absPath = SecureDownload::resolveUnderBase($uploadBase, (string) ($doc['file_path'] ?? ''));

        if ($absPath === null) {
            http_response_code(404);
            echo 'File not found on disk.';
            exit;
        }

        $mime = $doc['mime_type'] ?: \mime_content_type($absPath) ?: 'application/octet-stream';
        $fileName = basename((string) ($doc['file_name'] ?? 'download'));
        SecureDownload::sendFile($absPath, $mime, $fileName, 'attachment');
    }

    private function handleEmailView(int $userId): void
    {
        $emailId = (int) ($_GET['id'] ?? 0);
        if (!$emailId) {
            Response::jsonFail('Invalid email ID.');
        }

        $sql = "SELECT seh.id, seh.subject, seh.body, seh.sent_at, seh.sent_by,
                       seh.read_at, seh.action_section, sc.service_type, sc.label AS service_label
                FROM service_email_history seh
                JOIN user_services us ON us.id = seh.user_service_id
                JOIN service_costs sc ON sc.id = us.service_cost_id
                WHERE seh.id = ? AND us.user_id = ?
                LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([$emailId, $userId]);
        $email = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$email) {
            Response::jsonFail('Email not found.', 404);
        }

        $markSql = "UPDATE service_email_history
                    SET read_at = NOW()
                    WHERE id = ? AND read_at IS NULL";
        $this->db()->prepare($markSql)->execute([$emailId]);

        $readAt = $email['read_at'] ?? date('Y-m-d H:i:s');
        $actionSection = $email['action_section'] ?? null;
        $safe = HtmlSanitizer::clean($email['body'] ?? '');
        $body = ServiceEmailCta::appendToBody($safe, $actionSection);

        Response::jsonOk([
            'id' => (int) $email['id'],
            'subject' => $email['subject'],
            'body' => $body,
            'action_section' => $actionSection,
            'sent_at' => $email['sent_at'],
            'sent_by' => $email['sent_by'],
            'service_type' => $email['service_type'],
            'service_label' => $email['service_label'],
            'read_at' => $readAt,
        ]);
    }

    private function loadInboxEmails(int $userId): array
    {
        $sql = "SELECT seh.*, sc.service_type, sc.label AS service_label
                FROM service_email_history seh
                JOIN user_services us ON us.id = seh.user_service_id
                JOIN service_costs sc ON sc.id = us.service_cost_id
                WHERE us.user_id = ?
                ORDER BY seh.sent_at DESC";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['service_type']][] = $r;
        }
        return $grouped;
    }
}
