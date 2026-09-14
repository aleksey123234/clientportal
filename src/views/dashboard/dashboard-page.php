<?php

use App\Support\Asset;
use App\Support\Html;

/**
 * Dashboard view — real-time overview of the client's portal data.
 *
 * Variables injected by DashboardController::index():
 *   $user           — array (first_name, last_name, client_id, last_login_at, …)
 *   $unreadEmails   — int
 *   $recentEmails   — array of recent inbox rows
 *   $cifStatus      — array (progress, completed_at, pdf_count, started)
 *   $nextPayment    — ?array (due_date, total_amount)
 *   $paymentSummary — array (total_paid, total_missed, paid_count, missed_count)
 *   $docStats       — array (total, pending, uploaded, approved, completed)
 *   $services       — array of user_services rows
 *   $serviceColors  — assoc array  service_type → [bg, text, border, icon]
 *   $serviceLabels  — assoc array  service_type → human label
 */

$h = [Html::class, 'e'];
$firstName = $h($user['first_name'] ?? 'Client');

// Greeting based on time of day
$hour = (int) date('G');
if ($hour < 12)      $greeting = 'Good Morning';
elseif ($hour < 18)  $greeting = 'Good Afternoon';
else                 $greeting = 'Good Evening';

// Next payment formatting
$nextPaymentLabel  = '—';
$nextPaymentDate   = 'No pending payments';
if ($nextPayment) {
    $nextPaymentLabel = '$' . number_format((float) $nextPayment['total_amount'], 2);
    $nextPaymentDate  = date('M j, Y', strtotime($nextPayment['due_date']));
}

// Office time (America/Toronto — Eastern Time)
$officeTz   = new \DateTimeZone('America/Toronto');
$officeNow  = new \DateTime('now', $officeTz);
$officeTime = $officeNow->format('g:i A');
$etHour     = (int) $officeNow->format('G');
$etDay      = (int) $officeNow->format('N'); // 1=Mon … 7=Sun
$officeOpen = ($etDay >= 1 && $etDay <= 5 && $etHour >= 9 && $etHour < 15);

// Status badge helper
$statusBadge = function ($status) {
    $map = [
        'active'          => ['Active',          'bg-success'],
        'pending'         => ['Pending',         'bg-warning text-dark'],
        'on-hold'         => ['On Hold',         'bg-secondary'],
        'completed'       => ['Completed',       'bg-primary'],
        'closed'          => ['Closed',          'bg-dark'],
        'waiting_payment' => ['Awaiting Payment', 'bg-info text-dark'],
    ];
    $entry = $map[$status] ?? [ucfirst(str_replace(['_', '-'], ' ', $status)), 'bg-secondary'];
    return '<span class="badge ' . $entry[1] . '">' . htmlspecialchars($entry[0]) . '</span>';
};
?>

<link rel="stylesheet" href="<?= Asset::url('/css/dashboard.css') ?>">

<!-- ════════════ WELCOME BANNER ════════════ -->
<div class="dash-welcome mb-4">
    <div>
        <h4 class="mb-1"><?= $greeting ?>, <?= $firstName ?>!</h4>
        <p class="text-muted mb-0">Here's what's happening with your case.</p>
    </div>
    <?php if (!empty($user['last_login_at'])): ?>
        <small class="text-muted">
            <i class="bi bi-clock me-1"></i>Last login: <?= date('M j, Y \a\t g:i A', strtotime($user['last_login_at'])) ?>
        </small>
    <?php endif; ?>
</div>

