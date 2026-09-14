<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Log;
use PDO;

/**
 * Session / remember-me helpers shared by Auth, Settings, PasswordReset.
 */
class AuthSession
{
    /**
     * After password change or reset: drop remember-me, clear cookie, regenerate session id.
     * User stays logged in when a session is active (Settings path).
     */
    public static function onPasswordChanged(PDO $db, int $userId): void
    {
        try {
            $db->prepare('UPDATE users SET remember_token=NULL WHERE id=?')->execute([$userId]);
        } catch (\Throwable $e) {
            Log::security()->error('AuthSession::onPasswordChanged clear remember failed: ' . $e->getMessage());
        }

        self::clearRememberCookie();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function clearRememberCookie(): void
    {
        setcookie('remember_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => self::cookieSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE['remember_token']);
    }

    public static function cookieSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || (strtolower((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')) === 'production');
    }
}
