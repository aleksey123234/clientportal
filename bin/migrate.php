#!/usr/bin/env php
<?php

/**
 * Forward-only migration runner.
 *
 * Usage (from client-portal/):
 *   php bin/migrate.php            Apply ALL pending migrations
 *   php bin/migrate.php --status   List applied vs pending
 *   php bin/migrate.php --baseline Mark all current *.sql as applied (existing DB)
 *
 * @see database/README.md
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($root);
$dotenv->safeLoad();

\App\Support\AppTimezone::applyPhp();

$host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');
$port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
$dbname = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? 'mysql_clients_portal');
$user = getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? 'root');
$pass = getenv('DB_PASSWORD');
if ($pass === false) {
    $pass = $_ENV['DB_PASSWORD'] ?? '';
}

$args = array_slice($argv, 1);
$doStatus = in_array('--status', $args, true);
$doBaseline = in_array('--baseline', $args, true);

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ]
    );
    \App\Support\AppTimezone::applyPdo($pdo);
} catch (PDOException $e) {
    fwrite(STDERR, 'DB connection failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$migrationsDir = $root . '/database/migrations';
$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files, SORT_STRING);
$basenames = array_map('basename', $files);

ensureSchemaMigrationsTable($pdo);

$applied = $pdo->query('SELECT filename FROM schema_migrations ORDER BY filename')
    ->fetchAll(PDO::FETCH_COLUMN);
$appliedSet = array_fill_keys($applied, true);

if ($doBaseline) {
    $marked = 0;
    $ins = $pdo->prepare(
        'INSERT IGNORE INTO schema_migrations (filename, applied_at) VALUES (?, NOW())'
    );
    foreach ($basenames as $name) {
        $ins->execute([$name]);
        if ($ins->rowCount() > 0) {
            $marked++;
            echo "baseline: {$name}" . PHP_EOL;
        }
    }
    echo "Baseline complete. Newly marked: {$marked}. Total tracked: "
        . count($basenames) . PHP_EOL;
    exit(0);
}

$pending = array_values(array_filter(
    $basenames,
    static fn(string $n): bool => !isset($appliedSet[$n])
));

if ($doStatus) {
    echo "Database: {$dbname}" . PHP_EOL;
    echo 'Applied (' . count($applied) . '):' . PHP_EOL;
    foreach ($applied as $a) {
        echo "  [x] {$a}" . PHP_EOL;
    }
    echo 'Pending (' . count($pending) . '):' . PHP_EOL;
    foreach ($pending as $p) {
        echo "  [ ] {$p}" . PHP_EOL;
    }
    exit(0);
}

if ($pending === []) {
    echo 'Nothing to migrate. All ' . count($basenames) . ' files applied.' . PHP_EOL;
    exit(0);
}

echo 'Applying ' . count($pending) . ' pending migration(s)…' . PHP_EOL;

$mark = $pdo->prepare(
    'INSERT INTO schema_migrations (filename, applied_at) VALUES (?, NOW())'
);

foreach ($pending as $name) {
    $path = $migrationsDir . '/' . $name;
    $sql = file_get_contents($path);
    if ($sql === false) {
        fwrite(STDERR, "Failed to read {$name}" . PHP_EOL);
        exit(1);
    }
    $sql = stripUseStatements($sql);
    $sql = trim($sql);
    if ($sql === '') {
        echo "skip empty: {$name}" . PHP_EOL;
        $mark->execute([$name]);
        continue;
    }

    echo "migrate: {$name} … ";
    try {
        executeSqlFile($pdo, $sql);
        $mark->execute([$name]);
        echo 'OK' . PHP_EOL;
    } catch (Throwable $e) {
        echo 'FAIL' . PHP_EOL;
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        fwrite(STDERR, "Stopped. {$name} was NOT marked applied." . PHP_EOL);
        exit(1);
    }
}

echo 'Done. Applied ' . count($pending) . ' migration(s).' . PHP_EOL;
exit(0);

function ensureSchemaMigrationsTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            filename   VARCHAR(255) NOT NULL,
            applied_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (filename)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function stripUseStatements(string $sql): string
{
    return (string) preg_replace(
        '/^\s*USE\s+[`\'"]?[\w]+[`\'"]?\s*;\s*/im',
        '',
        $sql
    );
}

/** Split on semicolons outside quotes/comments; exec each statement. */
function executeSqlFile(PDO $pdo, string $sql): void
{
    foreach (splitSqlStatements($sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || preg_match('/^--/', $stmt) === 1 && strpos($stmt, "\n") === false) {
            continue;
        }
        $pdo->exec($stmt);
    }
}

/** Split on semicolons outside quotes; treat -- as line comments. */
function splitSqlStatements(string $sql): array
{
    $out = [];
    $buf = '';
    $len = strlen($sql);
    $inSingle = false;
    $inDouble = false;
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        // Line comment
        if (!$inSingle && !$inDouble && $ch === '-' && $next === '-') {
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
            if ($i < $len) {
                $buf .= "\n";
            }
            continue;
        }

        if ($ch === "'" && !$inDouble) {
            // handle escaped '' inside single quotes
            if ($inSingle && $next === "'") {
                $buf .= "''";
                $i++;
                continue;
            }
            $inSingle = !$inSingle;
            $buf .= $ch;
            continue;
        }
        if ($ch === '"' && !$inSingle) {
            $inDouble = !$inDouble;
            $buf .= $ch;
            continue;
        }
        if ($ch === ';' && !$inSingle && !$inDouble) {
            $out[] = $buf;
            $buf = '';
            continue;
        }
        $buf .= $ch;
    }
    if (trim($buf) !== '') {
        $out[] = $buf;
    }
    return $out;
}
