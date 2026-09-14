/* ═══════════════════════════════════════════════════════════════
   LOGIN PAGE — panel slide animation + password toggle + forgot form
   ═══════════════════════════════════════════════════════════════ */
(function () {
    const loginPanel = document.getElementById('panel-login');
    const forgotPanel = document.getElementById('panel-forgot');

    if (!loginPanel || !forgotPanel) return;

    /* ── Slide between login ↔ forgot panels ──────────────────── */
    function slideTo(show, hide) {
        hide.classList.add('auth-panel--exit');

        hide.addEventListener('animationend', function onHideDone() {
            hide.removeEventListener('animationend', onHideDone);
            hide.classList.add('auth-panel--hidden');
            hide.classList.remove('auth-panel--exit');

            show.classList.remove('auth-panel--hidden');
            show.classList.add('auth-panel--enter');
            show.addEventListener('animationend', function onShowDone() {
                show.removeEventListener('animationend', onShowDone);
                show.classList.remove('auth-panel--enter');
            });
        });
    }

    document
        .getElementById('showForgot')
        .addEventListener('click', function (e) {
            e.preventDefault();
            slideTo(forgotPanel, loginPanel);
            setTimeout(function () {
                document.getElementById('forgot-email').focus();
            }, 370);
        });

    document
        .getElementById('showLogin')
        .addEventListener('click', function (e) {
            e.preventDefault();
            slideTo(loginPanel, forgotPanel);
            setTimeout(function () {
                document.getElementById('email').focus();
            }, 370);
        });

    /* ── Password visibility toggle ───────────────────────────── */
    document
        .getElementById('togglePassword')
        .addEventListener('click', function () {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                pwd.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });

    /* ── Forgot password form — POST to /forgot-password ──────── */
    document
        .getElementById('forgot-form')
        .addEventListener('submit', function (e) {
            e.preventDefault();
            var form = this;
            var btn = form.querySelector('button[type="submit"]');
            var orig = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML =
                '<span class="spinner-border spinner-border-sm me-2"></span>Sending…';

            fetch('/forgot-password', {
                method: 'POST',
                body: new FormData(form),
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    if (data && data.ok === false) {
                        if (window.PortalToast) {
                            PortalToast.show(
                                data.error || 'Could not send reset email.',
                                'danger'
                            );
                        } else {
                            var errBox = document.getElementById('forgot-error');
                            if (errBox) {
                                errBox.textContent =
                                    data.error || 'Could not send reset email.';
                                errBox.classList.remove('d-none');
                            }
                        }
                        return;
                    }
                    document
                        .getElementById('forgot-success')
                        .classList.remove('d-none');
                    form.classList.add('d-none');
                })
                .catch(function () {
                    document
                        .getElementById('forgot-success')
                        .classList.remove('d-none');
                    form.classList.add('d-none');
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.innerHTML = orig;
                });
        });
})();
