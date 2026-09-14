<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Documents catalog by service_type (doc_key + titles).
 */
final class DocumentCatalog
{
    public const WAIVER_DOCS = [
        ['key' => 'waiver-ccr',              'title' => 'Canadian Criminal Record Check (CCR)'],
        ['key' => 'waiver-consent-forms',    'title' => 'Consent Forms'],
        ['key' => 'waiver-id',               'title' => 'Identity Documents (Passport)'],
        ['key' => 'waiver-g28-form',         'title' => 'G-28 Form'],
        ['key' => 'waiver-previous-waiver',  'title' => 'Copy of previous Waiver (if any)'],
        ['key' => 'waiver-personal-stmt',    'title' => 'Personal Statement'],
        ['key' => 'waiver-employment',       'title' => 'Employment Letter'],
        ['key' => 'waiver-reference',        'title' => 'Character Reference Letters'],
        ['key' => 'waiver-supporting',       'title' => 'Supporting Documents'],
        ['key' => 'waiver-additional',       'title' => 'Additional Documents'],
        ['key' => 'waiver-granted-pardon',   'title' => 'Granted Pardon'],
        ['key' => 'waiver-port-of-entry',    'title' => 'Port of entry'],
    ];

    public const SERVICES = [
        'pardon' => [
            'label' => 'Pardon',
            'icon'  => 'bi-shield-check',
            'docs'  => [
                ['key' => 'pardon-ccr',            'title' => 'Canadian Criminal Record Check (CCR)'],
                ['key' => 'pardon-consent-forms',  'title' => 'Consent Forms'],
                ['key' => 'pardon-id',             'title' => 'Identity Documents (Passport / Driver License)'],
                ['key' => 'pardon-rsf',            'title' => 'RSF (Record Suspension Application Form)'],
                ['key' => 'pardon-mbf',            'title' => 'MBF (Measure Benefit Form)'],
                ['key' => 'pardon-lprc',           'title' => 'LPRC (Local Police Records Check Form)'],
                ['key' => 'pardon-military',       'title' => 'Military Conduct Sheet Consent Form (if applicable)'],
                ['key' => 'pardon-additional',     'title' => 'Additional Documents'],
            ],
        ],
        'criminal-rehab' => [
            'label' => 'Criminal Rehabilitation',
            'icon'  => 'bi-globe-americas',
            'docs'  => [
                ['key' => 'cr-fbi-check',          'title' => 'FBI Check Report'],
                ['key' => 'cr-consent-forms',      'title' => 'Consent Forms'],
                ['key' => 'cr-id',                 'title' => 'Identity Documents (Passport)'],
                ['key' => 'cr-final-forms',        'title' => 'Final Forms (IMMs + RLS)'],
                ['key' => 'cr-personal-statement', 'title' => 'Personal Statement'],
                ['key' => 'cr-employment-letter',  'title' => 'Employment Letter'],
                ['key' => 'cr-police-cert',        'title' => 'Police Clearance Certificate (PCC)'],
                ['key' => 'cr-reference-letters',  'title' => 'Character Reference Letters'],
                ['key' => 'cr-passport-photos',    'title' => '2 Passport Photos'],
                ['key' => 'cr-supporting',         'title' => 'Supporting Documents'],
                ['key' => 'cr-additional',         'title' => 'Additional Documents'],
            ],
        ],
        'trp' => [
            'label' => 'Temporary Resident Permit',
            'icon'  => 'bi-clock-history',
            'docs'  => [
                ['key' => 'trp-fbi-check',          'title' => 'FBI Check Report'],
                ['key' => 'trp-consent-forms',      'title' => 'Consent Forms'],
                ['key' => 'trp-id',                 'title' => 'Identity Documents (Passport)'],
                ['key' => 'trp-final-forms',        'title' => 'Final Forms (IMMs + RLS)'],
                ['key' => 'trp-personal-statement', 'title' => 'Personal Statement'],
                ['key' => 'trp-employment-letter',  'title' => 'Employment Letter'],
                ['key' => 'trp-police-cert',        'title' => 'Police Clearance Certificate (PCC)'],
                ['key' => 'trp-reference-letters',  'title' => 'Character Reference Letters'],
                ['key' => 'trp-passport-photos',    'title' => '2 Passport Photos'],
                ['key' => 'trp-supporting',         'title' => 'Supporting Documents'],
                ['key' => 'trp-additional',         'title' => 'Additional Documents'],
                ['key' => 'trp-travel-itinerary',   'title' => 'Travel Itinerary / Reason for Visit'],
            ],
        ],
        'nexus' => [
            'label' => 'NEXUS',
            'icon'  => 'bi-airplane',
            'docs'  => [
                ['key' => 'nexus-id',         'title' => 'Identity Documents'],
                ['key' => 'nexus-additional', 'title' => 'Additional Documents'],
            ],
        ],
        'expunging' => [
            'label' => 'Expunging',
            'icon'  => 'bi-eraser',
            'docs'  => [
                ['key' => 'exp-ccr',           'title' => 'Canadian Criminal Record Check (CCR)'],
                ['key' => 'exp-consent-forms', 'title' => 'Consent Forms'],
                ['key' => 'exp-id',            'title' => 'Identity Documents'],
                ['key' => 'exp-additional',    'title' => 'Additional Documents'],
            ],
        ],
        'waiver' => [
            'label' => 'US Entry Waiver',
            'icon'  => 'bi-globe-americas',
            'docs'  => self::WAIVER_DOCS,
        ],
        'waiver-renewal' => [
            'label' => 'US Waiver Renewal',
            'icon'  => 'bi-globe-americas',
            'docs'  => self::WAIVER_DOCS,
        ],
    ];

    public static function services(): array
    {
        return self::SERVICES;
    }

    public static function titleFor(string $serviceType, string $docKey): string
    {
        foreach (self::SERVICES[$serviceType]['docs'] ?? [] as $d) {
            if (($d['key'] ?? '') === $docKey) {
                return (string) $d['title'];
            }
        }
        return $docKey;
    }

    /** @return list<string> */
    public static function allowedKeys(string $serviceType): array
    {
        return array_column(self::SERVICES[$serviceType]['docs'] ?? [], 'key');
    }
}
