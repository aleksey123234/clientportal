<?php

/**
 * CIF Question Schema — Client Information Form
 *
 * Maps every question to its applicable forms and input type.
 *
 * Each question has:
 *   - key:            unique identifier used as the DB field name in cif_responses.response_data JSON
 *   - label:          display label shown to the client
 *   - type:           input type (text, date, month, date_optional_day, dropdown, checkbox, checkboxes, yes_no, textarea, table)
 *   - section:        which section/step the question belongs to
 *   - forms:          array of service_type values that need this question
 *   - options:        (for dropdown/checkboxes) available choices
 *   - required:       whether the field is always required
 *   - columns:        (for table type) column definitions
 *   - help:           optional help text
 *   - row:            optional layout group key — questions sharing a row render side-by-side
 *   - col:            optional Bootstrap column width (1–12) within the row
 *   - show_when:      optional conditional visibility, e.g. ['other_citizenships' => 'Yes']
 *   - show_also_when_forms: optional — visible if client has any of these forms OR show_when matches
 *   - required_when:  optional conditional required, e.g. ['has_drivers_license' => 'Yes']
 *   - validate:       optional validation rules, e.g. ['maxLength' => 100, 'pattern' => 'name'],
 *                     ['type' => 'phone'], or ['type' => 'date', 'not_future' => true]
 *   - lock_value / lock_when: optional locked display value when a condition is met,
 *                     e.g. ['is_current_military' => 'Yes', 'value' => 'Present']
 *   - timeline:       (for address/employment tables) period rules per form:
 *                     'since_18' => form list; 'last_years' => [form => years]
 *
 * Types: yes_no = Yes/No radios; checkboxes = multi-select; checkbox = single Yes checkbox (rare).
 *
 * TOC (section id → content):
 *   personal     — names, DOB, citizenship, contact
 *   spousal      — marital / former spouses (Waiver)
 *   addresses    — address history (+ timeline)
 *   employment   — employment history (+ timeline)
 *   military     — CR served/abroad + Pardon Canadian Forces
 *   criminal     — offences tables, expunged, Waiver CA/US, Expg charges
 *   biographic   — TRP/CR biographic
 *   travel       — intended travel, denied/deported, travel pack
 *
 * Form types map to service_costs.service_type:
 *   'pardon'         → Record Suspension (Pardon)
 *   'criminal-rehab' → Criminal Rehabilitation (CR Rehab)
 *   'trp'            → Temporary Residence Permit
 *   'expunging'      → Record Expungement
 *   'waiver'         → US Entry Waiver
 *   'waiver-renewal' → US Waiver Renewal
 *
 * Exclusion rules (handled in CifController):
 *   - If client has CR → skip TRP CIF
 *   - If client has Pardon → skip EXPG CIF
 *
 * @see src/controllers/CifController.php
 * @see src/views/cif/cif-page.php
 */

// All form types
define('CIF_ALL_FORMS', ['pardon', 'criminal-rehab', 'trp', 'expunging', 'waiver', 'waiver-renewal']);

// Forms that use the "since age 18" address/employment history
define('CIF_SINCE_18', ['trp', 'criminal-rehab']);

// Travel core (intended date, length of stay, travel pack) — all except Pardon/Expg
define('CIF_TRAVEL_CORE', ['criminal-rehab', 'trp', 'waiver', 'waiver-renewal']);

// Forms that use "last 5/10 years" address history
define('CIF_LAST_YEARS', ['pardon', 'waiver', 'waiver-renewal']);

/**
 * Offences table columns — same data key `statute`; label Statute # vs Police by context.
 *
 * @param string $statuteOrPolice 'Police' or anything else → 'Statute #'
 * @return list<array{key:string,label:string,type:string}>
 */
function cif_offences_columns(string $statuteOrPolice = 'Statute'): array
{
    $statuteLabel = ($statuteOrPolice === 'Police') ? 'Police' : 'Statute #';
    return [
        ['key' => 'date',        'label' => 'Date of Offence',     'type' => 'month'],
        ['key' => 'place',       'label' => 'Place of Offence',    'type' => 'text'],
        ['key' => 'description', 'label' => 'Offence Description', 'type' => 'text'],
        ['key' => 'sentence',    'label' => 'Sentence',            'type' => 'text'],
        ['key' => 'statute',     'label' => $statuteLabel,         'type' => 'text'],
    ];
}

