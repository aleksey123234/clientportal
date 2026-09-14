<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Timeline / gap validation for CIF address & employment tables.
 *
 * Month granularity (yyyy-mm). Adjacent months (2013-12 → 2014-01) are OK.
 * A gap of 2+ months is an error. Overlapping intervals are merged first.
 *
 * Dual windows (Phase 5): when both since_18 and last_years apply (e.g. CR+Pardon),
 * every window must pass independently — no priority of one over the other.
 */
class CifTimeline
{
    /**
     * Resolve ALL applicable timeline windows (since_18 and/or last_years).
     *
     * @param array       $timeline  Schema timeline block
     * @param array       $userForms Active CIF form types
     * @param string|null $dob       Y-m-d
     * @return list<array{mode:string,start:string,label:string,years?:int,error?:string}>
     */
    public static function resolveWindows(array $timeline, array $userForms, ?string $dob): array
    {
        $windows = [];

        $since18Forms = $timeline['since_18'] ?? [];
        if (!empty(array_intersect($since18Forms, $userForms))) {
            if (!$dob || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
                $windows[] = [
                    'mode'  => 'since_18',
                    'start' => '',
                    'label' => 'age 18',
                    'error' => 'Please enter Date of Birth before completing this history.',
                ];
            } else {
                $parts = explode('-', $dob);
                $y = (int)$parts[0] + 18;
                $m = (int)$parts[1];
                $windows[] = [
                    'mode'  => 'since_18',
                    'start' => sprintf('%04d-%02d', $y, $m),
                    'label' => 'age 18',
                ];
            }
        }

        $lastYears = $timeline['last_years'] ?? [];
        $years = 0;
        foreach ($lastYears as $form => $y) {
            if (in_array($form, $userForms, true)) {
                $years = max($years, (int)$y);
            }
        }
        if ($years > 0) {
            $now = new \DateTimeImmutable('first day of this month');
            $startDt = $now->modify("-{$years} years");
            $windows[] = [
                'mode'  => 'last_years',
                'start' => $startDt->format('Y-m'),
                'label' => "last {$years} years",
                'years' => $years,
            ];
        }

        return $windows;
    }

    /**
     * First applicable window (compat). Prefer resolveWindows() for dual validation.
     *
     * @return array{mode:string,start:string,label:string}|null
     */
    public static function resolveWindow(array $timeline, array $userForms, ?string $dob): ?array
    {
        $windows = self::resolveWindows($timeline, $userForms, $dob);
        return $windows[0] ?? null;
    }

    /**
     * Validate rows; return messages only.
     *
     * @return string[]
     */
    public static function validate(array $rows, string $windowStart, string $todayYm): array
    {
        return self::validateDetailed($rows, $windowStart, $todayYm)['errors'];
    }

