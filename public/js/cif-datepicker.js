/**
 * CIF Flatpickr plugins — month/year header dropdowns + monthSelect sync.
 * Loaded before cif.js.
 */
(function (global) {
    'use strict';

    /**
     * Shared floating list panel for calendar header (month / year).
     * Appended inside calendarContainer, position:fixed, opens UPWARD.
     */
    function openHeaderListPanel(fp, btn, opts) {
        var existing = fp.calendarContainer.querySelector('.fp-year-panel');
        if (existing) {
            existing.parentNode.removeChild(existing);
            if (fp._cifOpenHeaderBtn === btn) {
                fp._cifOpenHeaderBtn = null;
                btn.setAttribute('aria-expanded', 'false');
                return null;
            }
        }
        fp.calendarContainer
            .querySelectorAll('.fp-header-btn[aria-expanded="true"]')
            .forEach(function (b) {
                b.setAttribute('aria-expanded', 'false');
            });

        var panel = document.createElement('div');
        panel.className = 'fp-year-panel fp-year-panel-up';
        panel.setAttribute('role', 'listbox');
        panel.setAttribute('aria-label', opts.label || 'Select');

        opts.items.forEach(function (it) {
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'fp-year-panel-item';
            item.setAttribute('role', 'option');
            item.textContent = it.label;
            if (it.selected) {
                item.classList.add('is-selected');
                item.setAttribute('aria-selected', 'true');
            }
            item.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (panel.parentNode) panel.parentNode.removeChild(panel);
                fp._cifOpenHeaderBtn = null;
                btn.setAttribute('aria-expanded', 'false');
                opts.onPick(it.value);
            });
            panel.appendChild(item);
        });

        fp.calendarContainer.appendChild(panel);
        btn.setAttribute('aria-expanded', 'true');
        fp._cifOpenHeaderBtn = btn;

        var rect = btn.getBoundingClientRect();
        var rowH = 28;
        var maxVis = opts.maxVisible || 8;
        var maxH = Math.min(opts.items.length, maxVis) * rowH;
        panel.style.position = 'fixed';
        panel.style.zIndex = '100000';
        panel.style.left = Math.round(rect.left + rect.width / 2) + 'px';
        panel.style.maxHeight = maxH + 'px';
        panel.style.top = Math.round(rect.top - 4) + 'px';
        panel.style.transform = 'translate(-50%, -100%)';

        var selected = panel.querySelector('.is-selected');
        if (selected) selected.scrollIntoView({ block: 'center' });

        return panel;
    }

    function closeHeaderListPanel(fp) {
        if (!fp || !fp.calendarContainer) return;
        var existing = fp.calendarContainer.querySelector('.fp-year-panel');
        if (existing) existing.parentNode.removeChild(existing);
        fp.calendarContainer
            .querySelectorAll('.fp-header-btn[aria-expanded="true"]')
            .forEach(function (b) {
                b.setAttribute('aria-expanded', 'false');
            });
        fp._cifOpenHeaderBtn = null;
    }

    /**
     * Month dropdown for FULL date pickers only (DoB, marriage, etc.).
     * Same button + upward panel style as year. Not used with monthSelectPlugin.
     */
    function monthDropdownPlugin() {
        return function (fp) {
            var btn = null;
            var monthNames = null;

            function monthLabel(i) {
                if (!monthNames) {
                    monthNames =
                        (fp.l10n && fp.l10n.months && fp.l10n.months.shorthand) ||
                        [
                            'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                            'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
                        ];
                }
                return monthNames[i];
            }

            function syncMonth() {
                if (btn) btn.textContent = monthLabel(fp.currentMonth);
            }

            function build() {
                if (
                    fp.calendarContainer.classList.contains(
                        'flatpickr-monthSelect-theme-light'
                    ) ||
                    fp.calendarContainer.querySelector(
                        '.flatpickr-monthSelect-months'
                    )
                ) {
                    return;
                }

                var currentMonth = fp.calendarContainer.querySelector(
                    '.flatpickr-current-month'
                );
                if (!currentMonth) return;
                var nativeSel = currentMonth.querySelector(
                    'select.flatpickr-monthDropdown-months'
                );
                if (!nativeSel) return;
                if (currentMonth.querySelector('.fp-month-btn')) return;

                btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'fp-header-btn fp-month-btn';
                btn.setAttribute('aria-label', 'Month');
                btn.setAttribute('aria-haspopup', 'listbox');
                btn.setAttribute('aria-expanded', 'false');
                syncMonth();
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var items = [];
                    for (var i = 0; i < 12; i++) {
                        items.push({
                            value: i,
                            label: monthLabel(i),
                            selected: i === fp.currentMonth,
                        });
                    }
                    openHeaderListPanel(fp, btn, {
                        label: 'Month',
                        items: items,
                        maxVisible: 8,
                        onPick: function (monthIdx) {
                            fp.changeMonth(monthIdx, false);
                            syncMonth();
                        },
                    });
                });

                nativeSel.style.display = 'none';
                nativeSel.setAttribute('aria-hidden', 'true');
                currentMonth.insertBefore(btn, currentMonth.firstChild);

                fp.calendarContainer.addEventListener(
                    'click',
                    function (e) {
                        var panel = fp.calendarContainer.querySelector(
                            '.fp-year-panel'
                        );
                        if (!panel) return;
                        if (
                            e.target.closest('.fp-header-btn') ||
                            panel.contains(e.target)
                        ) {
                            return;
                        }
                        closeHeaderListPanel(fp);
                    },
                    true
                );
            }

            return {
                onReady: build,
                onOpen: function () {
                    syncMonth();
                    closeHeaderListPanel(fp);
                },
                onClose: function () {
                    closeHeaderListPanel(fp);
                },
                onMonthChange: syncMonth,
                onYearChange: syncMonth,
                onDestroy: function () {
                    closeHeaderListPanel(fp);
                },
            };
        };
    }

    /**
     * Year button + list (~8 rows) opening UPWARD.
     */
    function yearDropdownPlugin(minYear, maxYear, opts) {
        opts = opts || {};
        return function (fp) {
            var btn = null;

            function syncYear() {
                if (btn) btn.textContent = String(fp.currentYear);
            }

            function build() {
                var wrapper = fp.calendarContainer.querySelector(
                    '.flatpickr-current-month .numInputWrapper'
                );
                if (!wrapper) return;
                var oldInput = wrapper.querySelector('input.cur-year');
                if (!oldInput) return;
                if (wrapper.querySelector('.fp-year-btn')) return;

                btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'fp-header-btn fp-year-btn';
                btn.setAttribute('aria-label', 'Year');
                btn.setAttribute('aria-haspopup', 'listbox');
                btn.setAttribute('aria-expanded', 'false');
                btn.textContent = String(fp.currentYear);
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var items = [];
                    for (var y = maxYear; y >= minYear; y--) {
                        items.push({
                            value: y,
                            label: String(y),
                            selected: y === fp.currentYear,
                        });
                    }
                    openHeaderListPanel(fp, btn, {
                        label: 'Year',
                        items: items,
                        maxVisible: 8,
                        onPick: function (yr) {
                            fp.changeYear(yr);
                            syncYear();
                            if (typeof opts.afterChange === 'function') {
                                opts.afterChange(fp);
                            }
                        },
                    });
                });

                oldInput.style.display = 'none';
                wrapper
                    .querySelectorAll('.arrowUp, .arrowDown, select.fp-year-select')
                    .forEach(function (a) {
                        a.style.display = 'none';
                    });
                wrapper.appendChild(btn);

                fp.calendarContainer.addEventListener(
                    'click',
                    function (e) {
                        var panel = fp.calendarContainer.querySelector(
                            '.fp-year-panel'
                        );
                        if (!panel) return;
                        if (
                            e.target.closest('.fp-header-btn') ||
                            panel.contains(e.target)
                        ) {
                            return;
                        }
                        closeHeaderListPanel(fp);
                    },
                    true
                );
            }

            return {
                onReady: build,
                onOpen: function () {
                    syncYear();
                    closeHeaderListPanel(fp);
                },
                onClose: function () {
                    closeHeaderListPanel(fp);
                },
                onMonthChange: syncYear,
                onYearChange: syncYear,
                onDestroy: function () {
                    closeHeaderListPanel(fp);
                },
            };
        };
    }

    /**
     * Keep monthSelectPlugin month cells on the same year as the header dropdown.
     * monthSelect only rebuilds on prev/next nav clicks, not on changeYear alone.
     */
    function syncMonthSelectYear(fp) {
        if (!fp || !fp.rContainer) return;
        var months = fp.rContainer.querySelectorAll(
            '.flatpickr-monthSelect-month'
        );
        if (!months.length) return;
        var y = fp.currentYear;
        months.forEach(function (month, i) {
            if (month.dateObj) {
                month.dateObj.setFullYear(y);
                month.dateObj.setMonth(i);
            }
            var disabled = false;
            if (fp.config.minDate && month.dateObj < fp.config.minDate) {
                disabled = true;
            }
            if (fp.config.maxDate && month.dateObj > fp.config.maxDate) {
                disabled = true;
            }
            month.classList.toggle('flatpickr-disabled', disabled);
        });
        if (fp.prevMonthNav) {
            if (
                fp.config.minDate &&
                y === fp.config.minDate.getFullYear()
            ) {
                fp.prevMonthNav.classList.add('flatpickr-disabled');
            } else {
                fp.prevMonthNav.classList.remove('flatpickr-disabled');
            }
        }
        if (fp.nextMonthNav) {
            if (
                fp.config.maxDate &&
                y === fp.config.maxDate.getFullYear()
            ) {
                fp.nextMonthNav.classList.add('flatpickr-disabled');
            } else {
                fp.nextMonthNav.classList.remove('flatpickr-disabled');
            }
        }
    }

    /** Year range for address/employment month pickers. */
    function monthPickerYearRange() {
        return { min: 1920, max: new Date().getFullYear() };
    }

    /** First day of current month — no future months for addresses/employment. */
    function monthPickerMaxDate() {
        var d = new Date();
        return new Date(d.getFullYear(), d.getMonth(), 1);
    }

    /**
     * Build monthSelect + year dropdown plugins for yyyy-mm fields.
     * opts.yearRange — override { min, max }; default address/employment range.
     */
    function buildMonthSelectPlugins(opts) {
        opts = opts || {};
        var yr = opts.yearRange || monthPickerYearRange();
        var plugins = [];
        if (typeof monthSelectPlugin !== 'undefined') {
            plugins.push(
                new monthSelectPlugin({
                    shorthand: true,
                    dateFormat: 'Y-m',
                    altFormat: 'F Y',
                })
            );
        }
        plugins.push(
            yearDropdownPlugin(yr.min, yr.max, {
                afterChange: syncMonthSelectYear,
            })
        );
        return plugins;
    }

    global.CifDatepicker = {
        monthDropdownPlugin: monthDropdownPlugin,
        yearDropdownPlugin: yearDropdownPlugin,
        syncMonthSelectYear: syncMonthSelectYear,
        monthPickerYearRange: monthPickerYearRange,
        monthPickerMaxDate: monthPickerMaxDate,
        buildMonthSelectPlugins: buildMonthSelectPlugins,
    };
})(window);
