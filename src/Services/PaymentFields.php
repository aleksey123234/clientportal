<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Server-side card field checks before Moneris Direct Post.
 * Expects PAN/CVD already stripped to digits; expiry trimmed.
 */
final class PaymentFields
{
    public static function isValid(string $panDigits, string $expiry, string $cvdDigits): bool
    {
        $expiry = trim($expiry);
        $lenCvd = strlen($cvdDigits);
        return strlen($panDigits) >= 13
            && $expiry !== ''
            && $lenCvd >= 3
            && $lenCvd <= 4;
    }
}
