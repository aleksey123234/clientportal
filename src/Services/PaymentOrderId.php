<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Deterministic Moneris order_id for a user + payment id set (Phase 4a).
 *
 * Claim / gateway policy (PaymentsController::pay):
 * - order_id is stable for the same user + payment id set (replay-safe).
 * - Short FOR UPDATE claim sets moneris_order_id before gateway call.
 * - Transport/timeout (success=false): keep claim; do not clear; user may see
 *   "already in progress" on retry until ops clears or Moneris settles.
 * - Explicit decline: clearPayClaim so the user can try again.
 * - Already-paid selection: idempotent JSON ok (no second charge).
 *
 * @see docs/test-plan-payments.md §18.10–18.11
 * @see docs/test-plan-hardening.md
 */
final class PaymentOrderId
{
    /**
     * @param list<int|string> $paymentIds
     */
    public static function build(int $userId, array $paymentIds): string
    {
        $ids = array_values(array_unique(array_map('intval', $paymentIds)));
        sort($ids);
        return 'portal_u' . $userId . '_' . substr(hash('sha256', implode(',', $ids)), 0, 16);
    }
}
