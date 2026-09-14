<?php

use App\Support\Asset;
use App\Support\Html;

/**
 * Profile page view
 * Variables: $user, $profile, $phones, $emails,
 *            $addrLiving, $addrMail, $saved, $error
 */

/* ── Helpers ── */
$firstName  = $user['first_name']  ?? '';
$middleName = $user['middle_name'] ?? '';
$lastName   = $user['last_name']   ?? '';
$prefName   = $profile['preferred_name']     ?? '';
$addContact = $profile['additional_contact'] ?? '';
$timezone   = $profile['timezone']           ?? '';
$dob        = $profile['dob']                ?? '';
$dobDisplay = $dob ? (new DateTime($dob))->format('d/m/Y') : '';
$hasPrinter    = $profile['has_printer']          ?? null;

/* Canadian provinces + US states + Jamaica (value = IANA name prefix) */
$caProvinces = [
    'AB' => 'Alberta',
    'BC' => 'British Columbia',
    'MB' => 'Manitoba',
    'NB' => 'New Brunswick',
    'NL' => 'Newfoundland & Labrador',
    'NS' => 'Nova Scotia',
    'NT' => 'Northwest Territories',
    'NU' => 'Nunavut',
    'ON' => 'Ontario',
    'PE' => 'Prince Edward Island',
    'QC' => 'Quebec',
    'SK' => 'Saskatchewan',
    'YT' => 'Yukon',
];
$usStates = [
    'AL' => 'Alabama',
    'AK' => 'Alaska',
    'AZ' => 'Arizona',
    'AR' => 'Arkansas',
    'CA' => 'California',
    'CO' => 'Colorado',
    'CT' => 'Connecticut',
    'DE' => 'Delaware',
    'FL' => 'Florida',
    'GA' => 'Georgia',
    'HI' => 'Hawaii',
    'ID' => 'Idaho',
    'IL' => 'Illinois',
    'IN' => 'Indiana',
    'IA' => 'Iowa',
    'KS' => 'Kansas',
    'KY' => 'Kentucky',
    'LA' => 'Louisiana',
    'ME' => 'Maine',
    'MD' => 'Maryland',
    'MA' => 'Massachusetts',
    'MI' => 'Michigan',
    'MN' => 'Minnesota',
    'MS' => 'Mississippi',
    'MO' => 'Missouri',
    'MT' => 'Montana',
    'NE' => 'Nebraska',
    'NV' => 'Nevada',
    'NH' => 'New Hampshire',
    'NJ' => 'New Jersey',
    'NM' => 'New Mexico',
    'NY' => 'New York',
    'NC' => 'North Carolina',
    'ND' => 'North Dakota',
    'OH' => 'Ohio',
    'OK' => 'Oklahoma',
    'OR' => 'Oregon',
    'PA' => 'Pennsylvania',
    'RI' => 'Rhode Island',
    'SC' => 'South Carolina',
    'SD' => 'South Dakota',
    'TN' => 'Tennessee',
    'TX' => 'Texas',
    'UT' => 'Utah',
    'VT' => 'Vermont',
    'VA' => 'Virginia',
    'WA' => 'Washington',
    'WV' => 'West Virginia',
    'WI' => 'Wisconsin',
    'WY' => 'Wyoming',
    'DC' => 'District of Columbia',
];

/*
 * Timezones mapped to the 8 CRM codes.
 * Value stored in DB = CRM code (NST / AST / EST / CST / MST / PST / AKST / HAST).
 * Labels list which provinces/states fall in each zone so clients can identify theirs.
 */
