<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Global exception / error handlers — log + user-safe response.
 * No Whoops; never dump PDO passwords or stack traces to the client when APP_DEBUG is off.
 */
final class ExceptionHandler
{
    public static function register(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /** @return bool */
    public static function handleError(int $severity, string $message, string $file, int $line)
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        // Convert serious errors to exceptions
        if (in_array($severity, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }
        Log::app()->warning($message, ['file' => $file, 'line' => $line, 'severity' => $severity]);
        return true;
    }

    public static function handleException(\Throwable $e): void
    {
        $debug = self::isDebug();
        Log::app()->error($e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $debug ? $e->getTraceAsString() : null,
        ]);

        if (!headers_sent()) {
            http_response_code(500);
        }

        $msg = $debug
            ? ($e->getMessage() ?: 'Something went wrong.')
            : 'Something went wrong. Please try again later.';

        if (self::wantsJson()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Error</title></head>'
            . '<body style="font-family:sans-serif;padding:2rem;">'
            . '<h1>Something went wrong</h1>'
            . '<p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><a href="/dashboard">Back to portal</a></p>'
            . '</body></html>';
    }

    public static function handleShutdown(): void
    {
        $err = error_get_last();
        if ($err === null) {
            return;
        }
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (!in_array($err['type'], $fatal, true)) {
            return;
        }
        self::handleException(new \ErrorException(
            $err['message'],
            0,
            $err['type'],
            $err['file'],
            $err['line']
        ));
    }

    private static function isDebug(): bool
    {
        $v = getenv('APP_DEBUG');
        if ($v === false) {
            $v = $_ENV['APP_DEBUG'] ?? 'false';
        }
        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

    private static function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (stripos($accept, 'application/json') !== false) {
            return true;
        }
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '';
        if (strpos($uri, '/payments') !== false && ($_GET['action'] ?? '') === 'pay') {
            return true;
        }
        if (strpos($uri, '/cif') !== false && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            return true;
        }
        if (strpos($uri, '/forgot-password') !== false) {
            return true;
        }
        $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') !== false) {
            return true;
        }
        return false;
    }
}
