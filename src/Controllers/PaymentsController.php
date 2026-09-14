<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Services\Csrf;
use App\Services\MonerisService;
use App\Services\PaymentFields;
use App\Services\PaymentOrderId;
use App\Services\PaymentQuoteService;
use App\Services\RateLimiter;
use App\Support\Log;

/**
 * Payments page controller.
 *
 * Renders a Kanban-style board with four columns.
 * Quote/tax math: PaymentQuoteService. Kanban board assembly stays here (ARCHITECTURE backlog).
 *
 * Actions:
 *   GET  /payments          → index()  — render the Kanban board
 *   POST /payments?action=pay → pay()  — process a Moneris purchase (JSON response)
 *
 * @see src/Services/PaymentQuoteService.php
 * @see src/views/payments/payments-page.php
 * @see public/js/payments.js
 */
class PaymentsController extends BaseController
{
    public function __construct()
    {
        require_once __DIR__ . '/../config/service_colors.php';
    }

    /* ══════════════════════════════════════════════════════════════
       ACTION DISPATCHER
       Route POST ?action=pay to pay(); everything else to index().
       ══════════════════════════════════════════════════════════════ */
    public function dispatch(): void
    {
        $action = $_GET['action'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'pay') {
            $this->pay();
            return;
        }

        $this->index();
    }

    /* ══════════════════════════════════════════════════════════════
       PAY ACTION  —  POST /payments?action=pay
       Accepts JSON body or form fields:
         payment_ids[]  int[]   IDs of payments in this bundle
         amount         float   Optional client total; must match server (±0.01) or omitted
         csrf_token     string  Session CSRF (required)
         pan            string  Card number digits only
         expiry         string  MM/YYYY
         cvd            string  3- or 4-digit security code
         cardholder     string  Name on card (unused by Moneris, stored locally)
       Server amount = PaymentQuoteService::quote(). Client amount ignored if matching.
       Returns JSON: { ok, approved, error?, code?, txn_id?, ... }
       ══════════════════════════════════════════════════════════════ */
    private function pay(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId === 0) {
            Response::jsonFail('Not authenticated.', 401);
        }

        $body = file_get_contents('php://input');
        $data = json_decode($body, true);
        if (!is_array($data)) {
            $data = $_POST;
        }

        if (!Csrf::validate(isset($data[Csrf::FIELD]) ? (string) $data[Csrf::FIELD] : null)) {
            Response::jsonFail('Security token mismatch.');
        }

        $payKey = RateLimiter::bucketKey('pay', (string) $userId);
        try {
            if (!RateLimiter::hit(
                $this->db(),
                $payKey,
                RateLimiter::PAY_MAX,
                RateLimiter::WINDOW_SEC
            )) {
                Response::jsonFail('Too many payment attempts. Please try again later.');
            }
        } catch (\Throwable $e) {
            Log::payments()->error('Pay rate limit failed: ' . $e->getMessage());
        }

        $paymentIds = array_values(array_unique(array_map('intval', (array) ($data['payment_ids'] ?? []))));
        sort($paymentIds);
        $pan = preg_replace('/\D/', '', $data['pan'] ?? '');
        $expiry = trim($data['expiry'] ?? '');
        $cvd = preg_replace('/\D/', '', $data['cvd'] ?? '');

        if (empty($paymentIds) || !PaymentFields::isValid($pan, $expiry, $cvd)) {
            Response::jsonFail('Missing or invalid payment fields.');
        }

        $orderId = PaymentOrderId::build($userId, $paymentIds);
        $province = $this->livingTaxProvince($userId);
        $db = $this->db();
        $placeholders = implode(',', array_fill(0, count($paymentIds), '?'));
        $idParams = [...$paymentIds, $userId];

        /* ── Claim unpaid rows (short lock; not held during Moneris) ── */
        $verified = [];
        try {
            $db->beginTransaction();

            $lockStmt = $db->prepare(
                "SELECT id, amount, status, moneris_order_id, moneris_txn_id, moneris_response
                 FROM payments
                 WHERE id IN ($placeholders) AND user_id = ?
                 FOR UPDATE"
            );
            $lockStmt->execute($idParams);
            $rows = $lockStmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($rows) !== count($paymentIds)) {
                $db->rollBack();
                Response::jsonFail('Invalid payment selection.');
            }

