<?php

/**
 * Front-controller — sole HTTP entry for the portal.
 *
 * Routes: config/routes.php (one line per path).
 * Controllers autoloaded via Composer PSR-4 (App\ → src/).
 *
 * @see docs/ARCHITECTURE.md
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

\App\Support\AppTimezone::applyPhp();

\App\Support\ExceptionHandler::register();

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
    || (strtolower((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')) === 'production');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

$uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
$uri = rtrim($uri, '/') ?: '/';

$registerToken = '';
if (preg_match('#^/register/([^/]+)$#i', $uri, $m)) {
    $page = 'register';
    $registerToken = $m[1];
} else {
    $page = trim($uri, '/');
}

if ($page === '' || $page === 'index.php') {
    $page = $_GET['page'] ?? 'login';
}

/** @var array<string, array{0: class-string, 1: string, 2: bool}> $routes */
$routes = require __DIR__ . '/../config/routes.php';

$isLoggedIn = !empty($_SESSION['user_id']);

if (!isset($routes[$page])) {
    http_response_code(404);
    require __DIR__ . '/../src/views/layouts/404.php';
    exit;
}

[$controllerClass, $method, $requiresAuth] = $routes[$page];

if ($requiresAuth && !$isLoggedIn) {
    header('Location: /login');
    exit;
}
if ($isLoggedIn && $page === 'login') {
    header('Location: /dashboard');
    exit;
}

$ctrl = new $controllerClass();
if ($page === 'register') {
    $ctrl->{$method}($registerToken);
} elseif ($page === 'dashboard') {
    $ctrl->{$method}($page);
} else {
    $ctrl->{$method}();
}
