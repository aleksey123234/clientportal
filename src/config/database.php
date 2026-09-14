<?php

/**
 * PDO database connection (no Dotenv — env loaded by front controller / CLI).
 *
 * @var PDO $pdo
 */

declare(strict_types=1);

$host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');
$port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
$dbname = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? 'mysql_clients_portal');
$username = getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? 'root');
$password = getenv('DB_PASSWORD');
if ($password === false) {
    $password = $_ENV['DB_PASSWORD'] ?? '';
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    if (class_exists(\App\Support\AppTimezone::class)) {
        \App\Support\AppTimezone::applyPdo($pdo);
    }
} catch (PDOException $e) {
    // Do not log DSN credentials — message only (never password).
    if (class_exists(\App\Support\Log::class)) {
        \App\Support\Log::app()->error('DB Connection failed: ' . $e->getMessage(), [
            'host' => $host,
            'database' => $dbname,
        ]);
    }
    http_response_code(500);
    die(json_encode(['ok' => false, 'error' => 'Database connection failed']));
}
