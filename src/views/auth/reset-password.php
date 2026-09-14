<?php
// Variables: $error, $success, $token, $user, $pageTitle
?>

<div class="auth-slider-wrap">
    <div class="auth-panel" id="panel-reset">
        <div class="card shadow border-0">
            <div class="card-body p-4 p-md-5">

                <div class="text-center mb-4">
                    <img src="/images/Logo.png" alt="FPWS Logo" style="max-height:72px; width:auto;" class="mb-3">
                    <h5 class="fw-bold mb-1">Set New Password</h5>
                    <p class="text-muted small">Choose a strong password (at least 8 characters).</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>Password has been reset successfully!</span>
                    </div>
                    <div class="text-center">
                        <a href="/login" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                        </a>
                    </div>
                <?php elseif (!empty($error) && empty($user)): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                    <div class="text-center">
                        <a href="/login" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-left me-1"></i>Back to Sign In
                        </a>
                    </div>
                <?php else: ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 mb-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="/reset-password" novalidate>
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                        <div class="mb-3">
                            <label for="new_password" class="form-label fw-semibold">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="new_password"
                                    name="new_password" placeholder="At least 8 characters" required
                                    minlength="8" autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label fw-semibold">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                <input type="password" class="form-control" id="confirm_password"
                                    name="confirm_password" placeholder="Repeat password" required>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-shield-check me-2"></i>Reset Password
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>
