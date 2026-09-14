/* Profile contacts column bindings */
/* ── Bind contact-column events (called after each refresh) ────── */
function bindContactEvents() {
    /* ── Phone inactive toggle forms ── */
    document
        .querySelectorAll('#contactsColumn .ajax-toggle-form')
        .forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var data = new FormData(form);
                fetch('/profile', { method: 'POST', body: data })
                    .then(function (r) {
                        return r.text();
                    })
                    .then(function () {
                        refreshContactsColumn();
                    })
                    .catch(function () {});
            });
        });

    /* ── Email delete forms (still use confirmation) ── */
    document
        .querySelectorAll('#contactsColumn .ajax-delete-form')
        .forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var label = 'email address';

                var titleEl = document.getElementById('deleteConfirmTitle');
                var bodyEl = document.getElementById('deleteConfirmBody');
                var okBtn = document.getElementById('deleteConfirmOk');
                if (titleEl) titleEl.textContent = 'Remove ' + label;
                if (bodyEl)
                    bodyEl.textContent =
                        'This ' +
                        label +
                        ' will be permanently removed. Continue?';

                var freshBtn = okBtn.cloneNode(true);
                okBtn.parentNode.replaceChild(freshBtn, okBtn);
                freshBtn.addEventListener('click', function () {
                    var modal = bootstrap.Modal.getInstance(
                        document.getElementById('deleteConfirmModal')
                    );
                    if (modal) modal.hide();
                    var data = new FormData(form);
                    fetch('/profile', { method: 'POST', body: data })
                        .then(function (r) {
                            return r.text();
                        })
                        .then(function () {
                            refreshContactsColumn();
                        })
                        .catch(function () {});
                });

                var bsModal = new bootstrap.Modal(
                    document.getElementById('deleteConfirmModal')
                );
                bsModal.show();
            });
        });
}

