<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Registration invite token format (UUID shape).
 * DB existence check stays in RegisterController.
 */
final class RegistrationToken
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public static function isValidFormat(string $token): bool
    {
        return (bool) preg_match(self::UUID_PATTERN, $token);
    }
}
