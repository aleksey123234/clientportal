#!/usr/bin/env php
<?php

/**
 * Syntax-check PHP sources (php -l).
 *
 * Usage (from client-portal/):
 *   php bin/lint-php.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$targets = [
    $root . DIRECTORY_SEPARATOR . 'src',
    $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php',
    $root . DIRECTORY_SEPARATOR . 'bin',
];

$files = [];
foreach ($targets as $target) {
    if (is_file($target) && substr($target, -4) === '.php') {
        $files[] = $target;
        continue;
    }
    if (!is_dir($target)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        /** @var SplFileInfo $file */
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files, SORT_STRING);
$failed = 0;

foreach ($files as $file) {
    $cmd = 'php -l ' . escapeshellarg($file) . ' 2>&1';
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);
    if ($code !== 0) {
        $failed++;
        echo implode(PHP_EOL, $out) . PHP_EOL;
    }
}

if ($failed > 0) {
    fwrite(STDERR, "lint-php: {$failed} file(s) failed php -l" . PHP_EOL);
    exit(1);
}

echo 'lint-php: OK (' . count($files) . ' files)' . PHP_EOL;
exit(0);
