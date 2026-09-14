<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Path containment + safe Content-Disposition for file downloads.
 */
final class SecureDownload
{
    /**
     * Resolve a path that must live under $base (absolute upload root).
     * $relativeOrAbsolute may be absolute (documents) or relative (CIF PDFs).
     */
    public static function resolveUnderBase(string $base, string $relativeOrAbsolute): ?string
    {
        $base = trim($base);
        $relativeOrAbsolute = trim($relativeOrAbsolute);
        if ($base === '' || $relativeOrAbsolute === '') {
            return null;
        }

        $baseReal = realpath($base);
        if ($baseReal === false || !is_dir($baseReal)) {
            return null;
        }

        $candidate = $relativeOrAbsolute;
        if (!self::isAbsolutePath($candidate)) {
            $candidate = rtrim($baseReal, '/\\') . DIRECTORY_SEPARATOR
                . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);
        }

        $resolved = realpath($candidate);
        if ($resolved === false || !is_file($resolved)) {
            return null;
        }

        $baseNorm = self::normalizeDirPrefix($baseReal);
        $fileNorm = self::normalizePath($resolved);
        if (strpos($fileNorm, $baseNorm) !== 0) {
            return null;
        }

        return $resolved;
    }

    public static function safeContentDisposition(string $downloadName, string $disposition = 'attachment'): string
    {
        $disposition = strtolower($disposition) === 'inline' ? 'inline' : 'attachment';
        $name = basename(str_replace(["\r", "\n", '"'], '', $downloadName));
        $name = preg_replace('/[^\x20-\x7E]/', '_', $name) ?? '';
        $name = trim($name, " .\t");
        if ($name === '') {
            $name = 'download';
        }
        return $disposition . '; filename="' . $name . '"';
    }

    public static function sendFile(
        string $absPath,
        string $mime,
        string $downloadName,
        string $disposition = 'attachment'
    ): void {
        $size = filesize($absPath);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . self::safeContentDisposition($downloadName, $disposition));
        if ($size !== false) {
            header('Content-Length: ' . $size);
        }
        header('Cache-Control: private, no-cache, no-store');
        header('Pragma: no-cache');

        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        readfile($absPath);
        exit;
    }

    private static function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }
        return (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }

    private static function normalizePath(string $path): string
    {
        return strtolower(str_replace('\\', '/', $path));
    }

    private static function normalizeDirPrefix(string $dir): string
    {
        return rtrim(self::normalizePath($dir), '/') . '/';
    }
}
