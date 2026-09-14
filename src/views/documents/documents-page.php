<?php

use App\Support\Asset;
use App\Support\Html;

/**
 * Documents page view.
 *
 * Variables from DocumentsController:
 *  $services  — SERVICES constant (service_type => config)
 *  $uploaded  — service_type => doc_key => [row, …]
 *  $error     — string|null
 *  $saved     — bool
 *
 * Each service gets a tab.  Inside each tab:
 *   • "Completed Documents" table
 *   • "Waiting For Your Documents" table with Upload button
 */

/* Service color map — matches user-specified colors */
$serviceColors = [
    'pardon'         => ['bg' => 'beige',              'text' => '#6b5b3a',  'textInactive' => '#6b5b3a',      'border' => '#c8b67e'],
    'criminal-rehab' => ['bg' => 'rgb(163,141,229)',   'text' => '#fff',     'textInactive' => 'rgb(83,61,149)', 'border' => 'rgb(133,111,199)'],
    'trp'            => ['bg' => 'rgb(39,211,230)',     'text' => '#0c4a50',  'textInactive' => '#0c4a50',      'border' => 'rgb(29,181,200)'],
    'nexus'          => ['bg' => 'rgb(46,142,245)',     'text' => '#fff',     'textInactive' => 'rgb(16,82,175)', 'border' => 'rgb(26,112,215)'],
    'expunging'      => ['bg' => 'lightskyblue',        'text' => '#1a3e5c',  'textInactive' => '#1a3e5c',      'border' => '#6bb8e8'],
    'waiver'         => ['bg' => 'lightgreen',          'text' => '#1a5c2a',  'textInactive' => '#1a5c2a',      'border' => '#6bc86b'],
    'waiver-renewal' => ['bg' => '#1a5c2a',             'text' => '#fff',     'textInactive' => '#1a5c2a',      'border' => '#0f3d1c'],
];

$csrfToken = Html::e($_SESSION['csrf_token'] ?? '');
$firstKey  = array_key_first($services);
?>

<link rel="stylesheet" href="<?= Asset::url('/css/documents.css') ?>">

<!-- ── Flash messages ─────────────────────────────────────── -->
<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= Html::e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($saved): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-check-circle-fill"></i>
        Operation completed successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════════
     SERVICE TABS  — colour-coded pills
     ════════════════════════════════════════════════════════════ -->
<ul class="nav nav-pills doc-service-tabs flex-wrap gap-2 mb-4" id="docServiceTabs" role="tablist">
    <?php $idx = 0;
    foreach ($services as $svcKey => $svc):
        $c = $serviceColors[$svcKey] ?? ['bg' => '#ddd', 'text' => '#333', 'border' => '#bbb'];
        $isActive = ($idx === 0);
    ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link doc-tab-pill <?= $isActive ? 'active' : '' ?>"
                id="tab-<?= Html::e($svcKey) ?>"
                data-bs-toggle="pill"
                data-bs-target="#pane-<?= Html::e($svcKey) ?>"
                type="button" role="tab"
                aria-controls="pane-<?= Html::e($svcKey) ?>"
                aria-selected="<?= $isActive ? 'true' : 'false' ?>"
                style="--doc-tab-bg: <?= $c['bg'] ?>; --doc-tab-text: <?= $c['text'] ?>; --doc-tab-text-inactive: <?= $c['textInactive'] ?>; --doc-tab-border: <?= $c['border'] ?>;">
                <i class="bi <?= Html::e($svc['icon']) ?> me-1"></i><?= Html::e($svc['label']) ?>
            </button>
        </li>
    <?php $idx++;
    endforeach; ?>
</ul>

<!-- ════════════════════════════════════════════════════════════
     TAB PANES
     ════════════════════════════════════════════════════════════ -->