            $allPaid = true;
            foreach ($rows as $row) {
                if ($row['status'] !== 'paid') {
                    $allPaid = false;
                    break;
                }
            }

            if ($allPaid) {
                $db->commit();
                $txnId = (string) ($rows[0]['moneris_txn_id'] ?? '');
                $code = (string) ($rows[0]['moneris_response'] ?? '');
                Response::jsonOk([
                    'approved' => true,
                    'idempotent' => true,
                    'code' => $code,
                    'txn_id' => $txnId,
                    'receipt' => $txnId,
                    'card_type' => '',
                ]);
            }

            foreach ($rows as $row) {
                if (!in_array($row['status'], ['pending', 'missed'], true)) {
                    $db->rollBack();
                    Response::jsonFail('Invalid payment selection.');
                }
                $existingOrder = trim((string) ($row['moneris_order_id'] ?? ''));
                if ($existingOrder !== '' && $existingOrder !== $orderId) {
                    $db->rollBack();
                    Response::jsonFail('Payment already in progress. Please wait and try again.');
                }
            }

            $alreadyClaimed = true;
            foreach ($rows as $row) {
                if (trim((string) ($row['moneris_order_id'] ?? '')) !== $orderId) {
                    $alreadyClaimed = false;
                    break;
                }
            }

            if (!$alreadyClaimed) {
                $claimStmt = $db->prepare(
                    "UPDATE payments
                     SET moneris_order_id = ?
                     WHERE id IN ($placeholders)
                       AND user_id = ?
                       AND status IN ('pending', 'missed')
                       AND (moneris_order_id IS NULL OR moneris_order_id = '')"
                );
                $claimStmt->execute([$orderId, ...$paymentIds, $userId]);
                if ($claimStmt->rowCount() !== count($paymentIds)) {
                    $db->rollBack();
                    Response::jsonFail('Payment already in progress. Please wait and try again.');
                }
            }

