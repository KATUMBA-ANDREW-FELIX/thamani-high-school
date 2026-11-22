<?php
/**
 * Thamani High School - bitlink pay (Yo! Uganda Mobile Money Collection + Receipts)
 * ---------------------------------------------------------------------------------
 * Customer-facing checkout, SMS receipts, and email receipts are branded as
 * "bitlink pay" (#powered by bitlink ug#), matching the STEIM site architecture,
 * while Mobile Money collections and polling run on Yo! Payments (Uganda) XML API.
 */

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/sms_helper.php';

if (!function_exists('bitlink_pay_env')) {
    function bitlink_pay_env(string $key, string $default = ''): string {
        static $fileEnv = null;
        if ($fileEnv === null) {
            $fileEnv = [];
            $envPath = __DIR__ . '/.env';
            if (is_readable($envPath)) {
                foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
                    [$k, $v] = explode('=', $line, 2);
                    $fileEnv[trim($k)] = trim(trim($v), "\"'");
                }
            }
        }
        $val = getenv($key);
        if ($val !== false && trim((string)$val) !== '') {
            return trim((string)$val);
        }
        if (isset($fileEnv[$key]) && $fileEnv[$key] !== '') {
            return $fileEnv[$key];
        }
        return $default;
    }
}

if (!function_exists('bitlink_pay_config')) {
    function bitlink_pay_config(): array {
        $unitPrice = max(1, (int)bitlink_pay_env('SMS_UNIT_PRICE_UGX', '50'));
        $collPct   = (float)bitlink_pay_env('BITLINK_COLLECTION_FEE_PERCENT', '4.0');
        $thamaniEmail = bitlink_pay_env('THAMANI_RECEIPT_EMAIL', 'info@thamani.ac.ug');
        $supportEmail = bitlink_pay_env('BITLINK_SUPPORT_EMAIL', 'felxkatumba4@gmail.com');
        $supportPhone = bitlink_pay_env('BITLINK_SUPPORT_PHONE', '0700126209');
        $fromEmail    = bitlink_pay_env('BITLINK_RECEIPT_FROM_EMAIL', $thamaniEmail);
        $fromName     = bitlink_pay_env('BITLINK_RECEIPT_FROM_NAME', 'bitlink pay');
        $mode         = strtolower(bitlink_pay_env('YO_PAYMENTS_MODE', 'production'));

        return [
            'unit_price_ugx'         => $unitPrice,
            'collection_fee_percent' => $collPct,
            'clearing_fee_tiers'     => [
                ['min_ugx' => 500,       'max_ugx' => 60000,   'fee_ugx' => 700],
                ['min_ugx' => 60001,     'max_ugx' => 500000,  'fee_ugx' => 1100],
                ['min_ugx' => 500001,    'max_ugx' => 1000000, 'fee_ugx' => 1700],
                ['min_ugx' => 1000001,   'max_ugx' => null,    'fee_ugx' => 2100],
            ],
            'packages' => [
                ['sms_units' => 300,  'label' => 'Starter Pack · 300 SMS'],
                ['sms_units' => 600,  'label' => 'Termly Pack · 600 SMS'],
                ['sms_units' => 1200, 'label' => 'School Pack · 1,200 SMS'],
                ['sms_units' => 3000, 'label' => 'Bulk Campus Pack · 3,000 SMS'],
            ],
            'thamani_receipt_email'  => $thamaniEmail,
            'support_email'          => $supportEmail,
            'support_phone'          => $supportPhone,
            'from_email'             => $fromEmail,
            'from_name'              => $fromName,
            'mode'                   => bitlink_pay_is_mock() ? 'mock' : $mode,
        ];
    }
}

