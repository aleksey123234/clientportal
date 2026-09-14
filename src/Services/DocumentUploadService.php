<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Validate + store client document uploads.
 */
final class DocumentUploadService
{
    private const MAX_SIZE = 25 * 1024 * 1024;

    private const ALLOWED_MIME = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
    ];

    private const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'txt'];

    public static function uploadBaseDir(): string
    {
        $base = $_ENV['UPLOAD_BASE_PATH'] ?? '';
        if ($base !== '') {
            return rtrim($base, '/\\');
        }
        return rtrim(dirname(__DIR__, 2) . '/storage/uploads', '/\\');
    }

    /**
     * @param array<string, mixed> $file  $_FILES['doc_file']
     * @return array{ok: bool, error: ?string, title: ?string, service_type: ?string, abs_path: ?string}
     */
    public static function store(
        PDO $db,
        int $userId,
        string $serviceType,
        string $docKey,
        array $file,
        ?string $customFileName = null
    ): array
    {
        $fail = static fn(string $e): array => [
            'ok' => false, 'error' => $e, 'title' => null, 'service_type' => null, 'abs_path' => null,
        ];

        $serviceType = trim($serviceType);
        $docKey = trim($docKey);
        if ($serviceType === '' || $docKey === '') {
            return $fail('Missing document identifier.');
        }
        if (!isset(DocumentCatalog::SERVICES[$serviceType])) {
            return $fail('Unknown service type.');
        }
        $allowedKeys = DocumentCatalog::allowedKeys($serviceType);
        if (
            !preg_match('/^[a-z0-9_-]+$/i', $docKey)
            || strpos($docKey, '..') !== false
            || !in_array($docKey, $allowedKeys, true)
        ) {
            return $fail('Invalid document type.');
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $code = $file['error'] ?? -1;
            $msg = $code === UPLOAD_ERR_INI_SIZE
                ? 'File exceeds maximum allowed size.'
                : 'No file uploaded or upload error (code ' . $code . ').';
            return $fail($msg);
        }

        if (($file['size'] ?? 0) > self::MAX_SIZE) {
            return $fail('File exceeds the 25 MB limit.');
        }

        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            return $fail('File type not allowed. Accepted: PDF, JPG, PNG, DOC, DOCX, TXT.');
        }

        $ext = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return $fail('File type not allowed. Accepted: PDF, JPG, PNG, DOC, DOCX, TXT.');
        }

        $safeName = $docKey . '_' . date('Ymd_His') . '.' . $ext;
        $absDir = self::uploadBaseDir() . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . $serviceType;
        if (!is_dir($absDir)) {
            mkdir($absDir, 0755, true);
        }
        $absPath = $absDir . DIRECTORY_SEPARATOR . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            return $fail('Failed to save file. Please try again.');
        }

        $title = DocumentCatalog::titleFor($serviceType, $docKey);
        $displayName = self::resolveDisplayFileName(
            (string) ($file['name'] ?? ''),
            $customFileName,
            $ext
        );
        $db->prepare(
            'INSERT INTO documents (user_id, service_type, doc_key, title, file_name, file_path, file_size, mime_type, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $userId,
            $serviceType,
            $docKey,
            $title,
            $displayName,
            $absPath,
            $file['size'],
            $mime,
            'uploaded',
        ]);

        return [
            'ok' => true,
            'error' => null,
            'title' => $title,
            'service_type' => $serviceType,
            'abs_path' => $absPath,
        ];
    }

    /**
     * Build a safe display/download filename; always keeps the real upload extension.
     */
    public static function resolveDisplayFileName(
        string $originalName,
        ?string $customFileName,
        string $ext
    ): string {
        $ext = strtolower(ltrim($ext, '.'));
        $candidate = trim((string) $customFileName);
        if ($candidate === '') {
            $candidate = $originalName;
        }

        $candidate = basename(str_replace(["\0", '\\'], ['', '/'], $candidate));
        $candidate = preg_replace('/[\x00-\x1F\x7F]/', '', $candidate) ?? '';
        $candidate = trim($candidate);
        if ($candidate === '' || $candidate === '.' || $candidate === '..') {
            $candidate = $originalName !== '' ? basename($originalName) : ('document.' . $ext);
        }

        $base = pathinfo($candidate, PATHINFO_FILENAME);
        $base = trim((string) $base);
        if ($base === '') {
            $base = 'document';
        }

        $maxBaseLen = 200 - (strlen($ext) + 1);
        if ($maxBaseLen < 1) {
            $maxBaseLen = 1;
        }
        if (strlen($base) > $maxBaseLen) {
            $base = substr($base, 0, $maxBaseLen);
        }

        return $base . '.' . $ext;
    }
}
