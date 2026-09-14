<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Dashboard controller вЂ” main landing page with real-time widgets.
 *
 * Aggregates key data from every area of the portal into a single
 * at-a-glance overview so the client can immediately see what needs
 * attention.
 *
 * Widgets:
 *   1. Welcome banner (greeting + client name)
 *   2. Stat cards row (client ID, unread emails, next payment, office time)
 *   3. Services overview (status badges)
 *   4. Payment summary (paid / missed)
 *   5. Recent inbox (last 5 emails)
 *   6. Document summary (uploaded / pending / approved / completed)
 *
 * Routes:
 *   GET /dashboard вЂ” render the dashboard
 *
 * @see src/views/dashboard/dashboard-page.php
 * @see public/css/dashboard.css
 * @see src/views/layouts/main.php
 * @see src/config/service_colors.php  вЂ” shared colour/label constants
 */
class DashboardController extends BaseController
{
    public function __construct()
    {
        require_once __DIR__ . '/../config/service_colors.php';
    }

    /* ================================================================
     *  Main entry point
     * ================================================================ */
    public function index(string $page = 'dashboard'): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        // в”Ђв”Ђ User info в”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђ
        $user = $this->loadUser($userId);

        // в”Ђв”Ђ Unread emails в”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђ
        $unreadEmails  = $this->countUnreadEmails($userId);
        $recentEmails  = $this->loadRecentEmails($userId, 5);

        // в”Ђв”Ђ CIF status в”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђ
        $cifStatus     = $this->loadCifStatus($userId);

        // в”Ђв”Ђ Next payment в”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђ
        $nextPayment   = $this->loadNextPayment($userId);

        // в”Ђв”Ђ Payment summary в”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђ
        $paymentSummary = $this->loadPaymentSummary($userId);

        // в”Ђв”Ђ Documents в”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђ
        $docStats      = $this->loadDocumentStats($userId);

        // в”Ђв”Ђ Services в”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђ
        $services      = $this->loadServices($userId);

        // в”Ђв”Ђ Colour maps в”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђ
        $serviceColors = SERVICE_COLORS;
        $serviceLabels = SERVICE_LABELS;

        // в”Ђв”Ђ Render в”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђ
        $pageTitle  = 'Dashboard';
        $activePage = 'dashboard';

        ob_start();
        require __DIR__ . '/../views/dashboard/dashboard-page.php';
        $content = ob_get_clean();

        require __DIR__ . '/../views/layouts/main.php';
    }

    /* ================================================================
     *  Data loaders
     * ================================================================ */

    /** Load basic user info */
    private function loadUser(int $userId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT first_name, middle_name, last_name, email, client_id,
                    last_login_at, created_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    /** Count unread emails (service_email_history where read_at IS NULL) */
    private function countUnreadEmails(int $userId): int
    {
        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) FROM service_email_history seh
             JOIN user_services us ON us.id = seh.user_service_id
             WHERE us.user_id = ? AND seh.read_at IS NULL"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /** Load N most recent inbox emails */
    private function loadRecentEmails(int $userId, int $limit = 5): array
    {
        $stmt = $this->db()->prepare(
            "SELECT seh.id, seh.subject, seh.sent_at, seh.sent_by, seh.read_at,
                    sc.service_type, sc.label AS service_label
             FROM service_email_history seh
             JOIN user_services us ON us.id = seh.user_service_id
             JOIN service_costs sc ON sc.id = us.service_cost_id
             WHERE us.user_id = ?
             ORDER BY seh.sent_at DESC
             LIMIT ?"
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Load CIF progress + completion + generated PDFs count */
    private function loadCifStatus(int $userId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT progress, completed_at, updated_at
             FROM cif_responses WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $stmt2 = $this->db()->prepare(
            'SELECT COUNT(*) FROM cif_generated_pdfs WHERE user_id = ?'
        );
        $stmt2->execute([$userId]);
        $pdfCount = (int) $stmt2->fetchColumn();

        return [
            'progress'     => (int) ($row['progress'] ?? 0),
            'completed_at' => $row['completed_at'] ?? null,
            'updated_at'   => $row['updated_at'] ?? null,
            'pdf_count'    => $pdfCount,
            'started'      => !empty($row),
        ];
    }

    /** Load next pending payment (closest due date) */
    private function loadNextPayment(int $userId): ?array
    {
        $stmt = $this->db()->prepare(
            "SELECT p.due_date, SUM(p.amount) AS total_amount
             FROM payments p
             WHERE p.user_id = ? AND p.status = 'pending'
             GROUP BY p.due_date
             ORDER BY p.due_date ASC
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Load payment totals (paid, missed) */
    private function loadPaymentSummary(int $userId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT
                 COALESCE(SUM(CASE WHEN status = 'paid'   THEN amount ELSE 0 END), 0) AS total_paid,
                 COALESCE(SUM(CASE WHEN status = 'missed' THEN amount ELSE 0 END), 0) AS total_missed,
                 COUNT(CASE WHEN status = 'paid' THEN 1 END)   AS paid_count,
                 COUNT(CASE WHEN status = 'missed' THEN 1 END) AS missed_count
             FROM payments WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'total_paid' => 0,
            'total_missed' => 0,
            'paid_count' => 0,
            'missed_count' => 0,
        ];
    }

    /** Load document stats (uploaded vs pending) */
    private function loadDocumentStats(int $userId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT
                 COUNT(*) AS total,
                 COUNT(CASE WHEN status = 'pending'        THEN 1 END) AS pending,
                 COUNT(CASE WHEN status = 'uploaded'       THEN 1 END) AS uploaded,
                 COUNT(CASE WHEN status = 'approved'       THEN 1 END) AS approved,
                 COUNT(CASE WHEN status = 'completed_fpws' THEN 1 END) AS completed
             FROM documents WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'total' => 0,
            'pending' => 0,
            'uploaded' => 0,
            'approved' => 0,
            'completed' => 0,
        ];
    }

    /** Load user services with status */
    private function loadServices(int $userId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT us.id, us.current_status, us.date_added,
                    sc.service_type, sc.label AS service_label
             FROM user_services us
             JOIN service_costs sc ON sc.id = us.service_cost_id
             WHERE us.user_id = ? AND sc.is_extra = 0
             ORDER BY us.date_added ASC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
