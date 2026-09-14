<?php

declare(strict_types=1);

namespace App\Support;

/**
 * HTML escaping for views.
 */
final class Html
{
    public static function e(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}
