<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Server-side payment totals (subtotal + provincial tax).
 */
final class PaymentQuoteService
{
    public const PROVINCE_TAX = [
        'Ontario'               => 0.13,
        'British Columbia'      => 0.12,
        'Alberta'               => 0.05,
        'Quebec'                => 0.14975,
        'Nova Scotia'           => 0.15,
        'New Brunswick'         => 0.15,
        'Manitoba'              => 0.12,
        'Saskatchewan'          => 0.11,
        'PEI'                   => 0.15,
        'Newfoundland'          => 0.15,
        'Northwest Territories' => 0.05,
        'Nunavut'               => 0.05,
        'Yukon'                 => 0.05,
    ];

    /** Profile `province_state` codes → tax map keys. */
    public const CODE_TO_PROVINCE = [
        'ON' => 'Ontario',
        'BC' => 'British Columbia',
        'AB' => 'Alberta',
        'QC' => 'Quebec',
        'NS' => 'Nova Scotia',
        'NB' => 'New Brunswick',
        'MB' => 'Manitoba',
        'SK' => 'Saskatchewan',
        'PE' => 'PEI',
        'NL' => 'Newfoundland',
        'NT' => 'Northwest Territories',
        'NU' => 'Nunavut',
        'YT' => 'Yukon',
    ];

    public const TAX_PROVINCE = 'Ontario';

    /**
     * Resolve a profile code (ON) or full name to a PROVINCE_TAX key.
     * Unknown / empty → TAX_PROVINCE (Ontario).
     */
    public static function resolveProvince(?string $codeOrName): string
    {
        $raw = trim((string) $codeOrName);
        if ($raw === '') {
            return self::TAX_PROVINCE;
        }

        $upper = strtoupper($raw);
        if (isset(self::CODE_TO_PROVINCE[$upper])) {
            return self::CODE_TO_PROVINCE[$upper];
        }

        if (isset(self::PROVINCE_TAX[$raw])) {
            return $raw;
        }

        // Case-insensitive full-name match
        foreach (array_keys(self::PROVINCE_TAX) as $name) {
            if (strcasecmp($name, $raw) === 0) {
                return $name;
            }
        }

        return self::TAX_PROVINCE;
    }

    public static function taxRate(?string $province = null): float
    {
        $province = self::resolveProvince($province);
        return (float) (self::PROVINCE_TAX[$province] ?? 0.0);
    }

    /**
     * @param list<array{amount?: float|string|int}> $paymentRows
     * @return array{subtotal: float, tax_rate: float, tax: float, total: float}
     */
    public static function quote(array $paymentRows, ?string $province = null): array
    {
        $province = self::resolveProvince($province);
        $subtotal = 0.0;
        foreach ($paymentRows as $row) {
            $subtotal += (float) ($row['amount'] ?? 0);
        }
        $taxRate = self::taxRate($province);
        $total = round($subtotal * (1 + $taxRate), 2);
        $tax = round($total - $subtotal, 2);
        return [
            'subtotal' => round($subtotal, 2),
            'tax_rate' => $taxRate,
            'tax'      => $tax,
            'total'    => $total,
        ];
    }
}
