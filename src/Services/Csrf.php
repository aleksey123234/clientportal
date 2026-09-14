<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Session CSRF token — ensure + validate for forms and JSON APIs.
 */
class Csrf
{
    public const FIELD = 'csrf_token';

    public static function ensureToken(): string
    {
        if (empty($_SESSION[self::FIELD])) {
            $_SESSION[self::FIELD] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION[self::FIELD];
    }

    public static function validate(?string $token): bool
    {
        $session = $_SESSION[self::FIELD] ?? '';
        if ($session === '' || $token === null || $token === '') {
            return false;
        }
        return hash_equals($session, $token);
    }
}
