<?php

use App\Support\Asset;
use App\Support\Html;

/**
 * CIF (Client Information Form) — multi-section form view.
 *
 * Variables provided by CifController::index() via compact():
 *   @var array  $questionSchema  — full question definition from cif_questions.php
 *   @var array  $userForms       — list of service_type strings applicable to this client
 *   @var array  $savedData       — previously saved answers (keyed by question key)
 *   @var int    $progress        — 0-100 completion percentage
 *   @var array  $serviceColors   — colour map for service badges
 *   @var array       $generatedPdfs   — list of generated PDF records
 *   @var string|null $completedAt     — cif_responses.completed_at when submitted
 *
 * @see src/controllers/CifController.php
 * @see src/config/cif_questions.php
 * @see public/js/cif.js
 * @see public/css/cif.css
  */

$sections = $questionSchema['sections'] ?? [];

/**
 * Check if a question applies to any of the user's forms.
 */
function questionApplies(array $q, array $userForms): bool
{
    return !empty(array_intersect($q['forms'], $userForms));
}

/**
 * Count total visible questions per section (for progress).
 */
function countVisibleQuestions(array $section, array $userForms): int
{
    $count = 0;
    foreach ($section['questions'] as $q) {
        if (questionApplies($q, $userForms)) $count++;
    }
    return $count;
}

/**
 * Render a single CIF question field (inputs only, inside .cif-question).
 *
 * @param bool $splitCitizenship Waiver/WR → separate US citizenship + Green Card fields
 */