$timezones = [
    'NST'  => 'NST — Newfoundland Standard Time  (NL)',
    'AST'  => 'AST — Atlantic Standard Time  (NB, NS, PE, QC east)',
    'EST'  => 'EST — Eastern Standard Time  (ON, QC, NY, FL, PA, OH, MI, …)',
    'CST'  => 'CST — Central Standard Time  (MB, SK, TX, IL, MN, WI, …)',
    'MST'  => 'MST — Mountain Standard Time  (AB, BC east, CO, AZ, UT, NM, …)',
    'PST'  => 'PST — Pacific Standard Time  (BC, WA, OR, CA, NV)',
    'AKST' => 'AKST — Alaska Standard Time  (AK)',
    'HAST' => 'HAST — Hawaii-Aleutian Standard Time  (HI)',
];

$phoneTypes = ['alternative' => 'Alternative', 'home' => 'Home', 'cell' => 'Cell', 'work' => 'Work', 'other' => 'Other'];

?>

<?php if (!empty($saved)): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-check-circle-fill"></i> Changes saved successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= Html::e($error) ?>
    </div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════
     MAIN PROFILE FORM
     ══════════════════════════════════════════════════════════ -->
<div class="row g-4">

    <!-- Left column: personal info -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Personal Information</div>
            <div class="card-body">
                <form method="POST" action="/profile" id="profileForm">
                    <input type="hidden" name="csrf_token" value="<?= Html::e($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="profile">

                    <div class="row g-3">

                        <!-- First Name -->
                        <div class="col-sm-4">
                            <label class="form-label">
                                First Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="first_name"
                                value="<?= Html::e($firstName) ?>"
                                placeholder="First name"
                                required>
                        </div>

                        <!-- Middle Name -->
                        <div class="col-sm-4">
                            <label class="form-label">Middle Name
                                <span class="text-muted small fw-normal">(optional)</span>
                            </label>
                            <input type="text" class="form-control" name="middle_name"
                                value="<?= Html::e($middleName) ?>"
                                placeholder="Middle name">
                        </div>

                        <!-- Last Name -->
                        <div class="col-sm-4">
                            <label class="form-label">
                                Last Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="last_name"
                                value="<?= Html::e($lastName) ?>"
                                placeholder="Last name"
                                required>
                        </div>

                        <!-- Preferred Name -->
                        <div class="col-sm-6">
                            <label class="form-label">Preferred Name
                                <span class="text-muted small fw-normal">(optional)</span>
                            </label>
                            <input type="text" class="form-control" name="preferred_name"
                                value="<?= Html::e($prefName) ?>"
                                placeholder="Name you prefer to go by">
                        </div>

                        <!-- Additional contact -->
                        <div class="col-sm-6">
                            <label class="form-label">Additional Person to Contact
                                <span class="text-muted small fw-normal">(optional)</span>
                            </label>
                            <input type="text" class="form-control" name="additional_contact"
                                value="<?= Html::e($addContact) ?>"
                                placeholder="e.g. Jane Smith (Spouse)">
                            <div class="form-text">Someone we may speak with on your behalf if needed.</div>
                        </div>

                        <!-- Date of Birth -->
                        <div class="col-sm-6">
                            <label class="form-label">Date of Birth
                                <span class="text-danger">*</span>
                            </label>
                            <!-- flatpickr renders a visible alt-input; dobInput is hidden by flatpickr -->
                            <input type="text" class="form-control" name="dob"
                                id="dobInput"
                                value="<?= Html::e($dobDisplay) ?>"
                                placeholder="DD / MM / YYYY"
                                autocomplete="off"
                                required>
                            <!-- hidden field keeps Y-m-d for server after flatpickr fires -->
                            <input type="hidden" id="dobHidden" name="dob_hidden"
                                value="<?= Html::e($dob) ?>">
                        </div>

                        <!-- Access to Printer -->
                        <div class="col-sm-6">
                            <label class="form-label">Access to Printer
                                <span class="text-muted small fw-normal">(optional)</span>
                            </label>
                            <select class="form-select" name="has_printer">
                                <option value="">— Select —</option>
                                <option value="1" <?= $hasPrinter === '1' || $hasPrinter === 1 ? 'selected' : '' ?>>Yes</option>
                                <option value="0" <?= $hasPrinter === '0' || $hasPrinter === 0 ? 'selected' : '' ?>>No</option>
                            </select>
                        </div>

                        <!-- Timezone -->
                        <div class="col-12">
                            <label class="form-label">Current Time Zone
                                <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="timezone" id="timezoneSelect" required>
                                <option value="">— Select time zone —</option>
                                <?php foreach ($timezones as $tz => $label): ?>
                                    <option value="<?= Html::e($tz) ?>"
                                        <?= $timezone === $tz ? 'selected' : '' ?>>
                                        <?= Html::e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                <i class="bi bi-lightbulb me-1"></i>
                                Not sure? Pick the zone you are currently in.
                            </div>
                        </div>

                    </div><!-- /row -->

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Save Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right column: phone, email, addresses -->
    <div class="col-lg-5 d-flex flex-column gap-4" id="contactsColumn">

        <!-- ── Phone Numbers ── -->
        <div class="card border-0 shadow-sm" id="phonesCard">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Phone Numbers</span>
                <button type="button" class="btn btn-outline-primary btn-sm"
                    data-bs-toggle="modal" data-bs-target="#addPhoneModal">
                    <i class="bi bi-plus-lg me-1"></i>Add Phone
                </button>
            </div>
            <div class="card-body p-0">
                <?php if (empty($phones)): ?>
                    <p class="text-muted text-center py-4 mb-0 small">No phone numbers on file.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($phones as $ph): ?>
                            <li class="list-group-item d-flex align-items-center gap-2 py-2 px-3">
                                <i class="bi bi-telephone text-primary flex-shrink-0"></i>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-medium small text-truncate">
                                        <?= Html::e($ph['number']) ?><?= !empty($ph['extension']) ? ' ext. ' . Html::e($ph['extension']) : '' ?>
                                    </div>
                                    <div class="d-flex gap-1 mt-1 flex-wrap">
                                        <?php if ($ph['is_main']): ?>
                                            <span class="badge bg-primary">Main</span>
                                        <?php endif; ?>
                                        <?php if ($ph['is_old']): ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                        <span class="badge bg-light text-secondary border">
                                            <?= Html::e(ucfirst($ph['type'])) ?>
                                        </span>
                                        <?php if (!empty($ph['label'])): ?>
                                            <span class="badge bg-dark bg-opacity-10 text-dark border border-dark border-opacity-25">
                                                <?= Html::e($ph['label']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if (!$ph['is_main']): ?>
                                    <form method="POST" action="/profile" class="flex-shrink-0 ajax-toggle-form">
                                        <input type="hidden" name="csrf_token" value="<?= Html::e($_SESSION['csrf_token'] ?? '') ?>">
                                        <input type="hidden" name="action" value="toggle_phone_inactive">
                                        <input type="hidden" name="phone_id" value="<?= (int)$ph['id'] ?>">
                                        <button type="submit"
                                            class="btn btn-sm <?= $ph['is_old'] ? 'btn-outline-success' : 'btn-outline-secondary' ?>"
                                            title="<?= $ph['is_old'] ? 'Mark as Active' : 'Mark as Inactive' ?>">
                                            <?= $ph['is_old'] ? 'Activate' : 'Deactivate' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Email Addresses ── -->
        <div class="card border-0 shadow-sm" id="emailsCard">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Email Addresses</span>
                <button type="button" class="btn btn-outline-primary btn-sm"
                    data-bs-toggle="modal" data-bs-target="#addEmailModal">
                    <i class="bi bi-plus-lg me-1"></i>Add Email
                </button>
            </div>
            <div class="card-body p-0">
                <?php
                /* Always show the login email at minimum */
                $loginEmail = $user['email'] ?? '';
                $hasMain = false;
                foreach ($emails as $em) {
                    if ($em['is_main']) {
                        $hasMain = true;
                        break;
                    }
                }
                ?>
                <ul class="list-group list-group-flush">
                    <?php if (!$hasMain && $loginEmail): ?>
                        <li class="list-group-item d-flex align-items-center gap-2 py-2 px-3">
                            <i class="bi bi-envelope text-primary flex-shrink-0"></i>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-medium small text-truncate"><?= Html::e($loginEmail) ?></div>
                                <span class="badge bg-primary mt-1">Main</span>
                            </div>
                        </li>
                    <?php endif; ?>
                    <?php foreach ($emails as $em): ?>
                        <li class="list-group-item d-flex align-items-center gap-2 py-2 px-3">
                            <i class="bi bi-envelope text-primary flex-shrink-0"></i>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-medium small text-truncate"><?= Html::e($em['email']) ?></div>
                                <div class="mt-1">
                                    <?php if ($em['is_main']): ?>
                                        <span class="badge bg-primary">Main</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border">Alternative</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!$em['is_main']): ?>
                                <form method="POST" action="/profile" class="flex-shrink-0 ajax-delete-form">
                                    <input type="hidden" name="csrf_token" value="<?= Html::e($_SESSION['csrf_token'] ?? '') ?>">
                                    <input type="hidden" name="action" value="delete_email">
                                    <input type="hidden" name="email_id" value="<?= (int)$em['id'] ?>">
                                    <button type="submit" class="btn btn-link btn-sm text-danger p-0" title="Remove">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($emails) && !$loginEmail): ?>
                        <li class="list-group-item text-muted text-center py-4 small">No emails on file.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- ── Addresses ── -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Current Addresses</div>
            <div class="card-body d-flex flex-column gap-3">

                <!-- Living Address -->
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div>
                        <div class="small fw-semibold text-uppercase text-muted mb-1 ls-wide">
                            <i class="bi bi-house me-1"></i>Physical Address
                        </div>
                        <?php if (!empty($addrLiving['street'])): ?>
                            <div class="small"><?= Html::e(trim($addrLiving['street'])) ?><?= trim($addrLiving['unit'] ?? '') ? ', Unit ' . Html::e(trim($addrLiving['unit'])) : '' ?></div>
                            <?php
                            $aCity   = trim($addrLiving['city']           ?? '');
                            $aProv   = trim($addrLiving['province_state'] ?? '');
                            $aPost   = trim($addrLiving['postal_code']    ?? '');
                            $aCtry   = trim($addrLiving['country']        ?? '');
                            /* Hide province for non-standard countries */
                            $aIsStandard = in_array($aCtry, ['Canada', 'United States'], true);
                            $cityLine = Html::e($aCity)
                                . ($aIsStandard && $aProv ? ', ' . Html::e($aProv) : '')
                                . ($aPost ? ' ' . Html::e($aPost) : '');
                            ?>
                            <div class="small text-muted"><?= $cityLine ?></div>
                            <div class="small text-muted"><?= Html::e($aCtry) ?></div>
                            <?php if (!empty($addrLiving['move_in_date'])): ?>
                                <div class="small text-muted mt-1">
                                    <i class="bi bi-calendar-event me-1"></i>Move-in: <?= (new DateTime($addrLiving['move_in_date']))->format('M j, Y') ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted small">Not set</span>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm flex-shrink-0"
                        data-bs-toggle="modal" data-bs-target="#addrLivingModal">
                        <i class="bi bi-pencil"></i>
                    </button>
                </div>

                <hr class="my-1">

                <!-- Mail Address -->
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div>
                        <div class="small fw-semibold text-uppercase text-muted mb-1">
                            <i class="bi bi-mailbox me-1"></i>Mail Address
                        </div>
                        <?php if (!empty($addrMail['street'])): ?>
                            <div class="small"><?= Html::e(trim($addrMail['street'])) ?><?= trim($addrMail['unit'] ?? '') ? ', Unit ' . Html::e(trim($addrMail['unit'])) : '' ?></div>
                            <?php
                            $mCity  = trim($addrMail['city']           ?? '');
                            $mProv  = trim($addrMail['province_state'] ?? '');
                            $mPost  = trim($addrMail['postal_code']    ?? '');
                            $mCtry  = trim($addrMail['country']        ?? '');
                            /* Hide province for non-standard countries */
                            $mIsStandard = in_array($mCtry, ['Canada', 'United States'], true);
                            $mCityLine = Html::e($mCity)
                                . ($mIsStandard && $mProv ? ', ' . Html::e($mProv) : '')
                                . ($mPost ? ' ' . Html::e($mPost) : '');
                            ?>
                            <div class="small text-muted"><?= $mCityLine ?></div>
                            <div class="small text-muted"><?= Html::e($mCtry) ?></div>
                            <?php if (!empty($addrMail['move_in_date'])): ?>
                                <div class="small text-muted mt-1">
                                    <i class="bi bi-calendar-event me-1"></i>Move-in: <?= (new DateTime($addrMail['move_in_date']))->format('M j, Y') ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-muted small">Not set</div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm flex-shrink-0"
                        data-bs-toggle="modal" data-bs-target="#addrMailModal">
                        <i class="bi bi-pencil"></i>
                    </button>
                </div>

            </div>
        </div>

    </div><!-- /right col -->