    /**
     * Validate coverage + gaps; return errors and highlight targets (0-based row index).
     *
     * @return array{errors: string[], highlights: list<array{row:int,col:string}>}
     */
    public static function validateDetailed(array $rows, string $windowStart, string $todayYm): array
    {
        $errors = [];
        $highlights = [];

        $addHl = function (int $row, string $col) use (&$highlights): void {
            foreach ($highlights as $h) {
                if ($h['row'] === $row && $h['col'] === $col) {
                    return;
                }
            }
            $highlights[] = ['row' => $row, 'col' => $col];
        };

        if ($windowStart === '' || !self::validYm($windowStart)) {
            $errors[] = 'Invalid timeline window start.';
            return ['errors' => $errors, 'highlights' => $highlights];
        }
        if (!self::validYm($todayYm)) {
            $todayYm = date('Y-m');
        }

        // [a, b, fromRow, toRow]
        $intervals = [];
        $rowFormatErrors = false;
        foreach ($rows as $idx => $row) {
            $from = trim((string)($row['from'] ?? ''));
            $toRaw = trim((string)($row['to'] ?? ''));
            $to = $toRaw;
            if ($from === '' && $to === '') {
                continue;
            }
            if ($to === 'Present' || strcasecmp($to, 'present') === 0) {
                $to = $todayYm;
            }
            $fromOk = self::validYm($from);
            $toOk = self::validYm($to);
            if (!$fromOk || !$toOk) {
                $errors[] = 'Row ' . ($idx + 1) . ': From/To must be yyyy-mm (or Present).';
                $rowFormatErrors = true;
                if (!$fromOk) {
                    $addHl((int)$idx, 'from');
                }
                if ($toRaw === '' || (strcasecmp($toRaw, 'present') !== 0 && !self::validYm($toRaw))) {
                    $addHl((int)$idx, 'to');
                }
                continue;
            }
            $a = self::ymToInt($from);
            $b = self::ymToInt($to);
            if ($a > $b) {
                $errors[] = 'Row ' . ($idx + 1) . ': From must be on or before To.';
                $rowFormatErrors = true;
                $addHl((int)$idx, 'from');
                $addHl((int)$idx, 'to');
                continue;
            }
            $intervals[] = [
                'a' => $a,
                'b' => $b,
                'fromRow' => (int)$idx,
                'toRow' => (int)$idx,
            ];
        }

        if (empty($intervals)) {
            if (!$rowFormatErrors) {
                $errors[] = 'Please add at least one history row covering the required period.';
            }
            return ['errors' => array_values(array_unique($errors)), 'highlights' => $highlights];
        }

        usort($intervals, function ($x, $y) {
            return $x['a'] <=> $y['a'];
        });

        $merged = [];
        $gapFound = false;
        foreach ($intervals as $iv) {
            if (empty($merged)) {
                $merged[] = $iv;
                continue;
            }
            $lastIdx = count($merged) - 1;
            if ($iv['a'] <= $merged[$lastIdx]['b'] + 1) {
                if ($iv['b'] > $merged[$lastIdx]['b']) {
                    $merged[$lastIdx]['b'] = $iv['b'];
                    $merged[$lastIdx]['toRow'] = $iv['toRow'];
                }
            } else {
                $gapStart = self::intToYm($merged[$lastIdx]['b'] + 1);
                $gapEnd   = self::intToYm($iv['a'] - 1);
                $errors[] = "Gap detected between {$gapStart} and {$gapEnd}. Periods may touch by one month (e.g. 2013-12 then 2014-01).";
                $addHl($merged[$lastIdx]['toRow'], 'to');
                $addHl($iv['fromRow'], 'from');
                $gapFound = true;
                $merged[] = $iv;
            }
        }

        // Old behavior: if gaps or row format errors, skip coverage checks
        if ($gapFound || $rowFormatErrors) {
            return [
                'errors' => array_values(array_unique($errors)),
                'highlights' => $highlights,
            ];
        }

        $winStart = self::ymToInt($windowStart);
        $winEnd   = self::ymToInt($todayYm);

        if ($merged[0]['a'] > $winStart) {
            $errors[] = 'Coverage must start on or before ' . $windowStart . '.';
            $addHl($merged[0]['fromRow'], 'from');
        }
        if ($merged[count($merged) - 1]['b'] < $winEnd) {
            $errors[] = 'Coverage must continue through present (' . $todayYm . ').';
            $addHl($merged[count($merged) - 1]['toRow'], 'to');
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'highlights' => $highlights,
        ];
    }