function renderCifQuestion(array $q, array $userForms, array $savedData, array $serviceColors, array $serviceLabels, bool $splitCitizenship = false): void
{
    $key   = $q['key'];

    // Combined citizenship UI: omit green_card (defense in depth; forms already W/WR-only)
    if ($key === 'green_card' && !$splitCitizenship) {
        return;
    }

    $label = $q['label'];
    if ($key === 'us_citizenship' && $splitCitizenship) {
        $label = 'US citizenship';
    }

    $val   = $savedData[$key] ?? '';
    $reqd  = !empty($q['required']) ? 'required' : '';
    $forms = array_intersect($q['forms'], $userForms);
    $col   = (int)($q['col'] ?? 12);
    if ($col < 1 || $col > 12) $col = 12;

    $eligibilityKeys = ['us_citizenship', 'green_card', 'canadian_citizenship'];

    $dataAttrs = '';
    if (!empty($q['hidden_when'])) {
        $dataAttrs .= ' data-hidden-when="' . Html::e(json_encode($q['hidden_when'])) . '"';
    }
    if (!empty($q['show_when'])) {
        $dataAttrs .= ' data-show-when="' . Html::e(json_encode($q['show_when'])) . '"';
    }
    if (!empty($q['show_also_when_forms'])) {
        $dataAttrs .= ' data-show-also-when-forms="' . Html::e(json_encode(array_values($q['show_also_when_forms']))) . '"';
    }
    if (!empty($q['required_when'])) {
        $dataAttrs .= ' data-required-when="' . Html::e(json_encode($q['required_when'])) . '"';
    }
    if (!empty($q['lock_when'])) {
        $dataAttrs .= ' data-lock-when="' . Html::e(json_encode($q['lock_when'])) . '"';
    }
    if (!empty($q['validate'])) {
        $dataAttrs .= ' data-validate="' . Html::e(json_encode($q['validate'])) . '"';
    }
    if (!empty($q['timeline'])) {
        $dataAttrs .= ' data-timeline="' . Html::e(json_encode($q['timeline'])) . '"';
    }
    ?>
    <div class="cif-question mb-3 col-md-<?= $col ?>" data-key="<?= Html::e($key) ?>"<?= $dataAttrs ?>>
        <label class="form-label fw-semibold">
            <?= Html::e($label) ?>
            <?php if ($reqd || !empty($q['required_when'])): ?>
                <span class="text-danger cif-req-star"<?= empty($q['required']) && !empty($q['required_when']) ? ' style="display:none"' : '' ?>>*</span>
            <?php endif; ?>
        </label>
        <div class="cif-form-badges mb-2">
            <?php foreach ($forms as $ft): ?>
                <span class="badge cif-form-badge"
                    style="background:<?= Html::e($serviceColors[$ft]['bg'] ?? '#eee') ?>;
                     color:<?= Html::e($serviceColors[$ft]['text'] ?? '#333') ?>;
                     font-size:.7rem">
                    <?= Html::e($serviceLabels[$ft] ?? $ft) ?>
                </span>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($q['help'])): ?>
            <div class="form-text mb-2"><?= Html::e($q['help']) ?></div>
        <?php endif; ?>

        <?php switch ($q['type']):
            case 'text': ?>
                <input type="text" class="form-control cif-input"
                    name="<?= Html::e($key) ?>"
                    value="<?= Html::e((string)$val) ?>"
                    <?= $reqd ?>>
            <?php break;

            case 'month': ?>
                <input type="text" class="form-control cif-input cif-month-picker"
                    name="<?= Html::e($key) ?>"
                    value="<?= Html::e((string)$val) ?>"
                    placeholder="yyyy-mm" readonly
                    <?= $reqd ?>>
            <?php break;

            case 'date': ?>
                <input type="text" class="form-control cif-input cif-date-picker"
                    name="<?= Html::e($key) ?>"
                    value="<?= Html::e((string)$val) ?>"
                    placeholder="Select date…" readonly
                    <?= $reqd ?>>
            <?php break;

            case 'date_optional_day':
                $odVal = trim((string)$val);
                $odYm = '';
                $odDay = '';
                if (preg_match('/^(\d{4}-\d{2})-(\d{2})$/', $odVal, $odm)) {
                    $odYm = $odm[1];
                    $odDay = (string)(int)$odm[2];
                } elseif (preg_match('/^\d{4}-\d{2}$/', $odVal)) {
                    $odYm = $odVal;
                }
                ?>
                <div class="cif-optional-day-wrap d-flex flex-wrap gap-2 align-items-center">
                    <input type="text"
                        class="form-control cif-input cif-optional-day-month"
                        name="<?= Html::e($key) ?>"
                        value="<?= Html::e($odVal) ?>"
                        data-ym="<?= Html::e($odYm) ?>"
                        placeholder="yyyy-mm" readonly
                        <?= $reqd ?>>
                    <select class="form-select cif-optional-day-select"
                        aria-label="Day (optional)">
                        <option value="">Day (optional)</option>
                        <?php for ($d = 1; $d <= 31; $d++): ?>
                            <option value="<?= $d ?>"<?= $odDay === (string)$d ? ' selected' : '' ?>><?= $d ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-text">Month and year required; day is optional.</div>
            <?php break;

            case 'checkbox': ?>
                <div class="form-check">
                    <input class="form-check-input cif-input" type="checkbox"
                        name="<?= Html::e($key) ?>" value="Yes"
                        id="<?= Html::e($key) ?>_cb"
                        <?= ($val === 'Yes') ? 'checked' : '' ?>
                        <?= $reqd ?>>
                    <label class="form-check-label" for="<?= Html::e($key) ?>_cb">Yes</label>
                </div>
            <?php break;

            case 'textarea': ?>
                <textarea class="form-control cif-input" rows="3"
                    name="<?= Html::e($key) ?>"
                    <?= $reqd ?>><?= Html::e((string)$val) ?></textarea>
            <?php break;

            case 'dropdown': ?>
                <select class="form-select cif-input"
                    name="<?= Html::e($key) ?>"
                    <?= $reqd ?>>
                    <option value="">-- Select --</option>
                    <?php foreach ($q['options'] as $opt): ?>
                        <option value="<?= Html::e($opt) ?>"
                            <?= ($val === $opt) ? 'selected' : '' ?>>
                            <?= Html::e($opt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php break;

            case 'yes_no': ?>
                <div class="d-flex gap-4">
                    <div class="form-check">
                        <input class="form-check-input cif-input" type="radio"
                            name="<?= Html::e($key) ?>" value="Yes"
                            id="<?= Html::e($key) ?>_yes"
                            <?= ($val === 'Yes') ? 'checked' : '' ?>
                            <?= $reqd ?>>
                        <label class="form-check-label" for="<?= Html::e($key) ?>_yes">Yes</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input cif-input" type="radio"
                            name="<?= Html::e($key) ?>" value="No"
                            id="<?= Html::e($key) ?>_no"
                            <?= ($val === 'No') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="<?= Html::e($key) ?>_no">No</label>
                    </div>
                </div>
                <?php if ($key === 'waiver_has_pardon'):
                    $docSvc = in_array('waiver', $userForms, true)
                        ? 'waiver'
                        : (in_array('waiver-renewal', $userForms, true) ? 'waiver-renewal' : 'waiver');
                    $docHref = '/documents?service=' . rawurlencode($docSvc) . '&doc=waiver-granted-pardon';
                ?>
                    <div class="cif-waiver-pardon-doc-link mt-2 d-none">
                        <a href="<?= Html::e($docHref) ?>"
                            class="link-danger fw-semibold text-decoration-underline">
                            Please provide a full copy of the granted pardon
                        </a>
                    </div>
                <?php endif; ?>
                <?php if (in_array($key, $eligibilityKeys, true)): ?>
                    <div class="cif-field-error text-danger small mt-1" data-eligibility-for="<?= Html::e($key) ?>" hidden></div>
                <?php endif; ?>
            <?php break;

            case 'checkboxes': ?>
                <div class="d-flex flex-wrap gap-3">
                    <?php
                    $checkedArr = is_array($val) ? $val : [];
                    foreach ($q['options'] as $optIdx => $opt): ?>
                        <div class="form-check">
                            <input class="form-check-input cif-input cif-checkbox-group"
                                type="checkbox"
                                name="<?= Html::e($key) ?>[]"
                                value="<?= Html::e($opt) ?>"
                                id="<?= Html::e($key) ?>_<?= $optIdx ?>"
                                data-group="<?= Html::e($key) ?>"
                                <?= in_array($opt, $checkedArr, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="<?= Html::e($key) ?>_<?= $optIdx ?>">
                                <?= Html::e($opt) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php break;

            case 'table':
                $cols = $q['columns'] ?? [];
                $tableVal = is_array($val) ? $val : [];
                $usesPresentRow = in_array($key, ['addresses', 'employment_history'], true);
                $allowEmptyTable = ($key === 'former_spouses');
                if (empty($tableVal) && !$allowEmptyTable) {
                    $emptyRow = [];
                    foreach ($cols as $c) {
                        $emptyRow[$c['key']] = ($usesPresentRow && $c['key'] === 'to') ? 'Present' : '';
                    }
                    $tableVal = [$emptyRow];
                } elseif ($usesPresentRow && !empty($tableVal)) {
                    $tableVal[0]['to'] = 'Present';
                }
                $addLabel = $q['add_label'] ?? 'Add Row';
            ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm cif-table"
                        data-key="<?= Html::e($key) ?>"
                        data-cols="<?= Html::e(json_encode($cols)) ?>"
                        <?= $usesPresentRow ? 'data-present-row="1"' : '' ?>
                        <?= $allowEmptyTable ? 'data-allow-empty="1"' : '' ?>
                        <?php if (!empty($q['timeline'])): ?>
                        data-timeline="<?= Html::e(json_encode($q['timeline'])) ?>"
                        <?php endif; ?>>
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width:40px">#</th>
                                <?php foreach ($cols as $c): ?>
                                    <th><?= Html::e($c['label']) ?></th>
                                <?php endforeach; ?>
                                <th style="width:50px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tableVal as $rowIdx => $row): ?>
                                <tr data-row="<?= $rowIdx ?>">
                                    <td class="text-center text-muted align-middle cif-row-num"><?= $rowIdx + 1 ?></td>
                                    <?php foreach ($cols as $c): ?>
                                        <td>
                                            <?php if ($c['type'] === 'checkbox'): ?>
                                                <div class="form-check d-flex justify-content-center m-0">
                                                    <input class="form-check-input cif-table-input"
                                                        type="checkbox"
                                                        data-col="<?= Html::e($c['key']) ?>"
                                                        <?= !empty($row[$c['key']]) ? 'checked' : '' ?>>
                                                </div>
                                            <?php else:
                                                $isLockedPresent = $usesPresentRow && $rowIdx === 0 && $c['key'] === 'to';
                                                $cellVal = (string)($row[$c['key']] ?? '');
                                                $colType = $c['type'] ?? 'text';
                                                $pickerClass = '';
                                                if (!$isLockedPresent && $colType === 'month') {
                                                    $pickerClass = ' cif-month-picker';
                                                } elseif (!$isLockedPresent && $colType === 'date') {
                                                    $pickerClass = ' cif-date-picker';
                                                }
                                            ?>
                                                <input type="text"
                                                    class="form-control form-control-sm cif-table-input<?= $pickerClass ?>"
                                                    data-col="<?= Html::e($c['key']) ?>"
                                                    data-col-type="<?= Html::e($colType) ?>"
                                                    value="<?= Html::e($isLockedPresent ? 'Present' : $cellVal) ?>"
                                                    <?= $isLockedPresent ? 'readonly' : '' ?>
                                                    <?= ($pickerClass && !$isLockedPresent) ? 'placeholder="' . ($colType === 'month' ? 'yyyy-mm' : 'Select date…') . '" readonly' : '' ?>>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="text-center align-middle">
                                        <button type="button" class="btn btn-sm btn-outline-danger cif-row-remove"
                                            title="Remove row" <?= (!$allowEmptyTable && $rowIdx === 0) ? 'disabled' : '' ?>>
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="cif-timeline-msg text-danger small mt-1 d-none"></div>
                <button type="button" class="btn btn-sm btn-outline-primary cif-row-add"
                    data-table="<?= Html::e($key) ?>">
                    <i class="bi bi-plus-circle me-1"></i><?= Html::e($addLabel) ?>
                </button>
        <?php break;
        endswitch; ?>
    </div>
    <?php
}

// Build section list (only sections with visible questions)
$visibleSections = [];
foreach ($sections as $s) {
    if (countVisibleQuestions($s, $userForms) > 0) {
        $visibleSections[] = $s;
    }
}

// Service labels for badges
$serviceLabels = [
    'pardon'         => 'Pardon',
    'criminal-rehab' => 'Criminal Rehab',
    'trp'            => 'TRP',
    'expunging'      => 'Expungement',
    'waiver'         => 'Waiver',
    'waiver-renewal' => 'Waiver Renewal',
];

$userFormsJson = json_encode(array_values($userForms));
if (!isset($splitCitizenship)) {
    $splitCitizenship = \App\Services\CifEligibility::isSplitCitizenshipUi($userForms);
}
$clientCareMessage = \App\Services\CifEligibility::CLIENT_CARE_MESSAGE;
?>

<link rel="stylesheet" href="<?= Asset::url('/css/cif.css') ?>">

<!-- ══════════════════════════════════════════════════════════════ -->
<!--  Progress Bar                                                 -->
<!-- ══════════════════════════════════════════════════════════════ -->
<?php if (!empty($completedAt)): ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold py-2">
        <i class="bi bi-calendar-check me-2 text-success"></i>
        Submitted on: <?= Html::e(date('F j, Y \a\t g:i A', strtotime($completedAt))) ?>
    </div>
</div>
<?php endif; ?>

<div class="cif-progress-wrap mb-4"
     data-user-forms="<?= Html::e($userFormsJson) ?>"
     data-split-citizenship="<?= $splitCitizenship ? '1' : '0' ?>"
     data-client-care-message="<?= Html::e($clientCareMessage) ?>">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0 fw-bold">
            <i class="bi bi-file-person me-2"></i>Client Information Form
        </h5>
        <span class="badge bg-primary fs-6" id="cifProgressBadge"><?= $progress ?>%</span>
    </div>
    <div class="progress" style="height: 8px;">
        <div class="progress-bar bg-primary" id="cifProgressBar"
            role="progressbar" style="width: <?= $progress ?>%"
            aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"></div>
    </div>
    <div class="mt-2 d-flex flex-wrap gap-2">
        <?php foreach ($userForms as $ft): ?>
            <span class="badge rounded-pill"
                style="background:<?= Html::e($serviceColors[$ft]['bg'] ?? '#eee') ?>;
                         color:<?= Html::e($serviceColors[$ft]['text'] ?? '#333') ?>;
                         border:1px solid <?= Html::e($serviceColors[$ft]['border'] ?? '#ccc') ?>">
                <?= Html::e($serviceLabels[$ft] ?? $ft) ?>
            </span>
        <?php endforeach; ?>
    </div>
</div>

<?php if (empty($userForms)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        You currently have no services that require a CIF form.
    </div>
<?php else: ?>

    <ul class="nav nav-pills cif-section-nav mb-4 flex-nowrap overflow-auto" id="cifSectionNav">
        <?php foreach ($visibleSections as $idx => $section): ?>
            <li class="nav-item">
                <button class="nav-link <?= $idx === 0 ? 'active' : '' ?>"
                    data-section="<?= Html::e($section['id']) ?>"
                    type="button">
                    <i class="bi <?= Html::e($section['icon']) ?> me-1"></i>
                    <span class="d-none d-md-inline"><?= Html::e($section['title']) ?></span>
                    <span class="d-md-none"><?= Html::e($section['id']) ?></span>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <form id="cifForm" autocomplete="off" data-user-forms="<?= Html::e($userFormsJson) ?>">
        <input type="hidden" id="cifCsrf" name="csrf_token" value="<?= Html::e($csrfToken ?? ($_SESSION['csrf_token'] ?? '')) ?>">

        <?php foreach ($visibleSections as $idx => $section): ?>
            <div class="cif-section card border-0 shadow-sm mb-4 <?= $idx === 0 ? '' : 'd-none' ?>"
                data-section-id="<?= Html::e($section['id']) ?>">

                <div class="card-header bg-white fw-semibold">
                    <i class="bi <?= Html::e($section['icon']) ?> me-2"></i><?= Html::e($section['title']) ?>
                    <?php if (!empty($section['help'])): ?>
                        <small class="text-muted ms-2"><?= Html::e($section['help']) ?></small>
                    <?php endif; ?>
                </div>

                <div class="card-body">
                    <?php if ($section['id'] === 'spousal'): ?>
                        <div class="alert alert-info cif-spousal-empty d-none mb-3" role="status">
                            No spousal details required for your marital status.
                        </div>
                    <?php endif; ?>
                    <?php
                    // Group consecutive questions that share the same `row` key
                    $qs = array_values(array_filter(
                        $section['questions'],
                        function ($q) use ($userForms) {
                            return questionApplies($q, $userForms);
                        }
                    ));
                    $i = 0;
                    $n = count($qs);
                    while ($i < $n) {
                        $q = $qs[$i];
                        $rowId = $q['row'] ?? null;
                        if ($rowId === null) {
                            echo '<div class="row">';
                            renderCifQuestion($q, $userForms, $savedData, $serviceColors, $serviceLabels, $splitCitizenship);
                            echo '</div>';
                            $i++;
                            continue;
                        }
                        echo '<div class="row g-3 mb-2" data-cif-row="' . Html::e($rowId) . '">';
                        while ($i < $n && ($qs[$i]['row'] ?? null) === $rowId) {
                            renderCifQuestion($qs[$i], $userForms, $savedData, $serviceColors, $serviceLabels, $splitCitizenship);
                            $i++;
                        }
                        echo '</div>';
                    }
                    ?>
                </div>

                <div class="card-footer bg-white d-flex justify-content-between">
                    <?php if ($idx > 0): ?>
                        <button type="button" class="btn btn-outline-secondary cif-nav-btn" data-dir="prev">
                            <i class="bi bi-arrow-left me-1"></i>Previous
                        </button>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>

                    <?php if ($idx < count($visibleSections) - 1): ?>
                        <button type="button" class="btn btn-primary cif-nav-btn" data-dir="next">
                            Next<i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    <?php else: ?>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-success" id="cifSaveBtn">
                                <i class="bi bi-check-circle me-1"></i>Save
                            </button>
                            <button type="button" class="btn btn-primary" id="cifGenerateBtn">
                                <i class="bi bi-file-earmark-pdf me-1"></i>Generate PDFs
                            </button>
                            <button type="button" class="btn btn-warning fw-semibold" id="cifFinishBtn">
                                <i class="bi bi-flag-fill me-1"></i>Finish & Submit
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

    </form>

    <?php if (!empty($generatedPdfs)): ?> 
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-file-earmark-pdf me-2"></i>Generated PDF Forms
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Form</th>
                            <th>File</th>
                            <th>Pages</th>
                            <th>Generated</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($generatedPdfs as $gp): ?>
                            <tr>
                                <td>
                                    <span class="badge rounded-pill"
                                        style="background:<?= Html::e($serviceColors[$gp['form_type']]['bg'] ?? '#eee') ?>;
                                         color:<?= Html::e($serviceColors[$gp['form_type']]['text'] ?? '#333') ?>">
                                        <?= Html::e($serviceLabels[$gp['form_type']] ?? $gp['form_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                                    <?= Html::e(basename($gp['file_path'])) ?>
                                </td>
                                <td><?= Html::e($gp['page_count'] ?? 1) ?></td>
                                <td><?= Html::e($gp['generated_at']) ?></td>
                                <td class="text-center">
                                    <a href="/cif?action=download&id=<?= (int)$gp['id'] ?>"
                                        class="btn btn-sm btn-outline-primary" target="_blank"
                                        title="View / Download PDF">
                                        <i class="bi bi-download me-1"></i>Download
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
     <?php endif; ?>

<?php endif; ?>

<div id="cifToast" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1080">
    <div class="toast align-items-center border-0" id="cifToastEl" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="cifToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="<?= Asset::url('/js/cif-datepicker.js') ?>"></script>
<script src="<?= Asset::url('/js/cif/dates.js') ?>"></script>
<script src="<?= Asset::url('/js/cif/eligibility.js') ?>"></script>
<script src="<?= Asset::url('/js/cif/visibility.js') ?>"></script>
<script src="<?= Asset::url('/js/cif/timeline.js') ?>"></script>
<script src="<?= Asset::url('/js/cif/tables.js') ?>"></script>
<script src="<?= Asset::url('/js/cif/data.js') ?>"></script>
<script src="<?= Asset::url('/js/cif/validate.js') ?>"></script>
<script src="<?= Asset::url('/js/cif.js') ?>"></script>
