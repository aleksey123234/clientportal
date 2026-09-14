<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Application timezone — PHP date() and MySQL session NOW() must stay aligned.
 *
 * @see public/index.php
 * @see src/config/database.php
 */
final class AppTimezone
{
    public const DEFAULT = 'America/Toronto';

    public static function name(): string
    {
        $tz = $_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE');
        if (!is_string($tz) || $tz === '') {
            return self::DEFAULT;
        }
        try {
            new \DateTimeZone($tz);
            return $tz;
        } catch (\Exception $e) {
            return self::DEFAULT;
        }
    }

    public static function applyPhp(): void
    {
        date_default_timezone_set(self::name());
    }

    public static function applyPdo(\PDO $pdo): void
    {
        $offset = (new \DateTime('now', new \DateTimeZone(self::name())))->format('P');
        $pdo->exec('SET time_zone = ' . $pdo->quote($offset));
    }
}
