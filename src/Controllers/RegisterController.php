<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Csrf;
use App\Services\RegistrationToken;
use App\Support\Log;

/**
 * Registration controller — token-gated 4-step registration wizard.
 *
 * Routes (dispatched by public/index.php):
 *   GET  /register/{uuid}  — show 4-step registration form
 *   POST /register/{uuid}  — validate password and activate account
 *
 * The UUID in the URL must match users.registration_token for a pending invite.
 *
 * @see src/views/auth/register.php    — 4-step wizard view
 * @see src/views/layouts/auth.php     — auth layout (blurred background)
 * @see public/js/register.js          — multi-step wizard, password strength
 * @see database/migrations/020_add_registration_token.sql
 */
class RegisterController extends BaseController
{
    public function index(string $token = ''): void
    {
        $tokenValid = $this->validateToken($token);
        $error      = null;
        $pageTitle  = 'Register — Client Portal';

        // ── POST: save password ──────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {

            // CSRF check
            if (!Csrf::validate($_POST[Csrf::FIELD] ?? null)) {
                $error = 'Security token mismatch. Please try again.';
            } else {
                $password        = $_POST['password']         ?? '';
                $passwordConfirm = $_POST['confirm_password'] ?? '';

                if (strlen($password) < 8) {
                    $error = 'Password must be at least 8 characters.';
                } elseif ($password !== $passwordConfirm) {
                    $error = 'Passwords do not match.';
                } else {
                    $activated = $this->activateAccount($token, $password);
                    if ($activated) {
                        header('Location: /login?registered=1');
                        exit;
                    }
                    $error = 'This registration link has expired or already been used.';
                }
            }
        }

        // Ensure CSRF token for GET or failed POST
        if ($_SERVER['REQUEST_METHOD'] === 'GET' || $error !== null) {
            Csrf::ensureToken();
        }

        $this->renderRegister($tokenValid, $token, $error, $pageTitle);
    }

    private function validateToken(string $token): bool
    {
        if (!RegistrationToken::isValidFormat($token)) {
            return false;
        }

        try {
            $stmt = $this->db()->prepare(
                'SELECT id FROM users WHERE registration_token = ? LIMIT 1'
            );
            $stmt->execute([$token]);
            return (bool) $stmt->fetch();
        } catch (\Throwable $e) {
            Log::security()->error('RegisterController::validateToken: ' . $e->getMessage());
            return false;
        }
    }

    private function activateAccount(string $token, string $password): bool
    {
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $this->db()->prepare(
                "UPDATE users
                 SET password_hash = ?, status = 1, registration_token = NULL, updated_at = NOW()
                 WHERE registration_token = ?"
            );
            $stmt->execute([$hash, $token]);
            return $stmt->rowCount() === 1;
        } catch (\Throwable $e) {
            Log::security()->error('RegisterController::activateAccount: ' . $e->getMessage());
            return false;
        }
    }

    private function renderRegister(bool $tokenValid, string $token, ?string $error, string $pageTitle): void
    {
        $mainClass = 'auth-card--fluid';
        ob_start();
        require __DIR__ . '/../views/auth/register.php';
        $content = ob_get_clean();

        require __DIR__ . '/../views/layouts/auth.php';
    }
}
