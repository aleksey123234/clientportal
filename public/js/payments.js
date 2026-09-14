/* ═══════════════════════════════════════════════════════════════
   PAYMENTS PAGE — client-side interactions
   File: public/js/payments.js

   Features:
     - CC change modal: flatpickr date picker, 4-day proximity warning
     - Card number formatting
     - Payment date change request (stub — no backend yet)
     - Moneris Pay Now modal: card form, AJAX submit, success/error UI

   Loaded by: src/views/payments/payments-page.php
   ═══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    /* ── Flatpickr on CC start-date input ──────────────────────── */
    var ccInput = document.getElementById('ccChangeDateInput');
    var ccHidden = document.getElementById('ccChangeDateHidden');
    var ccWarning = document.getElementById('ccChangeDateWarning');

    if (ccInput && typeof flatpickr !== 'undefined') {
        var ccPicker = flatpickr(ccInput, {
            dateFormat: 'd/m/Y',
            altInput: false,
            allowInput: false,
            disableMobile: true,
            minDate: 'today',
            monthSelectorType: 'dropdown',
            onChange: function (selectedDates) {
                if (!selectedDates.length) return;
                var d = selectedDates[0];
                var ymd =
                    d.getFullYear() +
                    '-' +
                    String(d.getMonth() + 1).padStart(2, '0') +
                    '-' +
                    String(d.getDate()).padStart(2, '0');
                if (ccHidden) ccHidden.value = ymd;

                /* 4-day proximity warning */
                if (ccWarning) {
                    var nextPayment = ccInput.dataset.nextPayment;
                    if (nextPayment) {
                        var chosenMs = d.getTime();
                        var nextMs = new Date(nextPayment).getTime();
                        var diffDays = Math.round(
                            (nextMs - chosenMs) / 86400000
                        );
                        ccWarning.classList.toggle(
                            'd-none',
                            !(diffDays >= 0 && diffDays <= 4)
                        );
                    } else {
                        ccWarning.classList.add('d-none');
                    }
                }
            },
        });
    }

    /* ── Reset CC change modal on close ────────────────────────── */
    var modal = document.getElementById('changeCcModal');
    if (modal) {
        modal.addEventListener('hidden.bs.modal', function () {
            var form = document.getElementById('changeCcForm');
            if (form) form.reset();
            /* Clear flatpickr */
            var dateEl = document.getElementById('ccChangeDateInput');
            if (dateEl && dateEl._flatpickr) dateEl._flatpickr.clear();
            var hiddenDate = document.getElementById('ccChangeDateHidden');
            if (hiddenDate) hiddenDate.value = '';
            var warn = document.getElementById('ccChangeDateWarning');
            if (warn) warn.classList.add('d-none');
            var succ = document.getElementById('ccChangeSuccess');
            if (succ) succ.classList.add('d-none');
            var btn = document.getElementById('ccChangeSubmitBtn');
            if (btn) btn.disabled = false;
            form.classList.remove('was-validated');
        });
    }

    /* ── Card number auto-spacing (CC change modal) ────────────── */
    var ccNum = document.getElementById('ccNumber');
    if (ccNum) {
        ccNum.addEventListener('input', function () {
            var v = this.value.replace(/\D/g, '').substring(0, 16);
            var parts = v.match(/.{1,4}/g);
            this.value = parts ? parts.join(' ') : '';
        });
    }

    /* ════════════════════════════════════════════════════════════
       MONERIS PAY NOW MODAL
       ════════════════════════════════════════════════════════════ */

    /* ── Flatpickr month picker on expiry input ─────────────────
       Shows a calendar locked to month/year selection only.
       Stores value as MM/YYYY in the hidden input and displays
       a human-friendly label in the visible (readonly) field.     */
    var monerisExpiryInput = document.getElementById('monerisCardExpiry');
    var monerisExpiryHidden = document.getElementById(
        'monerisCardExpiryHidden'
    );
    var monerisExpiryPicker = null;

    if (monerisExpiryInput && typeof flatpickr !== 'undefined') {
        monerisExpiryPicker = flatpickr(monerisExpiryInput, {
            plugins: [
                /* monthSelectPlugin adds the month-only picker UI */
                typeof monthSelectPlugin !== 'undefined'
                    ? new monthSelectPlugin({
                          shorthand: false,
                          dateFormat: 'm/Y',
                          altFormat: 'F Y',
                      })
                    : null,
            ].filter(Boolean),
            /* Fallback when plugin is absent: regular picker, day hidden */
            dateFormat: 'm/Y',
            minDate: 'today',
            disableMobile: true,
            allowInput: false,
            onChange: function (selectedDates, dateStr) {
                /* dateStr is already MM/YYYY from dateFormat */
                if (monerisExpiryHidden) monerisExpiryHidden.value = dateStr;
                /* Mark field valid */
                monerisExpiryInput.classList.remove('is-invalid');
            },
        });
    }

    /* ── Populate modal when any "Pay Now" button is clicked ──── */
    var monerisModal = document.getElementById('monerisPayModal');
    if (monerisModal) {
        monerisModal.addEventListener('show.bs.modal', function (e) {
            var trigger = e.relatedTarget;
            if (!trigger) return;

            var ids = trigger.dataset.paymentIds || '';
            var amount = trigger.dataset.amount || '0.00';
            var due = trigger.dataset.due || '';
            var last4 = trigger.dataset.ccLast4 || '';
            var expiry = trigger.dataset.ccExpiry || ''; // format: MM/YYYY e.g. "09/2027"

            /* Store payment IDs */
            var idsInput = document.getElementById('monerisPayIds');
            if (idsInput) idsInput.value = ids;

            /* Due date display */
            var dueDisplay = due
                ? new Date(due + 'T00:00:00').toLocaleDateString('en-CA', {
                      year: 'numeric',
                      month: 'long',
                      day: 'numeric',
                  })
                : '';
            var dueEl = document.getElementById('monerisPayDue');
            var amtEl = document.getElementById('monerisPayAmount');
            var btnAmtEl = document.getElementById('monerisPayBtnAmount');
            var formatted = '$' + parseFloat(amount).toFixed(2);
            if (dueEl) dueEl.textContent = dueDisplay;
            if (amtEl) amtEl.textContent = formatted;
            if (btnAmtEl) btnAmtEl.textContent = formatted;

            /* ── Auto-fill expiry from saved card ───────────────
               ccExpiry is stored as "MM/YYYY" (e.g. "09/2027").
               flatpickr expects the same MM/YYYY format.          */
            var expiryHint = document.getElementById('monerisCardExpiryHidden');
            if (expiry && monerisExpiryPicker) {
                try {
                    /* Parse MM/YYYY → Date for flatpickr setDate */
                    var parts = expiry.split('/'); // ["09","2027"]
                    var month = parseInt(parts[0], 10) - 1; // 0-indexed
                    var year = parseInt(parts[1], 10);
                    var dtObj = new Date(year, month, 1);
                    monerisExpiryPicker.setDate(dtObj, true);
                    /* hidden + visible already set by onChange, but set explicitly too */
                    if (expiryHint) expiryHint.value = expiry;
                    if (monerisExpiryInput) monerisExpiryInput.value = expiry;
                } catch (ignored) {}
            }

            /* ── Show/hide card-on-file hint ─────────────────── */
            var hintEl = document.getElementById('monerisCardHint');
            var last4El = document.getElementById('monerisCardHintLast4');
            if (last4 && hintEl && last4El) {
                last4El.textContent = last4;
                hintEl.classList.remove('d-none');
            } else if (hintEl) {
                hintEl.classList.add('d-none');
            }
        });

        /* ── Reset modal on close ──────────────────────────────── */
        monerisModal.addEventListener('hidden.bs.modal', function () {
            var form = document.getElementById('monerisPayForm');
            if (form) {
                form.reset();
                form.classList.remove('was-validated');
            }
            /* Clear flatpickr expiry */
            if (monerisExpiryPicker) monerisExpiryPicker.clear();
            var expiryHidden = document.getElementById(
                'monerisCardExpiryHidden'
            );
            if (expiryHidden) expiryHidden.value = '';

            _monerisSetLoading(false);

            var errEl = document.getElementById('monerisPayError');
            var succEl = document.getElementById('monerisPaySuccess');
            if (errEl) {
                errEl.classList.add('d-none');
                errEl.textContent = '';
            }
            if (succEl) {
                succEl.classList.add('d-none');
                succEl.textContent = '';
            }

            var submitBtn = document.getElementById('monerisPaySubmitBtn');
            var cancelBtn = document.getElementById('monerisPayCancelBtn');
            if (submitBtn) submitBtn.disabled = false;
            if (cancelBtn) cancelBtn.disabled = false;

            /* Reset card icon */
            var icon = document.getElementById('monerisCardIcon');
            if (icon) icon.className = 'bi bi-credit-card';
        });
    }

    /* Card number auto-spacing + card type icon (Moneris modal) */
    var monerisCardNum = document.getElementById('monerisCardNumber');
    if (monerisCardNum) {
        monerisCardNum.addEventListener('input', function () {
            var digits = this.value.replace(/\D/g, '').substring(0, 16);
            var parts = digits.match(/.{1,4}/g);
            this.value = parts ? parts.join(' ') : '';

            var icon = document.getElementById('monerisCardIcon');
            if (icon) {
                if (/^4/.test(digits)) {
                    icon.className = 'bi bi-credit-card-fill text-primary';
                } else if (/^5[1-5]/.test(digits)) {
                    icon.className = 'bi bi-credit-card-fill text-warning';
                } else if (/^3[47]/.test(digits)) {
                    icon.className = 'bi bi-credit-card-fill text-success';
                } else {
                    icon.className = 'bi bi-credit-card';
                }
            }
        });
    }
})();

