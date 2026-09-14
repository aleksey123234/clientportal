<?php

use App\Support\Asset;

/**
 * Settings Page — 3-tab UI.
 * File: src/views/settings/settings-page.php
 *
 * Tabs:
 *   1. Password      — change password (current + new + confirm)
 *   2. Notifications — master toggle + payment reminders + case updates + test email
 *   3. Appearance    — sidebar side (left/right) + dark mode (on/off)
 *
 * Variables injected by SettingsController::index():
 *   $pageTitle, $user, $settings, $saved, $error, $activeTab
 */

// Variables: $pageTitle, $user, $settings, $saved, $error, $activeTab
$activeTab = $activeTab ?? 'password';

$notifyAll      = ($settings['notify_all']       ?? '1') === '1';
$notifyPayments = ($settings['notify_payments']  ?? '1') === '1';
$notifyCase     = ($settings['notify_case']      ?? '1') === '1';

$sidebarSide = $settings['sidebar_side'] ?? 'left';
$darkMode    = ($settings['dark_mode']   ?? '0') === '1';
?>

<?php if (!empty($saved)): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-check-circle-fill"></i> Settings saved successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- ══ Settings Tabs ══════════════════════════════════════════ -->
<ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'password'      ? 'active' : '' ?>"
            data-bs-toggle="tab" data-bs-target="#tab-password" type="button">
            <i class="bi bi-shield-lock me-1"></i>Password
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'notifications' ? 'active' : '' ?>"
            data-bs-toggle="tab" data-bs-target="#tab-notifications" type="button">
            <i class="bi bi-bell me-1"></i>Notifications
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'viewsettings'  ? 'active' : '' ?>"
            data-bs-toggle="tab" data-bs-target="#tab-viewsettings" type="button">
            <i class="bi bi-palette me-1"></i>Appearance
        </button>
    </li>
</ul>