if (!function_exists('bitlink_pay_calculate_fees')) {
    /**
     * Calculate SMS package subtotal, 4% collection fee, Yo! clearing fee tier, and total UGX.
     */
    function bitlink_pay_calculate_fees(int $smsUnits): array {
        $cfg       = bitlink_pay_config();
        $smsUnits  = max(10, $smsUnits);
        $unitPrice = (int)$cfg['unit_price_ugx'];
        $subtotal  = $smsUnits * $unitPrice;

        $collectionFee = (int)round($subtotal * ((float)$cfg['collection_fee_percent'] / 100.0));
        $clearingFee   = 700;
        foreach ($cfg['clearing_fee_tiers'] as $tier) {
            if ($subtotal < $tier['min_ugx']) continue;
            if ($tier['max_ugx'] === null || $subtotal <= $tier['max_ugx']) {
                $clearingFee = (int)$tier['fee_ugx'];
                break;
            }
        }

        return [
            'sms_units'          => $smsUnits,
            'unit_price_ugx'     => $unitPrice,
            'subtotal_ugx'       => $subtotal,
            'collection_fee_ugx' => $collectionFee,
            'clearing_fee_ugx'   => $clearingFee,
            'processing_fee_ugx' => $collectionFee + $clearingFee,
            'total_ugx'          => $subtotal + $collectionFee + $clearingFee,
        ];
    }
}

if (!function_exists('bitlink_pay_get_wallet_balance')) {
    function bitlink_pay_get_wallet_balance($db): int {
        $raw = thamani_get_setting($db, 'sms_credit_balance', '');
        if ($raw === '') {
            // Initialize default school wallet with 250 starter SMS credits
            thamani_set_setting($db, 'sms_credit_balance', '250');
            return 250;
        }
        return max(0, (int)$raw);
    }

    function bitlink_pay_adjust_wallet_balance($db, int $delta): int {
        $current = bitlink_pay_get_wallet_balance($db);
        $next    = max(0, $current + $delta);
        thamani_set_setting($db, 'sms_credit_balance', (string)$next);
        return $next;
    }
}

if (!function_exists('bitlink_pay_is_mock')) {
    function bitlink_pay_is_mock(): bool {
        $mock = strtolower(bitlink_pay_env('YO_PAYMENTS_MOCK', ''));
        if (in_array($mock, ['1', 'true', 'yes'], true)) return true;
        if (in_array($mock, ['0', 'false', 'no'], true)) return false;
        return false;
    }

    function bitlink_pay_base_url(): string {
        $explicit = bitlink_pay_env('YO_PAYMENTS_BASE_URL', '');
        if ($explicit !== '') return rtrim($explicit, '/');
        $mode = strtolower(bitlink_pay_env('YO_PAYMENTS_MODE', 'production'));
        return $mode === 'production'
            ? 'https://paymentsapi1.yo.co.ug/ybs/task.php'
            : 'https://sandbox.yo.co.ug/services/yopaymentsdev/task.php';
    }

    function bitlink_pay_ipn_urls(): array {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
        $scheme = $https ? 'https' : 'http';
        $host   = trim((string)($_SERVER['HTTP_HOST'] ?? bitlink_pay_env('DOMAIN', 'thamaniacademy.work.gd')));
        $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $base   = "{$scheme}://{$host}{$dir}";

        $instant = bitlink_pay_env('YO_PAYMENTS_INSTANT_NOTIFICATION_URL', "{$base}/bitlink_pay_ipn.php");
        $failure = bitlink_pay_env('YO_PAYMENTS_FAILURE_NOTIFICATION_URL', "{$base}/bitlink_pay_ipn.php?type=failure");
        return [$instant, $failure];
    }
}