</div><!-- /row -->


<!-- ══════════════════════════════════════════════════════════
     MODAL — Add Phone
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addPhoneModal" tabindex="-1" aria-labelledby="addPhoneModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="/profile" id="addPhoneForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= Html::e($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="add_phone">

                <div class="modal-header">
                    <h5 class="modal-title" id="addPhoneModalLabel">
                        <i class="bi bi-telephone-plus me-2"></i>Add Phone Number
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Phone Type</label>
                        <select class="form-select" name="phone_type" id="phoneTypeSelect">
                            <option value="cell">Cell</option>
                            <option value="home">Home</option>
                            <option value="work">Work</option>
                            <option value="alternative">Alternative</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" name="phone_number" id="phoneNumberInput"
                            placeholder="+1 (555) 000-0000"
                            pattern="[\d\s\+\-\(\)]{7,20}"
                            required>
                        <div class="form-text">Include country code for international numbers.</div>
                    </div>
                    <div class="mb-3" id="extFieldWrap" style="display:none">
                        <label class="form-label">Extension
                            <span class="text-muted small fw-normal">(optional)</span>
                        </label>
                        <input type="text" class="form-control" name="phone_extension" id="phoneExtInput"
                            placeholder="e.g. 204" maxlength="20">
                    </div>
                    <div class="mb-3" id="phoneLabelWrap" style="display:none">
                        <label class="form-label">Label
                            <span class="text-muted small fw-normal">(optional)</span>
                        </label>
                        <input type="text" class="form-control" name="phone_label" id="phoneLabelInput"
                            placeholder="e.g. Janet's Phone" maxlength="100">
                        <div class="form-text">Describe whose phone this is or any helpful note.</div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="phone_is_main"
                            id="phoneIsMain" value="1">
                        <label class="form-check-label" for="phoneIsMain">
                            Set as new primary number
                            <button type="button" class="btn btn-link p-0 ms-1"
                                tabindex="-1"
                                data-bs-toggle="popover"
                                data-bs-trigger="hover focus"
                                data-bs-placement="right"
                                data-bs-content="New phone will be used as main contact if we need to reach you manually (e.g. case updates, follow-ups, or important notices).">
                                <i class="bi bi-question-circle text-muted"></i>
                            </button>
                            <span class="text-muted small d-block">
                                Current primary number will be retained and marked as alternative.
                            </span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>Add Phone
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL — Add Email
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addEmailModal" tabindex="-1" aria-labelledby="addEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="/profile" id="addEmailForm">
                <input type="hidden" name="csrf_token" value="<?= Html::e($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="add_email">

                <div class="modal-header">
                    <h5 class="modal-title" id="addEmailModalLabel">
                        <i class="bi bi-envelope-plus me-2"></i>Add Email Address
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email_address"
                            placeholder="name@example.com" required>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="email_is_main"
                            id="emailIsMain" value="1">
                        <label class="form-check-label" for="emailIsMain">
                            Set as new primary email
                            <span class="text-muted small d-block">
                                This email will be used when we need to contact you manually
                                (e.g. case updates, follow-ups, or important notices).
                            </span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>Add Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL — Living Address
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addrLivingModal" tabindex="-1" aria-labelledby="addrLivingLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <?= addressModalBody(
            'living',
            'addrLivingLabel',
            'Physical Address',
            $addrLiving,
            $caProvinces,
            $usStates,
            $_SESSION['csrf_token'] ?? ''
        ) ?>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL — Mail Address
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addrMailModal" tabindex="-1" aria-labelledby="addrMailLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <?= addressModalBody(
            'mail',
            'addrMailLabel',
            'Mailing Address',
            $addrMail,
            $caProvinces,
            $usStates,
            $_SESSION['csrf_token'] ?? ''
        ) ?>
    </div>