<div class="tab-content">

    <!-- ══════════════════════════════════════════════════════
         TAB — PASSWORD
         ══════════════════════════════════════════════════════ -->
    <div class="tab-pane fade <?= $activeTab === 'password' ? 'show active' : '' ?>"
        id="tab-password" role="tabpanel">
        <div class="card border-0 shadow-sm" style="max-width:480px;">
            <div class="card-header bg-white fw-semibold">Change Password</div>
            <div class="card-body">
                <form method="POST" action="/settings" id="passwordForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="tab" value="password">

                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="current_password"
                                id="currentPwd" required autocomplete="current-password">
                            <button class="btn btn-outline-secondary" type="button"
                                data-pwd-toggle="currentPwd,eyeCurrent">
                                <i class="bi bi-eye" id="eyeCurrent"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="new_password"
                                id="newPwd" minlength="8" required autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button"
                                data-pwd-toggle="newPwd,eyeNew">
                                <i class="bi bi-eye" id="eyeNew"></i>
                            </button>
                        </div>
                        <div class="form-text">At least 8 characters.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="confirm_password"
                                id="confirmPwd" required autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button"
                                data-pwd-toggle="confirmPwd,eyeConfirm">
                                <i class="bi bi-eye" id="eyeConfirm"></i>
                            </button>
                        </div>
                        <div class="text-danger small d-none mt-1" id="pwdMismatch">
                            Passwords do not match.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning" id="pwdSubmitBtn">
                        <i class="bi bi-key me-1"></i>Update Password
                    </button>
                </form>
            </div>
        </div>
    </div><!-- /tab-password -->

    <!-- ══════════════════════════════════════════════════════
         TAB — NOTIFICATIONS
         ══════════════════════════════════════════════════════ -->
    <div class="tab-pane fade <?= $activeTab === 'notifications' ? 'show active' : '' ?>"
        id="tab-notifications" role="tabpanel">
        <div class="card border-0 shadow-sm" style="max-width:520px;">
            <div class="card-header bg-white fw-semibold">Notification Preferences</div>
            <div class="card-body">
                <form method="POST" action="/settings" id="notifForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="tab" value="notifications">

                    <!-- Master toggle -->
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" name="notify_all"
                            id="notifyAll" value="1"
                            <?= $notifyAll ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="notifyAll">
                            All Notifications
                        </label>
                    </div>
                    <hr class="my-3">

                    <div id="notifChildren" class="<?= $notifyAll ? '' : 'opacity-50' ?>">

                        <!-- Payment reminders -->
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input notif-child" type="checkbox"
                                name="notify_payments" id="notifyPayments" value="1"
                                <?= $notifyPayments ? 'checked' : '' ?>
                                <?= !$notifyAll ? 'disabled' : '' ?>>
                            <label class="form-check-label" for="notifyPayments">
                                <i class="bi bi-credit-card me-1 text-primary"></i>
                                Payment Reminders
                                <button type="button" class="btn btn-link p-0 ms-1 notif-help-btn"
                                    tabindex="-1"
                                    data-bs-toggle="popover"
                                    data-bs-trigger="hover focus"
                                    data-bs-placement="right"
                                    data-bs-content="A payment reminder will be sent to your email address 3 days prior to each scheduled payment due date.">
                                    <i class="bi bi-question-circle text-muted"></i>
                                </button>
                            </label>
                        </div>

                        <!-- Case status updates -->
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input notif-child" type="checkbox"
                                name="notify_case" id="notifyCase" value="1"
                                <?= $notifyCase ? 'checked' : '' ?>
                                <?= !$notifyAll ? 'disabled' : '' ?>>
                            <label class="form-check-label" for="notifyCase">
                                <i class="bi bi-briefcase me-1 text-warning"></i>
                                Case Status Updates
                                <button type="button" class="btn btn-link p-0 ms-1 notif-help-btn"
                                    tabindex="-1"
                                    data-bs-toggle="popover"
                                    data-bs-trigger="hover focus"
                                    data-bs-placement="right"
                                    data-bs-content="You will receive a notification each time your case status changes. Please note: this includes all follow-up updates and supplementary status changes.">
                                    <i class="bi bi-question-circle text-muted"></i>
                                </button>
                            </label>
                        </div>

                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Preferences
                    </button>
                </form>

                <hr class="my-3">
                <form method="POST" action="/settings" id="testEmailForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="tab" value="test_email">
                    <p class="text-muted small mb-2">
                        <i class="bi bi-info-circle me-1"></i>Send a test notification to all your email addresses to verify email delivery is working.
                    </p>
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-envelope-check me-1"></i>Send Test Email
                    </button>
                </form>
            </div>
        </div>
    </div><!-- /tab-notifications -->

    <!-- ══════════════════════════════════════════════════════
         TAB — APPEARANCE
         ══════════════════════════════════════════════════════ -->
    <div class="tab-pane fade <?= $activeTab === 'viewsettings' ? 'show active' : '' ?>"
        id="tab-viewsettings" role="tabpanel">
        <div class="card border-0 shadow-sm" style="max-width:520px;">
            <div class="card-header bg-white fw-semibold">Appearance &amp; Layout</div>
            <div class="card-body">
                <form method="POST" action="/settings" id="viewForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="tab" value="viewsettings">

                    <!-- Sidebar side -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Sidebar position</label>
                        <div class="d-flex gap-3">
                            <div class="appearance-card <?= $sidebarSide === 'left'  ? 'is-selected' : '' ?>"
                                data-sidebar-side="left" role="button" tabindex="0">
                                <div class="appearance-preview sidebar-left-preview"></div>
                                <div class="appearance-label">
                                    <i class="bi bi-layout-sidebar me-1"></i>Left
                                </div>
                                <input type="radio" name="sidebar_side" value="left" class="d-none"
                                    <?= $sidebarSide === 'left' ? 'checked' : '' ?>>
                            </div>
                            <div class="appearance-card <?= $sidebarSide === 'right' ? 'is-selected' : '' ?>"
                                data-sidebar-side="right" role="button" tabindex="0">
                                <div class="appearance-preview sidebar-right-preview"></div>
                                <div class="appearance-label">
                                    <i class="bi bi-layout-sidebar-reverse me-1"></i>Right
                                </div>
                                <input type="radio" name="sidebar_side" value="right" class="d-none"
                                    <?= $sidebarSide === 'right' ? 'checked' : '' ?>>
                            </div>
                        </div>
                    </div>

                    <!-- Theme -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Theme</label>
                        <div class="d-flex gap-3">
                            <div class="appearance-card <?= !$darkMode ? 'is-selected' : '' ?>"
                                data-theme="light" role="button" tabindex="0">
                                <div class="appearance-preview theme-light-preview"></div>
                                <div class="appearance-label">
                                    <i class="bi bi-sun me-1"></i>Light
                                </div>
                            </div>
                            <div class="appearance-card <?= $darkMode ? 'is-selected' : '' ?>"
                                data-theme="dark" role="button" tabindex="0">
                                <div class="appearance-preview theme-dark-preview"></div>
                                <div class="appearance-label">
                                    <i class="bi bi-moon-stars me-1"></i>Dark
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="dark_mode" id="darkModeInput"
                            value="<?= $darkMode ? '1' : '' ?>">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Appearance
                    </button>
                </form>
            </div>
        </div>
    </div><!-- /tab-viewsettings -->

</div><!-- /tab-content -->


<script src="<?= Asset::url('/js/settings.js') ?>"></script>