<div class="tab-content" id="docTabContent">
    <?php $idx = 0;
    foreach ($services as $svcKey => $svc):
        $c = $serviceColors[$svcKey] ?? ['bg' => '#ddd', 'text' => '#333', 'border' => '#bbb'];
        $isActive  = ($idx === 0);
        $svcDocs   = $uploaded[$svcKey] ?? [];

        /* Split docs into completed vs waiting */
        $completedDocs = [];
        $waitingDocs   = [];

        foreach ($svc['docs'] as $docDef) {
            $key = $docDef['key'];
            if (!empty($svcDocs[$key])) {
                $completedDocs[] = ['def' => $docDef, 'files' => $svcDocs[$key]];
            } else {
                $waitingDocs[] = $docDef;
            }
        }

        $allComplete = empty($waitingDocs);
    ?>
        <div class="tab-pane fade <?= $isActive ? 'show active' : '' ?>"
            id="pane-<?= Html::e($svcKey) ?>" role="tabpanel" aria-labelledby="tab-<?= Html::e($svcKey) ?>">

            <!-- ── Status banner ── -->
            <?php if ($allComplete): ?>
                <div class="alert doc-status-banner d-flex align-items-center gap-2 mb-4"
                    style="background: <?= $c['bg'] ?>; color: <?= $c['text'] ?>; border: 1px solid <?= $c['border'] ?>;">
                    <i class="bi bi-check-circle-fill fs-4"></i>
                    <div>
                        <strong>All documents have been received</strong><br>
                        <span class="small opacity-75">Thank you for submitting all required documents for <?= Html::e($svc['label']) ?>.</span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ── Documents: Completed + Waiting side by side ── -->
            <div class="row g-4">

                <!-- ── Completed Documents ─────────────────────── -->
                <div class="<?= !empty($waitingDocs) ? 'col-lg-6' : 'col-12' ?>">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
                            <span class="doc-section-dot" style="background: <?= $c['bg'] ?>; border-color: <?= $c['border'] ?>;"></span>
                            Completed Documents
                            <?php $totalFiles = 0;
                            foreach ($completedDocs as $cd) $totalFiles += count($cd['files']); ?>
                            <span class="badge bg-success ms-auto"><?= $totalFiles ?></span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($completedDocs)): ?>
                                <div class="text-muted text-center py-4">
                                    <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                    No documents uploaded yet for <?= Html::e($svc['label']) ?>.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Document</th>
                                                <th>File</th>
                                                <th>Size</th>
                                                <th>Uploaded</th>
                                                <th>Status</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($completedDocs as $cd):
                                                foreach ($cd['files'] as $fileIdx => $fileRow):
                                                    $canDelete = (time() - strtotime($fileRow['uploaded_at'])) <= 600;
                                                    switch ($fileRow['status']) {
                                                        case 'approved':
                                                            $statusBadge = '<span class="badge bg-success">Approved</span>';
                                                            break;
                                                        case 'completed_fpws':
                                                            $statusBadge = '<span class="badge" style="background-color: #279b34ff; color: #ffffffff;">Completed by FPWS</span>';
                                                            $canDelete = false; // company docs can't be deleted by client
                                                            break;
                                                        case 'uploaded':
                                                            $statusBadge = '<span class="badge bg-primary">Uploaded</span>';
                                                            break;
                                                        default:
                                                            $statusBadge = '<span class="badge bg-warning text-dark">Pending</span>';
                                                            break;
                                                    }
                                            ?>
                                                    <tr>
                                                        <td>
                                                            <?php if ($fileIdx === 0): ?>
                                                                <div class="fw-semibold"><?= Html::e($cd['def']['title']) ?></div>
                                                            <?php else: ?>
                                                                <div class="text-muted small">&nbsp;</div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <a href="/documents/download?id=<?= (int)$fileRow['id'] ?>" class="text-decoration-none">
                                                                <i class="bi bi-file-earmark me-1"></i><?= Html::e($fileRow['file_name']) ?>
                                                            </a>
                                                        </td>
                                                        <td class="text-muted small"><?= formatFileSize((int)$fileRow['file_size']) ?></td>
                                                        <td class="text-muted small"><?= date('M j, Y g:ia', strtotime($fileRow['uploaded_at'])) ?></td>
                                                        <td><?= $statusBadge ?></td>
                                                        <td class="text-end">
                                                            <?php $isCompanyDoc = ($fileRow['status'] === 'completed_fpws'); ?>
                                                            <?php if (!$isCompanyDoc): ?>
                                                                <?php if ($fileIdx === 0): ?>
                                                                    <button type="button"
                                                                        class="btn btn-sm btn-outline-primary doc-upload-btn me-1"
                                                                        data-service="<?= Html::e($svcKey) ?>"
                                                                        data-doc-key="<?= Html::e($cd['def']['key']) ?>"
                                                                        data-doc-title="<?= Html::e($cd['def']['title']) ?>"
                                                                        title="Upload additional file">
                                                                        <i class="bi bi-cloud-arrow-up"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                            <?php if ($canDelete): ?>
                                                                <form method="POST" action="/documents" class="d-inline doc-delete-form">
                                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                                    <input type="hidden" name="action" value="delete">
                                                                    <input type="hidden" name="doc_id" value="<?= (int)$fileRow['id'] ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove (available for 10 min)">
                                                                        <i class="bi bi-trash3"></i>
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <?php if ($isCompanyDoc): ?>
                                                                    <span class="text-muted small" title="Managed by FPWS"><i class="bi bi-lock"></i></span>
                                                                <?php else: ?>
                                                                    <span class="text-muted small" title="Delete window expired"><i class="bi bi-lock"></i></span>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                            <?php endforeach; // files loop
                                            endforeach; // completedDocs loop
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div><!-- /col completed -->

                <!-- ── Waiting For Your Documents ──────────────── -->
                <?php if (!empty($waitingDocs)): ?>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
                                <span class="doc-section-dot" style="background: <?= $c['bg'] ?>; border-color: <?= $c['border'] ?>;"></span>
                                Waiting For Your Documents
                                <span class="badge bg-warning text-dark ms-auto"><?= count($waitingDocs) ?></span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Document Required</th>
                                                <th class="text-end">Upload</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($waitingDocs as $wd): ?>
                                                <tr>
                                                    <td>
                                                        <i class="bi bi-file-earmark-arrow-up text-warning me-1"></i>
                                                        <span class="fw-semibold"><?= Html::e($wd['title']) ?></span>
                                                    </td>
                                                    <td class="text-end">
                                                        <button type="button"
                                                            class="btn btn-sm doc-upload-btn"
                                                            style="background: <?= $c['bg'] ?>; color: <?= $c['text'] ?>; border-color: <?= $c['border'] ?>;"
                                                            data-service="<?= Html::e($svcKey) ?>"
                                                            data-doc-key="<?= Html::e($wd['key']) ?>"
                                                            data-doc-title="<?= Html::e($wd['title']) ?>">
                                                            <i class="bi bi-cloud-arrow-up me-1"></i>Upload
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div><!-- /col waiting -->
                <?php endif; ?>

            </div><!-- /row side-by-side -->

        </div><!-- /tab-pane -->
    <?php $idx++;
    endforeach; ?>