/* ── Toggle loading state (spinner + disabled buttons) ──────── */
var _monerisPayInFlight = false;

function _monerisSetLoading(loading) {
    var spinner = document.getElementById('monerisPaySpinner');
    var form = document.getElementById('monerisPayForm');
    var submitBtn = document.getElementById('monerisPaySubmitBtn');
    var cancelBtn = document.getElementById('monerisPayCancelBtn');

    if (spinner) spinner.classList.toggle('d-none', !loading);
    if (form) form.classList.toggle('d-none', loading);
    if (submitBtn) submitBtn.disabled = loading;
    if (cancelBtn) cancelBtn.disabled = loading;
}

/* ── Submit Moneris payment ─────────────────────────────────── */
function submitMonerisPayment() {
    if (_monerisPayInFlight) return;

    var form = document.getElementById('monerisPayForm');
    if (!form) return;

    /* The expiry value comes from the hidden input (set by flatpickr) */
    var expiryHidden = document.getElementById('monerisCardExpiryHidden');
    var expiryInput = document.getElementById('monerisCardExpiry');
    var expiry =
        expiryHidden && expiryHidden.value
            ? expiryHidden.value
            : expiryInput
              ? expiryInput.value
              : '';

    /* Mark expiry invalid if empty before running checkValidity */
    if (!expiry) {
        if (expiryInput) expiryInput.classList.add('is-invalid');
        form.classList.add('was-validated');
        return;
    }

    /* HTML5 validation for all other fields */
    form.classList.add('was-validated');
    if (!form.checkValidity()) return;

    var errEl = document.getElementById('monerisPayError');
    var succEl = document.getElementById('monerisPaySuccess');
    if (errEl) {
        errEl.classList.add('d-none');
        errEl.textContent = '';
    }
    if (succEl) {
        succEl.classList.add('d-none');
        succEl.textContent = '';
    }

    var idsRaw = (document.getElementById('monerisPayIds')?.value || '').trim();
    var amount =
        document
            .getElementById('monerisPayAmount')
            ?.textContent?.replace(/[^0-9.]/g, '') || '0';
    var pan = (
        document.getElementById('monerisCardNumber')?.value || ''
    ).replace(/\D/g, '');
    var cvd = (document.getElementById('monerisCardCvd')?.value || '').replace(
        /\D/g,
        ''
    );

    var paymentIds;
    try {
        paymentIds = JSON.parse(idsRaw);
    } catch (e) {
        paymentIds = [];
    }

    if (!paymentIds.length) {
        if (errEl) {
            errEl.textContent = 'Invalid payment data. Please reload the page.';
            errEl.classList.remove('d-none');
        }
        return;
    }

    _monerisPayInFlight = true;
    _monerisSetLoading(true);

    fetch('/payments?action=pay', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            payment_ids: paymentIds,
            amount: amount,
            pan: pan,
            expiry: expiry,
            cvd: cvd,
            csrf_token:
                document.getElementById('monerisPayCsrf')?.value || '',
        }),
    })
        .then(function (res) {
            return res.json();
        })
        .then(function (data) {
            _monerisSetLoading(false);

            if (data.ok && data.approved) {
                if (succEl) {
                    succEl.textContent =
                        'Payment approved! Receipt: ' +
                        (data.receipt || data.txn_id || '—');
                    succEl.classList.remove('d-none');
                }
                var submitBtn = document.getElementById('monerisPaySubmitBtn');
                if (submitBtn) submitBtn.disabled = true;
                // Keep _monerisPayInFlight true through reload

                setTimeout(function () {
                    var modalEl = document.getElementById('monerisPayModal');
                    if (typeof bootstrap !== 'undefined' && modalEl) {
                        var inst = bootstrap.Modal.getInstance(modalEl);
                        if (inst) inst.hide();
                    }
                    if (typeof loadPage === 'function') {
                        loadPage('payments');
                    } else {
                        window.location.reload();
                    }
                }, 2000);
            } else {
                _monerisPayInFlight = false;
                var msg =
                    data.error ||
                    data.message ||
                    'Payment was not approved. Please try again.';
                if (errEl) {
                    errEl.textContent = msg;
                    errEl.classList.remove('d-none');
                }
            }
        })
        .catch(function () {
            _monerisPayInFlight = false;
            _monerisSetLoading(false);
            if (errEl) {
                errEl.textContent =
                    'A network error occurred. Please try again.';
                errEl.classList.remove('d-none');
            }
        });
}