return [
    // ═══════════════════════════════════════════════════════════
    // SECTION 1: Personal Information
    // ═══════════════════════════════════════════════════════════
    'sections' => [
        [
            'id'    => 'personal',
            'title' => 'Personal Information',
            'icon'  => 'bi-person',
            'questions' => [
                [
                    'key'      => 'first_name',
                    'label'    => 'First Name',
                    'type'     => 'text',
                    'required' => true,
                    'forms'    => CIF_ALL_FORMS,
                    'row'      => 'personal_names',
                    'col'      => 3,
                    'validate' => ['maxLength' => 100, 'pattern' => 'name'],
                ],
                [
                    'key'      => 'middle_names',
                    'label'    => 'Middle Names',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => CIF_ALL_FORMS,
                    'row'      => 'personal_names',
                    'col'      => 3,
                    'validate' => ['maxLength' => 100, 'pattern' => 'name'],
                ],
                [
                    'key'      => 'last_name',
                    'label'    => 'Last Name',
                    'type'     => 'text',
                    'required' => true,
                    'forms'    => CIF_ALL_FORMS,
                    'row'      => 'personal_names',
                    'col'      => 3,
                    'validate' => ['maxLength' => 100, 'pattern' => 'name'],
                ],
                [
                    'key'      => 'gender',
                    'label'    => 'Gender',
                    'type'     => 'dropdown',
                    'required' => true,
                    'options'  => ['Male', 'Female'],
                    'forms'    => CIF_ALL_FORMS,
                    'row'      => 'personal_names',
                    'col'      => 3,
                ],
                [
                    'key'      => 'other_names',
                    'label'    => 'Other Legal Names Used',
                    'help'     => 'Include birth name, legal name change, married name or alias',
                    'type'     => 'textarea',
                    'required' => false,
                    'forms'    => CIF_ALL_FORMS,
                    'row'      => 'personal_other_names',
                    'col'      => 12,
                    'validate' => ['maxLength' => 500],
                ],
                [
                    'key'      => 'dob',
                    'label'    => 'Date of Birth',
                    'type'     => 'date',
                    'required' => true,
                    'forms'    => CIF_ALL_FORMS,
                    'row'      => 'personal_dob',
                    'col'      => 4,
                    'validate' => ['type' => 'date', 'not_future' => true],
                ],
                [
                    'key'      => 'country_of_birth',
                    'label'    => 'Country of Birth',
                    'type'     => 'dropdown',
                    'required' => true,
                    'options'  => ['Canada', 'United States', 'Other'],
                    'forms'    => CIF_ALL_FORMS,
                    'row'      => 'personal_birth',
                    'col'      => 6,
                ],
                [
                    'key'      => 'place_of_birth',
                    'label'    => 'Place of Birth (City, Province)',
                    'type'     => 'text',
                    'required' => true,
                    'forms'    => CIF_ALL_FORMS,
                    'row'      => 'personal_birth',
                    'col'      => 6,
                ],
                [
                    'key'      => 'us_citizenship',
                    'label'    => 'US citizenship/Green Card',
                    'type'     => 'yes_no',
                    'required' => true,
                    'forms'    => ['trp', 'criminal-rehab', 'waiver', 'waiver-renewal'],
                    'row'      => 'personal_citizen',
                    'col'      => 4,
                ],
                [
                    'key'      => 'green_card',
                    'label'    => 'Green Card',
                    'type'     => 'yes_no',
                    'required' => true,
                    'forms'    => ['waiver', 'waiver-renewal'],
                    'row'      => 'personal_citizen',
                    'col'      => 4,
                ],
                [
                    'key'      => 'canadian_citizenship',
                    'label'    => 'Canadian Citizenship',
                    'type'     => 'yes_no',
                    'required' => true,
                    'forms'    => ['trp', 'criminal-rehab', 'waiver', 'waiver-renewal'],
                    'row'      => 'personal_citizen',
                    'col'      => 4,
                ],
                [
                    'key'      => 'other_citizenships',
                    'label'    => 'Other Citizenships',
                    'type'     => 'yes_no',
                    'required' => true,
                    'forms'    => ['trp', 'criminal-rehab', 'waiver', 'waiver-renewal'],
                    'row'      => 'personal_other',
                    'col'      => 4,
                ],
                [
                    'key'      => 'other_citizenships_country',
                    'label'    => 'Other Citizenship Country / Countries',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => ['trp', 'criminal-rehab', 'waiver', 'waiver-renewal'],
                    'row'      => 'personal_citizen_country',
                    'col'      => 12,
                    'show_when'     => ['other_citizenships' => 'Yes'],
                    'required_when' => ['other_citizenships' => 'Yes'],
                ],
                [
                    'key'      => 'permanent_resident',
                    'label'    => 'Permanent Resident',
                    'type'     => 'yes_no',
                    'required' => false,
                    'forms'    => ['criminal-rehab'],
                    'hidden_when' => ['country_of_birth' => 'United States'],
                    'col'      => 12,
                ],
                [
                    'key'      => 'marital_status',
                    'label'    => 'Marital Status',
                    'type'     => 'dropdown',
                    'required' => true,
                    'options'  => ['Single', 'Common-law', 'Married', 'Divorced', 'Widowed', 'Legally Separated', 'Marriage Annulled', 'Other'],
                    'forms'    => ['trp', 'criminal-rehab', 'waiver'],
                    'col'      => 12,
                ],
                [
                    'key'      => 'resided_in_canada_6mo',
                    'label'    => 'Have you ever resided in Canada for over 6 months?',
                    'type'     => 'yes_no',
                    'required' => true,
                    'forms'    => ['criminal-rehab'],
                    'col'      => 12,
                ],
                [
                    'key'      => 'residency_status',
                    'label'    => 'Status during residency in Canada',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => ['criminal-rehab'],
                    'col'      => 12,
                ],
                [
                    'key'      => 'has_drivers_license',
                    'label'    => "Do you have a valid driver's license?",
                    'type'     => 'yes_no',
                    'required' => true,
                    'forms'    => ['pardon'],
                    'row'      => 'personal_dl',
                    'col'      => 4,
                ],
                [
                    'key'      => 'drivers_license_province',
                    'label'    => 'Province of Issue',
                    'type'     => 'dropdown',
                    'required' => false,
                    'options'  => [
                        'Alberta',
                        'British Columbia',
                        'Manitoba',
                        'New Brunswick',
                        'Newfoundland and Labrador',
                        'Northwest Territories',
                        'Nova Scotia',
                        'Nunavut',
                        'Ontario',
                        'Prince Edward Island',
                        'Quebec',
                        'Saskatchewan',
                        'Yukon',
                    ],
                    'forms'    => ['pardon'],
                    'row'      => 'personal_dl',
                    'col'      => 4,
                    'show_when'     => ['has_drivers_license' => 'Yes'],
                    'required_when' => ['has_drivers_license' => 'Yes'],
                ],
                [
                    'key'      => 'drivers_license_number',
                    'label'    => "Driver's License Number",
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => ['pardon'],
                    'row'      => 'personal_dl',
                    'col'      => 4,
                    'show_when'     => ['has_drivers_license' => 'Yes'],
                    'required_when' => ['has_drivers_license' => 'Yes'],
                ],
                [
                    'key'      => 'primary_phone',
                    'label'    => 'Primary Phone #',
                    'type'     => 'text',
                    'required' => true,
                    'forms'    => CIF_ALL_FORMS,
                    'row'      => 'personal_phones',
                    'col'      => 6,
                    'validate' => ['type' => 'phone'],
                ],
                [
                    'key'      => 'alternative_phone',
                    'label'    => 'Alternative Phone #',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => CIF_ALL_FORMS,
                    'row'      => 'personal_phones',
                    'col'      => 6,
                    'validate' => ['type' => 'phone'],
                ],
            ],
        ],

        // ═══════════════════════════════════════════════════════════
        // SECTION 2: Previous Addresses
        // ═══════════════════════════════════════════════════════════
        [
            'id'    => 'addresses',
            'title' => 'Residential Addresses',
            'icon'  => 'bi-house',
            'help'  => 'List present address first. Do NOT use PO Boxes. Leave no gaps. For periods unemployed or studying, record those under Employment History.',
            'questions' => [
                [
                    'key'      => 'addresses',
                    'label'    => 'Residential Addresses',
                    'help'     => 'List present address first. No PO Boxes. Leave no gaps between addresses. First row "To" is automatically set to Present. Periods unemployed/student/traveling belong in Employment History.',
                    'type'     => 'table',
                    'required' => true,
                    'forms'    => ['trp', 'criminal-rehab', 'pardon', 'waiver', 'waiver-renewal'],
                    'columns'  => [
                        ['key' => 'street',  'label' => 'Street Address',    'type' => 'text'],
                        ['key' => 'city',    'label' => 'City/Town',         'type' => 'text'],
                        ['key' => 'prov',    'label' => 'Prov/State',        'type' => 'text'],
                        ['key' => 'country', 'label' => 'Country',           'type' => 'text'],
                        ['key' => 'from',    'label' => 'From (yyyy-mm)',    'type' => 'month'],
                        ['key' => 'to',      'label' => 'To (yyyy-mm)',      'type' => 'month'],
                    ],
                    'pdf_rows' => [
                        'trp'            => 9,
                        'criminal-rehab' => 7,
                        'pardon'         => 4,
                        'waiver'         => 5,
                        'waiver-renewal' => 2,
                    ],
                    'timeline' => [
                        'since_18'   => ['trp', 'criminal-rehab'],
                        'last_years' => ['pardon' => 10, 'waiver' => 5, 'waiver-renewal' => 5],
                    ],
                ],
            ],
        ],

        // ═══════════════════════════════════════════════════════════
        // SECTION 3: Employment History
        // ═══════════════════════════════════════════════════════════
        [
            'id'    => 'employment',
            'title' => 'Employment History',
            'icon'  => 'bi-briefcase',
            'questions' => [
                [
                    'key'      => 'currently_employed',
                    'label'    => 'Are you currently employed?',
                    'type'     => 'yes_no',
                    'required' => true,
                    'forms'    => ['pardon'],
                    'row'      => 'employment_pardon',
                    'col'      => 4,
                ],
                [
                    'key'      => 'present_employer',
                    'label'    => 'Present Employer',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => ['pardon'],
                    'row'      => 'employment_pardon',
                    'col'      => 4,
                    'show_when'     => ['currently_employed' => 'Yes'],
                    'required_when' => ['currently_employed' => 'Yes'],
                ],
                [
                    'key'      => 'employment_length',
                    'label'    => 'Length of Employment (years)',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => ['pardon'],
                    'row'      => 'employment_pardon',
                    'col'      => 4,
                    'show_when'     => ['currently_employed' => 'Yes'],
                    'required_when' => ['currently_employed' => 'Yes'],
                ],
                [
                    'key'      => 'employment_history',
                    'label'    => 'Employment History',
                    'help'     => 'List present employer first. Leave no gaps. For gaps use Unemployed, Student, or Traveling and close those months. First row "To" is Present when currently employed.',
                    'type'     => 'table',
                    'required' => true,
                    'forms'    => ['trp', 'criminal-rehab', 'waiver', 'waiver-renewal'],
                    'columns'  => [
                        ['key' => 'employer',   'label' => 'Full Name & Address of Employer', 'type' => 'text'],
                        ['key' => 'occupation', 'label' => 'Occupation',                      'type' => 'text'],
                        ['key' => 'from',       'label' => 'From (yyyy-mm)',                  'type' => 'month'],
                        ['key' => 'to',         'label' => 'To (yyyy-mm)',                    'type' => 'month'],
                    ],
                    'pdf_rows' => [
                        'trp'            => 10,
                        'criminal-rehab' => 10,
                        'waiver'         => 6,
                        'waiver-renewal' => 2,
                    ],
                    'timeline' => [
                        'since_18'   => ['trp', 'criminal-rehab'],
                        'last_years' => ['waiver' => 5, 'waiver-renewal' => 5],
                    ],
                ],
            ],
        ],

        // ═══════════════════════════════════════════════════════════
        // SECTION 4: Military Service (Pardon CF + CR served / abroad)
        // ═══════════════════════════════════════════════════════════
        [
            'id'    => 'military',
            'title' => 'Military Service History',
            'icon'  => 'bi-shield-shaded',
            'questions' => [
                // CR-only (NOT TRP) — abroad follow-ups below (same section)
                [
                    'key'      => 'cr_served_military',
                    'label'    => 'Have you ever served in military?',
                    'type'     => 'yes_no',
                    'required' => false,
                    'forms'    => ['criminal-rehab'],
                    'col'      => 12,
                ],
                [
                    'key'           => 'military_abroad_dd214',
                    'label'         => 'Have you ever served abroad (foreign service on DD214) for 6 months or over?',
                    'type'          => 'yes_no',
                    'required'      => false,
                    'forms'         => ['criminal-rehab'],
                    'show_when'     => ['cr_served_military' => 'Yes'],
                ],
                [
                    'key'           => 'military_abroad_service',
                    'label'         => 'Which country(ies) did you serve in?',
                    'type'          => 'table',
                    'required'      => false,
                    'forms'         => ['criminal-rehab'],
                    'show_when'     => ['military_abroad_dd214' => 'Yes'],
                    'required_when' => ['military_abroad_dd214' => 'Yes'],
                    'columns'       => [
                        ['key' => 'country', 'label' => 'Country',          'type' => 'text'],
                        ['key' => 'from',    'label' => 'From (yyyy-mm)',   'type' => 'month'],
                        ['key' => 'to',      'label' => 'To (yyyy-mm)',     'type' => 'month'],
                    ],
                    'add_label'     => 'Add period',
                ],
                [
                    'key'      => 'is_military_member',
                    'label'    => 'Are you or have you ever been a member of the Canadian Forces?',
                    'type'     => 'yes_no',
                    'required' => true,
                    'forms'    => ['pardon'],
                    'col'      => 12,
                ],
                [
                    'key'      => 'military_service_level',
                    'label'    => 'Level of Service',
                    'type'     => 'dropdown',
                    'options'  => ['Regular Forces', 'Reserve Forces'],
                    'required' => false,
                    'forms'    => ['pardon'],
                    'row'      => 'military_r2',
                    'col'      => 4,
                    'show_when'     => ['is_military_member' => 'Yes'],
                    'required_when' => ['is_military_member' => 'Yes'],
                ],
                [
                    'key'      => 'is_current_military',
                    'label'    => 'Are you currently a member of the Canadian Forces?',
                    'type'     => 'yes_no',
                    'required' => false,
                    'forms'    => ['pardon'],
                    'row'      => 'military_r2',
                    'col'      => 4,
                    'show_when'     => ['is_military_member' => 'Yes'],
                    'required_when' => ['is_military_member' => 'Yes'],
                ],
                [
                    'key'      => 'military_service_id',
                    'label'    => 'Military / Service ID Number',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => ['pardon'],
                    'row'      => 'military_r2',
                    'col'      => 4,
                    'show_when'     => ['is_current_military' => 'Yes'],
                    'required_when' => ['is_current_military' => 'Yes'],
                ],
                [
                    'key'      => 'military_unit_name',
                    'label'    => 'Unit',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => ['pardon'],
                    'row'      => 'military_r3',
                    'col'      => 6,
                    'show_when'     => ['is_military_member' => 'Yes'],
                    'required_when' => ['is_military_member' => 'Yes'],
                ],
                [
                    'key'      => 'military_unit_address',
                    'label'    => 'Complete mailing address of your unit',
                    'type'     => 'textarea',
                    'required' => false,
                    'forms'    => ['pardon'],
                    'row'      => 'military_r3',
                    'col'      => 6,
                    'show_when'     => ['is_military_member' => 'Yes'],
                    'required_when' => ['is_military_member' => 'Yes'],
                ],
                [
                    'key'      => 'military_service_from',
                    'label'    => 'Years of Service — From',
                    'type'     => 'month',
                    'required' => false,
                    'forms'    => ['pardon'],
                    'row'      => 'military_r4',
                    'col'      => 6,
                    'show_when'     => ['is_military_member' => 'Yes'],
                    'required_when' => ['is_military_member' => 'Yes'],
                ],
                [
                    'key'      => 'military_service_to',
                    'label'    => 'Years of Service — To',
                    'type'     => 'month',
                    'required' => false,
                    'forms'    => ['pardon'],
                    'row'      => 'military_r4',
                    'col'      => 6,
                    'show_when'     => ['is_military_member' => 'Yes'],
                    'required_when' => ['is_military_member' => 'Yes'],
                    'lock_when' => ['is_current_military' => 'Yes', 'value' => 'Present'],
                ],
            ],
        ],

        // ═══════════════════════════════════════════════════════════
        // SECTION 5: Criminal History
        // ═══════════════════════════════════════════════════════════
        [
            'id'    => 'criminal',
            'title' => 'Criminal Record / Conviction History',
            'icon'  => 'bi-exclamation-triangle',
            'questions' => [
                // ── TRP + CR: Statute offences table ──
                [
                    'key'      => 'offences_table',
                    'label'    => 'Criminal Record: List All Offences',
                    'type'     => 'table',
                    'required' => true,
                    'forms'    => ['trp', 'criminal-rehab'],
                    'columns'  => cif_offences_columns('Statute'),
                    'pdf_rows' => [
                        'trp'            => 13,
                        'criminal-rehab' => 6,
                    ],
                ],
                [
                    'key'      => 'offences_expunged',
                    'label'    => 'Were any of the offences Expunged?',
                    'type'     => 'yes_no',
                    'required' => false,
                    'forms'    => ['criminal-rehab'],
                ],
                [
                    'key'           => 'offences_expunged_which',
                    'label'         => 'Which offences were expunged?',
                    'type'          => 'textarea',
                    'required'      => false,
                    'forms'         => ['criminal-rehab'],
                    'show_when'     => ['offences_expunged' => 'Yes'],
                    'required_when' => ['offences_expunged' => 'Yes'],
                ],
                // ── Pardon: sexual/assault ages only (other conviction_* removed → table) ──
                [
                    'key'      => 'conviction_ages',
                    'label'    => 'For sexual/assault charges: your age and victim age at time of offence',
                    'type'     => 'textarea',
                    'required' => false,
                    'forms'    => ['pardon'],
                ],
                // ── Waiver / WR: granted pardon → Documents link (UI) ──
                [
                    'key'      => 'waiver_has_pardon',
                    'label'    => 'Have you been granted a pardon?',
                    'type'     => 'yes_no',
                    'required' => false,
                    'forms'    => ['waiver', 'waiver-renewal'],
                ],
                // ── Waiver / WR: Canada convictions → Police table ──
                [
                    'key'      => 'waiver_convicted_canada',
                    'label'    => 'Have you ever been convicted of a crime in the Canada?',
                    'type'     => 'yes_no',
                    'required' => false,
                    'forms'    => ['waiver', 'waiver-renewal'],
                ],
                [
                    'key'                   => 'offences_table_ca',
                    'label'                 => 'List All Offences',
                    'type'                  => 'table',
                    'required'              => true,
                    'forms'                 => ['pardon', 'waiver', 'waiver-renewal'],
                    'columns'               => cif_offences_columns('Police'),
                    'show_when'             => ['waiver_convicted_canada' => 'Yes'],
                    'show_also_when_forms'  => ['pardon'],
                    'add_label'             => 'Add Offence',
                    'pdf_rows'              => [
                        'pardon'         => 6,
                        'waiver'         => 6,
                        'waiver-renewal' => 6,
                    ],
                ],
                // ── Waiver / WR: US convictions → Police table ──
                [
                    'key'      => 'waiver_convicted_us',
                    'label'    => 'Have you been convicted in the US?',
                    'type'     => 'yes_no',
                    'required' => false,
                    'forms'    => ['waiver', 'waiver-renewal'],
                ],
                [
                    'key'           => 'offences_table_us',
                    'label'         => 'List All Offences',
                    'type'          => 'table',
                    'required'      => true,
                    'forms'         => ['waiver', 'waiver-renewal'],
                    'columns'       => cif_offences_columns('Police'),
                    'show_when'     => ['waiver_convicted_us' => 'Yes'],
                    'add_label'     => 'Add Offence',
                    'pdf_rows'      => [
                        'waiver'         => 6,
                        'waiver-renewal' => 6,
                    ],
                ],
                // ── Expungement: charge details (text) ──
                [
                    'key'      => 'expg_police_detachment',
                    'label'    => 'Police detachment(s) that arrested and charged you (Name, City and Province)',
                    'type'     => 'textarea',
                    'required' => true,
                    'forms'    => ['expunging'],
                ],
                [
                    'key'      => 'expg_charges',
                    'label'    => 'Charge(s)',
                    'type'     => 'textarea',
                    'required' => true,
                    'forms'    => ['expunging'],
                ],
                [
                    'key'      => 'expg_dates_charged',
                    'label'    => 'Date(s) when you were charged (approximate if unsure)',
                    'type'     => 'textarea',
                    'required' => true,
                    'forms'    => ['expunging'],
                ],
                [
                    'key'      => 'expg_disposition',
                    'label'    => 'Disposition (decision of the court — withdrawn, dismissed, etc.)',
                    'type'     => 'textarea',
                    'required' => true,
                    'forms'    => ['expunging'],
                ],
            ],
        ],

        // ═══════════════════════════════════════════════════════════
        // SECTION 6: Biographic Information (TRP + CR only)
        // ═══════════════════════════════════════════════════════════
        [
            'id'    => 'biographic',
            'title' => 'Biographic Information',
            'icon'  => 'bi-fingerprint',
            'questions' => [
                [
                    'key'      => 'ethnicity',
                    'label'    => 'Ethnicity',
                    'type'     => 'dropdown',
                    'options'  => ['Hispanic or Latino', 'Not Hispanic or Latino'],
                    'required' => false,
                    'forms'    => ['trp', 'criminal-rehab'],
                ],
                [
                    'key'      => 'race',
                    'label'    => 'Race (select all that apply)',
                    'type'     => 'checkboxes',
                    'options'  => [
                        'American Indian or Alaska Native',
                        'Asian',
                        'Black or African American',
                        'Native Hawaiian or Other Pacific Islander',
                        'White',
                    ],
                    'required' => false,
                    'forms'    => ['trp', 'criminal-rehab'],
                ],
                [
                    'key'      => 'ssn_last4',
                    'label'    => 'Last four digits of SSN',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => ['criminal-rehab'],
                    'validate' => ['maxLength' => 4, 'pattern' => 'digits4'],
                ],
                [
                    'key'      => 'height',
                    'label'    => 'Height',
                    'type'     => 'text',
                    'required' => true,
                    'forms'    => ['criminal-rehab'],
                    'row'      => 'biographic_hw',
                    'col'      => 6,
                ],
                [
                    'key'      => 'weight',
                    'label'    => 'Weight',
                    'type'     => 'text',
                    'required' => true,
                    'forms'    => ['criminal-rehab'],
                    'row'      => 'biographic_hw',
                    'col'      => 6,
                ],
                [
                    'key'      => 'hair_color',
                    'label'    => 'Hair Color',
                    'type'     => 'dropdown',
                    'options'  => ['Bald', 'Black', 'Blonde/Strawberry', 'Blue', 'Brown', 'Gray', 'Green', 'Orange', 'Pink', 'Purple', 'Red/Auburn', 'Sandy', 'White', 'Unknown'],
                    'required' => true,
                    'forms'    => ['criminal-rehab'],
                    'row'      => 'biographic_he',
                    'col'      => 6,
                ],
                [
                    'key'      => 'eye_color',
                    'label'    => 'Eye Color',
                    'type'     => 'dropdown',
                    'options'  => ['Black', 'Blue', 'Brown', 'Gray', 'Green', 'Hazel', 'Maroon', 'Multicolored', 'Pink', 'Unknown'],
                    'required' => true,
                    'forms'    => ['criminal-rehab'],
                    'row'      => 'biographic_he',
                    'col'      => 6,
                ],
            ],
        ],

        // ═══════════════════════════════════════════════════════════
        // SECTION 7: Spousal Information (Waiver + Waiver Renewal)
        // ═══════════════════════════════════════════════════════════
        [
            'id'    => 'spousal',
            'title' => 'Spousal Information',
            'icon'  => 'bi-people',
            'questions' => [
                // Visible for all marital statuses except Single
                [
                    'key'      => 'spouse_current_first_name',
                    'label'    => 'Current or Recent Spouse — First Name',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => ['waiver'],
                    'row'      => 'spouse_current_names',
                    'col'      => 6,
                    'show_when' => ['marital_status' => ['Widowed', 'Married', 'Common-law', 'Divorced', 'Legally Separated', 'Marriage Annulled', 'Other']],
                ],
                [
                    'key'      => 'spouse_current_maiden_name',
                    'label'    => 'Current or Recent Spouse — Family/Maiden Name',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => ['waiver'],
                    'row'      => 'spouse_current_names',
                    'col'      => 6,
                    'show_when' => ['marital_status' => ['Widowed', 'Married', 'Common-law', 'Divorced', 'Legally Separated', 'Marriage Annulled', 'Other']],
                ],
                [
                    'key'      => 'spouse_current_dob',
                    'label'    => 'Current Spouse — Date of Birth',
                    'type'     => 'date',
                    'required' => false,
                    'forms'    => ['waiver'],
                    'row'      => 'spouse_current_birth',
                    'col'      => 6,
                    'show_when' => ['marital_status' => ['Widowed', 'Married', 'Common-law', 'Divorced', 'Legally Separated', 'Marriage Annulled', 'Other']],
                ],
                [
                    'key'      => 'spouse_current_birthplace',
                    'label'    => 'Current Spouse — City and Country of Birth',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => ['waiver'],
                    'row'      => 'spouse_current_birth',
                    'col'      => 6,
                    'show_when' => ['marital_status' => ['Widowed', 'Married', 'Common-law', 'Divorced', 'Legally Separated', 'Marriage Annulled', 'Other']],
                ],
                [
                    'key'      => 'spouse_current_marriage_date_place',
                    'label'    => 'Date & Place of Marriage',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => ['waiver'],
                    'row'      => 'spouse_current_marriage',
                    'col'      => 12,
                    'show_when' => ['marital_status' => ['Widowed', 'Married', 'Common-law', 'Divorced', 'Legally Separated', 'Marriage Annulled', 'Other']],
                ],
                [
                    'key'      => 'spouse_current_termination_date',
                    'label'    => 'Date of Termination of Marriage',
                    'type'     => 'date',
                    'required' => false,
                    'forms'    => ['waiver'],
                    'row'      => 'spouse_current_term',
                    'col'      => 6,
                    'show_when' => ['marital_status' => ['Widowed', 'Divorced', 'Legally Separated', 'Marriage Annulled', 'Other']],
                ],
                [
                    'key'      => 'spouse_current_termination_place',
                    'label'    => 'Place of Termination of Marriage',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => ['waiver'],
                    'row'      => 'spouse_current_term',
                    'col'      => 6,
                    'show_when' => ['marital_status' => ['Widowed', 'Divorced', 'Legally Separated', 'Marriage Annulled', 'Other']],
                ],
                [
                    'key'      => 'spouse_current_alien_number',
                    'label'    => 'Spouse A-Number (Alien Registration)',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => ['waiver'],
                    'row'      => 'spouse_current_alien',
                    'col'      => 12,
                    'show_when' => ['marital_status' => ['Widowed', 'Married', 'Common-law', 'Divorced', 'Legally Separated', 'Marriage Annulled', 'Other']],
                ],
                // Repeating former spouses (all except Single)
                [
                    'key'       => 'former_spouses',
                    'label'     => 'Former Spouse(s)',
                    'help'      => 'List every former spouse. Use Add former spouse for each additional person.',
                    'type'      => 'table',
                    'required'  => false,
                    'forms'     => ['waiver'],
                    'add_label' => 'Add former spouse',
                    'show_when' => ['marital_status' => ['Widowed', 'Married', 'Common-law', 'Divorced', 'Legally Separated', 'Marriage Annulled', 'Other']],
                    'columns'   => [
                        ['key' => 'first_name',          'label' => 'First Name',              'type' => 'text'],
                        ['key' => 'maiden_name',         'label' => 'Family/Maiden Name',      'type' => 'text'],
                        ['key' => 'dob',                 'label' => 'Date of Birth',           'type' => 'date'],
                        ['key' => 'birthplace',          'label' => 'City & Country of Birth', 'type' => 'text'],
                        ['key' => 'marriage_date_place', 'label' => 'Date & Place of Marriage','type' => 'text'],
                        ['key' => 'termination_date',    'label' => 'Date of Termination',     'type' => 'date'],
                        ['key' => 'termination_place',   'label' => 'Place of Termination',    'type' => 'text'],
                        ['key' => 'alien_number',        'label' => 'A-Number',                'type' => 'text'],
                    ],
                ],
                // ── Waiver Renewal ──
                [
                    'key'      => 'wr_marital_changed',
                    'label'    => 'Has your marital status changed since last Waiver Application?',
                    'type'     => 'yes_no',
                    'required' => false,
                    'forms'    => ['waiver-renewal'],
                    'row'      => 'wr_spouse',
                    'col'      => 4,
                ],
                [
                    'key'           => 'wr_spouse_full_name',
                    'label'         => 'Current spouse full maiden name',
                    'type'          => 'text',
                    'required'      => false,
                    'forms'         => ['waiver-renewal'],
                    'row'           => 'wr_spouse',
                    'col'           => 4,
                    'show_when'     => ['wr_marital_changed' => 'Yes'],
                    'required_when' => ['wr_marital_changed' => 'Yes'],
                ],
                [
                    'key'           => 'wr_spouse_dob_country',
                    'label'         => 'Current spouse Date & Country of Birth',
                    'type'          => 'text',
                    'required'      => false,
                    'forms'         => ['waiver-renewal'],
                    'row'           => 'wr_spouse',
                    'col'           => 4,
                    'show_when'     => ['wr_marital_changed' => 'Yes'],
                    'required_when' => ['wr_marital_changed' => 'Yes'],
                ],
            ],
        ],

        // ═══════════════════════════════════════════════════════════
        // SECTION 8: Travel Information (CR + TRP + Waiver + WR)
        // ═══════════════════════════════════════════════════════════
        [
            'id'    => 'travel',
            'title' => 'Travel Information',
            'icon'  => 'bi-globe',
            'questions' => [
                [
                    'key'      => 'intended_travel_date',
                    'label'    => 'Intended travel date',
                    'type'     => 'date',
                    'required' => false,
                    'forms'    => CIF_TRAVEL_CORE,
                    'col'      => 12,
                ],
                [
                    'key'      => 'length_of_stay',
                    'label'    => 'Length of Stay',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => CIF_TRAVEL_CORE,
                    'col'      => 12,
                ],
                [
                    'key'      => 'alien_registration_number',
                    'label'    => 'Alien Registration Number',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => ['waiver', 'waiver-renewal'],
                    'col'      => 12,
                ],
                [
                    'key'      => 'deported_removed_canada',
                    'label'    => 'Have you ever been deported/removed from the Canada?',
                    'type'     => 'yes_no',
                    'required' => false,
                    'forms'    => ['criminal-rehab'],
                    'col'      => 12,
                ],
                [
                    'key'      => 'deported_removed_canada_details',
                    'label'    => 'Please explain why you were deported/removed from Canada',
                    'type'     => 'textarea',
                    'required' => false,
                    'forms'    => ['criminal-rehab'],
                    'col'      => 12,
                    'show_when'     => ['deported_removed_canada' => 'Yes'],
                    'required_when' => ['deported_removed_canada' => 'Yes'],
                ],
                [
                    'key'      => 'denied_entry_canada',
                    'label'    => 'Have you ever been denied entry into Canada?',
                    'type'     => 'yes_no',
                    'required' => true,
                    'forms'    => ['criminal-rehab'],
                    'row'      => 'denied_entry',
                    'col'      => 6,
                ],
                [
                    'key'      => 'denied_entry_when',
                    'label'    => 'When were you denied entry?',
                    'type'     => 'date_optional_day',
                    'required' => false,
                    'forms'    => ['criminal-rehab'],
                    'row'      => 'denied_entry',
                    'col'      => 6,
                    'show_when'     => ['denied_entry_canada' => 'Yes'],
                    'required_when' => ['denied_entry_canada' => 'Yes'],
                ],
                [
                    'key'      => 'travel_purpose',
                    'label'    => 'What is your purpose or reason for visiting the US?',
                    'type'     => 'textarea',
                    'required' => false,
                    'forms'    => ['waiver', 'waiver-renewal'],
                ],
                [
                    'key'      => 'travel_purpose_canada',
                    'label'    => 'What is your purpose or reason for visiting Canada?',
                    'type'     => 'textarea',
                    'required' => false,
                    'forms'    => ['criminal-rehab', 'trp'],
                ],
                [
                    'key'      => 'inadmissibility_reason',
                    'label'    => 'Reason for not being admissible to the U.S. (select all that apply)',
                    'type'     => 'checkboxes',
                    'options'  => ['Criminal Record in Canada', 'Criminal Record in US', 'Immigration Issues', 'Other'],
                    'required' => false,
                    'forms'    => ['waiver', 'waiver-renewal'],
                ],
                [
                    'key'      => 'immigration_details',
                    'label'    => 'If Immigration Issues or Other: provide all documents/details regarding removal from US',
                    'type'     => 'textarea',
                    'required' => false,
                    'forms'    => ['waiver', 'waiver-renewal'],
                ],
                [
                    'key'      => 'applied_waiver_before',
                    'label'    => 'Have you applied for a US Waiver before?',
                    'type'     => 'yes_no',
                    'required' => false,
                    'forms'    => ['waiver', 'waiver-renewal'],
                ],
                [
                    'key'      => 'waiver_filed_where',
                    'label'    => 'Where you filed your application (City, State/Province, Country)',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => ['waiver', 'waiver-renewal'],
                ],
                [
                    'key'      => 'waiver_filed_when',
                    'label'    => 'When did you last file the application?',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => ['waiver', 'waiver-renewal'],
                ],
                [
                    'key'      => 'us_6months_stay',
                    'label'    => 'Have you ever been in the US for 6+ months? If yes: when, how long, immigration status?',
                    'type'     => 'textarea',
                    'required' => false,
                    'forms'    => ['waiver', 'waiver-renewal'],
                ],
                [
                    'key'      => 'us_immigration_applications',
                    'label'    => 'Have you ever filed for Immigration Benefits with the US Government?',
                    'help'     => 'If yes, list applications, filing locations, and outcome',
                    'type'     => 'textarea',
                    'required' => false,
                    'forms'    => ['waiver', 'waiver-renewal'],
                ],
                [
                    'key'      => 'us_benefit_denied',
                    'label'    => 'Have you ever been denied/refused an immigration benefit by the US Government?',
                    'help'     => 'Including but not limited to visas. Describe in detail.',
                    'type'     => 'textarea',
                    'required' => false,
                    'forms'    => ['waiver', 'waiver-renewal'],
                ],
                // ── Waiver Renewal specific ──
                [
                    'key'      => 'wr_entry_date',
                    'label'    => 'Date of Entry into US',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => ['waiver-renewal'],
                ],
                [
                    'key'      => 'wr_last_waiver_when',
                    'label'    => 'When did you apply for the last Waiver?',
                    'type'     => 'text',
                    'required' => false,
                    'forms'    => ['waiver-renewal'],
                ],
            ],
        ],
    ],
];
