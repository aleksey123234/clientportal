<?php

declare(strict_types=1);

namespace App\Services;

/**
 * CIF progress / completion helpers for save semantics.
 *
 * completed_at is set only when progress first reaches 100%;
 * later saves must not clear or shift it (see CifController UPSERT).
 *
 * @see CifProgress::nextCompletedAt() for pure COALESCE semantics (unit-tested)
 */
class CifProgress
{
    /**
     * Timestamp to bind for INSERT/VALUES when progress >= 100; null otherwise.
     * SQL ON DUPLICATE KEY UPDATE decides whether to keep an existing value.
     */
    public static function completedAtForSave(int $progress): ?string
    {
        return $progress >= 100 ? date('Y-m-d H:i:s') : null;
    }

    /**
     * Effective completed_at after a save, mirroring UPSERT:
     * IF progress >= 100 THEN COALESCE(existing, candidate) ELSE existing.
     *
     * Controller writes still use SQL + completedAtForSave(); this helper documents
     * and unit-tests the intended outcome without a DB.
     */
    public static function nextCompletedAt(
        int $progress,
        ?string $existingCompletedAt,
        ?string $candidateNow = null
    ): ?string {
        if ($progress < 100) {
            return $existingCompletedAt !== null && $existingCompletedAt !== ''
                ? $existingCompletedAt
                : null;
        }

        if ($existingCompletedAt !== null && $existingCompletedAt !== '') {
            return $existingCompletedAt;
        }

        return $candidateNow !== null && $candidateNow !== ''
            ? $candidateNow
            : date('Y-m-d H:i:s');
    }
}