/* ── Submit CC change request (modal form) ─────────────────── */
function submitCcChange() {
    var form = document.getElementById('changeCcForm');
    if (!form) return;

    /* ── 4-day proximity block ─────────────────────────────────
       If the chosen start date is within 4 days (inclusive) of
       the next payment date, block submission and force the user
       to call accounting instead.                               */
    var hiddenDate = document.getElementById('ccChangeDateHidden');
    var ccDateInput = document.getElementById('ccChangeDateInput');
    var nextPayment = ccDateInput ? ccDateInput.dataset.nextPayment : '';
    var warning = document.getElementById('ccChangeDateWarning');

    if (hiddenDate && hiddenDate.value && nextPayment) {
        var chosenMs = new Date(hiddenDate.value).getTime();
        var nextMs = new Date(nextPayment).getTime();
        var diffDays = Math.round((nextMs - chosenMs) / 86400000);

        if (diffDays >= 0 && diffDays <= 4) {
            /* Show warning, shake it, and abort submit */
            if (warning) {
                warning.classList.remove('d-none');
                warning.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            }
            return; // ← hard block: cannot submit
        }
    }

    /* HTML5 validation for all other fields */
    form.classList.add('was-validated');
    if (!form.checkValidity()) return;

    var btn = document.getElementById('ccChangeSubmitBtn');
    var succ = document.getElementById('ccChangeSuccess');

    /* Show success (real submission wired to backend later) */
    if (succ) succ.classList.remove('d-none');
    if (btn) btn.disabled = true;
}

