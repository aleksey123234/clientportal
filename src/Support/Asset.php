<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Local asset URLs with cache-bust query (?v=filemtime).
 */
final class Asset
{
    /** Absolute path to public/ */
    private static function publicRoot(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';
    }

    /**
     * @param string $webPath Path beginning with / (e.g. /js/profile.js)
     */
    public static function url(string $webPath): string
    {
        $webPath = '/' . ltrim($webPath, '/');
        $fsPath = self::publicRoot() . str_replace('/', DIRECTORY_SEPARATOR, $webPath);
        if (is_file($fsPath)) {
            return $webPath . '?v=' . (string) filemtime($fsPath);
        }
        return $webPath;
    }

    /**
     * Render a pinned CDN <link> or <script> from config/cdn.php.
     *
     * @param 'css'|'js' $type
     */
    public static function cdnTag(string $key, string $type = 'css'): string
    {
        static $cdn = null;
        if ($cdn === null) {
            $cdn = require dirname(__DIR__, 2) . '/config/cdn.php';
        }
        if (!isset($cdn[$key])) {
            return '';
        }
        $url = htmlspecialchars($cdn[$key]['url'], ENT_QUOTES, 'UTF-8');
        $integrity = htmlspecialchars($cdn[$key]['integrity'], ENT_QUOTES, 'UTF-8');
        if ($type === 'js') {
            return '<script src="' . $url . '" integrity="' . $integrity
                . '" crossorigin="anonymous"></script>';
        }
        return '<link rel="stylesheet" href="' . $url . '" integrity="' . $integrity
            . '" crossorigin="anonymous">';
    }
}
