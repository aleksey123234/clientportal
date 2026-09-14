/**
 * Shared Bootstrap toast helper (PortalToast).
 * Requires #portalToastContainer in layout + Bootstrap JS.
 */
(function (global) {
    'use strict';

    function ensureContainer() {
        var el = document.getElementById('portalToastContainer');
        if (el) return el;
        el = document.createElement('div');
        el.id = 'portalToastContainer';
        el.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        el.style.zIndex = '1100';
        document.body.appendChild(el);
        return el;
    }

    /**
     * @param {string} message
     * @param {'success'|'danger'|'info'|'warning'} [type]
     */
    function show(message, type) {
        type = type || 'info';
        var bg =
            type === 'success'
                ? 'text-bg-success'
                : type === 'danger'
                  ? 'text-bg-danger'
                  : type === 'warning'
                    ? 'text-bg-warning'
                    : 'text-bg-primary';

        var container = ensureContainer();
        var toastEl = document.createElement('div');
        toastEl.className = 'toast align-items-center border-0 ' + bg;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        toastEl.innerHTML =
            '<div class="d-flex">' +
            '<div class="toast-body"></div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
            '</div>';
        toastEl.querySelector('.toast-body').textContent = message || '';
        container.appendChild(toastEl);

        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            var t = new bootstrap.Toast(toastEl, { delay: 4000 });
            toastEl.addEventListener('hidden.bs.toast', function () {
                toastEl.remove();
            });
            t.show();
        } else {
            toastEl.classList.add('show');
            setTimeout(function () {
                toastEl.remove();
            }, 4000);
        }
    }

    global.PortalToast = { show: show };
})(window);