/* ── Submit payment date change request ─────────────────────── */
function submitPayDateRequest() {
    var day = document.getElementById('payDateDaySelect')?.value ?? '';
    var reason = document.getElementById('payDateReason')?.value.trim() ?? '';
    var msgEl = document.getElementById('payDateRequestMsg');
    if (!msgEl) return;

    msgEl.className = 'mt-2 alert alert-success py-1 small';
    msgEl.textContent =
        'Your request to change payment date to day ' +
        day +
        ' has been submitted. Accounting will contact you shortly.';
    msgEl.classList.remove('d-none');

    var btn = msgEl.previousElementSibling;
    if (btn) btn.disabled = true;
}

/* Bind pay actions (no inline onclick; SPA-safe) */
(function bindPayActions() {
    function bind() {
        var payBtn = document.getElementById('monerisPaySubmitBtn');
        if (payBtn && !payBtn._bound) {
            payBtn._bound = true;
            payBtn.addEventListener('click', function () {
                submitMonerisPayment();
            });
        }
        var ccBtn = document.getElementById('ccChangeSubmitBtn');
        if (ccBtn && !ccBtn._bound) {
            ccBtn._bound = true;
            ccBtn.addEventListener('click', function () {
                submitCcChange();
            });
        }
        var dateBtn = document.getElementById('payDateRequestBtn');
        if (dateBtn && !dateBtn._bound) {
            dateBtn._bound = true;
            dateBtn.addEventListener('click', function () {
                submitPayDateRequest();
            });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
