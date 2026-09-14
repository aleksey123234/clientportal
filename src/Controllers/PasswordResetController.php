<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Services\AuthSession;
use App\Services\Csrf;
use App\Services\EmailService;
use App\Services\RateLimiter;
use App\Support\Log;

/**
 * Handles "Forgot Password" (request token) and "Reset Password" (consume token).
 *
 * Reset tokens: raw in email link; SHA-256 hash stored in users.password_reset_token.
 */
class PasswordResetController extends BaseController
{
    public function forgotPassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::jsonFail('Invalid method');
        }

        if (!Csrf::validate($_POST[Csrf::FIELD] ?? null)) {
            Response::jsonFail('Security token mismatch.');
        }

        $forgotKey = RateLimiter::bucketKey('forgot', RateLimiter::clientIp());
        try {
            if (!RateLimiter::hit(
                $this->db(),
                $forgotKey,
                RateLimiter::FORGOT_MAX,
                RateLimiter::WINDOW_SEC
            )) {
                Response::jsonFail('Too many reset requests. Please try again later.');
            }
        } catch (\Throwable $e) {
            Log::security()->error('Forgot-password rate limit failed: ' . $e->getMessage());
        }

        $email = trim($_POST['forgot_email'] ?? '');

        if (!$email) {
            Response::jsonOk();
        }

        $userId = $this->findUserByAnyEmail($email);

        if ($userId) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $this->db()->prepare(
                'UPDATE users SET password_reset_token=?, password_reset_expires=? WHERE id=?'
            )->execute([hash('sha256', $token), $expires, $userId]);

            EmailService::sendPasswordReset($email, $token);
        }

        Response::jsonOk();
    }

    public function resetPassword(): void
    {
        Csrf::ensureToken();

        $token = $_GET['token'] ?? $_POST['token'] ?? '';
        $error = null;
        $success = false;

        $user = null;
        if ($token) {
            $stmt = $this->db()->prepare(
                'SELECT id FROM users
                 WHERE password_reset_token = ?
                   AND password_reset_expires > NOW()
                 LIMIT 1'
            );
            $stmt->execute([hash('sha256', (string) $token)]);
            $user = $stmt->fetch();
        }

        if (!$user && !$token) {
            Response::redirect('/login');
        }

        if (!$user) {
            $error = 'This reset link has expired or is invalid. Please request a new one.';
        }

        if ($user && $_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST[Csrf::FIELD] ?? null)) {
                $error = 'Security token mismatch. Please try again.';
            } else {
                $newPwd = $_POST['new_password'] ?? '';
                $confirmPwd = $_POST['confirm_password'] ?? '';

                if (strlen($newPwd) < 8) {
                    $error = 'Password must be at least 8 characters.';
                } elseif ($newPwd !== $confirmPwd) {
                    $error = 'Passwords do not match.';
                } else {
                    $hash = password_hash($newPwd, PASSWORD_BCRYPT);
                    $this->db()->prepare(
                        'UPDATE users SET password_hash=?, password_reset_token=NULL,
                         password_reset_expires=NULL, updated_at=NOW() WHERE id=?'
                    )->execute([$hash, $user['id']]);
                    AuthSession::onPasswordChanged($this->db(), (int) $user['id']);
                    $success = true;
                }
            }
        }

        $pageTitle = 'Reset Password — Client Portal';
        $content = $this->render(
            __DIR__ . '/../views/auth/reset-password.php',
            compact('error', 'success', 'token', 'pageTitle', 'user')
        );
        require __DIR__ . '/../views/layouts/auth.php';
    }

    private function findUserByAnyEmail(string $email): ?int
    {
        $stmt = $this->db()->prepare('SELECT id FROM users WHERE email=? AND status=1 LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row) {
            return (int) $row['id'];
        }

        $stmt = $this->db()->prepare('SELECT user_id FROM client_emails WHERE email=? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row) {
            $stmt2 = $this->db()->prepare('SELECT id FROM users WHERE id=? AND status=1 LIMIT 1');
            $stmt2->execute([$row['user_id']]);
            if ($stmt2->fetch()) {
                return (int) $row['user_id'];
            }
        }

        return null;
    }
}
