/* ═══════════════════════════════════════════════════════════
   SERVICES PAGE — popovers, email modal viewer
   File: public/js/services.js

   Features:
     - Bootstrap popover init (SPA-safe: disposes before re-init)
     - Email link click → fetches body via AJAX → shows in modal

   Loaded by: src/views/services/services-page.php
   ═══════════════════════════════════════════════════════════ */
(function () {
    // Dispose any existing popovers first (SPA re-navigation)
    document
        .querySelectorAll('[data-bs-toggle="popover"]')
        .forEach(function (el) {
            var existing = bootstrap.Popover.getInstance(el);
            if (existing) existing.dispose();
        });

    // Initialize popovers
    document
        .querySelectorAll('[data-bs-toggle="popover"]')
        .forEach(function (el) {
            new bootstrap.Popover(el, {
                html: false,
                sanitize: true,
            });
        });

    /* ── Email click → open modal ─────────────────────────── */
    document.querySelectorAll('.svc-email-link').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            var emailId = this.getAttribute('data-email-id');
            if (!emailId) return;

            var subjectSpan = this.querySelector('.svc-email-subject');
            var dateSpan = this.querySelector('.svc-email-date');
            var subject = subjectSpan
                ? subjectSpan.textContent.trim()
                : 'Email';
            var dateTxt = dateSpan ? dateSpan.textContent.trim() : '';

            var modalSubject = document.getElementById('svcEmailSubject');
            var modalMeta = document.getElementById('svcEmailMeta');
            var modalBody = document.getElementById('svcEmailBody');

            if (modalSubject) modalSubject.textContent = subject;
            if (modalMeta) modalMeta.textContent = dateTxt;
            if (modalBody)
                modalBody.innerHTML =
                    '<div class="text-center text-muted py-4">' +
                    '<div class="spinner-border spinner-border-sm me-2"></div>Loading\u2026</div>';

            var modal = new bootstrap.Modal(
                document.getElementById('svcEmailViewModal')
            );
            modal.show();

            fetch('/documents/email?id=' + emailId)
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    if (data.ok === false || data.error) {
                        modalBody.textContent = '';
                        var warn = document.createElement('div');
                        warn.className = 'alert alert-warning';
                        warn.textContent = data.error || 'Unable to load email.';
                        modalBody.appendChild(warn);
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
                })
                .catch(function () {
                    modalBody.innerHTML =
                        '<div class="alert alert-danger">Failed to load email content.</div>';
                });
        });
    });
})();