    public static function validYm(string $ym): bool
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            return false;
        }
        $m = (int)substr($ym, 5, 2);
        return $m >= 1 && $m <= 12;
    }

    public static function ymToInt(string $ym): int
    {
        $y = (int)substr($ym, 0, 4);
        $m = (int)substr($ym, 5, 2);
        return $y * 12 + ($m - 1);
    }

    public static function intToYm(int $n): string
    {
        $y = intdiv($n, 12);
        $m = ($n % 12) + 1;
        return sprintf('%04d-%02d', $y, $m);
    }

    /**
     * Migrate legacy CIF JSON keys (citizenship text, denied_entry textarea).
     */
    public static function migrateResponseData(array $data): array
    {
        // other_citizenships was free text → yes_no + country
        if (isset($data['other_citizenships']) && $data['other_citizenships'] !== 'Yes' && $data['other_citizenships'] !== 'No') {
            $old = trim((string)$data['other_citizenships']);
            if ($old !== '') {
                $data['other_citizenships'] = 'Yes';
                if (empty($data['other_citizenships_country'])) {
                    $data['other_citizenships_country'] = $old;
                }
            } else {
                unset($data['other_citizenships']);
            }
        }

        // denied_entry_canada was textarea "Have you… When?"
        if (isset($data['denied_entry_canada']) && !in_array($data['denied_entry_canada'], ['Yes', 'No'], true)) {
            $old = trim((string)$data['denied_entry_canada']);
            if ($old === '') {
                unset($data['denied_entry_canada']);
            } else {
                $data['denied_entry_canada'] = 'Yes';
                if (empty($data['denied_entry_when']) && preg_match('/(\d{4}-\d{2}-\d{2}|\d{4}-\d{2}|\d{1,2}\/\d{1,2}\/\d{2,4})/', $old, $m)) {
                    $data['denied_entry_when'] = $m[1];
                }
            }
        }

        // Flatten former spouse keys → former_spouses table
        if (empty($data['former_spouses']) || !is_array($data['former_spouses'])) {
            $map = [
                'first_name' => 'spouse_former_first_name',
                'maiden_name' => 'spouse_former_maiden_name',
                'dob' => 'spouse_former_dob',
                'birthplace' => 'spouse_former_birthplace',
                'marriage_date_place' => 'spouse_former_marriage_date_place',
                'termination_date' => 'spouse_former_termination_date',
                'termination_place' => 'spouse_former_termination_place',
                'alien_number' => 'spouse_former_alien_number',
            ];
            $row = [];
            $any = false;
            foreach ($map as $col => $oldKey) {
                $v = trim((string)($data[$oldKey] ?? ''));
                $row[$col] = $v;
                if ($v !== '') $any = true;
            }
            if ($any) {
                $data['former_spouses'] = [$row];
            }
        }

        // proposed_entry_date → intended_travel_date (Phase 4)
        if (!empty($data['proposed_entry_date']) && empty($data['intended_travel_date'])) {
            $data['intended_travel_date'] = $data['proposed_entry_date'];
        }
        unset($data['proposed_entry_date']);

        // offences_expunged was free text → yes_no + which (Phase 7)
        if (isset($data['offences_expunged']) && !in_array($data['offences_expunged'], ['Yes', 'No', ''], true)) {
            $old = trim((string)$data['offences_expunged']);
            if ($old !== '' && strcasecmp($old, 'No') !== 0) {
                if (empty($data['offences_expunged_which'])) {
                    $data['offences_expunged_which'] = $old;
                }
                $data['offences_expunged'] = 'Yes';
            } elseif (strcasecmp($old, 'No') === 0) {
                unset($data['offences_expunged']);
            }
        }

        // waiver_convicted_in_us textarea → yes_no + offences_table_us (Phase 7)
        if (!empty($data['waiver_convicted_in_us']) && is_string($data['waiver_convicted_in_us'])) {
            $oldUs = trim($data['waiver_convicted_in_us']);
            if ($oldUs !== '') {
                $data['waiver_convicted_us'] = 'Yes';
                if (empty($data['offences_table_us']) || !is_array($data['offences_table_us'])) {
                    $data['offences_table_us'] = [[
                        'date' => '',
                        'place' => '',
                        'description' => $oldUs,
                        'sentence' => '',
                        'statute' => '',
                    ]];
                }
            }
        }
        unset($data['waiver_convicted_in_us']);

        return $data;
    }
}
