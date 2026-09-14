<?php

use App\Support\Asset;
use App\Support\Html;

/**
 * Payments Kanban Board.
 *
 * Four columns:
 *  1. Service Cost    — enrolled services with name & price
 *  2. Payment Plan    — all installments (paid + missed + upcoming)
 *  3. Payment Method  — current CC info, CC change modal
 *  4. Additional Info — admin fee, tax rate, payment date change request
 *
 * Variables injected by PaymentsController::index():
 *  $serviceCosts, $plans, $paid, $pending, $missed,
 *  $extras, $serviceColors, $methodLabels,
 *  $planPaidCounts, $totalPaid, $totalPending,
 *  $totalMissed, $discount, $taxProvince, $taxRate,
 *  $ccLast4, $ccExpiry
 */

function fmtMoney(float $v): string
{
    return '$' . number_format($v, 2);
}

/* Format method label */
function fmtMethod(string $method, array $methodLabels): string
{
    return $methodLabels[$method] ?? ucfirst(str_replace('_', ' ', $method));
}
?>

<link rel="stylesheet" href="<?= Asset::url('/css/payments.css') ?>">

<!-- ── Summary bar ────────────────────────────────────────── -->
<?php
/* Grand total across all plans — computed here so the summary
       bar and the Kanban column share the same value. */
$grandTotal = 0.0;
foreach ($plans as $pl) {
    $grandTotal += (float)$pl['total_amount'];
}
$grandTotalWithTax = $grandTotal * (1 + $taxRate);
$taxAmount         = $grandTotal * $taxRate;
$taxPct            = round($taxRate * 100, 2);
$taxLabel          = $taxPct == intval($taxPct)
    ? intval($taxPct) . '%'
    : $taxPct . '%';

/* Build allBundles for Payment Plan column: paid + missed + pending in date order */
$allBundles = array_merge($paid, $missed, $pending);
usort($allBundles, fn($a, $b) => strcmp($a['due_date'], $b['due_date']));

/*
 * Re-classify statuses with strict 3-state logic:
 *  - Paid: consecutive from the start; once a gap (non-paid) is hit, stop.
 *  - Missed: everything unpaid with due_date <= today
 *  - Upcoming: everything with due_date > today
 * This means Missed can never appear between two Paid.
 */
$today = strtotime('today');
$paidStreak = true;               // tracks whether we're still in the paid streak
foreach ($allBundles as &$b) {
    if ($b['status'] === 'paid' && $paidStreak) {
        // remains Paid — part of consecutive paid streak
        $b['display_status'] = 'paid';
    } else {
        $paidStreak = false;       // streak broken
        if (strtotime($b['due_date']) <= $today) {
            $b['display_status'] = 'missed';
        } else {
            $b['display_status'] = 'upcoming';
        }
    }
}
unset($b);

/* Next upcoming payment date for the 4-day check */
$nextPaymentDate = null;
foreach ($allBundles as $b) {
    if (in_array($b['display_status'], ['missed', 'upcoming']) && strtotime($b['due_date']) >= strtotime('today')) {
        $nextPaymentDate = $b['due_date'];
        break;
    }
}

/* Recalculate missed totals based on display_status (strict 3-state) */
$missedBundles = array_filter($allBundles, fn($b) => $b['display_status'] === 'missed');
$totalMissedCalc = array_sum(array_map(fn($b) => $b['total_amount'] * (1 + $taxRate), $missedBundles));
$missedCount     = count($missedBundles);
?>

