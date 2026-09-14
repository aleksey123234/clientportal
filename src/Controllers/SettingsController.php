<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthSession;
use App\Services\Csrf;
use App\Services\EmailService;

/**
 * Settings page controller — password, notifications, appearance.
 *
 * Routes (dispatched by public/index.php):
 *   GET  /settings  — render settings page with 3 tabs
 *   POST /settings  — handle tab-specific save actions
 *
 * POST actions (sent via hidden field $_POST['tab']):
 *   "password"      — change password (current + new, bcrypt, min 8 chars)
 *   "notifications" — toggle notify_all, notify_payments, notify_case
 *   "test_email"    — send a test notification via EmailService
 *   "viewsettings"  — change sidebar side (left/right) and dark mode (on/off)
 *
 * Tables used: users, user_settings
 *
 * @see src/views/settings/settings-page.php  — view (3-tab UI)
 * @see public/js/settings.js                — pwd toggle, notifications, theme preview
 */
class SettingsController extends BaseController
{

    public function index(): void
    {
        Csrf::ensureToken();

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $saved  = false;
        $error  = null;
        $activeTab = 'password';

        /* ── Load user from DB ── */
        $user = $this->getUser($userId);

        /* ── Load user_settings from DB ── */
        $settings = $this->getSettings($userId);

        /* ── Handle POST ── */
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST[Csrf::FIELD] ?? null)) {
                $error = 'Invalid request. Please try again.';
            } else {
                $tab = $_POST['tab'] ?? 'password';
                $activeTab = $tab;

                switch ($tab) {

                    case 'password':
                        $current = $_POST['current_password'] ?? '';
                        $new     = $_POST['new_password']     ?? '';
                        $confirm = $_POST['confirm_password'] ?? '';

                        if (!password_verify($current, $user['password_hash'] ?? '')) {
                            $error = 'Current password is incorrect.';
                        } elseif (strlen($new) < 8) {
                            $error = 'New password must be at least 8 characters.';
                        } elseif ($new !== $confirm) {
                            $error = 'Passwords do not match.';
                        } else {
                            $hash = password_hash($new, PASSWORD_BCRYPT);
                            $this->db()->prepare(
                                'UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?'
                            )->execute([$hash, $userId]);
                            AuthSession::onPasswordChanged($this->db(), $userId);
                            $saved = true;
                        }
                        break;

                    case 'notifications':
                        $keys = ['notify_all', 'notify_payments', 'notify_case'];
                        foreach ($keys as $k) {
                            $val = isset($_POST[$k]) ? '1' : '0';
                            $this->upsertSetting($userId, $k, $val);
                        }
                        $saved = true;
                        break;

                    case 'test_email':
                        $activeTab = 'notifications';
                        try {
                            $appName  = $_ENV['APP_NAME'] ?? 'Client Portal';
                            $userName = $_SESSION['user_name'] ?? 'Client';
                            $result   = EmailService::notifyUser(
                                $this->db(),
                                $userId,
                                "Test Notification — {$appName}",
                                "<div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;'>
                                    <h2 style='color:#2c3e50;'>Test Notification</h2>
                                    <p>Hi <strong>{$userName}</strong>,</p>
                                    <p>This is a test notification from <strong>{$appName}</strong>. If you received this email, your notification settings are working correctly.</p>
                                    <p style='color:#888;font-size:13px;'>Sent at " . date('M j, Y g:i A') . "</p>
                                </div>",
                                'general'
                            );
                            if ($result) {
                                $saved = true;
                            } else {
                                $error = 'Failed to send test email. Check SMTP settings in .env (MAIL_USERNAME and MAIL_PASSWORD must be configured).';
                            }
                        } catch (\Throwable $e) {
                            $error = 'Email error: ' . $e->getMessage();
                        }
                        break;

                    case 'viewsettings':
                        $sidebarSide = in_array($_POST['sidebar_side'] ?? '', ['left', 'right']) ? $_POST['sidebar_side'] : 'left';
                        $darkMode    = (($_POST['dark_mode'] ?? '') === '1') ? '1' : '0';
                        $this->upsertSetting($userId, 'sidebar_side', $sidebarSide);
                        $this->upsertSetting($userId, 'dark_mode',    $darkMode);
                        $saved = true;
                        break;
                }

                /* Refresh after save */
                $user     = $this->getUser($userId);
                $settings = $this->getSettings($userId);

                /* Push view settings to session so layout picks them up */
                $_SESSION['sidebar_side'] = $settings['sidebar_side'] ?? 'left';
                $_SESSION['dark_mode']    = $settings['dark_mode']    ?? '0';
            }
        }

        /* Ensure session view prefs are set */
        if (!isset($_SESSION['sidebar_side'])) {
            $_SESSION['sidebar_side'] = $settings['sidebar_side'] ?? 'left';
            $_SESSION['dark_mode']    = $settings['dark_mode']    ?? '0';
        }

        $pageTitle  = 'Settings';
        $activePage = 'settings';

        $content = $this->render(
            __DIR__ . '/../views/settings/settings-page.php',
            compact('user', 'settings', 'saved', 'error', 'activeTab', 'pageTitle', 'activePage')
        );

        require __DIR__ . '/../views/layouts/main.php';
    }

    /* ── Helpers ─────────────────────────────────────────────── */

    private function getUser(int $id): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }

    private function getSettings(int $userId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
        $out  = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = $r['setting_value'];
        }
        return $out;
    }

    private function upsertSetting(int $userId, string $key, string $value): void
    {
        $this->db()->prepare(
            'INSERT INTO user_settings (user_id, setting_key, setting_value)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        )->execute([$userId, $key, $value]);
    }
}
