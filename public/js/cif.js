/**
 * CIF orchestrator — section nav + Finish. Logic lives in public/js/cif/*.js
 *
 * Load: cif-datepicker.js → cif/*.js → cif.js
 * @see docs/CIF_REFERENCE.md
 */
(function () {
    'use strict';

    var Cif = window.Cif;
    if (!Cif) {
        console.error('CIF modules failed to load');
        return;
    }

    var navPills = document.querySelectorAll('#cifSectionNav .nav-link');
    var sections = document.querySelectorAll('.cif-section');
    var currentSection = 0;

    Cif.showSection = function (idx) {
        if (idx < 0 || idx >= sections.length) return;
        if (idx !== currentSection) {
            Cif.autoSave();
        }
        sections.forEach(function (s) {
            s.classList.add('d-none');
        });
        navPills.forEach(function (p) {
            p.classList.remove('active');
        });
        sections[idx].classList.remove('d-none');
        if (navPills[idx]) navPills[idx].classList.add('active');
        currentSection = idx;
        Cif.updateSpousalEmptyState();
    };

    navPills.forEach(function (pill, i) {
        pill.addEventListener('click', function () {
            Cif.showSection(i);
        });
    });

    function showSectionErrors(result, scope) {
        Cif.clearEligibilityErrors();
        if (result.eligibilityKeys && result.eligibilityKeys.length) {
            Cif.showEligibilityErrors(result.eligibilityKeys);
            Cif.showToastHtml(
                '<strong>Eligibility issue</strong><ul class="mb-0 mt-1"><li>' +
                    Cif.escapeHtml(Cif.getClientCareMessage()) +
                    '</li></ul>',
                'warning',
                12000
            );
            Cif.scrollToValidationTarget(
                result.eligibilityKeys[0],
                scope,
                sections
            );
            return;
        }
        if (result.errors && result.errors.length) {
            Cif.showValidationErrors(result.errors);
            Cif.scrollToValidationTarget(result.errors[0], scope, sections);
        }
    }

    document.querySelectorAll('.cif-nav-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var dir = btn.getAttribute('data-dir');
            if (dir === 'prev') {
                Cif.showSection(currentSection - 1);
                return;
            }
            if (dir !== 'next') return;

            Cif.applyConditionalVisibility();
            var sectionEl = sections[currentSection];
            if (!sectionEl) return;
            var result = Cif.collectValidationErrors(sectionEl, {
                showTimelineMessages: true,
            });
            if (
                (result.eligibilityKeys && result.eligibilityKeys.length) ||
                (result.errors && result.errors.length)
            ) {
                showSectionErrors(result, sectionEl);
                return;
            }
            Cif.showSection(currentSection + 1);
        });
    });

    document.querySelectorAll('.cif-question').forEach(function (q) {
        ['showWhen', 'hiddenWhen', 'requiredWhen', 'lockWhen'].forEach(function (attr) {
            var raw = q.dataset[attr];
            if (!raw) return;
            try {
                var parsed = JSON.parse(raw);
                var rules =
                    attr === 'lockWhen'
                        ? (function () {
                              var r = {};
                              for (var k in parsed) {
                                  if (k !== 'value') r[k] = parsed[k];
                              }
                              return r;
                          })()
                        : parsed;
                Cif.bindDepListeners(rules, Cif.applyConditionalVisibility);
            } catch (e) {
                /* ignore */
            }
        });
    });
    Cif.applyConditionalVisibility();

    /* ================================================================
     *  Finish & Submit — validate all required fields, save + generate
     * ================================================================ */
    const finishBtn = document.getElementById('cifFinishBtn');
    if (finishBtn) {
        finishBtn.addEventListener('click', function () {
            Cif.applyConditionalVisibility();

            var result = Cif.collectValidationErrors(document, {
                showTimelineMessages: true,
            });
            if (
                (result.eligibilityKeys && result.eligibilityKeys.length) ||
                (result.errors && result.errors.length)
            ) {
                showSectionErrors(result, document);
                return;
            }

            /* Step 2: All valid — save + generate PDFs */
            finishBtn.disabled = true;
            finishBtn.innerHTML =
                '<span class="spinner-border spinner-border-sm me-1"></span>Submitting…';

            function resetFinishBtn() {
                finishBtn.disabled = false;
                finishBtn.innerHTML =
                    '<i class="bi bi-flag-fill me-1"></i>Finish & Submit';
            }

            var data = Cif.collectData();
            fetch('/cif', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    data: data,
                    finish: true,
                    csrf_token: Cif.getCsrfToken(),
                }),
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (saveRes) {
                    if (!saveRes.ok) {
                        if (
                            saveRes.eligibility_keys &&
                            saveRes.eligibility_keys.length
                        ) {
                            Cif.showEligibilityErrors(saveRes.eligibility_keys);
                        }
                        var serverErrs =
                            Array.isArray(saveRes.errors) &&
                            saveRes.errors.length
                                ? saveRes.errors
                                : [saveRes.error || 'Save failed'];
                        Cif.showValidationErrors(serverErrs);
                        resetFinishBtn();
                        return null;
                    }

                    var fd = new FormData();
                    fd.append('action', 'generate');
                    fd.append('csrf_token', Cif.getCsrfToken());
                    return fetch('/cif?action=generate', {
                        method: 'POST',
                        body: fd,
                    });
                })
                .then(function (r) {
                    if (!r) return null;
                    return r.json();
                })
                .then(function (genRes) {
                    if (!genRes) return;
                    resetFinishBtn();

                    if (genRes.ok) {
                        var count = genRes.generated
                            ? genRes.generated.length
                            : 0;
                        Cif.showToastHtml(
                            '<i class="bi bi-check-circle-fill me-1"></i>' +
                                '<strong>All forms completed!</strong><br>' +
                                count +
                                ' PDF form(s) generated and saved. ' +
                                'You may close this page or review the generated files below.',
                            'success',
                            10000
                        );
                        var bar = document.getElementById('cifProgressBar');
                        var badge = document.getElementById('cifProgressBadge');
                        if (bar) {
                            bar.style.width = '100%';
                            bar.setAttribute('aria-valuenow', 100);
                        }
                        if (badge) badge.textContent = '100%';

                        setTimeout(function () {
                            window.location.reload();
                        }, 3000);
                    } else {
                        Cif.showToast(
                            genRes.error || 'Generation failed',
                            'danger'
                        );
                    }
                })
                .catch(function (err) {
                    resetFinishBtn();
                    Cif.showToast(
                        err.message || 'Error submitting forms',
                        'danger'
                    );
                });
        });
    }

    // Initial progress update
    Cif.updateProgress();

    // Init all flatpickr date pickers on page load
    Cif.initPickersInContainer(document);
})();