<!-- ════════════ STAT CARDS ROW ════════════ -->
<div class="row g-3 mb-4">

    <!-- Client ID -->
    <div class="col-6 col-xl-3">
        <div class="card dash-stat-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="dash-stat-icon dash-stat-icon--clientid">
                    <i class="bi bi-person-badge"></i>
                </div>
                <div>
                    <div class="dash-stat-label">Client ID</div>
                    <div class="dash-stat-value"><?= $h($user['client_id'] ?? '—') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Unread Emails -->
    <div class="col-6 col-xl-3">
        <a href="/documents" class="text-decoration-none dash-stat-link" data-page="documents">
            <div class="card dash-stat-card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="dash-stat-icon dash-stat-icon--email">
                        <i class="bi bi-envelope-open"></i>
                    </div>
                    <div>
                        <div class="dash-stat-label">Unread Emails</div>
                        <div class="dash-stat-value"><?= $unreadEmails ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Next Payment -->
    <div class="col-6 col-xl-3">
        <a href="/payments" class="text-decoration-none dash-stat-link" data-page="payments">
            <div class="card dash-stat-card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="dash-stat-icon dash-stat-icon--payment">
                        <i class="bi bi-credit-card"></i>
                    </div>
                    <div>
                        <div class="dash-stat-label">Next Payment</div>
                        <div class="dash-stat-value"><?= $nextPaymentLabel ?></div>
                        <div class="dash-stat-sub"><?= $nextPaymentDate ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Office Time -->
    <div class="col-6 col-xl-3">
        <div class="card dash-stat-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="dash-stat-icon dash-stat-icon--office">
                    <i class="bi bi-clock"></i>
                </div>
                <div>
                    <div class="dash-stat-label">Office Time (ET)</div>
                    <div class="dash-stat-value" id="officeTime"><?= $officeTime ?></div>
                    <div class="dash-stat-sub" id="officeStatus"><?= $officeOpen ? '🟢 Office Open' : '🔴 Office Closed' ?></div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ════════════ TWO-COLUMN CONTENT ════════════ -->
