<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Log;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Email service — thin wrapper around PHPMailer.
 *
 * Reads SMTP settings from $_ENV (loaded by phpdotenv in public/index.php).
 * Falls back to PHP's built-in mail() when MAIL_DRIVER=mail.
 *
 * Public methods:
 *   send($to, $subject, $htmlBody, $textBody)  — send to one or more addresses
 *   sendPasswordReset($email, $token)           — password reset link email
 *   notifyUser($pdo, $userId, $subject, $html)  — respects user notification prefs
 *
 * Used by:
 *   - PasswordResetController  (forgot-password flow)
 *   - DocumentsController      (upload notification)
 *   - SettingsController       (test email)
 *
 * @see .env  — MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, etc.
 */
class EmailService
{
    /**
     * Send an email to one or more recipients.
     *
     * @param  string|string[] $to       Email address(es)
     * @param  string          $subject
     * @param  string          $htmlBody HTML content
     * @param  string          $textBody Plain-text fallback (optional)
     * @return bool
     */
    public static function send($to, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        $mail = new PHPMailer(true);

        try {
            $driver = $_ENV['MAIL_DRIVER'] ?? 'smtp';

            if ($driver === 'smtp') {
                $username = $_ENV['MAIL_USERNAME'] ?? '';
                $password = $_ENV['MAIL_PASSWORD'] ?? '';

                if (empty($username) || empty($password)) {
                    Log::app()->warning('EmailService: MAIL_USERNAME or MAIL_PASSWORD not configured in .env — email not sent.');
                    return false;
                }

                $mail->isSMTP();
                $mail->Host       = $_ENV['MAIL_HOST']       ?? 'smtp.gmail.com';
                $mail->Port       = (int) ($_ENV['MAIL_PORT'] ?? 587);
                $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? PHPMailer::ENCRYPTION_STARTTLS;
                $mail->SMTPAuth   = true;
                $mail->Username   = $username;
                $mail->Password   = $password;
            } else {
                $mail->isMail();
            }

            $mail->setFrom(
                $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@fpws.ca',
                $_ENV['MAIL_FROM_NAME']    ?? 'Client Portal'
            );

            foreach ((array) $to as $addr) {
                $addr = trim($addr);
                if ($addr) $mail->addAddress($addr);
            }

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (Exception $e) {
            Log::app()->error('EmailService error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a password-reset email.
     */
    public static function sendPasswordReset(string $email, string $token): bool
    {
        $appUrl  = rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/');
        $link    = $appUrl . '/reset-password?token=' . urlencode($token);
        $appName = $_ENV['APP_NAME'] ?? 'Client Portal';

        $html = <<<HTML
        <div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;">
            <h2 style="color:#2c3e50;">Password Reset</h2>
            <p>You requested a password reset for your <strong>{$appName}</strong> account.</p>
            <p>
                <a href="{$link}"
                   style="display:inline-block;padding:12px 28px;background:#4f8ef7;color:#fff;
                          text-decoration:none;border-radius:6px;font-weight:600;">
                    Reset Password
                </a>
            </p>
            <p style="color:#888;font-size:13px;">
                This link expires in 1 hour. If you did not request this, you can safely ignore this email.
            </p>
        </div>
        HTML;

        $text = "Password Reset\n\nVisit this link to reset your password:\n{$link}\n\nExpires in 1 hour.";

        return self::send($email, 'Password Reset — ' . $appName, $html, $text);
    }

    /**
     * Send a notification email to all of a user's email addresses.
     * Respects user notification preferences from user_settings table.
     *
     * @param \PDO   $pdo
     * @param int    $userId
     * @param string $subject
     * @param string $htmlBody
     * @param string $type  Notification type: 'payments', 'case', 'documents', 'general'
     */
    public static function notifyUser(\PDO $pdo, int $userId, string $subject, string $htmlBody, string $type = 'general'): bool
    {
        /* Check notification preferences */
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM user_settings WHERE user_id = ? AND setting_key IN ('notify_all','notify_payments','notify_case')");
        $stmt->execute([$userId]);
        $prefs = [];
        foreach ($stmt->fetchAll() as $r) {
            $prefs[$r['setting_key']] = $r['setting_value'];
        }

        /* Master toggle — if explicitly off, skip */
        if (isset($prefs['notify_all']) && $prefs['notify_all'] === '0') {
            return false;
        }

        /* Per-type toggle */
        $typeKey = 'notify_' . $type;
        if (isset($prefs[$typeKey]) && $prefs[$typeKey] === '0') {
            return false;
        }

        /* Gather every email: login email + all client_emails */
        $addresses = [];

        $stmt = $pdo->prepare('SELECT email FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row) $addresses[] = $row['email'];

        $stmt = $pdo->prepare('SELECT email FROM client_emails WHERE user_id=?');
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $r) {
            $addresses[] = $r['email'];
        }

        $addresses = array_unique(array_filter($addresses));
        if (empty($addresses)) return false;

        return self::send($addresses, $subject, $htmlBody);
    }
}
