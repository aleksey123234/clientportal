/* ═══════════════════════════════════════════════════════════════
   INIT — runs inside an IIFE (not DOMContentLoaded) so it works
   after SPA navigation re-executes <script> tags.
   ═══════════════════════════════════════════════════════════════ */
(function () {
    /* ── Flatpickr: parse Y-m-d string into a Date ────────────── */
    function parseYmd(ymd) {
        if (!ymd) return null;
        var parts = ymd.split('-');
        if (parts.length !== 3) return null;
        var d = new Date(+parts[0], +parts[1] - 1, +parts[2]);
        return isNaN(d.getTime()) ? null : d;
    }

    /* Helper: format Date → Y-m-d string */
    function toYmd(d) {
        return (
            d.getFullYear() +
            '-' +
            String(d.getMonth() + 1).padStart(2, '0') +
            '-' +
            String(d.getDate()).padStart(2, '0')
        );
    }

    if (typeof flatpickr !== 'undefined') {
        /**
         * yearDropdownPlugin — replaces flatpickr's tiny year numInput
         * with a real <select> dropdown for quick year navigation.
         *
         * @param {number} minYear  — first year in the list
         * @param {number} maxYear  — last year in the list
         */
        function yearDropdownPlugin(minYear, maxYear) {
            return function (fp) {
                function build() {
                    var wrapper = fp.calendarContainer.querySelector(
                        '.flatpickr-current-month .numInputWrapper'
                    );
                    if (!wrapper) return;

                    var oldInput = wrapper.querySelector('input.cur-year');
                    if (!oldInput) return;

                    /* If already replaced, skip */
                    if (wrapper.querySelector('select.fp-year-select')) return;

                    var sel = document.createElement('select');
                    sel.className = 'fp-year-select';
                    /* Copy core styles so it looks like the month dropdown */
                    sel.style.cssText =
                        'appearance:auto;-webkit-appearance:auto;background:transparent;' +
                        'border:none;font-size:inherit;font-weight:inherit;' +
                        'color:inherit;cursor:pointer;padding:0 2px;outline:none;' +
                        'font-family:inherit;height:auto;';

                    for (var y = maxYear; y >= minYear; y--) {
                        var opt = document.createElement('option');
                        opt.value = y;
                        opt.textContent = y;
                        if (y === fp.currentYear) opt.selected = true;
                        sel.appendChild(opt);
                    }

                    sel.addEventListener('change', function () {
                        fp.changeYear(parseInt(this.value, 10));
                    });

                    /* Hide original input + arrows, insert select */
                    oldInput.style.display = 'none';
                    var arrows = wrapper.querySelectorAll(
                        '.arrowUp, .arrowDown'
                    );
                    arrows.forEach(function (a) {
                        a.style.display = 'none';
                    });
                    wrapper.appendChild(sel);
                }

                function syncYear() {
                    var sel = fp.calendarContainer.querySelector(
                        'select.fp-year-select'
                    );
                    if (sel) sel.value = fp.currentYear;
                }

                return {
                    onReady: build,
                    onOpen: syncYear,
                    onMonthChange: syncYear,
                    onYearChange: syncYear,
                };
            };
        }

        /* ── DOB picker ────────────────────────────────────────── */
        var maxDob = new Date();
        maxDob.setFullYear(maxDob.getFullYear() - 18);

        var dobPicker = flatpickr('#dobInput', {
            locale: 'en',
            dateFormat: 'd/m/Y',
            allowInput: true,
            disableMobile: true,
            maxDate: maxDob,
            monthSelectorType: 'dropdown',
            plugins: [yearDropdownPlugin(1920, maxDob.getFullYear())],
            defaultDate: parseYmd(document.getElementById('dobHidden').value),
            onChange: function (selectedDates) {
                document.getElementById('dobHidden').value =
                    selectedDates.length ? toYmd(selectedDates[0]) : '';
            },
        });

        /* ── Move-in date pickers (living + mail address modals) ── */
        var curYear = new Date().getFullYear();
        ['living', 'mail'].forEach(function (addrType) {
            var input = document.getElementById('moveIn_' + addrType);
            var hidden = document.getElementById('moveInHidden_' + addrType);
            if (!input || !hidden) return;
            flatpickr(input, {
                locale: 'en',
                dateFormat: 'd/m/Y',
                allowInput: true,
                disableMobile: true,
                monthSelectorType: 'dropdown',
                plugins: [yearDropdownPlugin(1950, curYear + 2)],
                defaultDate: parseYmd(hidden.value),
                onChange: function (selectedDates) {
                    hidden.value = selectedDates.length
                        ? toYmd(selectedDates[0])
                        : '';
                },
            });
        });

        /* ── Sync hidden fields on form submit ─────────────────── */
        var profileForm = document.getElementById('profileForm');
        if (profileForm && !profileForm._submitBound) {
            profileForm._submitBound = true;
            profileForm.addEventListener('submit', function () {
                /* DOB */
                if (dobPicker && dobPicker.selectedDates.length) {
                    document.getElementById('dobHidden').value = toYmd(
                        dobPicker.selectedDates[0]
                    );
                }
            });
        }

        /* ── Sync move-in hidden fields on address form submit ── */
        ['addrLivingForm', 'addrMailForm'].forEach(function (formId) {
            var addrForm = document.getElementById(formId);
            var addrType = formId === 'addrLivingForm' ? 'living' : 'mail';
            if (addrForm && !addrForm._moveInBound) {
                addrForm._moveInBound = true;
                addrForm.addEventListener('submit', function () {
                    var hidden = document.getElementById(
                        'moveInHidden_' + addrType
                    );
                    var picker = document.getElementById('moveIn_' + addrType);
                    if (
                        hidden &&
                        picker &&
                        picker._flatpickr &&
                        picker._flatpickr.selectedDates.length
                    ) {
                        hidden.value = toYmd(
                            picker._flatpickr.selectedDates[0]
                        );
                    }
                });
            }
        });
    }

    /* ── Phone type → extension + label field visibility ──────── */
    var phoneTypeSelect = document.getElementById('phoneTypeSelect');
    if (phoneTypeSelect && !phoneTypeSelect._changeBound) {
        phoneTypeSelect._changeBound = true;
        function updatePhoneFields() {
            var val = phoneTypeSelect.value;
            var extWrap = document.getElementById('extFieldWrap');
            var labelWrap = document.getElementById('phoneLabelWrap');
            if (extWrap)
                extWrap.style.display = ['work', 'other'].includes(val)
                    ? ''
                    : 'none';
            if (labelWrap)
                labelWrap.style.display = val === 'other' ? '' : 'none';
        }
        phoneTypeSelect.addEventListener('change', updatePhoneFields);
        updatePhoneFields();
    }

    /* ── AJAX modal form intercepts ────────────────────────────── */
    /* Guard: in SPA mode the script re-runs each time the profile page is
       loaded.  Modals are moved to <body> by dashboard.js and survive
       across navigations, so we must NOT attach duplicate listeners. */
    ['addPhoneForm', 'addEmailForm', 'addrLivingForm', 'addrMailForm'].forEach(
        function (id) {
            var form = document.getElementById(id);
            if (!form) return;
            if (form._ajaxBound) return; // ← already bound
            form._ajaxBound = true;
            var modalId = form.closest('.modal')
                ? form.closest('.modal').id
                : null;
            if (!modalId) return;
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                ajaxSubmitForm(form, modalId, 'Saved.');
            });
        }
    );

    /* ── Initial bind for delete forms ─────────────────────────── */
    bindContactEvents();

    /* ── Phone number formatting hint ─────────────────────────── */
    var phoneInput = document.getElementById('phoneNumberInput');
    if (phoneInput && !phoneInput._inputBound) {
        phoneInput._inputBound = true;
        phoneInput.addEventListener('input', function () {
            this.setCustomValidity('');
        });
    }

    /* ── Reset add-phone modal on close ───────────────────────── */
    var addPhoneModal = document.getElementById('addPhoneModal');
    if (addPhoneModal && !addPhoneModal._resetBound) {
        addPhoneModal._resetBound = true;
        addPhoneModal.addEventListener('hidden.bs.modal', function () {
            var form = document.getElementById('addPhoneForm');
            if (form) {
                form.reset();
                var btn = form.querySelector('[type="submit"]');
                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('disabled');
                }
            }
            var extWrap = document.getElementById('extFieldWrap');
            if (extWrap) extWrap.style.display = 'none';
            var labelWrap = document.getElementById('phoneLabelWrap');
            if (labelWrap) labelWrap.style.display = 'none';
        });
    }

    /* ── Re-enable submit buttons when any modal closes ────────── */
    ['addEmailModal', 'addrLivingModal', 'addrMailModal'].forEach(
        function (id) {
            var modal = document.getElementById(id);
            if (!modal) return;
            if (modal._enableBound) return; // ← already bound
            modal._enableBound = true;
            modal.addEventListener('hidden.bs.modal', function () {
                var btn = modal.querySelector('[type="submit"]');
                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('disabled');
                }
            });
        }
    );

    /* ── Bootstrap Popovers ────────────────────────────────────── */
    if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
        document
            .querySelectorAll('[data-bs-toggle="popover"]')
            .forEach(function (el) {
                new bootstrap.Popover(el);
            });
    }
})();
