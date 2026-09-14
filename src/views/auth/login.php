<?php

use App\Support\Asset;
// This view is rendered by AuthController::login()
// Variables available: $error (string|null), $pageTitle (string)
?>

<div class="auth-slider-wrap">

    <!-- ── PANEL 1: LOGIN ─────────────────────────────────────── -->
    <div class="auth-panel" id="panel-login">
        <div class="card shadow border-0">
            <div class="card-body p-4 p-md-5">

                <div class="text-center mb-4">
                    <img src="/images/Logo.png" alt="FPWS Logo" style="max-height:72px; width:auto;" class="mb-3">
                    <p class="text-muted small">Sign in to your account</p>
                </div>

                <?php if (!empty($registered)): ?>
                    <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-3" role="alert">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>Account activated! You can now sign in.</span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/login" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Client ID</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                            <input type="text" class="form-control" id="email" name="email"
                                placeholder="e.g. 111111"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                required autofocus>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="password" class="form-label fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password"
                                name="password" placeholder="••••••••" required>
                            <button class="btn btn-outline-secondary" type="button"
                                id="togglePassword" tabindex="-1">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Remember me + Forgot password -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="remember_me"
                                id="rememberMe" value="1">
                            <label class="form-check-label small" for="rememberMe">
                                Remember me
                            </label>
                        </div>
                        <a href="#" class="small text-decoration-none" id="showForgot">
                            Forgot password?
                        </a>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div><!-- /panel-login -->

    <!-- ── PANEL 2: FORGOT PASSWORD ──────────────────────────── -->
    <div class="auth-panel auth-panel--hidden" id="panel-forgot">
        <div class="card shadow border-0">
            <div class="card-body p-4 p-md-5">

                <div class="text-center mb-4">
                    <img src="/images/Logo.png" alt="FPWS Logo" style="max-height:72px; width:auto;" class="mb-3">
                    <h5 class="fw-bold mb-1">Reset Password</h5>
                    <p class="text-muted small">Enter your email and we'll send you a reset link.</p>
                </div>

                <div id="forgot-success" class="alert alert-success d-none" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    If that email is registered, a reset link has been sent.
                </div>

                <form id="forgot-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                    <div class="mb-4">
                        <label for="forgot-email" class="form-label fw-semibold">Email address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" id="forgot-email"
                                name="forgot_email" placeholder="you@example.com" required>
                        </div>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-send me-2"></i>Send Reset Link
                        </button>
                    </div>
                </form>

                <div class="text-center">
                    <a href="#" class="small text-decoration-none" id="showLogin">
                        <i class="bi bi-arrow-left me-1"></i>Back to Sign In
                    </a>
                </div>

            </div>
        </div>
    </div><!-- /panel-forgot -->

</div><!-- /auth-slider-wrap -->

<script src="<?= Asset::url('/js/login.js') ?>"></script>
