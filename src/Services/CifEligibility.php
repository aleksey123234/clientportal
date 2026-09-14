<?php

declare(strict_types=1);

namespace App\Services;

/**
 * CIF citizenship UI helpers and Finish-time eligibility rules (Phase 3).
 *
 * @see docs/CHANGE_ORDER_PORTAL_JUL2026.md §0.1 / §0.5
 */
class CifEligibility
{
    public const CLIENT_CARE_MESSAGE =
        'Please contact our Client Care department as it seems that you are not eligible to proceed with the application. Client Care Department contact info: Phone: 1-800-543-2137 Ext: 823 Email at clientcare@pardonsandwaivers.com';

    public static function hasWaiver(array $userForms): bool
    {
        return in_array('waiver', $userForms, true)
            || in_array('waiver-renewal', $userForms, true);
    }

    public static function hasCrOrTrp(array $userForms): bool
    {
        return in_array('criminal-rehab', $userForms, true)
            || in_array('trp', $userForms, true);
    }

    /** Split citizenship UI when client has Waiver or Waiver Renewal. */
    public static function isSplitCitizenshipUi(array $userForms): bool
    {
        return self::hasWaiver($userForms);
    }

    /**
     * Finish eligibility checks. Returns list of ['key' => field, 'message' => text].
     * Empty answers are skipped (required validation handles them).
     *
     * @return list<array{key: string, message: string}>
     */
    public static function validate(array $userForms, array $data): array
    {
        $errors = [];
        $msg = self::CLIENT_CARE_MESSAGE;
        $us = isset($data['us_citizenship']) ? (string)$data['us_citizenship'] : '';
        $cad = isset($data['canadian_citizenship']) ? (string)$data['canadian_citizenship'] : '';
        $gc = isset($data['green_card']) ? (string)$data['green_card'] : '';

        if (self::hasCrOrTrp($userForms)) {
            if ($us === 'No') {
                $errors[] = ['key' => 'us_citizenship', 'message' => $msg];
            }
            if ($cad === 'Yes') {
                $errors[] = ['key' => 'canadian_citizenship', 'message' => $msg];
            }
        }

        if (self::hasWaiver($userForms)) {
            if ($us === 'Yes') {
                $errors[] = ['key' => 'us_citizenship', 'message' => $msg];
            }
            if ($gc === 'Yes') {
                $errors[] = ['key' => 'green_card', 'message' => $msg];
            }
            if ($cad === 'No') {
                $errors[] = ['key' => 'canadian_citizenship', 'message' => $msg];
            }
        }

        return $errors;
    }

    /**
     * Unique field keys from validate() results.
     *
     * @param list<array{key: string, message: string}> $eligibilityErrors
     * @return list<string>
     */
    public static function keys(array $eligibilityErrors): array
    {
        $keys = [];
        foreach ($eligibilityErrors as $e) {
            $k = $e['key'] ?? '';
            if ($k !== '' && !in_array($k, $keys, true)) {
                $keys[] = $k;
            }
        }
        return $keys;
    }

    /**
     * Unique messages from validate() results.
     *
     * @param list<array{key: string, message: string}> $eligibilityErrors
     * @return list<string>
     */
    public static function messages(array $eligibilityErrors): array
    {
        $msgs = [];
        foreach ($eligibilityErrors as $e) {
            $m = $e['message'] ?? '';
            if ($m !== '' && !in_array($m, $msgs, true)) {
                $msgs[] = $m;
            }
        }
        return $msgs;
    }
}
