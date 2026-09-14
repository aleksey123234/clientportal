/**
 * CIF module: timeline
 * Port of CifTimeline.php.
 */
(function (Cif) {
    'use strict';

Cif.ymToInt = function (ym) {
        var p = String(ym).split('-');
        return parseInt(p[0], 10) * 12 + (parseInt(p[1], 10) - 1);
    }

    Cif.intToYm = function (n) {
        var y = Math.floor(n / 12);
        var m = (n % 12) + 1;
        return y + '-' + String(m).padStart(2, '0');
    }

    Cif.validYm = function (ym) {
        return /^\d{4}-(0[1-9]|1[0-2])$/.test(ym);
    }

    Cif.currentYm = function () {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    }

    Cif.resolveTimelineWindows = function (timeline) {
        var forms = Cif.getUserForms();
        var windows = [];
        var since18 = timeline.since_18 || [];
        var needs18 = since18.some(function (f) {
            return forms.indexOf(f) !== -1;
        });
        if (needs18) {
            var dob = Cif.getFieldValue('dob');
            if (!/^\d{4}-\d{2}-\d{2}$/.test(dob)) {
                windows.push({
                    start: '',
                    label: 'age 18',
                    mode: 'since_18',
                    error: 'Please enter Date of Birth before completing this history.',
                });
            } else {
                var parts = dob.split('-');
                var start =
                    parseInt(parts[0], 10) +
                    18 +
                    '-' +
                    parts[1];
                windows.push({ start: start, label: 'age 18', mode: 'since_18' });
            }
        }
        var lastYears = timeline.last_years || {};
        var years = 0;
        Object.keys(lastYears).forEach(function (f) {
            if (forms.indexOf(f) !== -1) {
                years = Math.max(years, parseInt(lastYears[f], 10) || 0);
            }
        });
        if (years > 0) {
            var now = new Date();
            var startDt = new Date(now.getFullYear() - years, now.getMonth(), 1);
            var startYm =
                startDt.getFullYear() +
                '-' +
                String(startDt.getMonth() + 1).padStart(2, '0');
            windows.push({
                start: startYm,
                label: 'last ' + years + ' years',
                mode: 'last_years',
                years: years,
            });
        }
        return windows;
    }

    /** @deprecated use Cif.resolveTimelineWindows */
    Cif.resolveTimelineWindow = function (timeline) {
        var wins = Cif.resolveTimelineWindows(timeline);
        return wins.length ? wins[0] : null;
    }

    Cif.validateTimelineRows = function (rows, windowStart, todayYm) {
        var errors = [];
        var highlights = [];
        function addHl(row, col) {
            for (var i = 0; i < highlights.length; i++) {
                if (highlights[i].row === row && highlights[i].col === col) return;
            }
            highlights.push({ row: row, col: col });
        }
        if (!windowStart || !Cif.validYm(windowStart)) {
            errors.push('Invalid timeline window start.');
            return { errors: errors, highlights: highlights };
        }
        todayYm = todayYm || Cif.currentYm();
        var intervals = [];
        var rowFormatErrors = false;
        rows.forEach(function (row, idx) {
            var from = String(row.from || '').trim();
            var toRaw = String(row.to || '').trim();
            var to = toRaw;
            if (!from && !to) return;
            if (to.toLowerCase() === 'present') to = todayYm;
            var fromOk = Cif.validYm(from);
            var toOk = Cif.validYm(to);
            if (!fromOk || !toOk) {
                errors.push(
                    'Row ' + (idx + 1) + ': From/To must be yyyy-mm (or Present).'
                );
                rowFormatErrors = true;
                if (!fromOk) addHl(idx, 'from');
                if (
                    !toRaw ||
                    (toRaw.toLowerCase() !== 'present' && !Cif.validYm(toRaw))
                ) {
                    addHl(idx, 'to');
                }
                return;
            }
            var a = Cif.ymToInt(from);
            var b = Cif.ymToInt(to);
            if (a > b) {
                errors.push('Row ' + (idx + 1) + ': From must be on or before To.');
                rowFormatErrors = true;
                addHl(idx, 'from');
                addHl(idx, 'to');
                return;
            }
            intervals.push({ a: a, b: b, fromRow: idx, toRow: idx });
        });
        if (!intervals.length) {
            if (!rowFormatErrors) {
                errors.push(
                    'Please add at least one history row covering the required period.'
                );
            }
            return { errors: errors, highlights: highlights };
        }
        intervals.sort(function (x, y) {
            return x.a - y.a;
        });
        var merged = [];
        var gapFound = false;
        intervals.forEach(function (iv) {
            if (!merged.length) {
                merged.push({
                    a: iv.a,
                    b: iv.b,
                    fromRow: iv.fromRow,
                    toRow: iv.toRow,
                });
                return;
            }
            var last = merged[merged.length - 1];
            if (iv.a <= last.b + 1) {
                if (iv.b > last.b) {
                    last.b = iv.b;
                    last.toRow = iv.toRow;
                }
            } else {
                errors.push(
                    'Gap detected between ' +
                        Cif.intToYm(last.b + 1) +
                        ' and ' +
                        Cif.intToYm(iv.a - 1) +
                        '. Periods may touch by one month (e.g. 2013-12 then 2014-01).'
                );
                addHl(last.toRow, 'to');
                addHl(iv.fromRow, 'from');
                gapFound = true;
                merged.push({
                    a: iv.a,
                    b: iv.b,
                    fromRow: iv.fromRow,
                    toRow: iv.toRow,
                });
            }
        });
        if (gapFound || rowFormatErrors) {
            return { errors: errors, highlights: highlights };
        }
        var winStart = Cif.ymToInt(windowStart);
        var winEnd = Cif.ymToInt(todayYm);
        if (merged[0].a > winStart) {
            errors.push('Coverage must start on or before ' + windowStart + '.');
            addHl(merged[0].fromRow, 'from');
        }
        if (merged[merged.length - 1].b < winEnd) {
            errors.push(
                'Coverage must continue through present (' + todayYm + ').'
            );
            addHl(merged[merged.length - 1].toRow, 'to');
        }
        return { errors: errors, highlights: highlights };
    }

    Cif.collectTableRows = function (table) {
        var rows = [];
        table.querySelectorAll('tbody tr').forEach(function (tr) {
            var rowData = {};
            tr.querySelectorAll('.cif-table-input').forEach(function (inp) {
                var col = inp.dataset.col;
                rowData[col] =
                    inp.type === 'checkbox'
                        ? inp.checked
                            ? '1'
                            : ''
                        : inp.value;
            });
            rows.push(rowData);
        });
        return rows;
    }

    Cif.clearTimelineHighlights = function (table) {
        table.querySelectorAll('.cif-table-input.is-invalid, .cif-table-input.cif-timeline-gap').forEach(function (inp) {
            inp.classList.remove('is-invalid', 'cif-timeline-gap');
        });
    }

    Cif.applyTimelineHighlights = function (table, highlights) {
        highlights.forEach(function (h) {
            var tr = table.querySelector('tbody tr[data-row="' + h.row + '"]');
            if (!tr) {
                var rows = table.querySelectorAll('tbody tr');
                tr = rows[h.row] || null;
            }
            if (!tr) return;
            var inp = tr.querySelector(
                '.cif-table-input[data-col="' + h.col + '"]'
            );
            if (inp) {
                inp.classList.add('is-invalid', 'cif-timeline-gap');
            }
        });
    }

    Cif.validateAllTimelines = function (showMessages, root) {
        var allErrors = [];
        var scope = root || document;
        scope.querySelectorAll('.cif-table[data-timeline]').forEach(function (table) {
            var q = table.closest('.cif-question');
            if (q && q.style.display === 'none') return;
            var msgEl = q ? q.querySelector('.cif-timeline-msg') : null;
            var timeline;
            try {
                timeline = JSON.parse(table.dataset.timeline);
            } catch (e) {
                return;
            }
            Cif.clearTimelineHighlights(table);
            var windows = Cif.resolveTimelineWindows(timeline);
            if (!windows.length) {
                if (msgEl) {
                    msgEl.classList.add('d-none');
                    msgEl.textContent = '';
                }
                return;
            }
            var errs = [];
            var highlights = [];
            var hlKey = {};
            windows.forEach(function (win) {
                if (win.error) {
                    errs.push(win.error);
                    return;
                }
                var detail = Cif.validateTimelineRows(
                    Cif.collectTableRows(table),
                    win.start,
                    Cif.currentYm()
                );
                if (detail.errors.length) {
                    if (win.mode === 'since_18') {
                        errs.push(
                            'Please fill continuously from ' +
                                win.start +
                                ' (age 18) to present — no gaps.'
                        );
                    } else if (win.mode === 'last_years') {
                        errs.push(
                            'Please fill continuously for the ' +
                                win.label +
                                ' (from ' +
                                win.start +
                                ') to present — no gaps.'
                        );
                    }
                    detail.errors.forEach(function (e) {
                        errs.push('(' + win.label + ') ' + e);
                    });
                }
                detail.highlights.forEach(function (h) {
                    var k = h.row + ':' + h.col;
                    if (!hlKey[k]) {
                        hlKey[k] = true;
                        highlights.push(h);
                    }
                });
            });
            if (showMessages && highlights.length) {
                Cif.applyTimelineHighlights(table, highlights);
            }
            if (msgEl) {
                if (showMessages && errs.length) {
                    var shown = errs.slice(0, 3).join(' ');
                    if (errs.length > 3) {
                        shown += ' (+' + (errs.length - 3) + ' more)';
                    }
                    msgEl.textContent = shown;
                    msgEl.classList.remove('d-none');
                } else if (!errs.length) {
                    msgEl.classList.add('d-none');
                    msgEl.textContent = '';
                }
            }
            errs.forEach(function (e) {
                allErrors.push(e);
            });
        });
        return allErrors;
    }

    document.addEventListener('change', function (ev) {
        if (
            ev.target &&
            (ev.target.classList.contains('cif-table-input') ||
                ev.target.name === 'dob')
        ) {
            Cif.validateAllTimelines(true);
        }
        if (
            ev.target &&
            ev.target.classList.contains('cif-input') &&
            (ev.target.name === 'us_citizenship' ||
                ev.target.name === 'green_card' ||
                ev.target.name === 'canadian_citizenship')
        ) {
            Cif.clearEligibilityErrors();
        }
    });

})(window.Cif = window.Cif || {});
