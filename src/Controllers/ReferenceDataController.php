<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Csrf;

final class ReferenceDataController extends BaseController
{
    private const TYPES = [
        'canada_court' => ['label' => 'Canada Court Locations', 'country' => 'CA'],
        'usa_court' => ['label' => 'USA Court Locations', 'country' => 'US'],
        'sbc' => ['label' => 'SBC Locations', 'country' => 'US'],
        'lprc' => ['label' => 'LPRC Locations', 'country' => 'CA'],
    ];

    public function index(): void
    {
        $type = (string) ($_GET['type'] ?? 'canada_court');
        if (!isset(self::TYPES[$type])) {
            $type = 'canada_court';
        }
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'region' => max(0, (int) ($_GET['region'] ?? 0)),
            'city' => max(0, (int) ($_GET['city'] ?? 0)),
            'covered_city' => max(0, (int) ($_GET['covered_city'] ?? 0)),
            'status' => $this->allowed((string) ($_GET['status'] ?? 'active'), ['all', 'active', 'inactive'], 'active'),
            'review' => $this->allowed((string) ($_GET['review'] ?? 'all'), ['all', 'yes', 'no'], 'all'),
            'fee' => $this->allowed((string) ($_GET['fee'] ?? 'all'), ['all', 'free', 'paid'], 'all'),
            'method' => $this->allowed((string) ($_GET['method'] ?? ''), ['', 'fingerprint_by_mail', 'online_name_based', 'mail_name_based', 'livescan_in_person', 'fax_or_email', 'other'], ''),
        ];
        $perPage = $this->allowedInt((int) ($_GET['per_page'] ?? 50), [25, 50, 100, 200], 50);
        $page = max(1, (int) ($_GET['p'] ?? 1));

