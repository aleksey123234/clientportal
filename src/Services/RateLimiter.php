<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Persisted rate limits (rate_limit_buckets).
 *
 * hit() returns true if the request is allowed (count bumped), false if throttled
 * (count still bumped so the window does not reset oddly).
 */
class RateLimiter
{
    public const LOGIN_MAX = 10;
    public const FORGOT_MAX = 5;
    public const PAY_MAX = 10;
    public const WINDOW_SEC = 900;

    public static function bucketKey(string $action, string $id): string
    {
        return $action . ':' . $id;
    }

    public static function clientIp(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return $ip !== '' ? $ip : 'unknown';
    }

    /**
     * @return bool true = allowed; false = throttled
     */
    public static function hit(PDO $db, string $key, int $max, int $windowSec): bool
    {
        $now = time();
        $startedTx = false;
        try {
            if (!$db->inTransaction()) {
                $db->beginTransaction();
                $startedTx = true;
            }

            $stmt = $db->prepare(
                'SELECT window_start, hit_count FROM rate_limit_buckets WHERE bucket_key = ? FOR UPDATE'
            );
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || ($now - (int) $row['window_start']) > $windowSec) {
                $upsert = $db->prepare(
                    'INSERT INTO rate_limit_buckets (bucket_key, window_start, hit_count)
                     VALUES (?, ?, 1)
                     ON DUPLICATE KEY UPDATE window_start = VALUES(window_start), hit_count = VALUES(hit_count)'
                );
                $upsert->execute([$key, $now]);
                if ($startedTx) {
                    $db->commit();
                }
                return true;
            }

            $count = (int) $row['hit_count'];
            $allowed = $count < $max;
            $newCount = $count + 1;

            $upd = $db->prepare(
                'UPDATE rate_limit_buckets SET hit_count = ? WHERE bucket_key = ?'
            );
            $upd->execute([$newCount, $key]);

            if ($startedTx) {
                $db->commit();
            }
            return $allowed;
        } catch (\Throwable $e) {
            if ($startedTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
