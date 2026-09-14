<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Log;

/**
 * Moneris Direct API wrapper.
 *
 * Sends Purchase transactions to the Moneris gateway via cURL (no
 * third-party SDK required — avoids composer dependency on mpgClasses).
 *
 * Response codes:
 *   "001"–"049"   => Approved
 *   "050"–"999"   => Declined / error
 *   "null"        => System/communication error
 *
 * Test credentials (QA sandbox):
 *   store_id  = store5
 *   api_token = yesguy
 *   URL       = https://esqa.moneris.com/gateway2/servlet/MpgRequest
 *
 * Test cards:
 *   Visa   4242424242424242
 *   MC     5454545454545454
 *   Expiry any future MMYY, CVD any 3 digits
 *
 * @see https://developer.moneris.com
 */
class MonerisService
{
    private const PROD_URL = 'https://www3.moneris.com/gateway2/servlet/MpgRequest';
    private const QA_URL   = 'https://esqa.moneris.com/gateway2/servlet/MpgRequest';

    private string $storeId;
    private string $apiToken;
    private bool   $testMode;

    public function __construct(string $storeId, string $apiToken, bool $testMode = false)
    {
        $this->storeId  = $storeId;
        $this->apiToken = $apiToken;
        $this->testMode = $testMode;
    }

    /* ──────────────────────────────────────────────────────────────
       Factory: build from environment variables.
       Usage: MonerisService::fromEnv()
       ────────────────────────────────────────────────────────────── */
    public static function fromEnv(): self
    {
        $storeId  = $_ENV['MONERIS_STORE_ID']  ?? getenv('MONERIS_STORE_ID')  ?? '';
        $apiToken = $_ENV['MONERIS_API_TOKEN'] ?? getenv('MONERIS_API_TOKEN') ?? '';
        $testMode = filter_var(
            $_ENV['MONERIS_TEST_MODE'] ?? getenv('MONERIS_TEST_MODE') ?? 'false',
            FILTER_VALIDATE_BOOLEAN
        );
        return new self($storeId, $apiToken, $testMode);
    }

    /* ──────────────────────────────────────────────────────────────
       Purchase transaction.

       @param string $orderId    Unique order ID (max 50 chars).
                                 Tip: use "portal_<paymentId>_<time()>"
       @param string $custId     Client identifier (user ID as string).
       @param string $amount     Decimal string, e.g. "13.00".
       @param string $pan        Card number, digits only.
       @param string $expdate    Expiry in YYMM format (e.g. "2612").
       @param string $cvd        3- or 4-digit security code.

       @return array {
           success:  bool
           approved: bool           (response code 001–049)
           code:     string         raw response code
           message:  string         gateway message
           txn_id:   string         Moneris TxnNumber (for refunds)
           order_id: string         echo of $orderId
           card_type:string
           receipt:  string
       }
       ────────────────────────────────────────────────────────────── */
    public function purchase(
        string $orderId,
        string $custId,
        string $amount,
        string $pan,
        string $expdate,
        string $cvd
    ): array {
        $xml = $this->buildPurchaseXml($orderId, $custId, $amount, $pan, $expdate, $cvd);
        $raw = $this->post($xml);

        if ($raw === false) {
            return $this->errorResult($orderId, 'Gateway connection failed.');
        }

        return $this->parseResponse($raw, $orderId);
    }

    /* ── XML builder ──────────────────────────────────────────── */
    private function buildPurchaseXml(
        string $orderId,
        string $custId,
        string $amount,
        string $pan,
        string $expdate,
        string $cvd
    ): string {
        $esc = fn(string $v): string => htmlspecialchars($v, ENT_XML1, 'UTF-8');

        // Moneris Direct API: NO <?xml declaration — bare <request> root only.
        // CVD block is optional; omit entirely when no CVD provided.
        $cvdBlock = $cvd !== ''
            ? '<cvd_info><cvd_indicator>1</cvd_indicator><cvd_value>' . $esc($cvd) . '</cvd_value></cvd_info>'
            : '';

        // Build XML using string concatenation to avoid heredoc indentation
        // issues in PHP 7.x and to guarantee no leading whitespace or BOM.
        $xml  = '<request>';
        $xml .= '<store_id>'  . $esc($this->storeId)  . '</store_id>';
        $xml .= '<api_token>' . $esc($this->apiToken) . '</api_token>';
        $xml .= '<purchase>';
        $xml .= '<order_id>'  . $esc($orderId)  . '</order_id>';
        $xml .= '<cust_id>'   . $esc($custId)   . '</cust_id>';
        $xml .= '<amount>'    . $esc($amount)   . '</amount>';
        $xml .= '<pan>'       . $esc($pan)      . '</pan>';
        $xml .= '<expdate>'   . $esc($expdate)  . '</expdate>';
        $xml .= '<crypt_type>7</crypt_type>';
        $xml .= $cvdBlock;
        $xml .= '</purchase>';
        $xml .= '</request>';

        return $xml;
    }