<!-- Row 1: Paid | Outstanding | Discount | Total -->
<div class="row g-3 mb-2">
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="pay-stat-icon" style="background:rgba(13,110,253,.1);color:rgb(13,110,253);">
                    <i class="bi bi-check-circle-fill fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small text-uppercase fw-semibold">Paid</div>
                    <div class="fs-5 fw-bold" style="color:rgb(13,110,253);"><?= fmtMoney($totalPaid) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="pay-stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-clock-fill fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small text-uppercase fw-semibold">Outstanding</div>
                    <div class="fs-5 fw-bold text-warning"><?= fmtMoney($totalPending) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="pay-stat-icon" style="background:rgba(240,166,13,.1);color:rgb(240,166,13);">
                    <i class="bi bi-tag-fill fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small text-uppercase fw-semibold">Discount</div>
                    <div class="fs-5 fw-bold" style="color:rgb(240,166,13);"><?= fmtMoney($discount ?? 0.0) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="pay-stat-icon" style="background:rgba(25,135,84,.1);color:rgb(25,135,84);">
                    <i class="bi bi-calculator-fill fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="text-muted small text-uppercase fw-semibold">Total</div>
                    <div class="fs-5 fw-bold" style="color:rgb(25,135,84);"><?= fmtMoney($grandTotalWithTax) ?></div>
                    <div class="text-muted" style="font-size:0.72rem;line-height:1.3;margin-top:2px;">
                        <?= fmtMoney($grandTotal) ?> + <?= $taxLabel ?> tax
                        <span class="pay-tax-province">(<?= Html::e($taxProvince) ?>)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Row 2: Missed Payments (single compact line) -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm pay-missed-row">
            <div class="card-body d-flex align-items-center gap-3 py-2 flex-wrap">
                <div class="pay-stat-icon bg-danger bg-opacity-10 text-danger" style="width:36px;height:36px;">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                </div>
                <span class="text-muted small text-uppercase fw-semibold">Missed Payments</span>
                <span class="fs-5 fw-bold text-danger"><?= fmtMoney($totalMissedCalc) ?></span>
                <?php if ($missedCount > 0): ?>
                    <span class="pay-missed-formula-inline">
                        <?= implode(' + ', array_map(fn($mb) => fmtMoney($mb['total_amount'] * (1 + $taxRate)), $missedBundles)) ?>
                    </span>
                    <span class="text-muted small ms-auto"><?= $missedCount ?> unpaid</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     KANBAN BOARD
     ════════════════════════════════════════════════════════════ -->