</div>

<!-- ════════════════════════════════════════════════════════════
     INBOX — Service Emails (filtered by active tab)
     ════════════════════════════════════════════════════════════ -->
<?php $totalInbox = 0;
foreach (($inboxEmails ?? []) as $_ => $arr) $totalInbox += count($arr); ?>
<div class="card border-0 shadow-sm mt-4" id="inboxCard">
    <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
        <i class="bi bi-inbox me-1"></i>Inbox
        <span class="inbox-service-label" id="inboxServiceLabel"></span>
        <span class="badge bg-secondary ms-auto" id="inboxBadge"><?= $totalInbox ?></span>
    </div>
    <div class="card-body p-0">
        <?php if ($totalInbox === 0): ?>
            <div class="text-muted text-center py-4">
                <i class="bi bi-envelope-open fs-3 d-block mb-1"></i>
                No messages in your inbox.
            </div>
        <?php else: ?>
            <?php foreach (($inboxEmails ?? []) as $svcType => $svcEmails):
                $sc = $serviceColors[$svcType] ?? ['bg' => '#ddd', 'text' => '#333', 'border' => '#bbb'];
                $svcLabel = $services[$svcType]['label'] ?? ucfirst($svcType);
            ?>
                <div class="inbox-service-group" data-inbox-service="<?= Html::e($svcType) ?>"
                    data-inbox-label="<?= Html::e($svcLabel) ?>"
                    data-inbox-bg="<?= $sc['bg'] ?>" data-inbox-text="<?= $sc['text'] ?>">
                    <div class="list-group list-group-flush">
                        <?php foreach ($svcEmails as $em):
                            $isRead = !empty($em['read_at']);
                            $readDate = $isRead ? date('M j, Y g:ia', strtotime($em['read_at'])) : '';
                        ?>
                            <a href="#" class="list-group-item list-group-item-action py-2 px-3 inbox-email-item <?= $isRead ? 'inbox-read' : 'inbox-unread' ?>"
                                data-email-id="<?= (int)$em['id'] ?>">
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!$isRead): ?>
                                        <span class="inbox-unread-dot flex-shrink-0"></span>
                                    <?php else: ?>
                                        <i class="bi bi-envelope-open text-muted flex-shrink-0" style="font-size:0.85rem;"></i>
                                    <?php endif; ?>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="<?= $isRead ? 'small text-truncate' : 'fw-semibold small text-truncate' ?>"><?= Html::e($em['subject']) ?></div>
                                        <div class="text-muted small">
                                            <?= date('M j, Y g:ia', strtotime($em['sent_at'])) ?>
                                            <?php if ($isRead): ?>
                                                <span class="ms-2 text-success" style="font-size:0.72rem;">
                                                    <i class="bi bi-check2-all me-1"></i>Read <?= $readDate ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <i class="bi bi-chevron-right text-muted flex-shrink-0 small"></i>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     EMAIL VIEW MODAL
     ════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="emailViewModal" tabindex="-1" aria-labelledby="emailViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="emailViewModalLabel">
                    <i class="bi bi-envelope-open me-2"></i><span id="emailViewSubject">Email</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-muted small mb-3" id="emailViewMeta"></div>
                <hr>
                <div id="emailViewBody" class="email-body-content">
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