    /* ── HTTP POST via cURL ───────────────────────────────────── */
    /** @return string|false */
    private function post(string $xml)
    {
        $url = $this->testMode ? self::QA_URL : self::PROD_URL;

        // In test mode the QA server uses a self-signed cert that Windows
        // PHP cannot verify without a CA bundle — disable peer verification
        // for sandbox only.  Production always verifies the peer certificate.
        $caBundle = __DIR__ . '/../../vendor/curl-ca-bundle/cacert.pem';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'xml=' . urlencode($xml),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => !$this->testMode,
            CURLOPT_SSL_VERIFYHOST => $this->testMode ? 0 : 2,
            CURLOPT_CAINFO         => (!$this->testMode && file_exists($caBundle)) ? $caBundle : null,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $errMsg   = curl_error($ch);
        curl_close($ch);

        if ($errno !== 0) {
            Log::payments()->error("MonerisService cURL error [{$errno}]: {$errMsg}");
            return false;
        }

        return $response;
    }

    /* ── XML response parser ──────────────────────────────────── */
    private function parseResponse(string $raw, string $orderId): array
    {
        // Suppress XML parse warnings; return error if malformed.
        $prev = libxml_use_internal_errors(true);
        $xml  = simplexml_load_string($raw);
        libxml_use_internal_errors($prev);

        if ($xml === false) {
            return $this->errorResult($orderId, 'Invalid response from gateway.');
        }

        $receipt = $xml->receipt ?? null;
        if ($receipt === null) {
            return $this->errorResult($orderId, 'No receipt in gateway response.');
        }

        $code     = (string)($receipt->ResponseCode ?? 'null');
        $message  = (string)($receipt->Message      ?? '');
        $txnId    = (string)($receipt->TxnNumber    ?? '');
        $cardType = (string)($receipt->CardType     ?? '');
        $receiptId = (string)($receipt->ReceiptId   ?? '');

        // Moneris: codes 001-049 = approved, anything else = declined/error
        $approved = ($code !== 'null') && ((int)$code >= 1) && ((int)$code <= 49);

        return [
            'success'   => true,       // communication succeeded
            'approved'  => $approved,
            'code'      => $code,
            'message'   => $message,
            'txn_id'    => $txnId,
            'order_id'  => $orderId,
            'card_type' => $cardType,
            'receipt'   => $receiptId,
        ];
    }

    /* ── Error result helper ──────────────────────────────────── */
    private function errorResult(string $orderId, string $message): array
    {
        return [
            'success'   => false,
            'approved'  => false,
            'code'      => 'null',
            'message'   => $message,
            'txn_id'    => '',
            'order_id'  => $orderId,
            'card_type' => '',
            'receipt'   => '',
        ];
    }

    /* ── Utility: convert user-entered MM/YYYY expiry to YYMM ── */
    public static function formatExpiry(string $input): string
    {
        // Accept: MM/YYYY or MM/YY
        $input = preg_replace('/\D/', '', $input); // digits only
        if (strlen($input) === 6) {
            // MMYYYY → YYMM
            $mm = substr($input, 0, 2);
            $yy = substr($input, 4, 2);
            return $yy . $mm;
        }
        if (strlen($input) === 4) {
            // MMYY → YYMM
            $mm = substr($input, 0, 2);
            $yy = substr($input, 2, 2);
            return $yy . $mm;
        }
        return $input; // fallback: return as-is
    }
}
