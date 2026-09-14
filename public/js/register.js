/* ═══════════════════════════════════════════════════════════════
   REGISTER PAGE — multi-step wizard, password strength, validation
   ═══════════════════════════════════════════════════════════════ */
(function () {
    const wrap = document.getElementById('reg-wrap');
    if (!wrap) return; /* invalid-token page has no reg-wrap */

    /* ── Wrapper width classes per step ────────────────────────── */
    const wrapClass = {
        'step-welcome': 'reg-wrap--welcome',
        'step-terms': 'reg-wrap--terms',
        'step-password': '',
        'step-declined': 'reg-wrap--declined',
        'step-success': '',
    };

    function setWrapClass(stepId) {
        wrap.classList.remove(
            'reg-wrap--welcome',
            'reg-wrap--terms',
            'reg-wrap--declined'
        );
        const cls = wrapClass[stepId];
        if (cls) wrap.classList.add(cls);
    }

    /* ── Step navigation ───────────────────────────────────────── */
    function goTo(nextId, prevId) {
        const prev = document.getElementById(prevId);
        const next = document.getElementById(nextId);

        prev.classList.add('reg-step--exit');
        prev.addEventListener('animationend', function h() {
            prev.removeEventListener('animationend', h);
            prev.classList.remove('active', 'reg-step--exit');

            setWrapClass(nextId);

            next.classList.add('active', 'reg-step--enter');
            next.addEventListener('animationend', function h2() {
                next.removeEventListener('animationend', h2);
                next.classList.remove('reg-step--enter');
            });
        });
    }

    document
        .getElementById('btn-welcome-next')
        .addEventListener('click', function () {
            goTo('step-terms', 'step-welcome');
        });

    document
        .getElementById('btn-terms-accept')
        .addEventListener('click', function () {
            goTo('step-password', 'step-terms');
            setTimeout(function () {
                document.getElementById('reg-password').focus();
            }, 420);
        });

    document
        .getElementById('btn-terms-decline')
        .addEventListener('click', function () {
            goTo('step-declined', 'step-terms');
        });

    document
        .getElementById('btn-declined-continue')
        .addEventListener('click', function () {
            goTo('step-terms', 'step-declined');
        });

    /* ── Strip non-ASCII from password inputs ─────────────────── */
    function sanitizeAsciiPrintable(str) {
        return str.replace(/[^\x20-\x7E]/g, '');
    }

    const pwdInput = document.getElementById('reg-password');
    const confInput = document.getElementById('reg-password-confirm');

    function applyInputSanitize(input) {
        input.addEventListener('input', function () {
            const pos = this.selectionStart;
            const clean = sanitizeAsciiPrintable(this.value);
            if (clean !== this.value) {
                this.value = clean;
                try {
                    this.setSelectionRange(
                        pos -
                            (this.value.length -
                                clean.length +
                                (this.value.length - clean.length)),
                        pos
                    );
                } catch (e) {
                    /* ignore */
                }
            }
        });
    }

    applyInputSanitize(pwdInput);
    applyInputSanitize(confInput);

    /* ── Password strength meter ──────────────────────────────── */
    pwdInput.addEventListener('input', function () {
        const val = this.value;
        const fill = document.getElementById('strength-fill');
        const label = document.getElementById('strength-label');

        let score = 0;
        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const colors = ['#dc3545', '#fd7e14', '#ffc107', '#28a745'];
        const labels = ['Too short', 'Weak', 'Fair', 'Strong'];

        fill.style.width = score * 25 + '%';
        fill.style.background = colors[score - 1] || '#e9ecef';
        label.textContent = score > 0 ? labels[score - 1] : 'Enter a password';
    });

    /* ── Password match validation ────────────────────────────── */
    const matchMsg = document.getElementById('pwd-match-msg');
    const matchText = document.getElementById('pwd-match-text');
    const regBtn = document.getElementById('btn-register');

    function showMatchError(text) {
        confInput.classList.add('is-invalid');
        matchMsg.classList.remove('d-none');
        matchText.textContent = text;
        regBtn.disabled = true;
    }

    function clearMatchError() {
        confInput.classList.remove('is-invalid');
        matchMsg.classList.add('d-none');
        matchText.textContent = '';
        regBtn.disabled = false;
    }

    confInput.addEventListener('input', function () {
        if (!this.value) {
            clearMatchError();
            return;
        }
        if (this.value !== pwdInput.value) {
            showMatchError('Passwords do not match.');
        } else {
            clearMatchError();
        }
    });

    pwdInput.addEventListener('input', function () {
        if (confInput.value && confInput.value !== this.value) {
            showMatchError('Passwords do not match.');
        } else if (confInput.value) {
            clearMatchError();
        }
    });

    /* ── Password toggle ──────────────────────────────────────── */
    document
        .getElementById('toggleRegPwd')
        .addEventListener('click', function () {
            const icon = document.getElementById('regEyeIcon');
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                pwdInput.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });

    /* ── Form submit validation ───────────────────────────────── */
    document
        .getElementById('reg-form')
        .addEventListener('submit', function (e) {
            let valid = true;

            if (/[^\x20-\x7E]/.test(pwdInput.value)) {
                e.preventDefault();
                pwdInput.classList.add('is-invalid');
                document.getElementById('strength-label').textContent =
                    'Only Latin letters & standard symbols allowed';
                valid = false;
            }

            if (!confInput.value) {
                e.preventDefault();
                showMatchError('Please confirm your password.');
                valid = false;
            } else if (confInput.value !== pwdInput.value) {
                e.preventDefault();
                showMatchError('Passwords do not match.');
                valid = false;
            }
        });
})();
