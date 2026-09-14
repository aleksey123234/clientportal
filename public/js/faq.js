/* ═══════════════════════════════════════════════════════════════
   FAQ PAGE — keyword search, multi-select section filter pills,
   visible count indicator, no-results message
   ═══════════════════════════════════════════════════════════════ */
(function () {
    var searchInput = document.getElementById('faqSearch');
    var clearBtn = document.getElementById('faqSearchClear');
    var filterPills = document.querySelectorAll('.faq-filter-pill');
    var sectionDivs = document.querySelectorAll('.faq-section');
    var faqItems = document.querySelectorAll('.faq-item');
    var visibleCount = document.getElementById('faqVisibleCount');
    var filterStatus = document.getElementById('faqFilterStatus');
    var noResults = document.getElementById('faqNoResults');

    /* Track which sections are active */
    var activeSections = new Set(['all']);

    /* ── Filter pills ─────────────────────────────────────────── */
    filterPills.forEach(function (pill) {
        pill.addEventListener('click', function () {
            var sec = this.dataset.section;

            if (sec === 'all') {
                activeSections = new Set(['all']);
            } else {
                activeSections.delete('all');
                if (activeSections.has(sec)) {
                    activeSections.delete(sec);
                } else {
                    activeSections.add(sec);
                }
                if (activeSections.size === 0) {
                    activeSections = new Set(['all']);
                }
            }

            updatePillStyles();
            applyFilters();
        });
    });

    function updatePillStyles() {
        filterPills.forEach(function (pill) {
            var sec = pill.dataset.section;
            var isActive = activeSections.has('all')
                ? sec === 'all'
                : activeSections.has(sec);

            /* Custom-colored pill (has data-faq-rgb) */
            var rgb = pill.dataset.faqRgb;
            var rgbText = pill.dataset.faqRgbText;
            var rgbTextInactive =
                pill.dataset.faqRgbTextInactive || rgbText || '#333';

            if (rgb) {
                if (isActive) {
                    pill.classList.add('is-active');
                    pill.style.background = rgb;
                    pill.style.color = rgbText || '#333';
                    pill.style.borderColor = rgb;
                } else {
                    pill.classList.remove('is-active');
                    pill.style.background = 'transparent';
                    pill.style.color = rgbTextInactive;
                    pill.style.borderColor = rgb;
                }
                return;
            }

            /* Bootstrap-colored pill */
            if (isActive) {
                pill.classList.add('is-active');
                var color = pill.className.match(/btn-outline-(\w+)/);
                if (color) {
                    pill.classList.remove('btn-outline-' + color[1]);
                    pill.classList.add('btn-' + color[1]);
                }
            } else {
                pill.classList.remove('is-active');
                if (sec === 'all') {
                    pill.classList.remove('btn-primary');
                    pill.classList.add('btn-outline-primary');
                } else {
                    var color = pill.className.match(
                        /btn-(?!outline-)(?!sm)(\w+)/
                    );
                    if (color) {
                        pill.classList.add('btn-outline-' + color[1]);
                        pill.classList.remove('btn-' + color[1]);
                    }
                }
            }
        });
    }

    /* ── Search ────────────────────────────────────────────────── */
    var searchTimeout;
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(applyFilters, 150);
            clearBtn.style.display = this.value.trim() ? '' : 'none';
        });
    }
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            searchInput.value = '';
            clearBtn.style.display = 'none';
            applyFilters();
            searchInput.focus();
        });
    }

    /* ── Apply combined section + search filter ─────────────────── */
    function applyFilters() {
        var query = (searchInput ? searchInput.value : '').toLowerCase().trim();
        var words = query ? query.split(/\s+/) : [];
        var showAll = activeSections.has('all');
        var count = 0;

        sectionDivs.forEach(function (secDiv) {
            var secId = secDiv.dataset.section;
            var sectionVisible = showAll || activeSections.has(secId);
            var sectionHasVisible = false;
            var items = secDiv.querySelectorAll('.faq-item');

            items.forEach(function (item) {
                var searchText = item.dataset.search;
                var matchesSearch =
                    words.length === 0 ||
                    words.every(function (w) {
                        return searchText.includes(w);
                    });
                var visible = sectionVisible && matchesSearch;

                item.style.display = visible ? '' : 'none';
                if (visible) {
                    count++;
                    sectionHasVisible = true;
                }
            });

            secDiv.style.display = sectionHasVisible ? '' : 'none';
        });

        visibleCount.textContent = count;
        noResults.classList.toggle('d-none', count > 0);

        /* Update status text */
        if (showAll && !query) {
            filterStatus.innerHTML =
                'Showing all sections &middot; <span id="faqVisibleCount">' +
                count +
                '</span> questions';
        } else {
            var parts = [];
            if (!showAll) {
                var names = [];
                activeSections.forEach(function (id) {
                    var pill = document.querySelector(
                        '.faq-filter-pill[data-section="' + id + '"]'
                    );
                    if (pill) names.push(pill.textContent.trim());
                });
                parts.push(names.join(', '));
            } else {
                parts.push('All sections');
            }
            if (query) parts.push('matching "' + query + '"');
            filterStatus.innerHTML =
                parts.join(' &middot; ') +
                ' &middot; <span id="faqVisibleCount">' +
                count +
                '</span> questions';
        }
    }
})();
