/**
 * CIF module: data
 * collectData, progress, save, toasts.
 */
(function (Cif) {
    'use strict';

Cif.collectData = function () {
        const data = {};

        // Simple inputs (text, date, textarea, dropdown, optional-day)
        document
            .querySelectorAll(
                '.cif-input:not([type="radio"]):not([type="checkbox"])'
            )
            .forEach((inp) => {
                if (inp.name) data[inp.name] = inp.value;
            });

        // Radio buttons (yes/no)
        document
            .querySelectorAll('.cif-input[type="radio"]:checked')
            .forEach((inp) => {
                if (inp.name) data[inp.name] = inp.value;
            });

        // Single checkboxes (not multi-option groups, not table cells)
        document
            .querySelectorAll(
                '.cif-input[type="checkbox"]:not(.cif-checkbox-group)'
            )
            .forEach((cb) => {
                if (!cb.name || cb.closest('.cif-table')) return;
                data[cb.name] = cb.checked ? 'Yes' : '';
            });

        // Checkbox groups
        const groups = {};
        document.querySelectorAll('.cif-checkbox-group').forEach((cb) => {
            const grp = cb.dataset.group;
            if (!grp) return;
            if (!groups[grp]) groups[grp] = [];
            if (cb.checked) groups[grp].push(cb.value);
        });
        Object.assign(data, groups);

        // Tables
        document.querySelectorAll('.cif-table').forEach((table) => {
            const key = table.dataset.key;
            const rows = [];
            table.querySelectorAll('tbody tr').forEach((tr) => {
                const rowData = {};
                tr.querySelectorAll('.cif-table-input').forEach((inp) => {
                    const col = inp.dataset.col;
                    if (inp.type === 'checkbox') {
                        rowData[col] = inp.checked ? '1' : '';
                    } else {
                        rowData[col] = inp.value;
                    }
                });
                rows.push(rowData);
            });
            data[key] = rows;
        });

        return data;
    }

    /* ================================================================
     *  Progress Bar
     * ================================================================ */
    Cif.updateProgress = function () {
        let total = 0;
        let filled = 0;

        document.querySelectorAll('.cif-question').forEach((q) => {
            if (q.style.display === 'none') return;

            var isRequired = false;
            if (q.dataset.requiredWhen) {
                try {
                    isRequired = Cif.rulesMatch(JSON.parse(q.dataset.requiredWhen));
                } catch (e) {
                    isRequired = false;
                }
            } else {
                isRequired = !!q.querySelector('[required], .cif-req-star');
            }
            if (!isRequired) return;

            total++;
            const key = q.dataset.key;

            const table = q.querySelector('.cif-table');
            if (table) {
                let hasData = false;
                table
                    .querySelectorAll('tbody tr .cif-table-input')
                    .forEach((inp) => {
                        if (inp.type === 'checkbox' && inp.checked)
                            hasData = true;
                        if (inp.type === 'text' && inp.value.trim())
                            hasData = true;
                    });
                if (hasData) filled++;
                return;
            }

            const inp = q.querySelector('.cif-input');
            if (!inp) return;

            if (inp.type === 'radio') {
                const checked = q.querySelector('.cif-input:checked');
                if (checked) filled++;
            } else if (inp.type === 'checkbox') {
                if (inp.checked) filled++;
            } else if (inp.value && inp.value.trim()) {
                filled++;
            }
        });

        const pct = total > 0 ? Math.round((filled / total) * 100) : 100;
        const bar = document.getElementById('cifProgressBar');
        const badge = document.getElementById('cifProgressBadge');
        if (bar) {
            bar.style.width = pct + '%';
            bar.setAttribute('aria-valuenow', pct);
        }
        if (badge) badge.textContent = pct + '%';
    }

    // Bind input listeners for progress updates
    Cif.bindInputListeners = function (container) {
        container
            .querySelectorAll('.cif-input, .cif-table-input')
            .forEach((inp) => {
                const evt =
                    inp.type === 'checkbox' || inp.type === 'radio'
                        ? 'change'
                        : 'input';
                inp.addEventListener(evt, Cif.updateProgress);
            });
    }
    Cif.bindInputListeners(document);

    /* ================================================================
     *  Toast Helper
     * ================================================================ */
    Cif.showToast = function (message, type) {
        const el = document.getElementById('cifToastEl');
        const body = document.getElementById('cifToastBody');
        if (!el || !body) return;
        body.textContent = message;
        el.className =
            'toast align-items-center border-0 text-bg-' + (type || 'success');
        const toast = new bootstrap.Toast(el, { delay: 3000 });
        toast.show();
    }

    /** Toast with HTML content and configurable delay */
    Cif.showToastHtml = function (html, type, delay) {
        const el = document.getElementById('cifToastEl');
        const body = document.getElementById('cifToastBody');
        if (!el || !body) return;
        body.innerHTML = html;
        el.className =
            'toast align-items-center border-0 text-bg-' + (type || 'success');
        const toast = new bootstrap.Toast(el, { delay: delay || 5000 });
        toast.show();
    }

    Cif.escapeHtml = function (str) {
        var d = document.createElement('div');
        d.textContent = str == null ? '' : String(str);
        return d.innerHTML;
    };

    /** Bulleted validation toast — client missing fields or server `errors`. */
    Cif.showValidationErrors = function (messages, type) {
        var list = [];
        if (Array.isArray(messages)) {
            messages.forEach(function (m) {
                var t = m == null ? '' : String(m).trim();
                if (t) list.push(t);
            });
        } else if (messages != null && String(messages).trim()) {
            list.push(String(messages).trim());
        }
        if (!list.length) {
            list.push('Please complete the required fields.');
        }
        var cap = 15;
        var html =
            '<strong>Please fix the following:</strong><ul class="mb-0 mt-1">';
        list.slice(0, cap).forEach(function (f) {
            html += '<li>' + Cif.escapeHtml(f) + '</li>';
        });
        if (list.length > cap) {
            html +=
                '<li>' +
                Cif.escapeHtml('…and ' + (list.length - cap) + ' more') +
                '</li>';
        }
        html += '</ul>';
        Cif.showToastHtml(html, type || 'warning', 12000);
    };

    /* ================================================================
     *  AJAX Save
     * ================================================================ */
    let saveTimer = null;

    Cif.getCsrfToken = function () {
        var el = document.getElementById('cifCsrf');
        return el ? el.value : '';
    }

    Cif.autoSave = function () {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(Cif.doSave, 300);
    }

    Cif.doSave = function () {
        const data = Cif.collectData();
        fetch('/cif', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                data: data,
                csrf_token: Cif.getCsrfToken(),
            }),
        })
            .then((r) => r.json())
            .then((res) => {
                if (res.ok) {
                    // Update progress from server
                    const bar = document.getElementById('cifProgressBar');
                    const badge = document.getElementById('cifProgressBadge');
                    if (bar) {
                        bar.style.width = res.progress + '%';
                        bar.setAttribute('aria-valuenow', res.progress);
                    }
                    if (badge) badge.textContent = res.progress + '%';
                }
            })
            .catch((err) => console.error('CIF auto-save error:', err));
    }

    // Save button
    const saveBtn = document.getElementById('cifSaveBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            const data = Cif.collectData();
            saveBtn.disabled = true;
            saveBtn.innerHTML =
                '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

            fetch('/cif', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    data: data,
                    csrf_token: Cif.getCsrfToken(),
                }),
            })
                .then((r) => r.json())
                .then((res) => {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML =
                        '<i class="bi bi-check-circle me-1"></i>Save';
                    if (res.ok) {
                        Cif.showToast(
                            'Form saved successfully! (' +
                                res.progress +
                                '% complete)',
                            'success'
                        );
                        const bar = document.getElementById('cifProgressBar');
                        const badge =
                            document.getElementById('cifProgressBadge');
                        if (bar) {
                            bar.style.width = res.progress + '%';
                            bar.setAttribute('aria-valuenow', res.progress);
                        }
                        if (badge) badge.textContent = res.progress + '%';
                    } else {
                        Cif.showToast(res.error || 'Save failed', 'danger');
                    }
                })
                .catch(() => {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML =
                        '<i class="bi bi-check-circle me-1"></i>Save';
                    Cif.showToast('Network error — please try again', 'danger');
                });
        });
    }

    /* Generate PDFs */
    var genBtn = document.getElementById('cifGenerateBtn');
    if (genBtn) {
        genBtn.addEventListener('click', function () {
            var data = Cif.collectData();
            genBtn.disabled = true;
            genBtn.innerHTML =
                '<span class="spinner-border spinner-border-sm me-1"></span>Generating…';
            fetch('/cif', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    data: data,
                    csrf_token: Cif.getCsrfToken(),
                }),
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (saveRes) {
                    if (!saveRes.ok) {
                        throw new Error(saveRes.error || 'Save failed');
                    }
                    var formData = new FormData();
                    formData.append('action', 'generate');
                    formData.append('csrf_token', Cif.getCsrfToken());
                    return fetch('/cif?action=generate', {
                        method: 'POST',
                        body: formData,
                    });
                })
                .then(function (r) {
                    return r.json();
                })
                .then(function (genRes) {
                    genBtn.disabled = false;
                    genBtn.innerHTML =
                        '<i class="bi bi-file-earmark-pdf me-1"></i>Generate Forms';
                    if (genRes.ok) {
                        var count = genRes.generated
                            ? genRes.generated.length
                            : 0;
                        Cif.showToast(
                            count + ' PDF form(s) generated successfully!',
                            'success'
                        );
                        setTimeout(function () {
                            window.location.reload();
                        }, 1500);
                    } else {
                        Cif.showToast(
                            genRes.error || 'Generation failed',
                            'danger'
                        );
                    }
                })
                .catch(function (err) {
                    genBtn.disabled = false;
                    genBtn.innerHTML =
                        '<i class="bi bi-file-earmark-pdf me-1"></i>Generate Forms';
                    Cif.showToast(
                        err.message || 'Error generating PDFs',
                        'danger'
                    );
                });
        });
    }

})(window.Cif = window.Cif || {});
