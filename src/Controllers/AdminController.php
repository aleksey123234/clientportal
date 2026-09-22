<?php

declare(strict_types=1);

namespace App\Controllers;

class AdminController extends BaseController
{
    public function index(): void
    {
        $search = trim((string) ($_GET['q'] ?? ''));
        $params = [];
        $where = "u.role = 'client'";

        if ($search !== '') {
            $where .= " AND (u.client_id LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR cp.case_number LIKE ?)";
            $needle = '%' . $search . '%';
            $params = array_fill(0, 5, $needle);
        }

        $stmt = $this->db()->prepare(
            "SELECT u.id, u.client_id, u.first_name, u.last_name, u.email, u.phone,
                    u.status, u.last_login_at, u.created_at,
                    cp.case_number, cp.case_status,
                    (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id) AS document_count,
                    (SELECT COUNT(*) FROM payments p WHERE p.user_id = u.id AND p.status IN ('pending','missed')) AS payment_attention
             FROM users u
             LEFT JOIN client_profiles cp ON cp.user_id = u.id
             WHERE {$where}
             ORDER BY u.created_at DESC
             LIMIT 200"
        );
        $stmt->execute($params);
        $clients = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stats = [
            'clients' => (int) $this->db()->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn(),
            'active' => (int) $this->db()->query("SELECT COUNT(*) FROM users WHERE role='client' AND status=1")->fetchColumn(),
            'documents' => (int) $this->db()->query("SELECT COUNT(*) FROM documents WHERE status IN ('pending','uploaded')")->fetchColumn(),
            'payments' => (int) $this->db()->query("SELECT COUNT(*) FROM payments WHERE status IN ('pending','missed')")->fetchColumn(),
        ];

        $pageTitle = 'Administration';
        $activePage = 'clients';
        $content = $this->render(__DIR__ . '/../views/admin/index.php', compact('clients', 'stats', 'search'));
        require __DIR__ . '/../views/layouts/admin.php';
    }

    public function client(): void
    {
        $userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$userId) {
            header('Location: /admin');
            exit;
        }

        $stmt = $this->db()->prepare(
            "SELECT u.id, u.client_id, u.first_name, u.middle_name, u.last_name, u.email, u.phone,
                    u.status, u.last_login_at, u.created_at, cp.*
             FROM users u
             LEFT JOIN client_profiles cp ON cp.user_id = u.id
             WHERE u.id = ? AND u.role = 'client'
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $client = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$client) {
            http_response_code(404);
            require __DIR__ . '/../views/layouts/404.php';
            return;
        }

        $servicesStmt = $this->db()->prepare(
            "SELECT us.*, sc.label AS service_label, sc.service_type
             FROM user_services us
             JOIN service_costs sc ON sc.id = us.service_cost_id
             WHERE us.user_id = ? ORDER BY us.date_added DESC"
        );
        $servicesStmt->execute([$userId]);
        $services = $servicesStmt->fetchAll(\PDO::FETCH_ASSOC);

        $documentsStmt = $this->db()->prepare(
            "SELECT * FROM documents WHERE user_id = ? ORDER BY created_at DESC LIMIT 100"
        );
        $documentsStmt->execute([$userId]);
        $documents = $documentsStmt->fetchAll(\PDO::FETCH_ASSOC);

        $paymentsStmt = $this->db()->prepare(
            "SELECT * FROM payments WHERE user_id = ? ORDER BY due_date DESC, id DESC LIMIT 100"
        );
        $paymentsStmt->execute([$userId]);
        $payments = $paymentsStmt->fetchAll(\PDO::FETCH_ASSOC);

        $pageTitle = 'Client case';
        $activePage = 'clients';
        $content = $this->render(
            __DIR__ . '/../views/admin/client.php',
            compact('client', 'services', 'documents', 'payments')
        );
        require __DIR__ . '/../views/layouts/admin.php';
    }
}
