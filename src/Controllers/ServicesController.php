<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Services page controller.
 *
 * Renders an accordion page listing every service the client is enrolled in.
 * Each accordion item has a two-column layout:
 *   Left  — Details, Status, Courts, Police Certificates (varies by type)
 *   Right — Email History (clickable → opens email modal)
 *
 * Type-specific sections:
 *   - Police Certificates table  → TRP and Criminal Rehab only
 *   - Police Certificate list    → Pardon and Expungement only
 *   - RCMP Record Exp Date       → Waiver only
 *
 * Data loaders:
 *   loadServices()           → user_services + service_costs
 *   loadCourtsPolice()       → service_courts_police
 *   loadEmailHistory()       → service_email_history
 *   loadPoliceCertificates() → service_sbc_records
 *
 * @see src/views/services/services-page.php  — view template
 * @see public/js/services.js                 — email modal, popovers
 * @see public/css/services.css               — accordion & card styles
 * @see src/config/service_colors.php         — shared colour constants
 */
class ServicesController extends BaseController
{
    public function __construct()
    {
        require_once __DIR__ . '/../config/service_colors.php';
    }

    /* ──────────────────────────────────────────────────────── */
    /*  Main entry point                                       */
    /* ──────────────────────────────────────────────────────── */
    public function index(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        // 1. Load all user services + service_costs label/type
        $services = $this->loadServices($userId);

        // 2. For each service, load associated data
        $serviceIds = array_column($services, 'id');

        $courtsPolice       = $this->loadCourtsPolice($serviceIds);
        $emailHistory       = $this->loadEmailHistory($serviceIds);
        $policeCertificates = $this->loadPoliceCertificates($serviceIds);

        // 3. Colour map (defined in src/config/service_colors.php)
        $serviceColors = SERVICE_COLORS;

        // 4. Render
        $pageTitle  = 'Services';
        $activePage = 'services';

        ob_start();
        require __DIR__ . '/../views/services/services-page.php';
        $content = ob_get_clean();

        require __DIR__ . '/../views/layouts/main.php';
    }

    /* ──────────────────────────────────────────────────────── */
    /*  Data loaders                                           */
    /* ──────────────────────────────────────────────────────── */

    private function loadServices(int $userId): array
    {
        $sql = "SELECT us.*, sc.service_type, sc.label AS service_label, sc.total_cost
                FROM user_services us
                JOIN service_costs sc ON sc.id = us.service_cost_id
                WHERE us.user_id = ?
                ORDER BY us.date_added ASC";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function loadCourtsPolice(array $serviceIds): array
    {
        if (empty($serviceIds)) return [];
        $in  = implode(',', array_fill(0, count($serviceIds), '?'));
        $sql = "SELECT * FROM service_courts_police
                WHERE user_service_id IN ($in)
                ORDER BY user_service_id, type, name";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($serviceIds);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $r) {
            $grouped[(int)$r['user_service_id']][] = $r;
        }
        return $grouped;
    }

    private function loadEmailHistory(array $serviceIds): array
    {
        if (empty($serviceIds)) return [];
        $in  = implode(',', array_fill(0, count($serviceIds), '?'));
        $sql = "SELECT * FROM service_email_history
                WHERE user_service_id IN ($in)
                ORDER BY user_service_id, sent_at ASC";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($serviceIds);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $r) {
            $grouped[(int)$r['user_service_id']][] = $r;
        }
        return $grouped;
    }

    private function loadPoliceCertificates(array $serviceIds): array
    {
        if (empty($serviceIds)) return [];
        $in  = implode(',', array_fill(0, count($serviceIds), '?'));
        $sql = "SELECT * FROM service_sbc_records
                WHERE user_service_id IN ($in)
                ORDER BY user_service_id, sort_order ASC";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($serviceIds);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $r) {
            $grouped[(int)$r['user_service_id']][] = $r;
        }
        return $grouped;
    }
}
