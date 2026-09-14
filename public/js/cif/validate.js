/**
 * CIF module: validate
 * Spousal + adult DoB.
 */
(function (Cif) {
    'use strict';

Cif.rowHasAny = function (row) {
        return Object.keys(row).some(function (k) {
            return String(row[k] || '').trim() !== '';
        });
    }

    Cif.formerRowComplete = function (row) {
        var req = [
            'first_name',
            'maiden_name',
            'dob',
            'birthplace',
            'marriage_date_place',
            'termination_date',
            'termination_place',
        ];
        return req.every(function (k) {
            return String(row[k] || '').trim() !== '';
        });
    }

    Cif.currentSpouseComplete = function (includeTermination) {
        var keys = [
            'spouse_current_first_name',
            'spouse_current_maiden_name',
            'spouse_current_dob',
            'spouse_current_birthplace',
            'spouse_current_marriage_date_place',
        ];
        if (includeTermination) {
            keys.push(
                'spouse_current_termination_date',
                'spouse_current_termination_place'
            );
        }
        return keys.every(function (k) {
            return String(Cif.getFieldValue(k) || '').trim() !== '';
        });
    }

    Cif.currentSpouseAnyFilled = function (includeTermination) {
        var keys = [
            'spouse_current_first_name',
            'spouse_current_maiden_name',
            'spouse_current_dob',
            'spouse_current_birthplace',
            'spouse_current_marriage_date_place',
            'spouse_current_alien_number',
        ];
        if (includeTermination) {
            keys.push(
                'spouse_current_termination_date',
                'spouse_current_termination_place'
            );
        }
        return keys.some(function (k) {
            return String(Cif.getFieldValue(k) || '').trim() !== '';
        });
    }

    Cif.getFormerRows = function () {
        var table = document.querySelector(
            '.cif-table[data-key="former_spouses"]'
        );
        if (!table) return [];
        return Cif.collectTableRows(table);
    }

    Cif.validateSpousalFinish = function (missing) {
        var ms = Cif.getFieldValue('marital_status');
        if (!ms || ms === 'Single') return;

        var formers = Cif.getFormerRows();
        formers.forEach(function (row, idx) {
            if (Cif.rowHasAny(row) && !Cif.formerRowComplete(row)) {
                missing.push(
                    'Former spouse #' +
                        (idx + 1) +
                        ': please complete all fields (A-Number optional).'
                );
            }
        });
        var hasCompleteFormer = formers.some(Cif.formerRowComplete);

        if (ms === 'Widowed') {
            var curOk = Cif.currentSpouseComplete(true);
            if (!curOk && !hasCompleteFormer) {
                missing.push(
                    'For Widowed, complete either Current spouse details or at least one full Former spouse entry.'
                );
            }
            return;
        }

        if (ms === 'Married' || ms === 'Common-law') {
            if (
                !Cif.getFieldValue('spouse_current_first_name') ||
                !Cif.getFieldValue('spouse_current_maiden_name') ||
                !Cif.getFieldValue('spouse_current_dob')
            ) {
                missing.push(
                    'Current spouse first name, family/maiden name, and date of birth are required.'
                );
            }
            return;
        }

        if (
            ms === 'Divorced' ||
            ms === 'Legally Separated' ||
            ms === 'Marriage Annulled' ||
            ms === 'Other'
        ) {
            if (
                !Cif.getFieldValue('spouse_current_first_name') ||
                !Cif.getFieldValue('spouse_current_maiden_name') ||
                !Cif.getFieldValue('spouse_current_dob')
            ) {
                missing.push(
                    'Current/recent spouse first name, family/maiden name, and date of birth are required.'
                );
            }
            if (
                !Cif.getFieldValue('spouse_current_termination_date') ||
                !Cif.getFieldValue('spouse_current_termination_place')
            ) {
                missing.push('Marriage termination date and place are required.');
            }
            if (!hasCompleteFormer) {
                missing.push(
                    'Please add at least one complete former spouse entry.'
                );
            }
        }
    }

    /** Reject DoB values for anyone under 18 (applicant + spouses). */
    Cif.isUnder18Ymd = function (ymd) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(ymd)) return false;
        var parts = ymd.split('-');
        var dob = new Date(+parts[0], +parts[1] - 1, +parts[2]);
        if (isNaN(dob.getTime())) return false;
        var cutoff = Cif.maxAdultDobDate();
        cutoff.setHours(23, 59, 59, 999);
        return dob > cutoff;
    }

    /**
     * @param {string[]} missing
     * @param {ParentNode} [root]
     */
    Cif.validateAdultDobFields = function (missing, root) {
        var scope = root || document;
        var clientDobQ = scope.querySelector('.cif-question[data-key="dob"]');
        if (clientDobQ && clientDobQ.style.display !== 'none') {
            var clientDob = Cif.getFieldValue('dob');
            if (clientDob && Cif.isUnder18Ymd(clientDob)) {
                missing.push(
                    'Date of Birth: applicant must be at least 18 years old.'
                );
            }
        }

        var spouseQ = scope.querySelector(
            '.cif-question[data-key="spouse_current_dob"]'
        );
        if (spouseQ && spouseQ.style.display !== 'none') {
            var sd = Cif.getFieldValue('spouse_current_dob');
            if (sd && Cif.isUnder18Ymd(sd)) {
                missing.push(
                    'Current spouse date of birth: spouse must be at least 18 years old.'
                );
            }
        }

        var formerTable = scope.querySelector(
            '.cif-table[data-key="former_spouses"]'
        );
        if (formerTable) {
            var fq = formerTable.closest('.cif-question');
            if (!fq || fq.style.display !== 'none') {
                Cif.getFormerRows().forEach(function (row, idx) {
                    if (row.dob && Cif.isUnder18Ymd(row.dob)) {
                        missing.push(
                            'Former spouse #' +
                                (idx + 1) +
                                ': date of birth — must be at least 18 years old.'
                        );
                    }
                });
            }
        }
    }

    /**
     * Collect Finish / section validation messages under `root` (section or document).
     * @param {ParentNode} [root]
     * @param {{ showTimelineMessages?: boolean }} [opts]
     * @returns {{ errors: string[], eligibilityKeys: string[] }}
     */
    Cif.collectValidationErrors = function (root, opts) {
        var scope = root || document;
        opts = opts || {};
        var showTimeline = opts.showTimelineMessages !== false;
        var missing = [];

        scope.querySelectorAll('.cif-question').forEach(function (q) {
            if (q.style.display === 'none') return;

            var isRequired = false;
            if (q.dataset.requiredWhen) {
                try {
                    isRequired = Cif.rulesMatch(
                        JSON.parse(q.dataset.requiredWhen)
                    );
                } catch (e) {
                    isRequired = false;
                }
            } else {
                isRequired = !!q.querySelector('[required], .cif-req-star');
            }
            if (!isRequired) return;

            var key = q.dataset.key;
            var label = q.querySelector('.form-label');
            var labelText = label
                ? label.textContent.replace(/\*/g, '').trim()
                : key;

            var table = q.querySelector('.cif-table');
            if (table) {
                var hasData = false;
                table
                    .querySelectorAll('tbody tr .cif-table-input')
                    .forEach(function (inp) {
                        if (inp.type === 'checkbox' && inp.checked)
                            hasData = true;
                        if (
                            inp.type === 'text' &&
                            inp.value.trim() &&
                            inp.value !== 'Present'
                        )
                            hasData = true;
                    });
                if (!hasData) missing.push(labelText);
                return;
            }

            var inp = q.querySelector('.cif-input');
            if (!inp) return;
            if (inp.type === 'radio') {
                var checked = q.querySelector('.cif-input:checked');
                if (!checked) missing.push(labelText);
            } else if (inp.type === 'checkbox') {
                if (!inp.checked) missing.push(labelText);
            } else if (!inp.value || !inp.value.trim()) {
                missing.push(labelText);
            }
        });

        var timelineErrors = Cif.validateAllTimelines(showTimeline, scope);
        timelineErrors.forEach(function (e) {
            if (missing.indexOf(e) === -1) missing.push(e);
        });

        scope.querySelectorAll('.cif-optional-day-wrap').forEach(function (wrap) {
            var q = wrap.closest('.cif-question');
            if (q && q.style.display === 'none') return;
            var dayInp = wrap.querySelector('.cif-input');
            if (!dayInp) return;
            var v = (dayInp.value || '').trim();
            if (!v) return;
            if (!/^\d{4}-(0[1-9]|1[0-2])(-([0-2]\d|3[01]))?$/.test(v)) {
                var label = q ? q.querySelector('.form-label') : null;
                var labelText = label
                    ? label.textContent.replace(/\*/g, '').trim()
                    : dayInp.name;
                missing.push(labelText + ' must be yyyy-mm or yyyy-mm-dd');
            }
        });

        scope.querySelectorAll('.cif-table').forEach(function (table) {
            var q = table.closest('.cif-question');
            if (q && q.style.display === 'none') return;
            table.querySelectorAll('.cif-table-input').forEach(function (cell) {
                if (cell.readOnly && cell.value === 'Present') return;
                var col = cell.dataset.col || '';
                var colType = cell.dataset.colType || '';
                var isYm =
                    col === 'from' || col === 'to' || colType === 'month';
                if (!isYm) return;
                var cellV = (cell.value || '').trim();
                if (!cellV || cellV.toLowerCase() === 'present') return;
                if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(cellV)) {
                    missing.push(
                        'Invalid month date "' +
                            cellV +
                            '" — use yyyy-mm (datepicker).'
                    );
                }
            });
        });

        if (
            scope.querySelector(
                '.cif-question[data-key="marital_status"], .cif-question[data-key="former_spouses"], .cif-question[data-key="spouse_current_first_name"]'
            )
        ) {
            Cif.validateSpousalFinish(missing);
        }

        Cif.validateAdultDobFields(missing, scope);

        scope
            .querySelectorAll('.cif-question[data-validate]')
            .forEach(function (q) {
                if (q.style.display === 'none') return;
                var vInp = q.querySelector('.cif-input:not([type="radio"])');
                if (!vInp || !vInp.value.trim()) return;
                var rules;
                try {
                    rules = JSON.parse(q.dataset.validate);
                } catch (e) {
                    return;
                }
                var val = vInp.value.trim();
                var label = q.querySelector('.form-label');
                var labelText = label
                    ? label.textContent.replace(/\*/g, '').trim()
                    : q.dataset.key;
                if (
                    rules.pattern === 'name' &&
                    !/^[A-Za-zÀ-ÖØ-öø-ÿ' \-]+$/.test(val)
                ) {
                    missing.push(labelText + ' (letters only)');
                }
                if (rules.type === 'phone') {
                    var digits = val.replace(/\D/g, '');
                    if (digits.length < 7 || digits.length > 20) {
                        missing.push(labelText + ' (invalid phone)');
                    }
                }
                if (rules.type === 'ssn4' && !/^\d{4}$/.test(val)) {
                    missing.push(labelText + ' (4 digits)');
                }
                if (rules.maxLength && val.length > rules.maxLength) {
                    missing.push(labelText + ' (too long)');
                }
            });

        var eligibilityKeys = [];
        if (
            scope.querySelector(
                '.cif-question[data-key="us_citizenship"], .cif-question[data-key="green_card"], .cif-question[data-key="canadian_citizenship"]'
            )
        ) {
            eligibilityKeys = Cif.validateCitizenshipEligibility().filter(
                function (k) {
                    return !!scope.querySelector(
                        '.cif-question[data-key="' + k + '"]'
                    );
                }
            );
        }

        return { errors: missing, eligibilityKeys: eligibilityKeys };
    }

    /**
     * Highlight / scroll to a question matching the first validation message.
     * @param {string} message
     * @param {ParentNode} [scope]
     * @param {NodeListOf<Element>|Element[]} [sectionList]
     */
    Cif.scrollToValidationTarget = function (message, scope, sectionList) {
        var root = scope || document;
        var needle = (message || '').trim();
        if (!needle) return;
        var questions = root.querySelectorAll('.cif-question');
        var target = null;
        for (var i = 0; i < questions.length; i++) {
            var q = questions[i];
            if (q.style.display === 'none') continue;
            if (q.dataset.key && q.dataset.key === needle) {
                target = q;
                break;
            }
            var lbl = q.querySelector('.form-label');
            var labelText = lbl
                ? lbl.textContent.replace(/\*/g, '').trim()
                : q.dataset.key || '';
            if (
                labelText === needle ||
                needle.indexOf(labelText) === 0 ||
                (labelText && needle.indexOf(labelText) !== -1)
            ) {
                target = q;
                break;
            }
        }
        if (!target) {
            target = root.querySelector(
                '.cif-question .is-invalid, .cif-question .cif-timeline-gap'
            );
            if (target) target = target.closest('.cif-question');
        }
        if (!target) return;

        if (sectionList && sectionList.length) {
            var sec = target.closest('.cif-section');
            if (sec && typeof Cif.showSection === 'function') {
                var secIdx = Array.prototype.indexOf.call(sectionList, sec);
                if (secIdx >= 0) Cif.showSection(secIdx);
            }
        }

        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target.style.outline = '2px solid #ffc107';
        setTimeout(function () {
            target.style.outline = '';
        }, 3000);
    }

})(window.Cif = window.Cif || {});