if (!function_exists('bitlink_pay_xml_post')) {
    /**
     * Send an XML request to Yo! Payments (Uganda) and parse the <Response> block.
     */
    function bitlink_pay_xml_post(string $xml): array {
        if (!function_exists('curl_init')) {
            return ['_ok' => false, '_error' => 'PHP cURL extension is required for bitlink pay.'];
        }

        $timeout = max(10, (int)bitlink_pay_env('YO_PAYMENTS_TIMEOUT_SECONDS', '30'));
        $ch = curl_init(bitlink_pay_base_url());
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: text/xml',
                'Content-transfer-encoding: text',
                'Accept: text/xml',
                'Content-Length: ' . strlen($xml),
            ],
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        unset($ch);

        if ($curlErr || $raw === false) {
            return ['_ok' => false, '_error' => 'Could not reach bitlink pay gateway: ' . $curlErr];
        }

        $prev = libxml_use_internal_errors(true);
        $sx   = simplexml_load_string((string)$raw);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if ($sx === false) {
            return ['_ok' => false, '_error' => "bitlink pay gateway returned invalid XML (HTTP {$httpCode})."];
        }

        $respNode = $sx->Response ?? ($sx->getName() === 'Response' ? $sx : null);
        if (!$respNode) {
            return ['_ok' => false, '_error' => 'bitlink pay response missing <Response> element.'];
        }

        $parsed = ['_ok' => true, '_http_status' => $httpCode];
        foreach ($respNode->children() as $child) {
            $parsed[$child->getName()] = trim((string)$child);
        }
        return $parsed;
    }
}

if (!function_exists('bitlink_pay_collect_funds')) {
    /**
     * Initiate a Mobile Money USSD collection prompt via Yo! Payments (acdepositfunds).
     */
    function bitlink_pay_collect_funds(string $msisdn, int $amountUgx, string $narrative, string $externalRef): array {
        $account = normalize_ug_phone($msisdn);
        if (!is_valid_ug_phone($account)) {
            return ['ok' => false, 'error' => 'Enter a valid Ugandan Mobile Money number, e.g. 0772 123 456.'];
        }
        if ($amountUgx < 500) {
            return ['ok' => false, 'error' => 'Minimum bitlink pay top-up amount is UGX 500.'];
        }

        if (bitlink_pay_is_mock()) {
            $mockRef = ' MOCK-' . strtoupper(substr(sha1($externalRef), 0, 16));
            return [
                'ok'                    => true,
                'status'                => 'OK',
                'transaction_status'    => 'SUCCEEDED',
                'transaction_reference' => trim($mockRef),
                'status_message'        => 'Mock collection accepted (bitlink pay sandbox).',
                'raw'                   => ['_mock' => true, 'TransactionStatus' => 'SUCCEEDED', 'TransactionReference' => trim($mockRef)],
            ];
        }

        $username = bitlink_pay_env('YO_PAYMENTS_API_USERNAME', '');
        $password = bitlink_pay_env('YO_PAYMENTS_API_PASSWORD', '');
        if ($username === '' || $password === '') {
            return [
                'ok'    => false,
                'error' => 'bitlink pay gateway credentials (YO_PAYMENTS_API_USERNAME / YO_PAYMENTS_API_PASSWORD) are not configured in .env.',
            ];
        }

        [$instantUrl, $failureUrl] = bitlink_pay_ipn_urls();
        $esc = fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<AutoCreate><Request>'
            . '<APIUsername>' . $esc($username) . '</APIUsername>'
            . '<APIPassword>' . $esc($password) . '</APIPassword>'
            . '<Method>acdepositfunds</Method>'
            . '<NonBlocking>TRUE</NonBlocking>'
            . '<Amount>' . $amountUgx . '</Amount>'
            . '<Account>' . $esc($account) . '</Account>'
            . '<Narrative>' . $esc($narrative) . '</Narrative>'
            . '<ExternalReference>' . $esc($externalRef) . '</ExternalReference>'
            . ($instantUrl !== '' ? '<InstantNotificationUrl>' . $esc($instantUrl) . '</InstantNotificationUrl>' : '')
            . ($failureUrl !== '' ? '<FailureNotificationUrl>' . $esc($failureUrl) . '</FailureNotificationUrl>' : '')
            . '</Request></AutoCreate>';

        $parsed = bitlink_pay_xml_post($xml);
        if (empty($parsed['_ok'])) {
            return ['ok' => false, 'error' => $parsed['_error'] ?? 'Payment gateway unreachable.', 'raw' => $parsed];
        }

        $status = strtoupper((string)($parsed['Status'] ?? ''));
        $txStat = strtoupper((string)($parsed['TransactionStatus'] ?? 'PENDING'));
        $txRef  = (string)($parsed['TransactionReference'] ?? '');
        $msg    = (string)($parsed['StatusMessage'] ?? $parsed['ErrorMessage'] ?? '');

        if ($status !== 'OK') {
            return [
                'ok'                 => false,
                'error'              => $msg !== '' ? $msg : 'bitlink pay declined the Mobile Money request.',
                'transaction_status' => $txStat ?: 'FAILED',
                'raw'                => $parsed,
            ];
        }

        return [
            'ok'                    => true,
            'status'                => $status,
            'transaction_status'    => $txStat ?: 'PENDING',
            'transaction_reference' => $txRef,
            'status_message'        => $msg,
            'raw'                   => $parsed,
        ];
    }
}

