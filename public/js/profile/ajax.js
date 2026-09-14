/* Profile AJAX helpers */
/* ── AJAX helpers ──────────────────────────────────────────────── */

/**
 * Re-fetches /profile HTML and refreshes only the #contactsColumn.
 */
function refreshContactsColumn() {
    fetch('/profile', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) {
            return r.text();
        })
        .then(function (html) {
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');
            var newCol = doc.getElementById('contactsColumn');
            var curCol = document.getElementById('contactsColumn');
            if (newCol && curCol) {
                curCol.innerHTML = newCol.innerHTML;
                bindContactEvents();
            }
        })
        .catch(function () {});
}

/**
 * Shows a temporary alert inside a modal's body.
 */
function showModalMsg(modalId, type, msg) {
    var modal = document.getElementById(modalId);
    if (!modal) return;
    var body = modal.querySelector('.modal-body');
    if (!body) return;
    var prior = body.querySelector('.ajax-modal-alert');
    if (prior) prior.remove();
    var div = document.createElement('div');
    div.className =
        'alert alert-' +
        type +
        ' alert-dismissible fade show ajax-modal-alert mb-3';
    div.innerHTML =
        msg +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    body.prepend(div);
}

/**
 * Submits a form via fetch, refreshes contacts column on success.
 */
function ajaxSubmitForm(form, modalId, successMsg) {
    var data = new FormData(form);

    /* Snapshot submitted values BEFORE any reset, so we can
       update address modal fields after a successful save.       */
    var submitted = {};
    data.forEach(function (val, key) {
        submitted[key] = val;
    });

    var btn = form.querySelector('[type="submit"]');
    if (btn) {
        btn.disabled = true;
        btn.classList.add('disabled');
    }

    fetch('/profile', { method: 'POST', body: data })
        .then(function (r) {
            return r.text();
        })
        .then(function (html) {
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');
            var errAlert = doc.querySelector('.alert-danger');
            if (errAlert) {
                showModalMsg(modalId, 'danger', errAlert.textContent.trim());
                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('disabled');
                }
            } else {
                var bsModal = bootstrap.Modal.getInstance(
                    document.getElementById(modalId)
                );
                if (bsModal) bsModal.hide();

                /* ── Address forms: DON'T reset — instead update the
                   form fields with the just-saved values so the modal
                   shows fresh data when reopened.                     */
                var action = submitted['action'] || '';
                if (action === 'address_living' || action === 'address_mail') {
                    var addrType =
                        action === 'address_living' ? 'living' : 'mail';
                    /* Update every named input/select/textarea */
                    [
                        'country',
                        'country_other',
                        'province_state',
                        'city',
                        'postal_code',
                        'street',
                        'unit',
                        'move_in_date_hidden',
                    ].forEach(function (field) {
                        var val =
                            submitted[field] !== undefined
                                ? submitted[field]
                                : '';
                        /* country and province_state are <select> elements with ids */
                        var elById = document.getElementById(
                            field === 'country'
                                ? 'country_' + addrType
                                : field === 'province_state'
                                  ? 'province_' + addrType
                                  : field === 'city'
                                    ? 'city_' + addrType
                                    : field === 'postal_code'
                                      ? 'postal_' + addrType
                                      : null
                        );
                        if (elById) {
                            elById.value = val;
                        } else {
                            /* street and unit have no id — find by name inside form */
                            var el = form.querySelector(
                                '[name="' + field + '"]'
                            );
                            if (el) el.value = val;
                        }
                    });
                    /* Rebuild province list to match the saved country */
                    updateProvinceSelect(addrType);
                    /* Re-apply saved province after list rebuild */
                    var provSel = document.getElementById(
                        'province_' + addrType
                    );
                    if (provSel && submitted['province_state']) {
                        provSel.value = submitted['province_state'];
                    }
                } else {
                    /* Non-address forms (phone, email): safe to reset */
                    form.reset();
                    var extWrap = document.getElementById('extFieldWrap');
                    if (extWrap) extWrap.style.display = 'none';
                    var labelWrap = document.getElementById('phoneLabelWrap');
                    if (labelWrap) labelWrap.style.display = 'none';
                }

                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('disabled');
                }
                refreshContactsColumn();
            }
        })
        .catch(function () {
            showModalMsg(
                modalId,
                'danger',
                'A network error occurred. Please try again.'
            );
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('disabled');
            }
        });
    return false;
}

