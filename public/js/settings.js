/* ═══════════════════════════════════════════════════════════════
   SETTINGS PAGE — password, notifications, appearance
   Bindings via DOMContentLoaded (no inline onclick/onchange).
   ═══════════════════════════════════════════════════════════════ */

(function () {
    'use strict';

    function togglePwd(inputId, iconId) {
        var input = document.getElementById(inputId);
        var icon = document.getElementById(iconId);
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        if (icon) icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    }

    function checkPwdMatch() {
        var nEl = document.getElementById('newPwd');
        var cEl = document.getElementById('confirmPwd');
        var msg = document.getElementById('pwdMismatch');
        var btn = document.getElementById('pwdSubmitBtn');
        if (!nEl || !cEl || !msg || !btn) return;
        var bad = cEl.value.length > 0 && nEl.value !== cEl.value;
        msg.classList.toggle('d-none', !bad);
        btn.disabled = bad;
    }

    function toggleAllNotifications(master) {
        var children = document.querySelectorAll('.notif-child');
        var wrap = document.getElementById('notifChildren');
        children.forEach(function (c) {
            c.disabled = !master.checked;
        });
        if (wrap) wrap.classList.toggle('opacity-50', !master.checked);
    }

    function selectSidebarSide(side) {
        document
            .querySelectorAll('#tab-viewsettings .appearance-card[data-sidebar-side]')
            .forEach(function (card) {
                var radio = card.querySelector('input[name=sidebar_side]');
                if (!radio) return;
                var selected = radio.value === side;
                card.classList.toggle('is-selected', selected);
                radio.checked = selected;
            });
        var wrapper = document.getElementById('wrapper');
        if (wrapper) wrapper.classList.toggle('sidebar-right', side === 'right');
        if (typeof window.positionRail === 'function') {
            requestAnimationFrame(function () {
                window.positionRail();
            });
        }
    }

    function selectTheme(theme) {
        document
            .querySelectorAll('#tab-viewsettings .appearance-card[data-theme]')
            .forEach(function (card) {
                card.classList.toggle(
                    'is-selected',
                    card.getAttribute('data-theme') === theme
                );
            });
        var darkInput = document.getElementById('darkModeInput');
        if (darkInput) darkInput.value = theme === 'dark' ? '1' : '';
        document.body.classList.toggle('dark-theme', theme === 'dark');
    }

    function initSettings() {
        document
            .querySelectorAll('[data-bs-toggle="popover"]')
            .forEach(function (el) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
                    new bootstrap.Popover(el, { html: false });
                }
            });

        document.querySelectorAll('[data-pwd-toggle]').forEach(function (btn) {
            if (btn._settingsBound) return;
            btn._settingsBound = true;
            btn.addEventListener('click', function () {
                var parts = (btn.getAttribute('data-pwd-toggle') || '').split(',');
                togglePwd(parts[0], parts[1]);
            });
        });

        ['newPwd', 'confirmPwd'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el && !el._settingsBound) {
                el._settingsBound = true;
                el.addEventListener('input', checkPwdMatch);
            }
        });

        var notifyAll = document.getElementById('notifyAll');
        if (notifyAll && !notifyAll._settingsBound) {
            notifyAll._settingsBound = true;
            notifyAll.addEventListener('change', function () {
                toggleAllNotifications(notifyAll);
            });
        }

        document
            .querySelectorAll('#tab-viewsettings .appearance-card[data-sidebar-side]')
            .forEach(function (card) {
                if (card._settingsBound) return;
                card._settingsBound = true;
                card.addEventListener('click', function () {
                    selectSidebarSide(card.getAttribute('data-sidebar-side'));
                });
            });

        document
            .querySelectorAll('#tab-viewsettings .appearance-card[data-theme]')
            .forEach(function (card) {
                if (card._settingsBound) return;
                card._settingsBound = true;
                card.addEventListener('click', function () {
                    selectTheme(card.getAttribute('data-theme'));
                });
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSettings);
    } else {
        initSettings();
    }
})();