<div class="row g-4">

    <!-- LEFT COLUMN -->
    <div class="col-lg-8">

        <!-- Services Overview -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header dash-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-briefcase me-2"></i>My Services</span>
                <a href="/services" class="btn btn-sm btn-outline-primary" data-page="services">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($services)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-briefcase fs-3 d-block mb-2 opacity-50"></i>
                        No services assigned yet.
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($services as $svc):
                            $type = $svc['service_type'];
                            $clr  = $serviceColors[$type] ?? ['bg' => '#eee', 'text' => '#333', 'border' => '#ccc', 'icon' => 'bi-briefcase'];
                            $label = $serviceLabels[$type] ?? ucfirst(str_replace('-', ' ', $type));
                        ?>
                            <div class="list-group-item dash-service-row d-flex align-items-center gap-3"
                                style="border-left: 4px solid <?= $clr['border'] ?>;">
                                <div class="dash-svc-icon" style="background: <?= $clr['bg'] ?>; color: <?= $clr['text'] ?>;">
                                    <i class="bi <?= $clr['icon'] ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold"><?= $h($label) ?></div>
                                    <small class="text-muted">Added <?= date('M j, Y', strtotime($svc['date_added'])) ?></small>
                                </div>
                                <div><?= $statusBadge($svc['current_status']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Inbox -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header dash-card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-envelope me-2"></i>Recent Inbox
                    <?php if ($unreadEmails > 0): ?>
                        <span class="badge bg-danger ms-1"><?= $unreadEmails ?></span>
                    <?php endif; ?>
                </span>
                <a href="/documents" class="btn btn-sm btn-outline-primary" data-page="documents">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentEmails)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
                        No emails yet.
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentEmails as $email):
                            $isUnread = empty($email['read_at']);
                            $eType = $email['service_type'];
                            $eClr  = $serviceColors[$eType] ?? ['bg' => '#eee', 'text' => '#333', 'border' => '#ccc'];
                        ?>
                            <a href="#" class="list-group-item list-group-item-action dash-email-row dash-email-item <?= $isUnread ? 'dash-email--unread' : '' ?>"
                                data-email-id="<?= (int) $email['id'] ?>">
                                <div class="d-flex align-items-start gap-2">
                                    <?php if ($isUnread): ?>
                                        <span class="dash-unread-dot mt-2"></span>
                                    <?php endif; ?>
                                    <div class="flex-grow-1 <?= $isUnread ? '' : 'ms-3' ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-semibold small <?= $isUnread ? '' : 'text-muted' ?>">
                                                <?= $h($email['subject'] ?: '(No subject)') ?>
                                            </span>
                                            <small class="text-muted text-nowrap ms-2">
                                                <?= date('M j', strtotime($email['sent_at'])) ?>
                                            </small>
                                        </div>
                                        <small class="text-muted">
                                            <span class="badge dash-svc-badge" style="background: <?= $eClr['bg'] ?>; color: <?= $eClr['text'] ?>;">
                                                <?= $h($email['service_label'] ?? $eType) ?>
                                            </span>
                                            <?php if (!empty($email['sent_by'])): ?>
                                                &middot; <?= $h($email['sent_by']) ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <i class="bi bi-chevron-right text-muted flex-shrink-0 small mt-2"></i>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- RIGHT COLUMN -->
    <div class="col-lg-4">

        <!-- Payment Summary -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header dash-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-wallet2 me-2"></i>Payment Summary</span>
                <a href="/payments" class="btn btn-sm btn-outline-primary" data-page="payments">View All</a>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Paid</span>
                    <span class="fw-semibold text-success">$<?= number_format((float) $paymentSummary['total_paid'], 2) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Missed</span>
                    <span class="fw-semibold text-danger">$<?= number_format((float) $paymentSummary['total_missed'], 2) ?></span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between">
                    <span class="fw-semibold">Total</span>
                    <span class="fw-bold">$<?= number_format((float) $paymentSummary['total_paid'] + (float) $paymentSummary['total_missed'], 2) ?></span>
                </div>
                <?php if ((float) $paymentSummary['total_missed'] > 0): ?>
                    <div class="alert alert-danger dash-alert mt-3 mb-0 py-2 px-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        You have <?= (int) $paymentSummary['missed_count'] ?> missed payment<?= (int) $paymentSummary['missed_count'] > 1 ? 's' : '' ?>.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CIF Details -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header dash-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-person me-2"></i>Client Info Form</span>
                <a href="/cif" class="btn btn-sm btn-outline-primary" data-page="cif">View All</a>
            </div>
            <div class="card-body">
                <?php if (!$cifStatus['started']): ?>
                    <p class="text-muted mb-3">You haven't started your CIF yet.</p>
                    <a href="/cif" class="btn btn-primary btn-sm w-100" data-page="cif">
                        <i class="bi bi-pencil-square me-1"></i>Start CIF
                    </a>
                <?php else: ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Progress</span>
                            <span class="fw-semibold"><?= $cifStatus['progress'] ?>%</span>
                        </div>
                        <div class="progress dash-progress">
                            <div class="progress-bar <?= $cifStatus['completed_at'] ? 'bg-success' : 'bg-primary' ?>"
                                role="progressbar" style="width: <?= $cifStatus['progress'] ?>%"
                                aria-valuenow="<?= $cifStatus['progress'] ?>" aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                    <?php if ($cifStatus['completed_at']): ?>
                        <small class="text-success d-block mb-1">
                            <i class="bi bi-check-circle me-1"></i>Completed <?= date('M j, Y', strtotime($cifStatus['completed_at'])) ?>
                        </small>
                    <?php endif; ?>
                    <?php if ($cifStatus['pdf_count'] > 0): ?>
                        <small class="text-muted d-block mb-2">
                            <i class="bi bi-file-earmark-pdf me-1"></i><?= $cifStatus['pdf_count'] ?> PDF<?= $cifStatus['pdf_count'] > 1 ? 's' : '' ?> generated
                        </small>
                    <?php endif; ?>
                    <?php if ($cifStatus['updated_at']): ?>
                        <small class="text-muted d-block">
                            Last updated <?= date('M j, Y \a\t g:i A', strtotime($cifStatus['updated_at'])) ?>
                        </small>
                    <?php endif; ?>
                    <a href="/cif" class="btn btn-outline-primary btn-sm w-100 mt-3" data-page="cif">
                        <i class="bi bi-pencil me-1"></i><?= $cifStatus['completed_at'] ? 'Review CIF' : 'Continue CIF' ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Document Summary -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header dash-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-folder2-open me-2"></i>Documents</span>
                <a href="/documents" class="btn btn-sm btn-outline-primary" data-page="documents">View All</a>
            </div>
            <div class="card-body">
                <?php if ((int) $docStats['total'] === 0): ?>
                    <p class="text-muted mb-0">No documents yet.</p>
                <?php else: ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Uploaded</span>
                        <span class="fw-semibold"><?= (int) $docStats['uploaded'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Pending</span>
                        <span class="fw-semibold"><?= (int) $docStats['pending'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Approved</span>
                        <span class="fw-semibold"><?= (int) $docStats['approved'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Completed (FPWS)</span>
                        <span class="fw-semibold"><?= (int) $docStats['completed'] ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>



    </div><!-- /right column -->

</div><!-- /two-column row -->

<!-- Email view modal (same API as Documents/Services) -->
<div class="modal fade" id="dashEmailViewModal" tabindex="-1" aria-labelledby="dashEmailViewLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dashEmailViewLabel">
                    <i class="bi bi-envelope-open me-2"></i><span id="dashEmailSubject">Email</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-muted small mb-3" id="dashEmailMeta"></div>
                <hr>
                <div id="dashEmailBody" class="email-body-content"></div>
            </div>
        </div>
    </div>
</div>

<!-- ════════════ OFFICE TIME LIVE CLOCK ════════════ -->
<script>
    (function() {
        document.querySelectorAll('.dash-email-item').forEach(function(el) {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                var emailId = this.getAttribute('data-email-id');
                if (!emailId) return;

                var clickedItem = this;
                var subjectEl = this.querySelector('.fw-semibold');
                var subject = subjectEl ? subjectEl.textContent.trim() : 'Email';

                var modalSubject = document.getElementById('dashEmailSubject');
                var modalMeta = document.getElementById('dashEmailMeta');
                var modalBody = document.getElementById('dashEmailBody');
                if (modalSubject) modalSubject.textContent = subject;
                if (modalMeta) modalMeta.textContent = '';
                if (modalBody)
                    modalBody.innerHTML =
                        '<div class="text-center text-muted py-4">' +
                        '<div class="spinner-border spinner-border-sm me-2"></div>Loading\u2026</div>';

                var modal = new bootstrap.Modal(document.getElementById('dashEmailViewModal'));
                modal.show();

                fetch('/documents/email?id=' + emailId)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.error) {
                            modalBody.innerHTML =
                                '<div class="alert alert-warning">' + data.error + '</div>';
                            return;
                        }
                        if (modalMeta) {
                            modalMeta.innerHTML =
                                '<i class="bi bi-calendar3 me-1"></i>' +
                                new Date(data.sent_at).toLocaleString() +
                                (data.service_label
                                    ? ' &middot; <span class="badge bg-secondary">' +
                                      data.service_label +
                                      '</span>'
                                    : '');
                        }
                        if (data.body) {
                            modalBody.innerHTML = data.body;
                        } else {
                            modalBody.innerHTML =
                                '<div class="text-muted text-center py-3">' +
                                '<i class="bi bi-info-circle me-1"></i>' +
                                'No email content available.</div>';
                        }
                        if (clickedItem.classList.contains('dash-email--unread')) {
                            clickedItem.classList.remove('dash-email--unread');
                            var dot = clickedItem.querySelector('.dash-unread-dot');
                            if (dot) dot.remove();
                        }
                    })
                    .catch(function() {
                        modalBody.innerHTML =
                            '<div class="alert alert-danger">Failed to load email content.</div>';
                    });
            });
        });
    })();

    (function() {
        var timeEl = document.getElementById('officeTime');
        var statusEl = document.getElementById('officeStatus');
        if (!timeEl || !statusEl) return;

        function updateOfficeClock() {
            var now = new Date();
            // Convert to America/Toronto (Eastern Time)
            var opts = {
                timeZone: 'America/Toronto',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            };
            var timeStr = now.toLocaleTimeString('en-US', opts);
            timeEl.textContent = timeStr;

            // Check if office open: Mon-Fri 9am-5pm ET
            var etOpts = {
                timeZone: 'America/Toronto',
                hour: 'numeric',
                hour12: false
            };
            var etHour = parseInt(now.toLocaleTimeString('en-US', etOpts), 10);
            var etDay = parseInt(now.toLocaleDateString('en-US', {
                timeZone: 'America/Toronto',
                weekday: 'narrow'
            }).length, 10);
            // Get actual weekday number
            var dayStr = now.toLocaleDateString('en-US', {
                timeZone: 'America/Toronto',
                weekday: 'long'
            });
            var weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            var dayNum = weekdays.indexOf(dayStr);
            var isOpen = dayNum >= 1 && dayNum <= 5 && etHour >= 9 && etHour < 15;
            statusEl.textContent = isOpen ? '🟢 Office Open' : '🔴 Office Closed';
        }

        setInterval(updateOfficeClock, 30000); // update every 30s
    })();
</script>