</div>


<?php
/**
 * Shared address modal body builder
 */
function addressModalBody(
    string $type,
    string $labelId,
    string $title,
    array  $addr,
    array  $caProvinces,
    array  $usStates,
    string $csrf
): string {
    $country  = $addr['country']        ?? 'Canada';
    $province = $addr['province_state'] ?? '';
    $city     = $addr['city']           ?? '';
    $postal   = $addr['postal_code']    ?? '';
    $street   = $addr['street']         ?? '';
    $unit     = $addr['unit']           ?? '';
    $moveIn   = $addr['move_in_date']   ?? '';
    $moveInDisplay = $moveIn ? (new \DateTime($moveIn))->format('d/m/Y') : '';
    $t        = $type; // "living" | "mail"

    ob_start();
?>
    <div class="modal-content">
        <form method="POST" action="/profile" id="addr<?= ucfirst($t) ?>Form">
            <input type="hidden" name="csrf_token" value="<?= Html::e($csrf) ?>">
            <input type="hidden" name="action" value="address_<?= Html::e($t) ?>">

            <div class="modal-header">
                <h5 class="modal-title" id="<?= Html::e($labelId) ?>">
                    <i class="bi bi-geo-alt me-2"></i><?= Html::e($title) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">

                    <!-- Country -->
                    <div class="col-sm-6">
                        <label class="form-label">Country <span class="text-danger">*</span></label>
                        <select class="form-select" name="country" id="country_<?= Html::e($t) ?>"
                            required>
                            <option value="Canada" <?= $country === 'Canada'        ? 'selected' : '' ?>>Canada</option>
                            <option value="United States" <?= $country === 'United States' ? 'selected' : '' ?>>United States</option>
                            <option value="Other" <?= (!in_array($country, ['Canada', 'United States', ''], true)) ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>

                    <!-- Other Country text field (shown when "Other" selected) -->
                    <div class="col-sm-6" id="otherCountryWrap_<?= Html::e($t) ?>"
                        style="display:<?= (!in_array($country, ['Canada', 'United States', ''], true)) ? '' : 'none' ?>">
                        <label class="form-label">Country Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="country_other" id="otherCountry_<?= Html::e($t) ?>"
                            value="<?= Html::e((!in_array($country, ['Canada', 'United States'], true)) ? $country : '') ?>"
                            placeholder="Enter country name">
                    </div>

                    <!-- Province / State (hidden when "Other" country) -->
                    <div class="col-sm-6" id="provinceWrap_<?= Html::e($t) ?>"
                        style="display:<?= (!in_array($country, ['Canada', 'United States', ''], true)) ? 'none' : '' ?>">
                        <label class="form-label">Province / State <span class="text-danger">*</span></label>
                        <select class="form-select" name="province_state" id="province_<?= Html::e($t) ?>">
                            <?php
                            $showCA = $country !== 'United States' && in_array($country, ['Canada', ''], true);
                            $showUS = $country === 'United States';
                            ?>
                            <?php if ($showCA): ?>
                                <?php foreach ($caProvinces as $code => $name): ?>
                                    <option value="<?= Html::e($name) ?>" <?= $province === $name ? 'selected' : '' ?>>
                                        <?= Html::e($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php elseif ($showUS): ?>
                                <?php foreach ($usStates as $code => $name): ?>
                                    <option value="<?= Html::e($name) ?>" <?= $province === $name ? 'selected' : '' ?>>
                                        <?= Html::e($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- City -->
                    <div class="col-sm-6">
                        <label class="form-label">City <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="city" id="city_<?= Html::e($t) ?>"
                            value="<?= Html::e($city) ?>" required placeholder="City">
                    </div>

                    <!-- Postal Code -->
                    <div class="col-sm-6">
                        <label class="form-label">Postal / ZIP Code</label>
                        <input type="text" class="form-control" name="postal_code" id="postal_<?= Html::e($t) ?>"
                            value="<?= Html::e($postal) ?>" placeholder="e.g. M5V 3L9 or 90210"
                            maxlength="10"
                            onblur="autoFillFromPostal('<?= Html::e($t) ?>')">
                    </div>

                    <!-- Street -->
                    <div class="col-12">
                        <label class="form-label">Street Name &amp; Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="street"
                            value="<?= Html::e($street) ?>" required
                            placeholder="e.g. 123 Main Street">
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>
                            Enter the full street address including the street number.
                        </div>
                    </div>

                    <!-- Unit -->
                    <div class="col-sm-4">
                        <label class="form-label">Unit / Apt
                            <span class="text-muted small fw-normal">(if applicable)</span>
                        </label>
                        <input type="text" class="form-control" name="unit"
                            value="<?= Html::e($unit) ?>" placeholder="e.g. 4B">
                    </div>

                    <!-- Move In Date -->
                    <div class="col-sm-4">
                        <label class="form-label">Move-in Date
                            <span class="text-muted small fw-normal">(optional)</span>
                        </label>
                        <input type="text" class="form-control addr-move-in-picker" name="move_in_date"
                            id="moveIn_<?= Html::e($t) ?>"
                            value="<?= Html::e($moveInDisplay) ?>"
                            placeholder="DD / MM / YYYY"
                            autocomplete="off">
                        <input type="hidden" name="move_in_date_hidden" id="moveInHidden_<?= Html::e($t) ?>"
                            value="<?= Html::e($moveIn) ?>">
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Save Address
                </button>
            </div>
        </form>
    </div>
<?php
    return ob_get_clean();
}
?>

<!-- ══════════════════════════════════════════════════════════
     MODAL — Delete Confirmation
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title text-danger d-flex align-items-center gap-2" id="deleteConfirmLabel">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span id="deleteConfirmTitle">Confirm removal</span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="mb-0 small" id="deleteConfirmBody">Are you sure you want to remove this item?</p>
            </div>
            <div class="modal-footer border-0 pt-1">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="deleteConfirmOk">Remove</button>
            </div>
        </div>
    </div>
</div>


<!-- Data bridge: pass PHP arrays to external JS -->
<script>
    window.PROFILE_DATA = {
        CA_PROVINCES: <?= json_encode(array_values($caProvinces)) ?>,
        US_STATES: <?= json_encode(array_values($usStates)) ?>
    };
</script>
<script src="<?= Asset::url('/js/profile/address.js') ?>"></script>
<script src="<?= Asset::url('/js/profile/ajax.js') ?>"></script>
<script src="<?= Asset::url('/js/profile/contacts.js') ?>"></script>
<script src="<?= Asset::url('/js/profile.js') ?>"></script>