        [$where, $params] = $this->whereClause($type, $filters);
        $count = $this->db()->prepare("SELECT COUNT(*) FROM reference_locations rl WHERE {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT rl.*, gr.name AS region_name, gr.code AS region_code, gc.name AS city_name,
                       (SELECT GROUP_CONCAT(cc.name ORDER BY cc.name SEPARATOR ', ') FROM reference_location_cities rlc JOIN geographic_cities cc ON cc.id = rlc.city_id WHERE rlc.location_id = rl.id) AS covered_cities,
                       (SELECT GROUP_CONCAT(sm.method_type ORDER BY sm.sort_order SEPARATOR ', ') FROM sbc_request_methods sm WHERE sm.location_id = rl.id AND sm.is_active = 1) AS sbc_methods
                  FROM reference_locations rl
                  JOIN geographic_regions gr ON gr.id = rl.region_id
             LEFT JOIN geographic_cities gc ON gc.id = rl.city_id
                 WHERE {$where}
              ORDER BY gr.name, COALESCE(gc.name, ''), rl.name
                 LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $regionStmt = $this->db()->prepare('SELECT id, code, name FROM geographic_regions WHERE country_code = ? AND is_active = 1 ORDER BY name');
        $regionStmt->execute([self::TYPES[$type]['country']]);
        $regions = $regionStmt->fetchAll(\PDO::FETCH_ASSOC);
        $cities = [];
        if ($filters['region'] > 0) {
            $cityStmt = $this->db()->prepare('SELECT id, name FROM geographic_cities WHERE region_id = ? AND is_active = 1 ORDER BY name');
            $cityStmt->execute([$filters['region']]);
            $cities = $cityStmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        $summary = $this->summary();
        $sources = [];
        if ($type === 'canada_court') {
            $sources = $this->db()->query(
                'SELECT cds.name, cds.url, gr.name AS region_name FROM court_directory_sources cds LEFT JOIN geographic_regions gr ON gr.id = cds.region_id WHERE cds.is_active = 1 ORDER BY COALESCE(gr.name, cds.name), cds.name'
            )->fetchAll(\PDO::FETCH_ASSOC);
        }
        $types = self::TYPES;
        $pageTitle = self::TYPES[$type]['label'];
        $activePage = 'reference-data';
        $content = $this->render(__DIR__ . '/../views/admin/reference-data.php', compact('type', 'types', 'filters', 'perPage', 'page', 'pages', 'total', 'rows', 'regions', 'cities', 'summary', 'sources'));
        require __DIR__ . '/../views/layouts/admin.php';
    }

    public function edit(): void
    {
        Csrf::ensureToken();
        $id = max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
        $stmt = $this->db()->prepare(
            'SELECT rl.*, gr.country_code, gr.name AS region_name, gc.name AS city_name
               FROM reference_locations rl
               JOIN geographic_regions gr ON gr.id = rl.region_id
          LEFT JOIN geographic_cities gc ON gc.id = rl.city_id
              WHERE rl.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$record) {
            http_response_code(404);
            require __DIR__ . '/../views/layouts/404.php';
            return;
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST[Csrf::FIELD] ?? null)) {
                $error = 'Invalid request. Please try again.';
            } else {
                try {
                    $this->saveRecord($record, $_POST);
                    header('Location: /admin/reference-data/edit?id=' . $id . '&saved=1');
                    exit;
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $stmt->execute([$id]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);
        $regionStmt = $this->db()->prepare('SELECT id, code, name FROM geographic_regions WHERE country_code = ? AND is_active = 1 ORDER BY name');
        $regionStmt->execute([$record['country_code']]);
        $regions = $regionStmt->fetchAll(\PDO::FETCH_ASSOC);
        $coveredStmt = $this->db()->prepare('SELECT gc.name FROM reference_location_cities rlc JOIN geographic_cities gc ON gc.id = rlc.city_id WHERE rlc.location_id = ? ORDER BY gc.name');
        $coveredStmt->execute([$id]);
        $coveredCities = implode('; ', $coveredStmt->fetchAll(\PDO::FETCH_COLUMN));
        $methodStmt = $this->db()->prepare('SELECT * FROM sbc_request_methods WHERE location_id = ? ORDER BY sort_order, id');
        $methodStmt->execute([$id]);
        $methods = [];
        foreach ($methodStmt->fetchAll(\PDO::FETCH_ASSOC) as $method) { $methods[$method['method_type']] = $method; }

        $saved = isset($_GET['saved']);
        $pageTitle = 'Edit reference record';
        $activePage = 'reference-data';
        $content = $this->render(__DIR__ . '/../views/admin/reference-data-edit.php', compact('record', 'regions', 'coveredCities', 'methods', 'saved', 'error'));
        require __DIR__ . '/../views/layouts/admin.php';
    }

    private function saveRecord(array $record, array $input): void
    {
        $name = trim((string) ($input['name'] ?? ''));
        $regionId = max(0, (int) ($input['region_id'] ?? 0));
        if ($name === '' || $regionId === 0) { throw new \InvalidArgumentException('Name and province/state are required.'); }
        $check = $this->db()->prepare('SELECT COUNT(*) FROM geographic_regions WHERE id = ? AND country_code = ?');
        $check->execute([$regionId, $record['country_code']]);
        if (!(int) $check->fetchColumn()) { throw new \InvalidArgumentException('Invalid province/state.'); }

        $city = trim((string) ($input['city'] ?? ''));
        $cityId = $city === '' ? null : $this->cityId($regionId, $city);
        $fields = ['address_line','postal_code','phone','alternate_phone','fax','email','website','contact_name','contact_phone','contact_email','manager_name','manager_contact','mailing_type','payable_to','payment_type','fee_text','additional_information','special_instructions','review_reason'];
        $values = [$regionId, $cityId, $name];
        foreach ($fields as $field) { $values[] = $this->nullValue($input[$field] ?? null); }
        $values[] = isset($input['no_fee']) ? 1 : 0;
        $values[] = isset($input['is_active']) ? 1 : 0;
        $values[] = isset($input['needs_review']) ? 1 : 0;
        $values[] = (int) $record['id'];
        $sql = 'UPDATE reference_locations SET region_id=?, city_id=?, name=?, address_line=?, postal_code=?, phone=?, alternate_phone=?, fax=?, email=?, website=?, contact_name=?, contact_phone=?, contact_email=?, manager_name=?, manager_contact=?, mailing_type=?, payable_to=?, payment_type=?, fee_text=?, additional_information=?, special_instructions=?, review_reason=?, no_fee=?, is_active=?, needs_review=?, updated_at=NOW() WHERE id=?';

        $this->db()->beginTransaction();
        try {
            $this->db()->prepare($sql)->execute($values);
            $this->db()->prepare('DELETE FROM reference_location_cities WHERE location_id = ?')->execute([$record['id']]);
            $covered = preg_split('/[;\r\n]+/', (string) ($input['covered_cities'] ?? '')) ?: [];
            $insertCovered = $this->db()->prepare('INSERT IGNORE INTO reference_location_cities (location_id, city_id) VALUES (?, ?)');
            foreach ($covered as $coveredCity) {
                $coveredCity = trim($coveredCity);
                if ($coveredCity !== '') { $insertCovered->execute([$record['id'], $this->cityId($regionId, $coveredCity)]); }
            }
            if ($record['directory_type'] === 'sbc') { $this->saveMethods((int) $record['id'], $input['methods'] ?? []); }
            $this->db()->commit();
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            throw $e;
        }
    }

    private function saveMethods(int $locationId, array $submitted): void
    {
        $types = ['fingerprint_by_mail','online_name_based','mail_name_based','livescan_in_person','fax_or_email','other'];
        $sql = 'INSERT INTO sbc_request_methods (location_id,method_type,is_primary,processing_time,required_documents,results_return_to,restrictions,website,no_fee,payable_to,payment_type,fee_text,special_instructions,is_active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE is_primary=VALUES(is_primary),processing_time=VALUES(processing_time),required_documents=VALUES(required_documents),results_return_to=VALUES(results_return_to),restrictions=VALUES(restrictions),website=VALUES(website),no_fee=VALUES(no_fee),payable_to=VALUES(payable_to),payment_type=VALUES(payment_type),fee_text=VALUES(fee_text),special_instructions=VALUES(special_instructions),is_active=VALUES(is_active),sort_order=VALUES(sort_order)';
        $stmt = $this->db()->prepare($sql);
        foreach ($types as $order => $type) {
            $m = is_array($submitted[$type] ?? null) ? $submitted[$type] : [];
            $stmt->execute([$locationId,$type,isset($m['is_primary'])?1:0,$this->nullValue($m['processing_time']??null),$this->nullValue($m['required_documents']??null),$this->nullValue($m['results_return_to']??null),$this->nullValue($m['restrictions']??null),$this->nullValue($m['website']??null),isset($m['no_fee'])?1:0,$this->nullValue($m['payable_to']??null),$this->nullValue($m['payment_type']??null),$this->nullValue($m['fee_text']??null),$this->nullValue($m['special_instructions']??null),isset($m['is_active'])?1:0,$order]);
        }
    }

    private function cityId(int $regionId, string $name): int
    {
        $name = trim($name);
        $normalized = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
        $stmt = $this->db()->prepare('INSERT INTO geographic_cities (region_id,name,normalized_name) VALUES (?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),name=VALUES(name),is_active=1');
        $stmt->execute([$regionId, $name, $normalized]);
        return (int) $this->db()->lastInsertId();
    }

    private function nullValue($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function whereClause(string $type, array $filters): array
    {
        $parts = ['rl.directory_type = ?'];
        $params = [$type];
        if ($filters['q'] !== '') {
            $parts[] = '(rl.name LIKE ? OR rl.address_line LIKE ? OR rl.phone LIKE ? OR rl.email LIKE ? OR rl.contact_email LIKE ?)';
            $needle = '%' . $filters['q'] . '%';
            array_push($params, $needle, $needle, $needle, $needle, $needle);
        }
        if ($filters['region'] > 0) { $parts[] = 'rl.region_id = ?'; $params[] = $filters['region']; }
        if ($filters['city'] > 0) { $parts[] = 'rl.city_id = ?'; $params[] = $filters['city']; }
        if ($filters['covered_city'] > 0) { $parts[] = 'EXISTS (SELECT 1 FROM reference_location_cities x WHERE x.location_id = rl.id AND x.city_id = ?)'; $params[] = $filters['covered_city']; }
        if ($filters['status'] !== 'all') { $parts[] = 'rl.is_active = ?'; $params[] = $filters['status'] === 'active' ? 1 : 0; }
        if ($filters['review'] !== 'all') { $parts[] = 'rl.needs_review = ?'; $params[] = $filters['review'] === 'yes' ? 1 : 0; }
        if ($filters['fee'] !== 'all') { $parts[] = 'rl.no_fee = ?'; $params[] = $filters['fee'] === 'free' ? 1 : 0; }
        if ($type === 'sbc' && $filters['method'] !== '') { $parts[] = 'EXISTS (SELECT 1 FROM sbc_request_methods sm WHERE sm.location_id = rl.id AND sm.method_type = ? AND sm.is_active = 1)'; $params[] = $filters['method']; }
        return [implode(' AND ', $parts), $params];
    }

    private function summary(): array
    {
        $out = array_fill_keys(array_keys(self::TYPES), 0);
        foreach ($this->db()->query('SELECT directory_type, COUNT(*) AS total FROM reference_locations GROUP BY directory_type') as $row) {
            $out[$row['directory_type']] = (int) $row['total'];
        }
        return $out;
    }

    private function allowed(string $value, array $allowed, string $fallback): string { return in_array($value, $allowed, true) ? $value : $fallback; }
    private function allowedInt(int $value, array $allowed, int $fallback): int { return in_array($value, $allowed, true) ? $value : $fallback; }
}