<!-- ════════════════════════════════════════════════════════════
     UPLOAD MODAL
     ════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="uploadDocModal" tabindex="-1" aria-labelledby="uploadDocModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="/documents" enctype="multipart/form-data" id="uploadDocForm">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="service_type" id="uploadServiceType">
                <input type="hidden" name="doc_key" id="uploadDocKey">

                <div class="modal-header">
                    <h5 class="modal-title" id="uploadDocModalLabel">
                        <i class="bi bi-cloud-arrow-up me-2"></i>Upload Document
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Document Type:</label>
                        <div class="form-control-plaintext fw-semibold" id="uploadDocTitle">—</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Choose file:</label>
                        <div class="doc-drop-zone" id="docDropZone">
                            <input type="file" class="doc-drop-input" id="docFileInput" name="doc_file"
                                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.txt" required>
                            <div class="doc-drop-content" id="docDropContent">
                                <i class="bi bi-cloud-arrow-up fs-1 text-primary mb-2"></i>
                                <div class="fw-semibold">Drag &amp; drop your file here</div>
                                <div class="text-muted small mt-1">or <span class="text-primary fw-semibold doc-drop-browse">browse</span> to choose</div>
                            </div>
                            <div class="doc-drop-preview d-none" id="docDropPreview">
                                <i class="bi bi-file-earmark-check fs-2 text-success me-2"></i>
                                <div>
                                    <div class="fw-semibold small" id="previewFileName">—</div>
                                    <div class="text-muted small" id="previewFileSize">—</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="docDropClear" title="Remove file">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                        <div class="doc-upload-error d-none" id="docUploadError">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <span id="docUploadErrorText"></span>
                        </div>
                        <div class="form-text mt-2">
                            Accepted: PDF, JPG, PNG, DOC, DOCX, TXT &middot; Max 25 MB
                        </div>
                    </div>
                    <div class="mb-3 d-none" id="uploadFileNameGroup">
                        <label class="form-label fw-semibold" for="uploadFileName">File name:</label>
                        <input type="text" class="form-control" name="file_name" id="uploadFileName"
                            maxlength="200" autocomplete="off"
                            placeholder="Name used in the portal and on download">
                        <div class="form-text">You can change the name before uploading. Extension is kept from the selected file.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="uploadSubmitBtn">
                        <i class="bi bi-cloud-arrow-up me-1"></i>Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
/* Helper: format bytes to human-readable */
function formatFileSize(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}
?>

<script src="<?= Asset::url('/js/documents.js') ?>"></script>
