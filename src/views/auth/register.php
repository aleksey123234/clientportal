<?php

use App\Support\Asset;
// This view is rendered by RegisterController::index()
// Variables: $token (string), $error (string|null), $tokenValid (bool)
?>

<?php if (!($tokenValid ?? false)): ?>
    <!-- ── INVALID TOKEN ─────────────────────────────────────────── -->
    <div class="card shadow border-0 text-center" style="max-width:440px; width:100%;">
        <div class="card-body p-5">
            <img src="/images/Logo.png" alt="FPWS Logo" style="max-height:64px;" class="mb-4">
            <i class="bi bi-x-circle text-danger fs-1 mb-3 d-block"></i>
            <h5 class="fw-bold">Invalid Registration Link</h5>
            <p class="text-muted">This link is invalid or has already been used.<br>
                Please contact our office for assistance.</p>
            <a href="/login" class="btn btn-outline-primary mt-2">Back to Sign In</a>
        </div>
    </div>

<?php else: ?>
    <!-- ── REGISTRATION FLOW ─────────────────────────────────────── -->
    <div class="reg-wrap reg-wrap--welcome" id="reg-wrap">

        <!-- STEP 1: Welcome -->
        <div class="reg-step active" id="step-welcome">
            <div class="card shadow-lg border-0">
                <div class="card-body text-center" style="padding: 4rem 5rem; min-height: 420px; display:flex; flex-direction:column; justify-content:center; align-items:center;">
                    <img src="/images/Logo.png" alt="FPWS Logo" style="max-height:90px;" class="mb-5 mx-auto d-block">
                    <div class="reg-welcome-text" style="max-width: 580px;">
                        <h4 class="fw-bold mb-4" style="font-size:2rem; letter-spacing:-0.5px;">Welcome to the Portal</h4>
                        <p class="mb-0 text-muted" style="font-size:1.125rem; line-height:1.85;">
                            <strong style="color:#1d1d1f;">Federal Pardon &amp; Waiver Services</strong><br>
                            We're glad you're here. This portal gives you secure access
                            to your case information, documents, and payment history.
                        </p>
                    </div>
                    <button class="btn btn-primary btn-lg mt-5 px-5" id="btn-welcome-next" style="font-size:1.05rem; border-radius:50px; min-width:200px;">
                        Continue <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- STEP 2: Terms & Conditions -->
        <div class="reg-step" id="step-terms">
            <div class="card shadow-lg border-0">
                <div class="card-body" style="padding: 3.5rem 4rem;">
                    <div class="text-center mb-4">
                        <img src="/images/Logo.png" alt="FPWS Logo" style="max-height:64px;" class="mb-3">
                        <h5 class="fw-bold" style="font-size:1.5rem; letter-spacing:-0.3px;">Terms &amp; Conditions</h5>
                        <p class="text-muted" style="font-size:0.95rem;">Please read carefully before continuing.</p>
                    </div>
                    <div class="terms-box border rounded p-4 mb-4" style="height: 360px; overflow-y:auto; font-size:1rem; line-height:1.8; color:#374151;">
                        <p><strong>1. Acceptance of Terms</strong><br>
                            By accessing this Client Portal, you agree to be bound by these Terms and Conditions and all applicable laws and regulations.</p>
                        <p><strong>2. Confidentiality</strong><br>
                            All information contained within this portal is strictly confidential. You agree not to share your login credentials or any case-related information with any third party.</p>
                        <p><strong>3. Accuracy of Information</strong><br>
                            You are responsible for ensuring all information you provide is accurate, complete, and up to date.</p>
                        <p><strong>4. Permitted Use</strong><br>
                            This portal is for your personal use only in relation to services provided by Federal Pardon &amp; Waiver Services. Any unauthorized use is strictly prohibited.</p>
                        <p><strong>5. Privacy</strong><br>
                            Your personal data is handled in accordance with applicable Canadian privacy laws (PIPEDA). We will not sell or share your data with third parties except as required to deliver our services.</p>
                        <p><strong>6. Limitation of Liability</strong><br>
                            Federal Pardon &amp; Waiver Services shall not be liable for any indirect or consequential damages arising from the use of this portal.</p>
                        <p><strong>7. Governing Law</strong><br>
                            These terms are governed by the laws of the Province of Ontario and the federal laws of Canada applicable therein.</p>
                    </div>
                    <div class="d-flex gap-3">
                        <button class="btn btn-primary flex-fill btn-lg" id="btn-terms-accept" style="border-radius:50px;">
                            <i class="bi bi-check-lg me-2"></i>Accept &amp; Continue
                        </button>
                        <button class="btn btn-outline-danger flex-fill btn-lg" id="btn-terms-decline" style="border-radius:50px;">
                            <i class="bi bi-x-lg me-2"></i>Decline
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 3a: Set Password (after Accept) -->
        <div class="reg-step" id="step-password">
            <div class="card shadow border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <img src="/images/Logo.png" alt="FPWS Logo" style="max-height:56px;" class="mb-3">
                        <h5 class="fw-bold">Create Your Password</h5>
                        <p class="text-muted small">Choose a strong password to secure your account.</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 py-2">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="/register/<?= htmlspecialchars($token ?? '') ?>" novalidate id="reg-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" name="password"
                                    id="reg-password" placeholder="Minimum 8 characters"
                                    required minlength="8"
                                    pattern="^[\x20-\x7E]{8,}$"
                                    title="Only Latin letters, digits and standard symbols are allowed (no Cyrillic or special Unicode)">
                                <button class="btn btn-outline-secondary" type="button" id="toggleRegPwd">
                                    <i class="bi bi-eye" id="regEyeIcon"></i>
                                </button>
                            </div>
                            <div class="password-strength mt-2" id="pwd-strength-bar">
                                <div class="strength-track">
                                    <div class="strength-fill" id="strength-fill"></div>
                                </div>
                                <small class="text-muted" id="strength-label">Enter a password</small>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                <input type="password" class="form-control" name="confirm_password"
                                    id="reg-password-confirm" placeholder="Repeat your password"
                                    required
                                    pattern="^[\x20-\x7E]{8,}$">
                            </div>
                            <!-- visible error — shown by JS, not Bootstrap validation -->
                            <div class="text-danger small mt-1 d-none" id="pwd-match-msg">
                                <i class="bi bi-exclamation-circle me-1"></i><span id="pwd-match-text"></span>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg" id="btn-register">
                                <i class="bi bi-shield-check me-2"></i>Activate Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- STEP 3b: Decline message -->
        <div class="reg-step" id="step-declined">
            <div class="card shadow border-0">
                <div class="card-body p-4 p-md-5 text-center">
                    <img src="/images/Logo.png" alt="FPWS Logo" style="max-height:64px;" class="mb-4">
                    <h5 class="fw-bold mb-3">We're Sorry to See You Go</h5>
                    <p class="text-muted mb-4">
                        To cancel your services, please contact our<br>
                        <strong>Client Care Department</strong>:
                    </p>
                    <div class="alert alert-light border mb-4 px-4">
                        <i class="bi bi-telephone me-2"></i>
                        <strong>+1 800 543 2137</strong>&nbsp;&nbsp;Ext.&nbsp;823
                    </div>
                    <p class="text-muted small mb-4">
                        Our team is available Monday – Friday,<br>
                        9 AM – 3 PM EST.
                    </p>
                    <button class="btn btn-outline-primary" id="btn-declined-continue">
                        <i class="bi bi-arrow-left me-1"></i>Go Back &amp; Accept
                    </button>
                </div>
            </div>
        </div>

        <!-- STEP 4: Success -->
        <div class="reg-step" id="step-success">
            <div class="card shadow border-0">
                <div class="card-body p-4 p-md-5 text-center">
                    <img src="/images/Logo.png" alt="FPWS Logo" style="max-height:64px;" class="mb-4">
                    <i class="bi bi-check-circle-fill text-success fs-1 mb-3 d-block"></i>
                    <h5 class="fw-bold mb-2">Account Activated!</h5>
                    <p class="text-muted">Your account has been successfully created.<br>You can now sign in to your portal.</p>
                    <a href="/login" class="btn btn-primary btn-lg mt-3 px-5">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                    </a>
                </div>
            </div>
        </div>

    </div><!-- /reg-wrap -->

    <script src="<?= Asset::url('/js/register.js') ?>"></script>
<?php endif; ?>