<div class="row g-3 pay-kanban">

    <!-- ── COLUMN 1: Service Cost ────────────────────────────── -->
    <div class="col-lg-3 col-md-6">
        <div class="pay-column">
            <div class="pay-column-header pay-col-service">
                <i class="bi bi-tags-fill me-2"></i>Service Cost
                <span class="badge bg-dark bg-opacity-25 ms-auto"><?= count($plans) ?></span>
            </div>
            <div class="pay-column-body">
                <?php if (empty($plans)): ?>
                    <div class="text-muted text-center py-4 small">
                        <i class="bi bi-inbox fs-4 d-block mb-1"></i>No services enrolled.
                    </div>
                <?php else: ?>
                    <?php foreach ($plans as $pl):
                        $svcType = $pl['service_type'];
                        $sc      = $serviceColors[$svcType] ?? ['bg' => '#eee', 'text' => '#333', 'border' => '#ccc'];
                    ?>
                        <div class="pay-card" style="border-left: 4px solid <?= $sc['border'] ?>;">
                            <div class="pay-card-title"><?= Html::e($pl['service_label']) ?></div>
                            <div class="pay-card-amount"><?= fmtMoney((float)$pl['total_amount']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── COLUMN 2: Payment Plan (all installments) ─────────── -->
    <div class="col-lg-3 col-md-6">
        <div class="pay-column">
            <div class="pay-column-header pay-col-paid">
                <i class="bi bi-calendar-range-fill me-2"></i>Payment Plan
                <span class="badge bg-dark bg-opacity-25 ms-auto"><?= count($allBundles) ?></span>
            </div>
            <div class="pay-column-body">
                <?php if (empty($allBundles)): ?>
                    <div class="text-muted text-center py-4 small">
                        <i class="bi bi-inbox fs-4 d-block mb-1"></i>No payment plan entries.
                    </div>
                <?php else: ?>
                    <?php foreach ($allBundles as $bundle):
                        $ds = $bundle['display_status'];
                        $amountWithTax = $bundle['total_amount'] * (1 + $taxRate);
                        if ($ds === 'paid') {
                            $badgeHtml = '<span class="badge bg-success">Paid</span>';
                            $cardClass = 'pay-card-paid';
                            $dateIcon  = 'bi-calendar-check';
                        } elseif ($ds === 'missed') {
                            $badgeHtml = '<span class="badge bg-danger">Missed</span>';
                            $cardClass = 'pay-card-missed';
                            $dateIcon  = 'bi-calendar-x';
                        } else {
                            $badgeHtml = '<span class="badge bg-secondary">Upcoming</span>';
                            $cardClass = 'pay-card-upcoming';
                            $dateIcon  = 'bi-calendar';
                        }
                    ?>
                        <div class="pay-card <?= $cardClass ?> pay-card-bundle"
                            data-payment-ids="<?= htmlspecialchars(json_encode($bundle['payment_ids']), ENT_QUOTES) ?>"
                            data-amount="<?= number_format($amountWithTax, 2, '.', '') ?>"
                            data-due="<?= Html::e($bundle['due_date']) ?>">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div class="pay-card-month">
                                    <i class="bi <?= $dateIcon ?> me-1"></i><?= date('M j, Y', strtotime($bundle['due_date'])) ?>
                                </div>
                                <?= $badgeHtml ?>
                            </div>
                            <div class="pay-card-amount"><?= fmtMoney($amountWithTax) ?></div>
                            <?php if ($ds === 'missed' || $ds === 'upcoming'): ?>
                                <button type="button"
                                    class="btn btn-sm btn-pay-now w-100 mt-2"
                                    data-bs-toggle="modal"
                                    data-bs-target="#monerisPayModal"
                                    data-payment-ids="<?= htmlspecialchars(json_encode($bundle['payment_ids']), ENT_QUOTES) ?>"
                                    data-amount="<?= number_format($amountWithTax, 2, '.', '') ?>"
                                    data-due="<?= Html::e($bundle['due_date']) ?>"
                                    data-cc-last4="<?= Html::e($ccLast4) ?>"
                                    data-cc-expiry="<?= Html::e($ccExpiry) ?>">
                                    <i class="bi bi-credit-card-fill me-1"></i>Pay Now
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── COLUMN 3: Payment Method ──────────────────────────── -->
    <div class="col-lg-3 col-md-6">
        <div class="pay-column">
            <div class="pay-column-header pay-col-method">
                <i class="bi bi-credit-card-fill me-2"></i>Payment Method
            </div>
            <div class="pay-column-body">
                <?php
                /* Collect unique methods from all payments */
                $allMethods = [];
                foreach (array_merge($paid, $missed, $pending) as $b) {
                    foreach ($b['methods'] ?? [] as $m) {
                        $allMethods[$m] = true;
                    }
                }
                $currentMethod = !empty($allMethods) ? array_key_first($allMethods) : '';

                /* Compute first payment, monthly payment, total number of payments */
                $firstPlanPayment  = 0.0;
                $monthlyPayment    = 0.0;
                $totalNumPayments  = 0;
                if (!empty($plans)) {
                    foreach ($plans as $pl) {
                        $inst = max((int)$pl['installments'], 1);
                        $totalNumPayments = max($totalNumPayments, $inst);
                        $monthlyPayment  += (float)$pl['total_amount'] / $inst;
                    }
                    $firstPlanPayment = $monthlyPayment;
                }

                /* Full payment: only show if single installment and fully paid */
                $isFullPayment = ($totalNumPayments === 1 && !empty($paid) && empty($missed) && empty($pending));

                /* Old credit cards — placeholder array (populated by admin in DB later) */
                $oldCards  = [];  // array of ['number' => 'xxxx...1234', 'expiry' => '01/2024']

                /* CC display values come from controller ($ccLast4, $ccExpiry) */
                $displayLast4  = !empty($ccLast4) ? $ccLast4 : '----';
                $displayExpiry = !empty($ccExpiry) ? $ccExpiry : '—';
                ?>

                <!-- Province - Tax -->
                <div class="pay-method-field">
                    <label class="pay-method-label">Province - Tax <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" disabled
                        value="<?= Html::e($taxProvince) ?> - <?= $taxLabel ?>">
                </div>

                <!-- Method of payment -->
                <div class="pay-method-field">
                    <label class="pay-method-label">Method of payment <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" disabled
                        value="<?= Html::e($currentMethod ? fmtMethod($currentMethod, $methodLabels) : 'Not Selected') ?>">
                </div>

                <!-- Credit card number (last 4 digits) -->
                <div class="pay-method-field pay-cc-block">
                    <label class="pay-method-label">Credit card number <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" disabled
                        value="xxxx xxxx xxxx <?= Html::e($displayLast4) ?>">
                </div>

                <!-- Expiry date (always shown) -->
                <div class="pay-method-field pay-cc-block">
                    <label class="pay-method-label">Expiry date <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" disabled
                        value="<?= Html::e($displayExpiry) ?>" placeholder="MM/YYYY">
                </div>

                <?php if ($isFullPayment): ?>
                    <!-- Full payment (only shown when single installment & fully paid) -->
                    <div class="pay-method-field">
                        <label class="pay-method-label">Full payment</label>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" disabled checked>
                            <label class="form-check-label small text-success">
                                <i class="bi bi-check-lg me-1"></i>Paid in full
                            </label>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Total number of payments -->
                <div class="pay-method-field">
                    <label class="pay-method-label">Total number of payments <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" disabled
                        value="<?= $totalNumPayments ?>">
                </div>

                <!-- First payment -->
                <div class="pay-method-field">
                    <label class="pay-method-label">First payment <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" disabled
                        value="<?= number_format($firstPlanPayment, 2) ?>">
                </div>

                <!-- Monthly payment -->
                <div class="pay-method-field">
                    <label class="pay-method-label">Monthly payment</label>
                    <input type="text" class="form-control form-control-sm" disabled readonly
                        value="<?= number_format($monthlyPayment, 2) ?>">
                </div>

                <?php if (!empty($oldCards)): ?>
                    <!-- Old Credit Cards (only shown when old cards exist) -->
                    <div class="pay-method-field pay-cc-block">
                        <label class="pay-method-label">Old Credit Cards</label>
                        <div class="pay-old-cards-list">
                            <?php foreach ($oldCards as $oc): ?>
                                <div class="pay-old-card-row">
                                    <input type="text" class="form-control form-control-sm" disabled
                                        value="<?= Html::e($oc['number']) ?>" style="width:180px;">
                                    <input type="text" class="form-control form-control-sm" disabled
                                        value="<?= Html::e($oc['expiry']) ?>" style="width:100px;">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <hr class="my-2">

                <!-- Change Credit Card button → opens modal -->
                <button type="button" class="btn btn-sm w-100 pay-btn-change-cc"
                    data-bs-toggle="modal" data-bs-target="#changeCcModal">
                    <i class="bi bi-credit-card me-1"></i>Change Credit Card
                </button>
            </div>
        </div>
    </div>

    <!-- ── COLUMN 4: Additional Info ─────────────────────────── -->
    <div class="col-lg-3 col-md-6">
        <div class="pay-column">
            <div class="pay-column-header pay-col-info">
                <i class="bi bi-info-circle-fill me-2"></i>Additional Info
            </div>
            <div class="pay-column-body">

                <!-- Admin Fee -->
                <div class="pay-card pay-card-info-item mb-2">
                    <div class="pay-card-title small text-muted text-uppercase mb-1">
                        <i class="bi bi-receipt me-1"></i>Admin Fee
                    </div>
                    <div class="pay-card-amount fs-6">
                        <?php
                        $adminFeeTotal = 0.0;
                        foreach ($extras as $ex) {
                            $lbl = strtolower($ex['service_label'] ?? '');
                            if (str_contains($lbl, 'admin')) {
                                $adminFeeTotal += (float)$ex['amount'];
                            }
                        }
                        echo fmtMoney($adminFeeTotal);
                        ?>
                    </div>
                </div>

                <!-- Tax Rate -->
                <div class="pay-card pay-card-info-item mb-2">
                    <div class="pay-card-title small text-muted text-uppercase mb-1">
                        <i class="bi bi-percent me-1"></i>Tax Rate
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><?= $taxLabel ?></span>
                        <span class="pay-tax-province badge bg-secondary"><?= Html::e($taxProvince) ?></span>
                    </div>
                    <div class="pay-card-meta mt-1">
                        Tax amount: <?= fmtMoney($taxAmount) ?>
                    </div>
                </div>

                <!-- Request to Change Payment Date -->
                <div class="pay-card pay-card-info-item">
                    <div class="pay-card-title small text-muted text-uppercase mb-2">
                        <i class="bi bi-calendar-event me-1"></i>Request Payment Date Change
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Preferred day of month</label>
                        <select class="form-select form-select-sm" id="payDateDaySelect">
                            <?php for ($d = 1; $d <= 28; $d++): ?>
                                <option value="<?= $d ?>"><?= $d ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Reason (optional)</label>
                        <textarea class="form-control form-control-sm" id="payDateReason"
                            rows="2" placeholder="e.g. paycheck timing"></textarea>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary w-100"
                        id="payDateRequestBtn">
                        <i class="bi bi-send me-1"></i>Submit Request
                    </button>
                    <div id="payDateRequestMsg" class="mt-2 d-none"></div>
                </div>

            </div>
        </div>
    </div>

</div><!-- /pay-kanban -->

<!-- ── Moneris Pay Now Modal ──────────────────────────────── -->
<div class="modal fade" id="monerisPayModal" tabindex="-1" aria-labelledby="monerisPayModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header pay-col-method text-white">
                <h6 class="modal-title" id="monerisPayModalLabel">
                    <i class="bi bi-lock-fill me-2"></i>Secure Payment
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Body: card form -->
            <div class="modal-body">

                <!-- Amount summary -->
                <div class="alert alert-light border py-2 mb-3 d-flex align-items-center gap-2" id="monerisPaySummary">
                    <i class="bi bi-calendar-event text-muted"></i>
                    <div>
                        <span class="text-muted small">Due date:</span>
                        <strong id="monerisPayDue"></strong>
                        <span class="ms-3 text-muted small">Amount:</span>
                        <strong id="monerisPayAmount" class="text-success"></strong>
                    </div>
                </div>

                <form id="monerisPayForm" novalidate autocomplete="on">
                    <input type="hidden" id="monerisPayIds" value="">
                    <input type="hidden" id="monerisPayCsrf" value="<?= Html::e($_SESSION['csrf_token'] ?? '') ?>">
                    <!-- Hidden: stores MM/YYYY from flatpickr (real submitted value) -->
                    <input type="hidden" id="monerisCardExpiryHidden" value="">

                    <!-- Cardholder name -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Cardholder Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="monerisCardName"
                            required placeholder="Name as it appears on card"
                            autocomplete="cc-name">
                    </div>

                    <!-- Card number with saved-card hint -->
                    <div class="mb-1">
                        <label class="form-label small fw-semibold">Card Number <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-credit-card" id="monerisCardIcon"></i></span>
                            <input type="text" class="form-control" id="monerisCardNumber"
                                required placeholder="•••• •••• •••• ••••"
                                maxlength="19" inputmode="numeric" autocomplete="cc-number">
                        </div>
                    </div>
                    <!-- Last-4 hint (shown when saved card exists) -->
                    <div id="monerisCardHint" class="d-none mb-3">
                        <span class="text-muted small">
                            <i class="bi bi-info-circle me-1"></i>Card on file ends in
                            <strong id="monerisCardHintLast4"></strong> — enter the full number to pay.
                        </span>
                    </div>
                    <div id="monerisCardHintNone" class="mb-3 d-none"><!-- spacer --></div>

                    <div class="row g-2 mb-3">
                        <!-- Expiry via flatpickr month picker -->
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Expiry <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                                <input type="text" class="form-control" id="monerisCardExpiry"
                                    required placeholder="MM/YYYY" readonly
                                    autocomplete="cc-exp">
                            </div>
                        </div>
                        <!-- CVD — always manual -->
                        <div class="col-6">
                            <label class="form-label small fw-semibold">CVD <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="monerisCardCvd"
                                required placeholder="•••" maxlength="4"
                                inputmode="numeric" autocomplete="cc-csc">
                            <div class="form-text text-muted" style="font-size:0.7rem;">3–4 digits on back of card</div>
                        </div>
                    </div>

                    <!-- Error / success messages -->
                    <div id="monerisPayError" class="alert alert-danger  py-2 small d-none"></div>
                    <div id="monerisPaySuccess" class="alert alert-success py-2 small d-none"></div>
                </form>

                <!-- Processing spinner (hidden until submit) -->
                <div id="monerisPaySpinner" class="text-center py-3 d-none">
                    <div class="spinner-border text-primary" role="status" style="width:1.5rem;height:1.5rem;"></div>
                    <div class="small text-muted mt-2">Processing payment…</div>
                </div>

            </div>

            <!-- Footer -->
            <div class="modal-footer">
                <div class="me-auto">
                    <i class="bi bi-shield-lock text-muted me-1" style="font-size:0.8rem;"></i>
                    <span class="text-muted" style="font-size:0.75rem;">Secured by Moneris</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="monerisPayCancelBtn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-pay-now" id="monerisPaySubmitBtn">
                    <i class="bi bi-lock-fill me-1"></i>Pay <span id="monerisPayBtnAmount"></span>
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ── Change CC Modal ────────────────────────────────────── -->
<div class="modal fade" id="changeCcModal" tabindex="-1" aria-labelledby="changeCcModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header pay-col-method text-white">
                <h6 class="modal-title" id="changeCcModalLabel">
                    <i class="bi bi-credit-card me-2"></i>Change Credit Card
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="changeCcForm" novalidate>
                    <!-- Cardholder Name -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Cardholder Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="ccName" required
                            placeholder="Name as it appears on card">
                    </div>
                    <!-- Card Number -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Card Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="ccNumber" required
                            placeholder="1234 5678 9012 3456" maxlength="19"
                            inputmode="numeric" autocomplete="cc-number">
                    </div>
                    <div class="row g-2 mb-3">
                        <!-- Expiry -->
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Expiry Date <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="ccExpiry" required
                                placeholder="MM/YYYY" maxlength="7" autocomplete="cc-exp">
                        </div>
                        <!-- CVV -->
                        <div class="col-6">
                            <label class="form-label small fw-semibold">CVV <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="ccCvv" required
                                placeholder="123" maxlength="4" inputmode="numeric" autocomplete="cc-csc">
                        </div>
                    </div>
                    <!-- Billing Address -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Billing Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="ccAddress" required
                            placeholder="123 Main St" autocomplete="street-address">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">City <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="ccCity" required
                                placeholder="City" autocomplete="address-level2">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Postal Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="ccPostal" required
                                placeholder="A1A 1A1" maxlength="7" autocomplete="postal-code">
                        </div>
                    </div>

                    <hr>

                    <!-- Requested Start Date -->
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Requested Start Date <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="ccChangeDateInput" required
                            placeholder="Select date…" readonly
                            data-next-payment="<?= Html::e($nextPaymentDate ?? '') ?>">
                        <input type="hidden" id="ccChangeDateHidden" value="">
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>We will start to use this card only after the date you choose.
                        </div>
                    </div>

                    <!-- 4-day warning -->
                    <div id="ccChangeDateWarning" class="pay-cc-warning d-none mb-3">
                        <i class="bi bi-telephone-fill me-1"></i>
                        We are unable to process your payment online, please call accounting to change your payment.
                        <strong>+1 800 543 2137 ext 822</strong>
                    </div>

                    <div id="ccChangeSuccess" class="alert alert-success py-2 small d-none">
                        <i class="bi bi-check-circle me-1"></i>Your request has been submitted. Accounting will contact you shortly.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm pay-btn-change-cc" id="ccChangeSubmitBtn">
                    <i class="bi bi-send me-1"></i>Submit Request
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= Asset::url('/js/payments.js') ?>"></script>
