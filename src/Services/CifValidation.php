<?php

declare(strict_types=1);

namespace App\Services;

/**
 * CIF Finish / server-side validation.
 */
final class CifValidation
{
    /**
     * Server-side Finish validation (required + timeline gaps).
     *
     * @return list<string>
     */
    public static function validateForFinish(array $schema, array $userForms, array $data): array
    {
        $errors = [];
        $dob = $data['dob'] ?? null;

        foreach ($schema['sections'] as $section) {
            foreach ($section['questions'] as $q) {
                if (empty(array_intersect($q['forms'], $userForms))) {
                    continue;
                }

                if (!CifVisibility::isVisible($q, $data, $userForms)) {
                    continue;
                }

                $val = $data[$q['key']] ?? null;

                // date_optional_day: YYYY-MM or YYYY-MM-DD when present (even if optional)
                if ($q['type'] === 'date_optional_day') {
                    $cell = is_string($val) ? trim($val) : '';
                    if ($cell !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])(-([0-2]\d|3[01]))?$/', $cell)) {
                        $errors[] = $q['label'] . ' must be yyyy-mm or yyyy-mm-dd.';
                    }
                }

                $required = !empty($q['required']);
                if (!empty($q['required_when'])) {
                    $required = CifVisibility::rulesMatch($q['required_when'], $data);
                }
                if (!$required) {
                    continue;
                }

                if ($q['type'] === 'table') {
                    $hasData = false;
                    if (is_array($val)) {
                        foreach ($val as $row) {
                            foreach ($row as $cell) {
                                if ($cell !== '' && $cell !== null && $cell !== 'Present') {
                                    $hasData = true;
                                    break 2;
                                }
                            }
                        }
                    }
                    if (!$hasData) {
                        $errors[] = $q['label'] . ' is required.';
                    }
                } elseif ($val === null || $val === '' || $val === []) {
                    $errors[] = $q['label'] . ' is required.';
                }

                // Timeline for tables — every applicable window must pass
                if ($q['type'] === 'table' && !empty($q['timeline']) && is_array($val)) {
                    $windows = CifTimeline::resolveWindows(
                        $q['timeline'],
                        $userForms,
                        is_string($dob) ? $dob : null
                    );
                    foreach ($windows as $win) {
                        if (!empty($win['error'])) {
                            $errors[] = $win['error'];
                            continue;
                        }
                        if (empty($win['start'])) {
                            continue;
                        }
                        $detail = CifTimeline::validateDetailed(
                            $val,
                            $win['start'],
                            date('Y-m')
                        );
                        foreach ($detail['errors'] as $te) {
                            $errors[] = $q['label'] . ' (' . ($win['label'] ?? $win['mode']) . '): ' . $te;
                        }
                    }
                }

                // yyyy-mm for from/to columns
                if ($q['type'] === 'table' && is_array($val)) {
                    foreach ($val as $ri => $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        foreach (['from', 'to'] as $col) {
                            if (!array_key_exists($col, $row)) {
                                continue;
                            }
                            $cell = trim((string) $row[$col]);
                            if ($cell === '' || strcasecmp($cell, 'Present') === 0) {
                                continue;
                            }
                            if (!CifTimeline::validYm($cell)) {
                                $errors[] = $q['label'] . ' row ' . ($ri + 1) . ': ' . $col . ' must be yyyy-mm.';
                            }
                        }
                    }
                }
            }
        }

        foreach (self::validateSpousalRules($userForms, $data) as $se) {
            $errors[] = $se;
        }

        foreach (self::validateAdultDobFields($data) as $ae) {
            $errors[] = $ae;
        }

        $eligErrs = CifEligibility::validate($userForms, $data);
        foreach (CifEligibility::messages($eligErrs) as $em) {
            $errors[] = $em;
        }

        return $errors;
    }

    /**
     * Applicant and spouse DoBs must be at least 18 years old.
     *
     * @return list<string>
     */
    public static function validateAdultDobFields(array $data): array
    {
        $errors = [];
        $cutoff = (new \DateTimeImmutable('today'))->modify('-18 years');

        $check = function (string $ymd, string $label) use (&$errors, $cutoff): void {
            if ($ymd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
                return;
            }
            try {
                $dob = new \DateTimeImmutable($ymd);
            } catch (\Exception $e) {
                return;
            }
            if ($dob > $cutoff) {
                $errors[] = $label . ': must be at least 18 years old.';
            }
        };

        $check(trim((string) ($data['dob'] ?? '')), 'Date of Birth');
        $check(trim((string) ($data['spouse_current_dob'] ?? '')), 'Current spouse date of birth');

        $formers = $data['former_spouses'] ?? [];
        if (is_array($formers)) {
            foreach ($formers as $idx => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $check(
                    trim((string) ($row['dob'] ?? '')),
                    'Former spouse #' . ($idx + 1) . ' date of birth'
                );
            }
        }

        return $errors;
    }

    /** @return list<string> */
    public static function validateSpousalRules(array $userForms, array $data): array
    {
        $errors = [];
        if (!in_array('waiver', $userForms, true)) {
            return $errors;
        }

        $ms = (string) ($data['marital_status'] ?? '');
        if ($ms === '' || $ms === 'Single') {
            return $errors;
        }

        $formers = $data['former_spouses'] ?? [];
        if (!is_array($formers)) {
            $formers = [];
        }

        $formerReq = ['first_name', 'maiden_name', 'dob', 'birthplace', 'marriage_date_place', 'termination_date', 'termination_place'];
        $hasCompleteFormer = false;
        foreach ($formers as $idx => $row) {
            if (!is_array($row)) {
                continue;
            }
            $any = false;
            foreach ($row as $cell) {
                if (trim((string) $cell) !== '') {
                    $any = true;
                    break;
                }
            }
            if (!$any) {
                continue;
            }
            $complete = true;
            foreach ($formerReq as $k) {
                if (trim((string) ($row[$k] ?? '')) === '') {
                    $complete = false;
                    break;
                }
            }
            if ($complete) {
                $hasCompleteFormer = true;
            } else {
                $errors[] = 'Former spouse #' . ($idx + 1) . ': please complete all fields (A-Number optional).';
            }
        }

        $currentComplete = function (bool $withTerm) use ($data): bool {
            $keys = [
                'spouse_current_first_name',
                'spouse_current_maiden_name',
                'spouse_current_dob',
                'spouse_current_birthplace',
                'spouse_current_marriage_date_place',
            ];
            if ($withTerm) {
                $keys[] = 'spouse_current_termination_date';
                $keys[] = 'spouse_current_termination_place';
            }
            foreach ($keys as $k) {
                if (trim((string) ($data[$k] ?? '')) === '') {
                    return false;
                }
            }
            return true;
        };

        if ($ms === 'Widowed') {
            if (!$currentComplete(true) && !$hasCompleteFormer) {
                $errors[] = 'For Widowed, complete either Current spouse details or at least one full Former spouse entry.';
            }
            return $errors;
        }

        if (in_array($ms, ['Married', 'Common-law'], true)) {
            foreach (['spouse_current_first_name', 'spouse_current_maiden_name', 'spouse_current_dob'] as $k) {
                if (trim((string) ($data[$k] ?? '')) === '') {
                    $errors[] = 'Current spouse first name, family/maiden name, and date of birth are required.';
                    break;
                }
            }
            return $errors;
        }

        if (in_array($ms, ['Divorced', 'Legally Separated', 'Marriage Annulled', 'Other'], true)) {
            foreach (['spouse_current_first_name', 'spouse_current_maiden_name', 'spouse_current_dob'] as $k) {
                if (trim((string) ($data[$k] ?? '')) === '') {
                    $errors[] = 'Current/recent spouse first name, family/maiden name, and date of birth are required.';
                    break;
                }
            }
            if (trim((string) ($data['spouse_current_termination_date'] ?? '')) === ''
                || trim((string) ($data['spouse_current_termination_place'] ?? '')) === '') {
                $errors[] = 'Marriage termination date and place are required.';
            }
            if (!$hasCompleteFormer) {
                $errors[] = 'Please add at least one complete former spouse entry.';
            }
        }

        return $errors;
    }
}