if (!function_exists('bitlink_pay_check_status')) {
    /**
     * Poll a Mobile Money collection on Yo! Payments (actransactioncheckstatus).
     */
    function bitlink_pay_check_status(string $providerRef, string $externalRef): array {
        if (bitlink_pay_is_mock()) {
            $ref = $providerRef ?: ('MOCK-' . strtoupper(substr(sha1($externalRef), 0, 16)));
            return [
                'ok'                    => true,
                'status'                => 'OK',
                'transaction_status'    => 'SUCCEEDED',
                'transaction_reference' => $ref,
                'raw'                   => ['_mock' => true, 'Status' => 'OK', 'TransactionStatus' => 'SUCCEEDED', 'TransactionReference' => $ref],
            ];
        }

        $username = bitlink_pay_env('YO_PAYMENTS_API_USERNAME', '');
        $password = bitlink_pay_env('YO_PAYMENTS_API_PASSWORD', '');
        if ($username === '' || $password === '') {
            return ['ok' => false, 'error' => 'bitlink pay gateway credentials are not configured.'];
        }

        $esc = fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<AutoCreate><Request>'
            . '<APIUsername>' . $esc($username) . '</APIUsername>'
            . '<APIPassword>' . $esc($password) . '</APIPassword>'
            . '<Method>actransactioncheckstatus</Method>'
            . ($providerRef !== '' ? '<TransactionReference>' . $esc($providerRef) . '</TransactionReference>' : '')
            . ($externalRef !== '' ? '<PrivateTransactionReference>' . $esc($externalRef) . '</PrivateTransactionReference>' : '')
            . '<DepositTransactionType>PULL</DepositTransactionType>'
            . '</Request></AutoCreate>';

        $parsed = bitlink_pay_xml_post($xml);
        if (empty($parsed['_ok'])) {
            return ['ok' => false, 'error' => $parsed['_error'] ?? 'Status check failed.'];
        }

        return [
            'ok'                    => true,
            'status'                => strtoupper((string)($parsed['Status'] ?? '')),
            'transaction_status'    => strtoupper((string)($parsed['TransactionStatus'] ?? 'PENDING')),
            'transaction_reference' => (string)($parsed['TransactionReference'] ?? $providerRef),
            'status_message'        => (string)($parsed['StatusMessage'] ?? ''),
            'raw'                   => $parsed,
        ];
    }
}

