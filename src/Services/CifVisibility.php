<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Shared CIF conditional visibility (show_when / show_also_when_forms / hidden_when).
 *
 * Used by CifController (Finish / progress) and CifPdfGenerator.
 * JS port: public/js/cif/visibility.js — keep behaviour in sync.
 */
class CifVisibility
{
    /**
     * @param array<string, mixed> $rules  e.g. ['denied_entry_canada' => 'Yes'] or ['marital_status' => ['Married', …]]
     * @param array<string, mixed> $data   CIF response_data
     */
    public static function rulesMatch(array $rules, array $data): bool
    {
        foreach ($rules as $key => $expected) {
            if ($key === 'value') {
                continue;
            }
            $actual = (string)($data[$key] ?? '');
            if (is_array($expected)) {
                if (!in_array($actual, $expected, true)) {
                    return false;
                }
            } elseif ($actual !== (string)$expected) {
                return false;
            }
        }
        return true;
    }

    /**
     * Question is visible when:
     *   (show_also_when_forms ∩ userForms) ≠ ∅  OR  show_when matches
     *   AND not hidden_when
     * If neither show_when nor show_also_when_forms is set → visible (subject to hidden_when).
     *
     * @param array<string, mixed> $q
     * @param array<string, mixed> $data
     * @param list<string>         $userForms
     */
    public static function isVisible(array $q, array $data, array $userForms = []): bool
    {
        $hasShowWhen = !empty($q['show_when']);
        $hasAlso = !empty($q['show_also_when_forms']);
        if ($hasShowWhen || $hasAlso) {
            $alsoOk = $hasAlso && !empty(array_intersect($q['show_also_when_forms'], $userForms));
            $whenOk = $hasShowWhen && self::rulesMatch($q['show_when'], $data);
            if (!$alsoOk && !$whenOk) {
                return false;
            }
        }
        if (!empty($q['hidden_when']) && self::rulesMatch($q['hidden_when'], $data)) {
            return false;
        }
        return true;
    }
}
