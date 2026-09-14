<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Shared HTTP responses for controllers.
 */
final class Response
{
    public static function jsonOk(array $data = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['ok' => true], $data), JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function jsonFail(string $error, int $status = 400, array $extra = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['ok' => false, 'error' => $error], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    /** @return int Authenticated user id */
    public static function requireAuth(): int
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId === 0) {
            self::redirect('/login');
        }
        return $userId;
    }
}
