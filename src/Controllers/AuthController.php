<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthSession;
use App\Services\Csrf;
use App\Services\RateLimiter;
use App\Support\Log;

/**
 * Authentication controller — login, logout, remember-me.
 *
 * Routes (dispatched by public/index.php):
 *   GET|POST /login   — show login form / authenticate by client_id + password
 *   GET      /logout  — destroy session, clear remember-me cookie, redirect to /login
 *
 * Login field is "client_id" (not email).
 * On success the session is populated with user_id, client_id, user_name, etc.
 * View preferences (dark_mode, sidebar_side) are loaded from user_settings.
 *
 * @see src/views/auth/login.php         — login form view
 * @see src/views/layouts/auth.php       — auth layout (blurred background)
 * @see public/js/login.js              — panel slide, password toggle, forgot form
 */
class AuthController extends BaseController
{

    public function login(): void
    {
        Csrf::ensureToken();

        $error      = null;
        $registered = isset($_GET['registered']);

        /* ── Check remember-me cookie ── */
        if (empty($_SESSION['user_id'])) {
            $this->checkRememberCookie();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST[Csrf::FIELD] ?? null)) {
                $error = 'Invalid request. Please try again.';
            } else {
                $loginKey = RateLimiter::bucketKey('login', RateLimiter::clientIp());
                $allowed = true;
                try {
                    $allowed = RateLimiter::hit(
                        $this->db(),
                        $loginKey,
                        RateLimiter::LOGIN_MAX,
                        RateLimiter::WINDOW_SEC
                    );
                } catch (\Throwable $e) {
                    Log::security()->error('Login rate limit failed: ' . $e->getMessage());
                }

                if (!$allowed) {
                    /* Same message as bad credentials — do not reveal throttle */
                    $error = 'Invalid Client ID or password.';
                } else {
                    $clientId = trim($_POST['email']    ?? ''); // form field is named "email" but holds Client ID
                    $password = trim($_POST['password'] ?? '');

                    if (empty($clientId) || empty($password)) {
                        $error = 'Please fill in all fields.';
                    } else {
                        $stmt = $this->db()->prepare(
                            'SELECT id, client_id, email, password_hash, first_name, last_name, role
                             FROM users WHERE client_id = ? AND status = 1 LIMIT 1'
                        );
                        $stmt->execute([$clientId]);
                        $user = $stmt->fetch();

                        if ($user && password_verify($password, $user['password_hash'])) {
                            $this->startUserSession($user);

                            /* Remember me */
                            if (!empty($_POST['remember_me'])) {
                                $this->setRememberCookie($user['id']);
                            }

                            header('Location: ' . (($user['role'] ?? 'client') === 'admin' ? '/admin' : '/dashboard'));
                            exit;
                        } else {
                            $error = 'Invalid Client ID or password.';
                        }
                    }
                }
            }
        }

        $pageTitle = 'Sign In — Client Portal';
        $content   = $this->render(
            __DIR__ . '/../views/auth/login.php',
            compact('error', 'pageTitle', 'registered')
        );
        require __DIR__ . '/../views/layouts/auth.php';
    }

    public function logout(): void
    {
        /* Clear remember-me token */
        if (!empty($_COOKIE['remember_token'])) {
            try {
                $this->db()->prepare(
                    'UPDATE users SET remember_token=NULL WHERE remember_token=?'
                )->execute([hash('sha256', (string) $_COOKIE['remember_token'])]);
            } catch (\Exception $e) {
                Log::security()->error('Logout clear remember failed: ' . $e->getMessage());
            }
            AuthSession::clearRememberCookie();
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $p['path'],
                $p['domain'],
                $p['secure'],
                $p['httponly']
            );
        }
        session_destroy();
        header('Location: /login');
        exit;
    }

    /* ── Remember-me helpers ──────────────────────────────────── */

    private function startUserSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['client_id']     = $user['client_id'] ?? '';
        $_SESSION['user_name']     = trim($user['first_name'] . ' ' . $user['last_name']) ?: $user['email'];
        $_SESSION['user_email']    = $user['email'];
        $_SESSION['user_role']     = $user['role'] ?? 'client';

        /* Load view prefs */
        $stmt = $this->db()->prepare(
            "SELECT setting_key, setting_value FROM user_settings
             WHERE user_id = ? AND setting_key IN ('sidebar_side','dark_mode')"
        );
        $stmt->execute([$user['id']]);
        foreach ($stmt->fetchAll() as $row) {
            $_SESSION[$row['setting_key']] = $row['setting_value'];
        }

        $this->db()->prepare(
            'UPDATE users SET last_login_at=NOW() WHERE id=?'
        )->execute([$user['id']]);
    }

    private function setRememberCookie(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $this->db()->prepare(
            'UPDATE users SET remember_token=? WHERE id=?'
        )->execute([hash('sha256', $token), $userId]);
        /* 30 days — Secure when HTTPS or APP_ENV=production */
        setcookie('remember_token', $token, [
            'expires'  => time() + 30 * 86400,
            'path'     => '/',
            'secure'   => AuthSession::cookieSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function checkRememberCookie(): void
    {
        if (empty($_COOKIE['remember_token'])) return;
        try {
            $stmt = $this->db()->prepare(
                'SELECT id, client_id, email, password_hash, first_name, last_name, role
                 FROM users WHERE remember_token = ? AND status = 1 LIMIT 1'
            );
            $stmt->execute([hash('sha256', (string) $_COOKIE['remember_token'])]);
            $user = $stmt->fetch();
            if ($user) {
                $this->startUserSession($user);
                /* Rotate token */
                $this->setRememberCookie($user['id']);
                header('Location: ' . (($user['role'] ?? 'client') === 'admin' ? '/admin' : '/dashboard'));
                exit;
            }
        } catch (\Exception $e) {
            Log::security()->error('Remember-me check failed: ' . $e->getMessage());
        }
    }
}
