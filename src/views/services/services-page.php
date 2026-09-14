<?php

use App\Support\Asset;
use App\Support\Html;

/**
 * Services Page — Accordion list of all enrolled services.
 *
 * Two-column layout per service:
 *   Left  — Details (date + cost), Status, Courts, Police Certificates
 *   Right — Email History (clickable links → modal viewer)
 *
 * Variables injected by ServicesController::index():
 *   $services            — array of user_services rows (with service_type, service_label, total_cost)
 *   $courtsPolice        — grouped by user_service_id
 *   $emailHistory        — grouped by user_service_id
 *   $policeCertificates  — grouped by user_service_id (from service_sbc_records)
 *   $serviceColors       — colour map keyed by service_type
 */

function fmtDate(?string $d, string $fmt = 'd/m/Y'): string
{
    if (!$d) return '—';
    $t = strtotime($d);
    return $t ? date($fmt, $t) : '—';
}
function fmtDateTime(?string $d): string
{
    if (!$d) return '—';
    $t = strtotime($d);
    return $t ? date('d/m/Y h:i A', $t) : '—';
}
?>

<!-- Page CSS -->
<link rel="stylesheet" href="<?= Asset::url('/css/services.css') ?>">

<div class="container-fluid py-4 px-3 px-md-4">

    <!-- ── Page header ────────────────────────────────────── -->
    <?php if (empty($services)): ?>
        <div class="text-center py-5">
            <i class="bi bi-briefcase fs-1 text-muted d-block mb-2"></i>
            <p class="text-muted">No services assigned to your account yet.</p>
        </div>
    <?php else: ?>

        <!-- ── Accordion: one item per service ────────────── -->
        <div class="accordion svc-accordion" id="servicesAccordion">
            <?php foreach ($services as $idx => $svc):
                $svcId   = (int)$svc['id'];
                $svcType = $svc['service_type'];
                $sc      = $serviceColors[$svcType] ?? ['bg' => '#eee', 'text' => '#333', 'border' => '#ccc', 'icon' => 'bi-briefcase'];
                $courts  = $courtsPolice[$svcId] ?? [];
                $emails  = $emailHistory[$svcId]  ?? [];
                $certs   = $policeCertificates[$svcId] ?? [];

                $isCrTrp   = in_array($svcType, ['trp', 'criminal-rehab'], true);
                $isParExp  = in_array($svcType, ['pardon', 'expunging'], true);
                $isWaiver  = ($svcType === 'waiver');

                // Split courts/police
                $courtList  = array_filter($courts, function ($c) {
                    return $c['type'] === 'court';
                });
                $policeList = array_filter($courts, function ($c) {
                    return $c['type'] === 'police';
                });
            ?>
                <div class="accordion-item svc-item" style="border-left: 4px solid <?= $sc['border'] ?>;">
                    <h2 class="accordion-header">
                        <button class="accordion-button <?= $idx > 0 ? 'collapsed' : '' ?>"
                            type="button" data-bs-toggle="collapse"
                            data-bs-target="#svc-<?= $svcId ?>"
                            aria-expanded="<?= $idx === 0 ? 'true' : 'false' ?>">
                            <span class="svc-header-icon me-2"
                                style="background: <?= $sc['bg'] ?>; color: <?= $sc['text'] ?>;">
                                <i class="bi <?= $sc['icon'] ?>"></i>
                            </span>
                            <span class="svc-header-label">
                                <?= Html::e($svc['service_label']) ?>
                            </span>
                            <span class="badge rounded-pill ms-2"
                                style="background: <?= $sc['bg'] ?>; color: <?= $sc['text'] ?>; font-size: 0.7rem;">
                                Since <?= fmtDate($svc['date_added']) ?>
                            </span>
                        </button>
                    </h2>

                    <div id="svc-<?= $svcId ?>" class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>"
                        data-bs-parent="#servicesAccordion">
                        <div class="accordion-body p-3 p-md-4">

                            <div class="row g-4">
                                <!-- ══════ LEFT COLUMN ══════ -->
                                <div class="col-lg-7 d-flex flex-column gap-3">

                                    <!-- Details (compact inline) -->
                                    <div class="svc-info-card svc-details-compact">
                                        <div class="svc-info-card-header">
                                            <i class="bi bi-info-circle me-2"></i>Details
                                        </div>
                                        <div class="d-flex flex-wrap gap-3 px-3 py-2">
                                            <div class="svc-detail-chip">
                                                <span class="svc-detail-chip-label">Date Added</span>
                                                <span class="svc-detail-chip-value"><?= fmtDate($svc['date_added']) ?></span>
                                            </div>
                                            <div class="svc-detail-chip">
                                                <span class="svc-detail-chip-label">Service Cost</span>
                                                <span class="svc-detail-chip-value">$<?= number_format((float)$svc['total_cost'], 2) ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Status -->
                                    <div class="svc-info-card">
                                        <div class="svc-info-card-header">
                                            <i class="bi bi-activity me-2"></i>Current Status:
                                            <span class="svc-status-inline"><?= Html::e($svc['current_status']) ?></span>
                                        </div>
                                        <?php if (!empty($svc['status_explanation'])): ?>
                                            <div class="svc-status-text">
                                                <?= Html::e($svc['status_explanation']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Courts (hidden for Expunging) -->
                                    <?php if ($svcType !== 'expunging' && !empty($courtList)): ?>
                                        <div class="svc-info-card">
                                            <div class="svc-info-card-header">
                                                <i class="bi bi-bank me-2"></i>Courts
                                            </div>
                                            <ul class="svc-institution-list px-3 py-2 mb-0">
                                                <?php foreach ($courtList as $c): ?>
                                                    <li>
                                                        <strong><?= Html::e($c['name']) ?></strong>
                                                        <?php if ($c['city']): ?>
                                                            <span class="text-muted">— <?= Html::e($c['city']) ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($c['received'])): ?>
                                                            <span class="badge bg-success ms-2">received</span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Police Certificates table (Criminal Rehab + TRP only) -->
                                    <?php if ($isCrTrp && !empty($certs)): ?>
                                        <div class="svc-info-card">
                                            <div class="svc-info-card-header">
                                                <i class="bi bi-table me-2"></i>Police Certificates
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-sm svc-data-table mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Issuing Authority</th>
                                                            <th>Expiration Date</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($certs as $cert):
                                                            $expShow = !empty($cert['snd_exp_date'])
                                                                ? $cert['snd_exp_date']
                                                                : ($cert['exp_date'] ?? null);
                                                        ?>
                                                            <tr>
                                                                <td class="fw-semibold"><?= Html::e($cert['issuing_authority'] ?? $cert['bc_name'] ?? '') ?></td>
                                                                <td><?= fmtDate($expShow) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Police Certificate section (Pardon + Expungement only) -->
                                    <?php if ($isParExp && !empty($policeList)): ?>
                                        <div class="svc-info-card">
                                            <div class="svc-info-card-header">
                                                <i class="bi bi-shield-check me-2"></i>Police Certificate
                                            </div>
                                            <ul class="svc-institution-list px-3 py-2 mb-0">
                                                <?php foreach ($policeList as $p): ?>
                                                    <li>
                                                        <strong><?= Html::e($p['name']) ?></strong>
                                                        <?php if ($p['city']): ?>
                                                            <span class="text-muted">— <?= Html::e($p['city']) ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($p['received'])): ?>
                                                            <span class="badge bg-success ms-2">received</span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>

                                    <!-- RCMP Record Exp Date (Waiver only) -->
                                    <?php if ($isWaiver): ?>
                                        <div class="svc-info-card">
                                            <div class="svc-info-card-header">
                                                <i class="bi bi-file-earmark-lock me-2"></i>RCMP Record
                                            </div>
                                            <div class="svc-meta-grid">
                                                <div class="svc-meta-item">
                                                    <span class="svc-meta-label">Expiration Date</span>
                                                    <span class="svc-meta-value"><?= fmtDate($svc['rcmp_record_exp_date'] ?? null) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                </div><!-- /left col -->

                                <!-- ══════ RIGHT COLUMN — Email History ══════ -->
                                <div class="col-lg-5">
                                    <div class="svc-info-card svc-email-card">
                                        <div class="svc-info-card-header d-flex align-items-center justify-content-between">
                                            <span><i class="bi bi-envelope me-2"></i>Email History</span>
                                            <span class="badge bg-secondary"><?= count($emails) ?></span>
                                        </div>
                                        <?php if (!empty($emails)): ?>
                                            <div class="svc-email-list">
                                                <?php foreach ($emails as $eIdx => $em): ?>
                                                    <a href="#" class="svc-email-item svc-email-link"
                                                        data-email-id="<?= (int)$em['id'] ?>">
                                                        <span class="svc-email-num"><?= $eIdx + 1 ?>.</span>
                                                        <span class="svc-email-date"><?= fmtDateTime($em['sent_at']) ?></span>
                                                        <span class="svc-email-sep">—</span>
                                                        <span class="svc-email-subject"><?= Html::e($em['subject']) ?></span>
                                                        <i class="bi bi-chevron-right svc-email-chevron ms-auto"></i>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center text-muted py-4">
                                                <i class="bi bi-inbox fs-4 d-block mb-1"></i>
                                                <small>No emails yet</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div><!-- /right col -->

                            </div><!-- /row -->

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>

<!-- ════════════════════════════════════════════════════════════
     EMAIL VIEW MODAL (Services page)
     ════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="svcEmailViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-envelope-open me-2"></i><span id="svcEmailSubject">Email</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-muted small mb-3" id="svcEmailMeta"></div>
                <hr>
                <div id="svcEmailBody" class="email-body-content">
                    <div class="text-center text-muted py-4">
                        <div class="spinner-border spinner-border-sm me-2"></div>Loading…
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Page JS -->
<script src="<?= Asset::url('/js/services.js') ?>"></script>
