/* ═══════════════════════════════════════════════════════════════
   DOCUMENTS PAGE — upload modal with drag & drop, inline errors
   ═══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var MAX_SIZE = 25 * 1024 * 1024; // 25 MB

    var ALLOWED_EXT = [
        '.pdf',
        '.jpg',
        '.jpeg',
        '.png',
        '.doc',
        '.docx',
        '.txt',
    ];

    /* ── Cache DOM elements ────────────────────────────────── */
    var fileInput = document.getElementById('docFileInput');
    var dropZone = document.getElementById('docDropZone');
    var dropContent = document.getElementById('docDropContent');
    var dropPreview = document.getElementById('docDropPreview');
    var previewName = document.getElementById('previewFileName');
    var previewSize = document.getElementById('previewFileSize');
    var clearBtn = document.getElementById('docDropClear');
    var errorBox = document.getElementById('docUploadError');
    var errorText = document.getElementById('docUploadErrorText');
    var submitBtn = document.getElementById('uploadSubmitBtn');
    var uploadForm = document.getElementById('uploadDocForm');
    var fileNameGroup = document.getElementById('uploadFileNameGroup');
    var fileNameInput = document.getElementById('uploadFileName');

    /* ── Upload button → open modal ────────────────────────── */
    document.querySelectorAll('.doc-upload-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('uploadServiceType').value =
                this.dataset.service;
            document.getElementById('uploadDocKey').value = this.dataset.docKey;
            document.getElementById('uploadDocTitle').textContent =
                this.dataset.docTitle;

            resetUpload();

            var modal = new bootstrap.Modal(
                document.getElementById('uploadDocModal')
            );
            modal.show();
        });
    });

    /* ── File input change ─────────────────────────────────── */
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                handleFile(this.files[0]);
            } else {
                resetUpload();
            }
        });
    }

    /* ── Drag & drop events ────────────────────────────────── */
    if (dropZone) {
        ['dragenter', 'dragover'].forEach(function (evt) {
            dropZone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.add('drag-over');
            });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            dropZone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.remove('drag-over');
            });
        });
        dropZone.addEventListener('drop', function (e) {
            var dt = e.dataTransfer;
            if (dt && dt.files && dt.files.length > 0) {
                fileInput.files = dt.files; // assign to input so form submits it
                handleFile(dt.files[0]);
            }
        });
    }

    /* ── Clear button ──────────────────────────────────────── */
    if (clearBtn) {
        clearBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            resetUpload();
        });
    }

    /* ── Form submit — show spinner ────────────────────────── */
    if (uploadForm) {
        uploadForm.addEventListener('submit', function (e) {
            /* Final check before submit */
            if (fileInput.files && fileInput.files[0]) {
                var err = validateFile(fileInput.files[0]);
                if (err) {
                    e.preventDefault();
                    showError(err);
                    return;
                }
            }
            if (fileNameInput) {
                var customName = fileNameInput.value.replace(/[\\/]/g, '').trim();
                fileNameInput.value = customName;
            }
            submitBtn.disabled = true;
            submitBtn.innerHTML =
                '<span class="spinner-border spinner-border-sm me-1"></span>Uploading…';
        });
    }

    /* ── Delete forms — confirm ────────────────────────────── */
    document.querySelectorAll('.doc-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm('Are you sure you want to delete this document?')) {
                e.preventDefault();
            }
        });
    });

    /* ── Helpers ───────────────────────────────────────────── */

    function handleFile(file) {
        hideError();
        var err = validateFile(file);
        if (err) {
            showError(err);
            fileInput.value = '';
            showDropContent();
            return;
        }
        previewName.textContent = file.name;
        previewSize.textContent = formatBytes(file.size);
        dropContent.classList.add('d-none');
        dropPreview.classList.remove('d-none');
        if (fileNameGroup && fileNameInput) {
            fileNameGroup.classList.remove('d-none');
            fileNameInput.value = file.name;
        }
        submitBtn.disabled = false;
    }

    function validateFile(file) {
        var ext = '.' + file.name.split('.').pop().toLowerCase();
        if (ALLOWED_EXT.indexOf(ext) === -1) {
            return (
                'File type "' +
                ext +
                '" is not allowed. Please choose a supported format.'
            );
        }
        if (file.size > MAX_SIZE) {
            return (
                'File exceeds the 25 MB limit (' +
                formatBytes(file.size) +
                '). Please choose a smaller file.'
            );
        }
        return null;
    }

    function showError(msg) {
        errorText.textContent = msg;
        errorBox.classList.remove('d-none');
    }

    function hideError() {
        errorBox.classList.add('d-none');
        errorText.textContent = '';
    }

    function showDropContent() {
        dropContent.classList.remove('d-none');
        dropPreview.classList.add('d-none');
    }

    function resetUpload() {
        fileInput.value = '';
        showDropContent();
        hideError();
        if (fileNameGroup && fileNameInput) {
            fileNameGroup.classList.add('d-none');
            fileNameInput.value = '';
        }
        submitBtn.disabled = false;
    }

    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    /* ── Inbox filtering by active service tab ─────────────── */
    function filterInbox(activeServiceKey) {
        var groups = document.querySelectorAll('.inbox-service-group');
        var visibleCount = 0;
        var activeLabelText = '';
        var activeBg = '';
        var activeTextColor = '';
        groups.forEach(function (g) {
            var svc = g.getAttribute('data-inbox-service');
            if (svc === activeServiceKey) {
                g.style.display = '';
                visibleCount += g.querySelectorAll('.list-group-item').length;
                activeLabelText = g.getAttribute('data-inbox-label') || '';
                activeBg = g.getAttribute('data-inbox-bg') || '#ddd';
                activeTextColor = g.getAttribute('data-inbox-text') || '#333';
            } else {
                g.style.display = 'none';
            }
        });

        /* Update inbox badge count */
        var badge = document.getElementById('inboxBadge');
        if (badge) badge.textContent = visibleCount;

        /* Update service label next to "Inbox" */
        var svcLabel = document.getElementById('inboxServiceLabel');
        if (svcLabel) {
            if (activeLabelText) {
                svcLabel.innerHTML =
                    '<span class="badge ms-1" style="background:' +
                    activeBg +
                    ';color:' +
                    activeTextColor +
                    ';">' +
                    activeLabelText +
                    '</span>';
            } else {
                svcLabel.innerHTML = '';
            }
        }

        /* Show/hide empty state */
        var inboxCard = document.getElementById('inboxCard');
        if (!inboxCard) return;
        if (visibleCount === 0) {
            var body = inboxCard.querySelector('.card-body');
            if (body) {
                var existingTmp = body.querySelector('.inbox-empty-filtered');
                if (!existingTmp) {
                    var tmpEmpty = document.createElement('div');
                    tmpEmpty.className =
                        'text-muted text-center py-4 inbox-empty-filtered';
                    tmpEmpty.innerHTML =
                        '<i class="bi bi-envelope-open fs-3 d-block mb-1"></i>No messages for this service.';
                    body.appendChild(tmpEmpty);
                }
            }
        } else {
            var existingTmp = inboxCard.querySelector('.inbox-empty-filtered');
            if (existingTmp) existingTmp.remove();
        }
    }

    /* Listen for tab changes */
    var tabButtons = document.querySelectorAll('#docServiceTabs .nav-link');
    tabButtons.forEach(function (btn) {
        btn.addEventListener('shown.bs.tab', function () {
            /* Extract service key from id="tab-pardon" → "pardon" */
            var svcKey = this.id.replace(/^tab-/, '');
            filterInbox(svcKey);
        });
    });

    /* Apply initial filter based on the first active tab */
    var activeTab = document.querySelector('#docServiceTabs .nav-link.active');
    if (activeTab) {
        var initialKey = activeTab.id.replace(/^tab-/, '');
        filterInbox(initialKey);
    }

    /* ── Inbox email click → open modal with body ─────────── */
    document.querySelectorAll('.inbox-email-item').forEach(function (item) {
        item.addEventListener('click', function (e) {
            e.preventDefault();
            var emailId = this.getAttribute('data-email-id');
            if (!emailId) return;

            var clickedItem = this;
            var subjectEl = this.querySelector('.fw-semibold, .text-truncate');
            var dateEl = this.querySelector('.text-muted.small');
            var subject = subjectEl ? subjectEl.textContent.trim() : 'Email';
            var dateTxt = dateEl ? dateEl.textContent.trim() : '';

            /* Set modal header */
            var modalSubject = document.getElementById('emailViewSubject');
            var modalMeta = document.getElementById('emailViewMeta');
            var modalBody = document.getElementById('emailViewBody');
            if (modalSubject) modalSubject.textContent = subject;
            if (modalMeta) modalMeta.textContent = dateTxt;
            if (modalBody)
                modalBody.innerHTML =
                    '<div class="text-center text-muted py-4">' +
                    '<div class="spinner-border spinner-border-sm me-2"></div>Loading\u2026</div>';

            var modal = new bootstrap.Modal(
                document.getElementById('emailViewModal')
            );
            modal.show();

            /* Fetch email body */
            fetch('/documents/email?id=' + emailId)
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    if (data.ok === false || data.error) {
                        modalBody.innerHTML =
                            '<div class="alert alert-warning">' +
                            (data.error || 'Unable to load email.') +
                            '</div>';
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
                            'No email content available. Subject: <strong>' +
                            subject +
                            '</strong></div>';
                    }

                    /* Mark as read in UI */
                    if (clickedItem.classList.contains('inbox-unread')) {
                        clickedItem.classList.remove('inbox-unread');
                        clickedItem.classList.add('inbox-read');

                        /* Replace unread dot with open envelope icon */
                        var dot =
                            clickedItem.querySelector('.inbox-unread-dot');
                        if (dot) {
                            var icon = document.createElement('i');
                            icon.className =
                                'bi bi-envelope-open text-muted flex-shrink-0';
                            icon.style.fontSize = '0.85rem';
                            dot.replaceWith(icon);
                        }

                        /* Remove bold from subject */
                        var subjEl = clickedItem.querySelector('.fw-semibold');
                        if (subjEl) subjEl.classList.remove('fw-semibold');

                        /* Add read timestamp */
                        var readAt = data.read_at || new Date().toISOString();
                        var readDate = new Date(readAt).toLocaleDateString(
                            'en-US',
                            {
                                month: 'short',
                                day: 'numeric',
                                year: 'numeric',
                                hour: 'numeric',
                                minute: '2-digit',
                            }
                        );
                        var dateLine =
                            clickedItem.querySelector('.text-muted.small');
                        if (dateLine) {
                            var readSpan = document.createElement('span');
                            readSpan.className = 'ms-2 text-success';
                            readSpan.style.fontSize = '0.72rem';
                            readSpan.innerHTML =
                                '<i class="bi bi-check2-all me-1"></i>Read ' +
                                readDate;
                            dateLine.appendChild(readSpan);
                        }
                    }
                })
                .catch(function () {
                    modalBody.innerHTML =
                        '<div class="alert alert-danger">Failed to load email content.</div>';
                });
        });
    });

    /* ── Deep-link highlight from CIF (?service=&doc=) ───────── */
    (function highlightDocFromQuery() {
        var params = new URLSearchParams(window.location.search);
        var service = params.get('service');
        var doc = params.get('doc');
        if (!service || !doc) return;

        function findSlot() {
            var pane = document.getElementById('pane-' + service);
            if (!pane) return null;
            var nodes = pane.querySelectorAll('[data-doc-key]');
            for (var i = 0; i < nodes.length; i++) {
                if (nodes[i].getAttribute('data-doc-key') === doc) {
                    return nodes[i].closest('tr') || nodes[i];
                }
            }
            return null;
        }

        function doHighlight() {
            var row = findSlot();
            if (!row) return;
            row.classList.add('doc-slot-highlight');
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(function () {
                row.classList.remove('doc-slot-highlight');
            }, 3500);
        }

        var tabBtn = document.getElementById('tab-' + service);
        if (tabBtn && typeof bootstrap !== 'undefined') {
            if (!tabBtn.classList.contains('active')) {
                tabBtn.addEventListener(
                    'shown.bs.tab',
                    function onShown() {
                        tabBtn.removeEventListener('shown.bs.tab', onShown);
                        doHighlight();
                    }
                );
                bootstrap.Tab.getOrCreateInstance(tabBtn).show();
            } else {
                doHighlight();
            }
        } else {
            doHighlight();
        }
    })();
})();
