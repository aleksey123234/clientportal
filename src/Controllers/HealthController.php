<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Support\AppTimezone;

/**
 * Ops health probe — no secrets in the response.
 *
 * GET /health → 200 {ok,checks} or 503 {ok:false,error,checks}
 *
 * Access:
 *   - APP_ENV=production and empty HEALTH_TOKEN → 404
 *   - HEALTH_TOKEN set → require X-Health-Token or Authorization: Bearer
 *   - non-production + empty token → open (dev)
 */
class HealthController
{
    public function index(): void
    {
        if (!$this->authorizeProbe()) {
            return;
        }

        $checks = [
            'db' => 'fail',
            'uploads' => 'fail',
            'logs' => 'fail',
        ];

        $checks['db'] = $this->checkDb() ? 'ok' : 'fail';
        $checks['uploads'] = $this->checkWritableDir($this->uploadBasePath(), false) ? 'ok' : 'fail';
        $checks['logs'] = $this->checkWritableDir($this->logsPath(), true) ? 'ok' : 'fail';

        $healthy = $checks['db'] === 'ok'
            && $checks['uploads'] === 'ok'
            && $checks['logs'] === 'ok';

        if ($healthy) {
            Response::jsonOk(['checks' => $checks]);
        }

        Response::jsonFail('unhealthy', 503, ['checks' => $checks]);
    }

    /**
     * @return bool true if probe may continue; false if response already sent
     */
    private function authorizeProbe(): bool
    {
        $env = strtolower((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'local'));
        $token = $_ENV['HEALTH_TOKEN'] ?? getenv('HEALTH_TOKEN');
        if ($token === false || $token === null) {
            $token = '';
        }
        $token = trim((string) $token);

        if ($env === 'production' && $token === '') {
            http_response_code(404);
            exit;
        }

        if ($token === '') {
            return true;
        }

        $provided = $this->providedHealthToken();
        if ($provided === '' || !hash_equals($token, $provided)) {
            Response::jsonFail('Unauthorized', 401);
            return false;
        }

        return true;
    }

    private function providedHealthToken(): string
    {
        $header = '';
        if (!empty($_SERVER['HTTP_X_HEALTH_TOKEN'])) {
            $header = (string) $_SERVER['HTTP_X_HEALTH_TOKEN'];
        } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = (string) $_SERVER['HTTP_AUTHORIZATION'];
            if (preg_match('/^Bearer\s+(\S+)$/i', $auth, $m)) {
                $header = $m[1];
            }
        }
        return trim($header);
    }

    private function checkDb(): bool
    {
        try {
            $host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');
            $port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
            $dbname = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? 'mysql_clients_portal');
            $username = getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? 'root');
            $password = getenv('DB_PASSWORD');
            if ($password === false) {
                $password = $_ENV['DB_PASSWORD'] ?? '';
            }

            $pdo = new \PDO(
                "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
                $username,
                $password,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            AppTimezone::applyPdo($pdo);
            $pdo->query('SELECT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function uploadBasePath(): string
    {
        $path = getenv('UPLOAD_BASE_PATH');
        if ($path === false || $path === '') {
            $path = $_ENV['UPLOAD_BASE_PATH'] ?? '';
        }
        return (string) $path;
    }

    private function logsPath(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    }

    /**
     * @param bool $allowCreate  If true, mkdir when missing (logs dir).
     */
    private function checkWritableDir(string $path, bool $allowCreate): bool
    {
        if ($path === '') {
            return false;
        }
        if (!is_dir($path)) {
            if (!$allowCreate) {
                return false;
            }
            if (!@mkdir($path, 0755, true) && !is_dir($path)) {
                return false;
            }
        }
        return is_writable($path);
    }
}
