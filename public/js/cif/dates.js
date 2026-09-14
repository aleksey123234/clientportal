/**
 * CIF module: dates
 * Pickers via CifDatepicker.
 */
(function (Cif) {
    'use strict';

var hasFlatpickr = typeof flatpickr !== 'undefined';
    var DP = window.CifDatepicker || {};
    Cif.parseYmd = function (ymd) {
        if (!ymd) return null;
        var parts = ymd.split('-');
        if (parts.length !== 3) return null;
        var d = new Date(+parts[0], +parts[1] - 1, +parts[2]);
        return isNaN(d.getTime()) ? null : d;
    }

    /** Format Date → Y-m-d */
    Cif.toYmd = function (d) {
        return (
            d.getFullYear() +
            '-' +
            String(d.getMonth() + 1).padStart(2, '0') +
            '-' +
            String(d.getDate()).padStart(2, '0')
        );
    }

    /** Format Date → Y-m (month pickers) */
    Cif.toYm = function (d) {
        return (
            d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0')
        );
    }

    /** True for applicant / spouse birth-date fields (must be 18+). */
    Cif.isAdultDobField = function (el) {
        if (!el) return false;
        if (el.name === 'dob' || el.name === 'spouse_current_dob') return true;
        /* Former spouses table column */
        return el.dataset.col === 'dob';
    }

    Cif.maxAdultDobDate = function () {
        var d = new Date();
        d.setFullYear(d.getFullYear() - 18);
        return d;
    }

    /**
     * Init flatpickr on a full-date field (DoB, intended travel date, etc.)
     */
    Cif.initDatePicker = function (el) {
        if (!hasFlatpickr || !el || el._flatpickr) return;
        var isAdultDob = Cif.isAdultDobField(el);
        var maxDate = isAdultDob ? Cif.maxAdultDobDate() : undefined;
        var curYear = new Date().getFullYear();

        flatpickr(el, {
            dateFormat: 'Y-m-d',
            allowInput: false,
            disableMobile: true,
            maxDate: maxDate,
            monthSelectorType: 'dropdown',
            plugins: [
                DP.monthDropdownPlugin(),
                DP.yearDropdownPlugin(
                    isAdultDob ? 1920 : 1950,
                    isAdultDob ? curYear - 18 : curYear + 5
                ),
            ],
            defaultDate: Cif.parseYmd(el.value),
            onChange: function (selectedDates) {
                el.value = selectedDates.length ? Cif.toYmd(selectedDates[0]) : '';
                Cif.updateProgress();
            },
        });
    }

    /** Accept typed/pasted month values → canonical yyyy-mm or null. */
    Cif.parseYmInput = function (str) {
        var s = String(str || '').trim();
        if (!s) return '';
        if (/^\d{4}-(0[1-9]|1[0-2])$/.test(s)) return s;
        var m;
        if ((m = s.match(/^(\d{4})[\/.\-](\d{1,2})$/))) {
            var mo = parseInt(m[2], 10);
            if (mo >= 1 && mo <= 12) {
                return m[1] + '-' + String(mo).padStart(2, '0');
            }
        }
        if ((m = s.match(/^(\d{1,2})[\/.\-](\d{4})$/))) {
            var mo2 = parseInt(m[1], 10);
            if (mo2 >= 1 && mo2 <= 12) {
                return m[2] + '-' + String(mo2).padStart(2, '0');
            }
        }
        return null;
    }

    /** Cap yyyy-mm at current month (addresses / employment). */
    Cif.clampYmToCurrent = function (ym) {
        if (!ym) return ym;
        var maxYm = Cif.toYm(DP.monthPickerMaxDate());
        return ym > maxYm ? maxYm : ym;
    }

    /**
     * Allow typing/paste into a month field; normalize on blur.
     * opts.clampToCurrent — reject months after current month.
     */
    Cif.bindMonthInputTyping = function (el, onNormalized, opts) {
        opts = opts || {};
        el.addEventListener('blur', function () {
            var raw = String(el.value || '').trim();
            if (!raw) {
                if (el._flatpickr) el._flatpickr.clear();
                if (typeof onNormalized === 'function') onNormalized('');
                Cif.updateProgress();
                Cif.validateAllTimelines(true);
                return;
            }
            var parsed = Cif.parseYmInput(raw);
            if (parsed) {
                if (opts.clampToCurrent) parsed = Cif.clampYmToCurrent(parsed);
                el.value = parsed;
                if (el._flatpickr) {
                    el._flatpickr.setDate(parsed + '-01', false);
                }
                if (typeof onNormalized === 'function') onNormalized(parsed);
            }
            Cif.updateProgress();
            Cif.validateAllTimelines(true);
        });
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                el.blur();
            }
        });
    }

    /**
     * Init flatpickr as a month-only picker for address/employment yyyy-mm columns.
     * Max = current month (no future). Typing/paste allowed. 'Present' skipped.
     */
    Cif.initMonthPicker = function (el) {
        if (!hasFlatpickr || !el || el._flatpickr) return;
        if (el.value === 'Present') return;

        var hasMonthPlugin = typeof monthSelectPlugin !== 'undefined';
        var maxDate = DP.monthPickerMaxDate();
        var plugins = DP.buildMonthSelectPlugins();

        el.readOnly = false;
        el.placeholder = el.placeholder || 'yyyy-mm';

        flatpickr(el, {
            dateFormat: 'Y-m',
            allowInput: true,
            clickOpens: true,
            disableMobile: true,
            maxDate: maxDate,
            monthSelectorType: 'dropdown',
            plugins: plugins,
            defaultDate: (function () {
                if (!el.value) return undefined;
                var p = el.value.split('-');
                if (p.length >= 2 && /^\d{4}$/.test(p[0])) {
                    var d = new Date(+p[0], +p[1] - 1, 1);
                    return d > maxDate ? maxDate : d;
                }
                return undefined;
            })(),
            onReady: function (_, __, fp) {
                fp.calendarContainer.classList.add('cif-month-picker-cal');
                if (!hasMonthPlugin) {
                    fp.calendarContainer.classList.add(
                        'cif-month-picker-fallback'
                    );
                }
                DP.syncMonthSelectYear(fp);
            },
            onMonthChange: function (_, __, fp) {
                DP.syncMonthSelectYear(fp);
            },
            onYearChange: function (_, __, fp) {
                DP.syncMonthSelectYear(fp);
            },
            onOpen: function (_, __, fp) {
                DP.syncMonthSelectYear(fp);
            },
            onChange: function (selectedDates) {
                el.value = selectedDates.length ? Cif.toYm(selectedDates[0]) : '';
                Cif.updateProgress();
                Cif.validateAllTimelines(true);
            },
        });

        Cif.bindMonthInputTyping(el, null, { clampToCurrent: true });
    }

    /** Days in month for yyyy-mm (1-based month). */
    Cif.daysInYm = function (ym) {
        var p = String(ym || '').split('-');
        if (p.length < 2) return 31;
        var y = parseInt(p[0], 10);
        var m = parseInt(p[1], 10);
        if (!y || !m) return 31;
        return new Date(y, m, 0).getDate();
    }

    Cif.composeOptionalDayValue = function (wrap) {
        var inp = wrap.querySelector('.cif-input.cif-optional-day-month');
        var sel = wrap.querySelector('.cif-optional-day-select');
        if (!inp) return;
        var ym = (inp.dataset.ym || '').trim();
        if (!ym || !/^\d{4}-(0[1-9]|1[0-2])$/.test(ym)) {
            inp.value = '';
            return;
        }
        var day = sel ? String(sel.value || '').trim() : '';
        if (day) {
            var maxD = Cif.daysInYm(ym);
            var dNum = parseInt(day, 10);
            if (dNum < 1 || dNum > maxD) {
                if (sel) sel.value = '';
                inp.value = ym;
            } else {
                inp.value = ym + '-' + (dNum < 10 ? '0' + dNum : String(dNum));
            }
        } else {
            inp.value = ym;
        }
    }

    /**
     * Month picker + optional day select → YYYY-MM or YYYY-MM-DD.
     */
    Cif.initOptionalDayPicker = function (wrap) {
        if (!wrap || wrap._optionalDayInit) return;
        var inp = wrap.querySelector('.cif-input.cif-optional-day-month');
        var sel = wrap.querySelector('.cif-optional-day-select');
        if (!inp || !sel) return;
        wrap._optionalDayInit = true;

        var v = (inp.value || '').trim();
        var ym = '';
        var day = '';
        if (/^\d{4}-\d{2}-\d{2}$/.test(v)) {
            ym = v.slice(0, 7);
            day = String(parseInt(v.slice(8), 10));
        } else if (/^\d{4}-\d{2}$/.test(v)) {
            ym = v;
        }
        inp.dataset.ym = ym;
        if (day) {
            sel.value = day;
        } else {
            sel.value = '';
        }
        if (ym) {
            Cif.composeOptionalDayValue(wrap);
        }

        if (hasFlatpickr && !inp._flatpickr) {
            var hasMonthPlugin = typeof monthSelectPlugin !== 'undefined';
            var curYear = new Date().getFullYear();
            var plugins = DP.buildMonthSelectPlugins({
                yearRange: { min: 1920, max: curYear + 5 },
            });
            inp.readOnly = false;
            inp.placeholder = inp.placeholder || 'yyyy-mm';
            flatpickr(inp, {
                dateFormat: 'Y-m',
                allowInput: true,
                clickOpens: true,
                disableMobile: true,
                monthSelectorType: 'dropdown',
                plugins: plugins,
                defaultDate: ym
                    ? (function () {
                          var p = ym.split('-');
                          return new Date(+p[0], +p[1] - 1, 1);
                      })()
                    : undefined,
                onReady: function (_, __, fp) {
                    fp.calendarContainer.classList.add('cif-month-picker-cal');
                    if (!hasMonthPlugin) {
                        fp.calendarContainer.classList.add(
                            'cif-month-picker-fallback'
                        );
                    }
                    DP.syncMonthSelectYear(fp);
                },
                onMonthChange: function (_, __, fp) {
                    DP.syncMonthSelectYear(fp);
                },
                onYearChange: function (_, __, fp) {
                    DP.syncMonthSelectYear(fp);
                },
                onOpen: function (_, __, fp) {
                    DP.syncMonthSelectYear(fp);
                },
                onChange: function (selectedDates) {
                    if (selectedDates.length) {
                        inp.dataset.ym = Cif.toYm(selectedDates[0]);
                    } else {
                        inp.dataset.ym = '';
                    }
                    Cif.composeOptionalDayValue(wrap);
                    Cif.updateProgress();
                },
            });
            Cif.bindMonthInputTyping(inp, function (parsed) {
                inp.dataset.ym = parsed || '';
                Cif.composeOptionalDayValue(wrap);
            });
        }

        sel.addEventListener('change', function () {
            Cif.composeOptionalDayValue(wrap);
            Cif.updateProgress();
            Cif.sizeOptionalDaySelect(sel);
        });
        Cif.sizeOptionalDaySelect(sel);
    }

    /** Grow day select to fit current option label. */
    Cif.sizeOptionalDaySelect = function (sel) {
        if (!sel) return;
        var opt = sel.options[sel.selectedIndex];
        var text = opt ? opt.text : '';
        var probe = document.createElement('span');
        probe.style.cssText =
            'position:absolute;visibility:hidden;white-space:nowrap;font:inherit';
        var cs = window.getComputedStyle(sel);
        probe.style.font = cs.font;
        probe.style.letterSpacing = cs.letterSpacing;
        probe.textContent = text || 'Day (optional)';
        document.body.appendChild(probe);
        var w = Math.ceil(probe.getBoundingClientRect().width) + 48;
        document.body.removeChild(probe);
        sel.style.width = Math.max(w, 176) + 'px';
    }

    /** Init all date pickers in a container (for dynamic rows) */
    Cif.initPickersInContainer = function (container) {
        if (!hasFlatpickr) return;
        container.querySelectorAll('.cif-date-picker').forEach(Cif.initDatePicker);
        container.querySelectorAll('.cif-month-picker').forEach(Cif.initMonthPicker);
        container
            .querySelectorAll('.cif-optional-day-wrap')
            .forEach(Cif.initOptionalDayPicker);
        container
            .querySelectorAll(
                '.cif-table-input[data-col="from"], .cif-table-input[data-col="to"], .cif-table-input[data-col-type="month"]'
            )
            .forEach(function (inp) {
                if (inp.type !== 'text') return;
                if (inp.value === 'Present' && inp.readOnly) return;
                if (!inp.classList.contains('cif-month-picker')) {
                    inp.classList.add('cif-month-picker');
                }
                Cif.initMonthPicker(inp);
            });
        container
            .querySelectorAll(
                '.cif-table-input[data-col="date"], .cif-table-input[data-col-type="date"]'
            )
            .forEach(function (inp) {
                if (inp.type !== 'text' || inp.readOnly) return;
                if (!inp.classList.contains('cif-date-picker')) {
                    inp.classList.add('cif-date-picker');
                }
                Cif.initDatePicker(inp);
            });
    }

})(window.Cif = window.Cif || {});