if (!function_exists('bitlink_pay_send_email')) {
    /**
     * Send an email via Brevo HTTP API (with automatic BREVO_SENDER_EMAIL retry)
     * or fallback to native PHP mail() — exact same pattern as STEIM's lib/email.py.
     */
    function bitlink_pay_send_email(
        string $toEmail,
        string $toName,
        string $subject,
        string $plainBody,
        string $htmlBody,
        ?string $fromEmail = null,
        ?string $fromName = null
    ): bool {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $cfg         = bitlink_pay_config();
        $brevoKey    = bitlink_pay_env('BREVO_API_KEY', '');
        $brevoSender = bitlink_pay_env('BREVO_SENDER_EMAIL', $cfg['from_email']);
        $senderEmail = $fromEmail ?: $cfg['from_email'];
        $senderName  = $fromName ?: $cfg['from_name'];

        if ($brevoKey !== '' && function_exists('curl_init')) {
            $sendBrevo = function(string $sEmail) use ($brevoKey, $senderName, $toEmail, $toName, $subject, $plainBody, $htmlBody): bool {
                $payload = json_encode([
                    'sender'      => ['name' => $senderName, 'email' => $sEmail],
                    'to'          => [['email' => $toEmail, 'name' => $toName ?: $toEmail]],
                    'subject'     => $subject,
                    'textContent' => $plainBody,
                    'htmlContent' => $htmlBody,
                ], JSON_UNESCAPED_UNICODE);

                $ch = curl_init('https://api.brevo.com/v3/smtp/email');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_HTTPHEADER     => [
                        'accept: application/json',
                        'api-key: ' . $brevoKey,
                        'content-type: application/json',
                    ],
                    CURLOPT_TIMEOUT        => 12,
                ]);
                $raw  = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                unset($ch);
                return ($raw !== false && $code >= 200 && $code < 300);
            };

            if ($sendBrevo($senderEmail)) {
                return true;
            }
            if ($brevoSender !== '' && strcasecmp($senderEmail, $brevoSender) !== 0) {
                if ($sendBrevo($brevoSender)) {
                    return true;
                }
            }
        }

        // Fallback to native PHP mail()
        $boundary = 'b1_' . bin2hex(random_bytes(8));
        $headers  = [
            'MIME-Version: 1.0',
            "From: {$senderName} <{$senderEmail}>",
            "Reply-To: {$cfg['support_email']}",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        ];
        $mimeBody = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . $plainBody . "\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
            . $htmlBody . "\r\n\r\n"
            . "--{$boundary}--";

        return @mail($toEmail, $subject, $mimeBody, implode("\r\n", $headers));
    }
}

