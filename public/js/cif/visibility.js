/**
 * CIF module: visibility
 * Port of CifVisibility.php.
 */
(function (Cif) {
    'use strict';

Cif.getFieldValue = function (name) {
        var radios = document.querySelectorAll(
            '.cif-input[type="radio"][name="' + name + '"]'
        );
        if (radios.length) {
            var checked = document.querySelector(
                '.cif-input[type="radio"][name="' + name + '"]:checked'
            );
            return checked ? checked.value : '';
        }
        var inp = document.querySelector('.cif-input[name="' + name + '"]');
        if (!inp) return '';
        if (inp.type === 'checkbox') {
            return inp.checked ? inp.value || 'Yes' : '';
        }
        return String(inp.value || '');
    }

    Cif.rulesMatch = function (rules) {
        if (!rules || typeof rules !== 'object') return false;
        for (var depKey in rules) {
            if (!Object.prototype.hasOwnProperty.call(rules, depKey)) continue;
            if (depKey === 'value') continue;
            var expected = rules[depKey];
            var actual = Cif.getFieldValue(depKey);
            if (Array.isArray(expected)) {
                if (expected.indexOf(actual) === -1) return false;
            } else if (actual !== expected) {
                return false;
            }
        }
        return true;
    }

    Cif.bindDepListeners = function (rules, handler) {
        if (!rules) return;
        for (var depKey in rules) {
            if (!Object.prototype.hasOwnProperty.call(rules, depKey)) continue;
            document
                .querySelectorAll('.cif-input[name="' + depKey + '"]')
                .forEach(function (depInput) {
                    depInput.addEventListener('change', handler);
                    depInput.addEventListener('input', handler);
                });
        }
    }

    Cif.applyConditionalVisibility = function () {
        var formsHost =
            document.querySelector('.cif-progress-wrap[data-user-forms]') ||
            document.getElementById('cifForm');
        var userForms = [];
        if (formsHost && formsHost.dataset.userForms) {
            try {
                userForms = JSON.parse(formsHost.dataset.userForms) || [];
            } catch (e) {
                userForms = [];
            }
        }

        document.querySelectorAll('.cif-question').forEach(function (q) {
            var shouldHide = false;

            if (q.dataset.showWhen || q.dataset.showAlsoWhenForms) {
                var alsoOk = false;
                if (q.dataset.showAlsoWhenForms) {
                    try {
                        var alsoForms = JSON.parse(q.dataset.showAlsoWhenForms);
                        if (Array.isArray(alsoForms)) {
                            alsoOk = alsoForms.some(function (f) {
                                return userForms.indexOf(f) !== -1;
                            });
                        }
                    } catch (e) {
                        /* ignore */
                    }
                }
                var whenOk = false;
                if (q.dataset.showWhen) {
                    try {
                        whenOk = Cif.rulesMatch(JSON.parse(q.dataset.showWhen));
                    } catch (e) {
                        whenOk = false;
                    }
                }
                shouldHide = !(alsoOk || whenOk);
            }
            if (!shouldHide && q.dataset.hiddenWhen) {
                try {
                    shouldHide = Cif.rulesMatch(JSON.parse(q.dataset.hiddenWhen));
                } catch (e) { /* ignore */ }
            }

            q.style.display = shouldHide ? 'none' : '';

            /* required_when */
            var needsRequired = false;
            if (!shouldHide) {
                if (q.dataset.requiredWhen) {
                    try {
                        needsRequired = Cif.rulesMatch(JSON.parse(q.dataset.requiredWhen));
                    } catch (e) {
                        needsRequired = false;
                    }
                    var star = q.querySelector('.cif-req-star');
                    if (star) star.style.display = needsRequired ? '' : 'none';
                } else if (q.querySelector('.cif-req-star')) {
                    needsRequired = true;
                }
            } else if (q.dataset.requiredWhen) {
                var starHide = q.querySelector('.cif-req-star');
                if (starHide) starHide.style.display = 'none';
            }

            q.querySelectorAll('.cif-input').forEach(function (inp) {
                if (shouldHide || !needsRequired) {
                    inp.removeAttribute('required');
                } else if (inp.type === 'radio') {
                    /* set required on first radio only */
                } else {
                    inp.setAttribute('required', 'required');
                }
            });
            if (!shouldHide && needsRequired) {
                var radios = q.querySelectorAll('.cif-input[type="radio"]');
                radios.forEach(function (r, i) {
                    if (i === 0) r.setAttribute('required', 'required');
                    else r.removeAttribute('required');
                });
            }

            /* lock_when — e.g. military To = Present */
            if (q.dataset.lockWhen) {
                try {
                    var lock = JSON.parse(q.dataset.lockWhen);
                    var lockRules = {};
                    for (var k in lock) {
                        if (k === 'value') continue;
                        lockRules[k] = lock[k];
                    }
                    var shouldLock = !shouldHide && Cif.rulesMatch(lockRules);
                    var lockVal = lock.value || 'Present';
                    q.querySelectorAll('.cif-input').forEach(function (inp) {
                        if (shouldLock) {
                            if (inp._flatpickr) {
                                inp._flatpickr.destroy();
                            }
                            inp.value = lockVal;
                            inp.readOnly = true;
                        } else if (inp.readOnly && inp.value === lockVal) {
                            inp.readOnly = false;
                            if (inp.classList.contains('cif-month-picker')) {
                                Cif.initMonthPicker(inp);
                            }
                        }
                    });
                } catch (e) { /* ignore */ }
            }

            /* A.16: granted pardon → Documents link */
            var pardonLink = q.querySelector('.cif-waiver-pardon-doc-link');
            if (pardonLink) {
                var showLink =
                    !shouldHide && Cif.getFieldValue('waiver_has_pardon') === 'Yes';
                pardonLink.classList.toggle('d-none', !showLink);
            }
        });
        /* Hide Bootstrap row wrappers when every question inside is hidden */
        document.querySelectorAll('[data-cif-row]').forEach(function (row) {
            var qs = row.querySelectorAll('.cif-question');
            if (!qs.length) return;
            var anyVisible = false;
            qs.forEach(function (q) {
                if (q.style.display !== 'none') anyVisible = true;
            });
            row.style.display = anyVisible ? '' : 'none';
        });

        Cif.updateProgress();
        Cif.validateAllTimelines(false);
        Cif.updateSpousalEmptyState();
    }

    Cif.updateSpousalEmptyState = function () {
        var sec = document.querySelector('.cif-section[data-section-id="spousal"]');
        if (!sec) return;
        var emptyEl = sec.querySelector('.cif-spousal-empty');
        if (!emptyEl) return;
        var anyVisible = false;
        sec.querySelectorAll('.cif-question').forEach(function (q) {
            if (q.style.display !== 'none') anyVisible = true;
        });
        emptyEl.classList.toggle('d-none', anyVisible);
    }

})(window.Cif = window.Cif || {});
