<?php

declare(strict_types=1);

namespace App\Controllers;

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