if (!function_exists('bitlink_pay_build_receipt')) {
    /**
     * Build the exact STEIM-styled bitlink pay plain-text and HTML receipt for an SMS top-up order.
     */
    function bitlink_pay_build_receipt(array $order): array {
        $cfg         = bitlink_pay_config();
        $orderNumber = (string)$order['order_number'];
        $customer    = (string)($order['payer_name'] ?: 'Thamani High School');
        $phone       = (string)$order['payer_phone'];
        $smsUnits    = (int)$order['sms_units'];
        $unitPrice   = (int)$order['unit_price_ugx'];
        $subtotal    = (int)$order['subtotal_ugx'];
        $procFees    = (int)$order['collection_fee_ugx'] + (int)$order['clearing_fee_ugx'];
        $total       = (int)$order['total_ugx'];
        $providerRef = (string)($order['provider_reference'] ?: $order['external_reference']);

        $ugx = fn(int $v): string => 'UGX ' . number_format($v);
        $esc = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        $plainLines = [
            'bitlink pay — Thamani High School SMS Top-Up Receipt',
            "Order: {$orderNumber}",
            "Reference: {$providerRef}",
            "Customer: {$customer}",
            "Phone: {$phone}",
            '',
            "- {$smsUnits} × Bulk SMS Credits (UGX {$unitPrice}/SMS): {$ugx($subtotal)}",
            '',
            "Subtotal: {$ugx($subtotal)}",
            "Processing and transactional fees: {$ugx($procFees)}",
            "Total paid: {$ugx($total)}",
            '',
            "{$smsUnits} SMS credits have been added to the Thamani High School SMS Centre wallet.",
            "Support: {$cfg['support_phone']} · {$cfg['support_email']}",
            'bitlink pay',
        ];
        $plain = implode("\n", $plainLines);

        $html = '
    <div style="font-family:Arial,sans-serif;background:#f8fafc;padding:24px">
      <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:20px;overflow:hidden;border:1px solid #e2e8f0">
        <div style="background:#0b1220;color:#fff;padding:22px 24px">
          <div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#fb923c;font-weight:700">bitlink pay</div>
          <h1 style="margin:8px 0 0;font-size:24px">Payment received</h1>
          <p style="margin:6px 0 0;color:#cbd5e1;font-size:13px">Thamani High School SMS Top-Up · ' . $esc($orderNumber) . '</p>
        </div>
        <div style="padding:24px">
          <p style="margin:0 0 16px;color:#334155">Hi ' . $esc($customer) . ',</p>
          <p style="margin:0 0 18px;color:#475569;font-size:14px;line-height:1.6">
            Your Mobile Money payment was confirmed and <strong>' . number_format($smsUnits) . ' SMS credits</strong> have been credited to the Thamani High School SMS wallet.
          </p>
          <table style="width:100%;border-collapse:collapse;font-size:14px">
            <tr>
              <td style="padding:8px 0">
                <strong>' . number_format($smsUnits) . ' &times; Thamani Portal Bulk SMS Credits</strong>
                <div style="color:#64748b;font-size:12px">Ego SMS Gateway Top-Up &middot; Ref: ' . $esc($providerRef) . ' &middot; Paid via ' . $esc($phone) . '</div>
              </td>
              <td style="text-align:right;padding:8px 0">' . $esc($ugx($subtotal)) . '</td>
            </tr>
          </table>
          <table style="width:100%;border-collapse:collapse;font-size:14px;margin-top:18px;border-top:1px solid #e2e8f0">
            <tr><td style="padding:6px 0;color:#64748b">Subtotal</td><td style="text-align:right">' . $esc($ugx($subtotal)) . '</td></tr>
            <tr><td style="padding:6px 0;color:#64748b">Processing and transactional fees</td><td style="text-align:right">' . $esc($ugx($procFees)) . '</td></tr>
            <tr><td style="padding:12px 0 0;font-weight:700;color:#0f172a">Total paid</td><td style="padding:12px 0 0;text-align:right;font-weight:700;color:#0f172a">' . $esc($ugx($total)) . '</td></tr>
          </table>
          <p style="margin:22px 0 0;color:#64748b;font-size:12px;line-height:1.6">
            Need help? Call ' . $esc($cfg['support_phone']) . ' or email ' . $esc($cfg['support_email']) . '.
          </p>
        </div>
        <div style="padding:16px 24px;background:#f1f5f9;color:#64748b;font-size:12px">
          Payment receipt by <strong style="color:#0f172a">bitlink pay</strong> for Thamani High School.
        </div>
      </div>
    </div>';

        return [$plain, $html];
    }
}

