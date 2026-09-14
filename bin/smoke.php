#!/usr/bin/env php
<?php

/**
 * Optional HTTP smoke against a running portal.
 *
 * Usage (from client-portal/):
 *   set SMOKE_BASE_URL=http://localhost
 *   php bin/smoke.php
 *
 * If SMOKE_BASE_URL is unset, exits 0 (skipped) so CI stays green without Apache.
 * Pay amount / CSRF pay / Moneris: PHPUnit + docs/test-plan-payments.md.
 */

declare(strict_types=1);

$base = getenv('SMOKE_BASE_URL') ?: ($_ENV['SMOKE_BASE_URL'] ?? '');
$base = rtrim((string) $base, '/');

if ($base === '') {
    echo "smoke: skipped (set SMOKE_BASE_URL to run HTTP checks)" . PHP_EOL;
    exit(0);
}

if (!function_exists('curl_init')) {
    fwrite(STDERR, "smoke: curl extension required" . PHP_EOL);
    exit(1);
}

$failed = 0;

/**
 * @return array{code:int, body:string, location:string}
 */
function smokeRequest(string $method, string $url, array $opts = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $opts['headers'] ?? [],
    ]);
    if (isset($opts['body'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body']);
    }
    $raw = (string) curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    if ($err !== '') {
        throw new RuntimeException($err);
    }
    $header = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    $location = '';
    if (preg_match('/^Location:\s*(.+)$/mi', $header, $m)) {
        $location = trim($m[1]);
    }
    return ['code' => $code, 'body' => $body, 'location' => $location];
}

function smokeAssert(bool $ok, string $label): void
{
    global $failed;
    if ($ok) {
        echo "  OK  {$label}" . PHP_EOL;
        return;
    }
    $failed++;
    echo " FAIL {$label}" . PHP_EOL;
}

echo "smoke: base={$base}" . PHP_EOL;

$healthHeaders = [];
$healthToken = getenv('HEALTH_TOKEN') ?: ($_ENV['HEALTH_TOKEN'] ?? '');
$healthToken = is_string($healthToken) ? trim($healthToken) : '';
if ($healthToken !== '') {
    $healthHeaders[] = 'X-Health-Token: ' . $healthToken;
}

try {
    $health = smokeRequest('GET', $base . '/health', ['headers' => $healthHeaders]);
    smokeAssert(
        $health['code'] === 200 || $health['code'] === 503,
        'GET /health → ' . $health['code'] . ($healthToken !== '' ? ' (with token)' : '')
    );
    $healthBody = json_decode($health['body'], true);
    smokeAssert(is_array($healthBody) && array_key_exists('ok', $healthBody), 'GET /health JSON has ok');

    $login = smokeRequest('GET', $base . '/login');
    smokeAssert($login['code'] === 200, 'GET /login → 200');

    $post = smokeRequest('POST', $base . '/login', [
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        'body' => http_build_query([
            'client_id' => '1',
            'password' => 'wrong',
        ]),
    ]);
    $landedDashboard = $post['code'] === 302 && stripos($post['location'], '/dashboard') !== false;
    smokeAssert(
        in_array($post['code'], [200, 302, 403], true) && !$landedDashboard,
        'POST /login without CSRF → not authenticated dashboard (' . $post['code'] . ')'
    );

    $reg = smokeRequest('GET', $base . '/register/not-a-uuid');
    smokeAssert(in_array($reg['code'], [200, 404], true), 'GET /register/not-a-uuid → ' . $reg['code']);
    smokeAssert(
        stripos($reg['body'], 'Uncaught') === false && stripos($reg['body'], 'Fatal error') === false,
        'GET /register/not-a-uuid has no fatal/uncaught'
    );
} catch (Throwable $e) {
    fwrite(STDERR, 'smoke: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

if ($failed > 0) {
    fwrite(STDERR, "smoke: {$failed} check(s) failed" . PHP_EOL);
    exit(1);
}

echo 'smoke: OK' . PHP_EOL;
exit(0);