            $verified = $rows;
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Log::payments()->error('Payment claim failed: ' . $e->getMessage());
            Response::jsonFail('Unable to start payment. Please try again.');
        }

        $quote = PaymentQuoteService::quote($verified, $province);
        $serverAmount = $quote['total'];
        $clientAmount = isset($data['amount']) ? round((float) $data['amount'], 2) : null;
        if ($clientAmount !== null && abs($clientAmount - $serverAmount) > 0.01) {
            $this->clearPayClaim($userId, $paymentIds, $orderId);
            Response::jsonFail('Amount mismatch. Please reload and try again.');
        }

        $monerisExpiry = MonerisService::formatExpiry($expiry);
        $amountStr = number_format($serverAmount, 2, '.', '');

        $moneris = MonerisService::fromEnv();
        $result = $moneris->purchase($orderId, (string) $userId, $amountStr, $pan, $monerisExpiry, $cvd);

        if (!$result['success']) {
            Log::payments()->warning(sprintf(
                'Moneris transport/unconfirmed failure: order_id=%s user_id=%d message=%s',
                $orderId,
                $userId,
                (string) ($result['message'] ?? '')
            ));
            Response::jsonFail(
                'Payment could not be confirmed. Wait a few minutes before retrying or contact support with reference: '
                . $orderId
            );
        }

        if (!$result['approved']) {
            $this->clearPayClaim($userId, $paymentIds, $orderId);
            Response::jsonFail(
                'Payment declined: ' . ($result['message'] ?: 'Please try a different card.'),
                400,
                ['approved' => false, 'code' => $result['code']]
            );
        }

        /* ── Mark paid atomically (single UPDATE) ── */
        $today = date('Y-m-d');
        try {
            $db->beginTransaction();
            $paidStmt = $db->prepare(
                "UPDATE payments
                 SET status = 'paid',
                     paid_date = ?,
                     moneris_txn_id = ?,
                     moneris_response = ?
                 WHERE id IN ($placeholders)
                   AND user_id = ?
                   AND moneris_order_id = ?
                   AND status IN ('pending', 'missed')"
            );
            $paidStmt->execute([
                $today,
                $result['txn_id'],
                $result['code'],
                ...$paymentIds,
                $userId,
                $orderId,
            ]);

            if ($paidStmt->rowCount() !== count($paymentIds)) {
                $db->rollBack();
                Log::payments()->critical(sprintf(
                    'CRITICAL payment DB update mismatch after Moneris approve: order_id=%s txn_id=%s user_id=%d payment_ids=%s',
                    $orderId,
                    (string) $result['txn_id'],
                    $userId,
                    implode(',', $paymentIds)
                ));
                Response::jsonFail(
                    'Payment may have been charged. Contact support with reference: ' . $orderId,
                    500
                );
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Log::payments()->critical(sprintf(
                'CRITICAL payment DB update failed after Moneris approve: order_id=%s txn_id=%s user_id=%d payment_ids=%s err=%s',
                $orderId,
                (string) ($result['txn_id'] ?? ''),
                $userId,
                implode(',', $paymentIds),
                $e->getMessage()
            ));
            Response::jsonFail(
                'Payment may have been charged. Contact support with reference: ' . $orderId,
                500
            );
        }

        Response::jsonOk([
            'approved' => true,
            'code' => $result['code'],
            'txn_id' => $result['txn_id'],
            'receipt' => $result['receipt'],
            'card_type' => $result['card_type'],
        ]);
    }

    /** @param list<int> $paymentIds */
    private function clearPayClaim(int $userId, array $paymentIds, string $orderId): void
    {
        if ($paymentIds === []) {
            return;
        }
        try {
            $placeholders = implode(',', array_fill(0, count($paymentIds), '?'));
            $stmt = $this->db()->prepare(
                "UPDATE payments
                 SET moneris_order_id = NULL
                 WHERE id IN ($placeholders)
                   AND user_id = ?
                   AND moneris_order_id = ?
                   AND status IN ('pending', 'missed')"
            );
            $stmt->execute([...$paymentIds, $userId, $orderId]);
        } catch (\Throwable $e) {
            Log::payments()->error(sprintf(
                'clearPayClaim failed: order_id=%s user_id=%d err=%s',
                $orderId,
                $userId,
                $e->getMessage()
            ));
        }
    }

    private function livingTaxProvince(int $userId): string
    {
        $stmt = $this->db()->prepare(
            "SELECT province_state FROM client_addresses
             WHERE user_id = ? AND address_type = 'living' LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        return PaymentQuoteService::resolveProvince($row['province_state'] ?? null);
    }

    /* ══════════════════════════════════════════════════════════════
       INDEX — render the Kanban board
       ══════════════════════════════════════════════════════════════ */
    public function index(): void
    {
        Csrf::ensureToken();

        $userId = (int) ($_SESSION['user_id'] ?? 0);

        /* ── 1. Service costs (main) ──────────────────────────────── */
        $serviceCosts = $this->db()->query(
            "SELECT * FROM service_costs WHERE is_extra = 0 ORDER BY label"
        )->fetchAll(\PDO::FETCH_ASSOC);

        /* ── 2. Payment plans for this user ──────────────────────── */
        $plansStmt = $this->db()->prepare(
            "SELECT pp.*, sc.service_type, sc.label AS service_label, sc.total_cost
             FROM payment_plans pp
             JOIN service_costs sc ON sc.id = pp.service_cost_id
             WHERE pp.user_id = ?
             ORDER BY sc.label"
        );
        $plansStmt->execute([$userId]);
        $plans = $plansStmt->fetchAll(\PDO::FETCH_ASSOC);

        /* ── 3. All payments (installments) ──────────────────────── */
        $paymentsStmt = $this->db()->prepare(
            "SELECT p.*, sc.service_type, sc.label AS service_label, pp.installments AS plan_installments
             FROM payments p
             JOIN payment_plans pp ON pp.id = p.payment_plan_id
             JOIN service_costs sc ON sc.id = pp.service_cost_id
             WHERE p.user_id = ?
             ORDER BY p.due_date ASC, sc.label ASC"
        );
        $paymentsStmt->execute([$userId]);
        $allPayments = $paymentsStmt->fetchAll(\PDO::FETCH_ASSOC);

        /* Group payments by due_date to create monthly bundles */
        $monthlyGroups = [];
        foreach ($allPayments as $pmt) {
            $monthKey = $pmt['due_date'];
            $monthlyGroups[$monthKey][] = $pmt;
        }

        /* Build monthly installment bundles */
        $paid    = [];
        $pending = [];
        $missed  = [];

        foreach ($monthlyGroups as $dueDate => $pmts) {
            $totalAmount = 0.0;
            $services    = [];
            $svcTypes    = [];
            $statuses    = [];
            $methods     = [];
            $paidDate    = null;
            $paymentIds  = [];
            $instNum     = 0;
            $isExtra     = false;

            foreach ($pmts as $p) {
                $totalAmount += (float) $p['amount'];
                $services[]   = $p['service_label'];
                $svcTypes[]   = $p['service_type'];
                $statuses[]   = $p['status'];
                $paymentIds[] = (int) $p['id'];
                if (!empty($p['method'])) {
                    $methods[$p['method']] = true;
                }
                if (!empty($p['paid_date']) && ($paidDate === null || $p['paid_date'] > $paidDate)) {
                    $paidDate = $p['paid_date'];
                }
                $instNum = max($instNum, (int) $p['installment_number']);

                if (count($pmts) === 1 && $p['service_type'] === 'trp' && (int) $p['installment_number'] > 12) {
                    $isExtra = true;
                }
            }

            $bundle = [
                'due_date'      => $dueDate,
                'total_amount'  => $totalAmount,
                'services'      => array_values(array_unique($services)),
                'service_types' => array_values(array_unique($svcTypes)),
                'installment'   => $instNum,
                'paid_date'     => $paidDate,
                'methods'       => array_keys($methods),
                'payment_ids'   => $paymentIds,
                'payments'      => $pmts,
                'is_trp_extra'  => $isExtra,
                'references'    => array_values(array_filter(array_unique(array_column($pmts, 'reference_number')))),
            ];

            if (in_array('missed', $statuses, true)) {
                $bundle['status'] = 'missed';
                $missed[] = $bundle;
            } elseif (in_array('pending', $statuses, true)) {
                $bundle['status'] = 'pending';
                $pending[] = $bundle;
            } else {
                $bundle['status'] = 'paid';
                $paid[] = $bundle;
            }
        }

        /* ── 4. Extra services ────────────────────────────────────── */
        $extraStmt = $this->db()->prepare(
            "SELECT ue.*, sc.label AS service_label, sc.service_type
             FROM user_extra_services ue
             JOIN service_costs sc ON sc.id = ue.service_cost_id
             WHERE ue.user_id = ?
             ORDER BY ue.created_at"
        );
        $extraStmt->execute([$userId]);
        $extras = $extraStmt->fetchAll(\PDO::FETCH_ASSOC);

        /* ── 5. Summary stats ─────────────────────────────────────── */
        $totalPaid    = array_sum(array_column($paid, 'total_amount'));
        $totalPending = array_sum(array_column($pending, 'total_amount'))
            + array_sum(array_column($missed, 'total_amount'));

        /* Sum of all missed (unpaid) installments up to today */
        $totalMissed = array_sum(array_column($missed, 'total_amount'));

        /* Discount — placeholder (admin can configure later) */
        $discount = 0.0;

        /* ── 6. Credit card info for display ─────────────────────── */
        $ccStmt = $this->db()->prepare(
            "SELECT cc_last4, cc_expiry FROM users WHERE id = ?"
        );
        $ccStmt->execute([$userId]);
        $ccRow    = $ccStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        $ccLast4  = $ccRow['cc_last4']  ?? '';
        $ccExpiry = $ccRow['cc_expiry'] ?? '';

        /* ── 7. Plan stats for Service Cost column ────────────────── */
        $planPaidCounts = [];
        foreach ($allPayments as $p) {
            $planId = (int) $p['payment_plan_id'];
            if ($p['status'] === 'paid') {
                $planPaidCounts[$planId] = ($planPaidCounts[$planId] ?? 0) + 1;
            }
        }

        $serviceColors = SERVICE_COLORS;
        $methodLabels  = METHOD_LABELS;
        $taxProvince   = $this->livingTaxProvince($userId);
        $taxRate       = PaymentQuoteService::taxRate($taxProvince);
        $pageTitle     = 'Payments';
        $activePage    = 'payments';

        $content = $this->render(
            __DIR__ . '/../views/payments/payments-page.php',
            compact(
                'pageTitle',
                'activePage',
                'serviceCosts',
                'plans',
                'paid',
                'pending',
                'missed',
                'extras',
                'serviceColors',
                'methodLabels',
                'planPaidCounts',
                'totalPaid',
                'totalPending',
                'totalMissed',
                'discount',
                'taxProvince',
                'taxRate',
                'ccLast4',
                'ccExpiry'
            )
        );

        require __DIR__ . '/../views/layouts/main.php';
    }
}