if (!function_exists('bitlink_pay_finalize_order')) {
    /**
     * Atomically mark a pending sms_topup_orders row as PAID, credit SMS units to the school
     * wallet, and send the bitlink pay email receipt (to Thamani email) + SMS receipt.
     */
    function bitlink_pay_finalize_order($db, int $orderId, array $providerResp = []): ?array {
        $stmt = thamani_db_prepare($db, "SELECT * FROM sms_topup_orders WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        thamani_db_stmt_bind_param($stmt, 'i', $orderId);
        thamani_db_stmt_execute($stmt);
        $res   = thamani_db_stmt_get_result($stmt);
        $order = $res ? thamani_db_fetch_assoc($res) : null;
        thamani_db_stmt_close($stmt);
        if (!$order) return null;

        // Already finalized -> idempotent return
        if (strtoupper((string)$order['status']) === 'PAID' && !empty($order['credited_at'])) {
            return $order;
        }

        $provRef  = trim((string)($providerResp['transaction_reference'] ?? $providerResp['TransactionReference'] ?? $order['provider_reference'] ?? ''));
        $provStat = trim((string)($providerResp['transaction_status'] ?? $providerResp['TransactionStatus'] ?? 'SUCCEEDED'));
        $rawJson  = !empty($providerResp) ? json_encode($providerResp, JSON_UNESCAPED_UNICODE) : (string)$order['raw_response'];

        $upd = thamani_db_prepare(
            $db,
            "UPDATE sms_topup_orders
             SET status = 'PAID',
                 provider_status = ?,
                 provider_reference = COALESCE(NULLIF(?, ''), provider_reference),
                 raw_response = ?,
                 credited_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND (status != 'PAID' OR credited_at IS NULL)"
        );
        $creditedNow = false;
        if ($upd) {
            thamani_db_stmt_bind_param($upd, 'sssi', $provStat, $provRef, $rawJson, $orderId);
            $creditedNow = thamani_db_stmt_execute($upd);
            thamani_db_stmt_close($upd);
        }

        if ($creditedNow) {
            bitlink_pay_adjust_wallet_balance($db, (int)$order['sms_units']);
        }

        // Reload fresh order
        $s2 = thamani_db_prepare($db, "SELECT * FROM sms_topup_orders WHERE id = ? LIMIT 1");
        if ($s2) {
            thamani_db_stmt_bind_param($s2, 'i', $orderId);
            thamani_db_stmt_execute($s2);
            $r2 = thamani_db_stmt_get_result($s2);
            if ($r2 && $fresh = thamani_db_fetch_assoc($r2)) $order = $fresh;
            thamani_db_stmt_close($s2);
        }

        $cfg = bitlink_pay_config();

        // 1. Send bitlink pay Email Receipt to Thamani email (and payer receipt_email if different)
        if (empty($order['email_receipt_sent_at'])) {
            [$plain, $html] = bitlink_pay_build_receipt($order);
            $subject = "bitlink pay receipt · {$order['order_number']}";
            $recipients = array_unique(array_filter([
                strtolower(trim((string)$cfg['thamani_receipt_email'])),
                strtolower(trim((string)($order['receipt_email'] ?? ''))),
            ]));
            $anySent = false;
            foreach ($recipients as $recipEmail) {
                if (bitlink_pay_send_email($recipEmail, (string)($order['payer_name'] ?: 'Thamani High School'), $subject, $plain, $html, $cfg['from_email'], $cfg['from_name'])) {
                    $anySent = true;
                }
            }
            if ($anySent) {
                $uEm = thamani_db_prepare($db, "UPDATE sms_topup_orders SET email_receipt_sent_at = CURRENT_TIMESTAMP WHERE id = ?");
                if ($uEm) {
                    thamani_db_stmt_bind_param($uEm, 'i', $orderId);
                    thamani_db_stmt_execute($uEm);
                    thamani_db_stmt_close($uEm);
                }
            }
        }

        // 2. Send bitlink pay SMS Receipt to payer phone (#powered by bitlink ug#)
        if (empty($order['sms_receipt_sent_at']) && !empty($order['payer_phone'])) {
            $creds = get_egosms_credentials($db);
            if (!empty($creds['username']) && !empty($creds['password'])) {
                $smsText = sprintf(
                    "THAMANI SMS: Paid UGX %s for %s SMS credits. Order %s. Credits added to school wallet. Help: %s #powered by bitlink ug#",
                    number_format((int)$order['total_ugx']),
                    number_format((int)$order['sms_units']),
                    $order['order_number'],
                    $cfg['support_phone']
                );
                $smsRes = egosms_send($order['payer_phone'], $smsText, $creds['username'], $creds['password']);
                if (!empty($smsRes['success'])) {
                    $uSm = thamani_db_prepare($db, "UPDATE sms_topup_orders SET sms_receipt_sent_at = CURRENT_TIMESTAMP WHERE id = ?");
                    if ($uSm) {
                        thamani_db_stmt_bind_param($uSm, 'i', $orderId);
                        thamani_db_stmt_execute($uSm);
                        thamani_db_stmt_close($uSm);
                    }
                }
            }
        }

        return $order;
    }
}
