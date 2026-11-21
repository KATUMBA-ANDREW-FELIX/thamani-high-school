<?php
// sms_helper.php — Thamani High School · Ego SMS (Pahappa Comms) Integration Helper
// Real API: POST https://comms.egosms.co/api/v1/json/
// Docs: https://comms.egosms.co (Settings → API Settings)

// ─────────────────────────────────────────────────────────────────────────────
// Phone helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Normalize any Ugandan phone number to the 256XXXXXXXXX format required by
 * Ego SMS (no +, no 00, no leading 0, exactly 12 digits: 256 + 9 digits).
 *
 * Handles:
 *   0772123456   → 256772123456
 *   +256772123456→ 256772123456
 *   772123456    → 256772123456   (9-digit bare number)
 *   00256772...  → 256772123456
 */
function normalize_ug_phone(string $phone): string {
    $phone = preg_replace('/\D/', '', trim($phone)); // strip everything non-digit
    if ($phone === '') return '';

    // Strip leading 00 (international dial prefix)
    if (strncmp($phone, '00', 2) === 0) {
        $phone = substr($phone, 2);
    }

    // 9-digit bare subscriber number (no leading 0 or country code)
    if (strlen($phone) === 9) {
        $phone = '256' . $phone;
    }

    // 10-digit local format (leading 0)
    if (strlen($phone) === 10 && $phone[0] === '0') {
        $phone = '256' . substr($phone, 1);
    }

    // Already 256XXXXXXXXX or some other 12-digit number — leave as-is
    return $phone;
}

/**
 * Validate that a normalized phone string looks like a valid Ugandan mobile.
 * Ugandan mobile numbers: 256 followed by 7X XXXXXXXX (starts with 7).
 */
function is_valid_ug_phone(string $phone): bool {
    return (bool) preg_match('/^256[7][0-9]{8}$/', $phone);
}

// ─────────────────────────────────────────────────────────────────────────────
// Ego SMS API caller
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Send one or more SMS messages via the Ego SMS (Pahappa Comms) JSON-RPC API.
 *
 * Endpoint: POST https://comms.egosms.co/api/v1/json/
 * Auth:     "userdata": { "username": "...", "password": "..." }
 * Payload:  "msgdata": [ { "number": "256...", "message": "...", "senderid": "...", "priority": 1 }, ... ]
 *
 * Success response: { "Status": "OK",     "Cost": 35, "MsgFollowUpUniqueCode": "..." }
 * Failure response: { "Status": "Failed", "Message": "Wrong Username or Password." }
 *
 * @param  string|array $recipients  Single phone string OR array of 256XXXXXXXXX numbers
 * @param  string       $message     SMS body text (160 chars per part, max ~10 parts)
 * @param  string       $username    Ego SMS API username (from Settings → API Settings)
 * @param  string       $password    Ego SMS API password
 * @param  string       $senderId    Approved sender ID — max 11 alphanumeric chars (e.g. "THAMANI")
 * @return array {
 *     'success'    => bool,
 *     'sent_count' => int,
 *     'failed'     => string[],   // invalid numbers that were skipped
 *     'raw'        => string,     // raw API JSON response
 *     'error'      => string,     // human-readable error message (empty on success)
 *     'tracking'   => string,     // MsgFollowUpUniqueCode from Ego SMS (empty on failure)
 *     'cost'       => int,        // SMS units consumed (0 on failure)
 * }
 */
function egosms_send($recipients, string $message, string $username, string $password, string $senderId = 'THAMANI'): array {
    // ── Validate cURL availability ──────────────────────────────────────────
    if (!function_exists('curl_init')) {
        return [
            'success'    => false,
            'sent_count' => 0,
            'failed'     => [],
            'raw'        => '',
            'error'      => 'PHP cURL extension is required to send SMS. Please enable ext-curl on your server.',
            'tracking'   => '',
            'cost'       => 0,
        ];
    }

    // ── Normalize, validate, and deduplicate recipients ─────────────────────
    $phones = is_array($recipients) ? $recipients : [$recipients];
    $valid  = [];
    $seen   = [];
    $failed = [];
    foreach ($phones as $p) {
        $n = normalize_ug_phone((string) $p);
        if (is_valid_ug_phone($n)) {
            if (!isset($seen[$n])) {
                $seen[$n] = true;
                $valid[] = $n;
            }
        } else {
            $failed[] = $p;
        }
    }

    if (empty($valid)) {
        return [
            'success'    => false,
            'sent_count' => 0,
            'failed'     => $failed,
            'raw'        => '',
            'error'      => 'No valid Ugandan phone numbers to send to. Numbers must be in the format 256XXXXXXXXX.',
            'tracking'   => '',
            'cost'       => 0,
        ];
    }

    // ── Build the Ego SMS JSON-RPC payload ──────────────────────────────────
    //
    // msgdata is an ARRAY — one object per recipient number.
    // Do NOT pass a comma-separated list; pass individual objects.
    //
    $msgdata = [];
    foreach ($valid as $number) {
        $msgdata[] = [
            'number'   => $number,
            'message'  => $message,
            'senderid' => $senderId,
            'priority' => 1,
        ];
    }

    $payload = [
        'method'   => 'SendSms',
        'userdata' => [
            'username' => $username,
            'password' => $password,
        ],
        'msgdata'  => $msgdata,
    ];

    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

    // ── cURL request ────────────────────────────────────────────────────────
    $url = trim((string)(getenv('EGO_SMS_API_URL') ?: 'https://comms.egosms.co/api/v1/json/'));

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonPayload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Content-Length: ' . strlen($jsonPayload),
        ],
        CURLOPT_TIMEOUT        => 45,          // 45 s — bulk sends can take time
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => 'ThamaniSchoolPortal/1.0',
    ]);

    $raw      = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    unset($ch);

    // ── cURL transport error ─────────────────────────────────────────────────
    if ($curlErr || $raw === false) {
        return [
            'success'    => false,
            'sent_count' => 0,
            'failed'     => $failed,
            'raw'        => '',
            'error'      => 'Network error connecting to Ego SMS: ' . $curlErr,
            'tracking'   => '',
            'cost'       => 0,
        ];
    }

    // ── Parse Ego SMS API response ──────────────────────────────────────────
    // Success: { "Status": "OK",     "Cost": 35, "MsgFollowUpUniqueCode": "abc123..." }
    // Failure: { "Status": "Failed", "Message": "Wrong Username or Password." }
    //
    $decoded    = json_decode($raw, true);
    $apiStatus  = $decoded['Status'] ?? $decoded['status'] ?? '';
    $apiSuccess = (strtoupper($apiStatus) === 'OK');

    if ($apiSuccess) {
        return [
            'success'    => true,
            'sent_count' => count($valid),
            'failed'     => $failed,
            'raw'        => $raw,
            'error'      => '',
            'tracking'   => $decoded['MsgFollowUpUniqueCode'] ?? '',
            'cost'       => (int) ($decoded['Cost'] ?? 0),
        ];
    }

    // Failed response
    $errMsg = $decoded['Message'] ?? $decoded['message'] ?? "HTTP {$httpCode}: " . substr($raw, 0, 300);
    return [
        'success'    => false,
        'sent_count' => 0,
        'failed'     => array_merge($failed, $valid), // all were unsent
        'raw'        => $raw,
        'error'      => $errMsg,
        'tracking'   => '',
        'cost'       => 0,
    ];
}

/**
 * Check live credit balance on Ego SMS (Pahappa Comms) JSON-RPC API.
 * Endpoint: POST https://comms.egosms.co/api/v1/json/
 * Payload:  { "method": "Balance", "userdata": { "username": "...", "password": "..." }, "walletType": "Local" }
 */
function egosms_check_balance(string $username, string $password, string $walletType = 'Local'): array {
    if (!function_exists('curl_init')) {
        return ['success' => false, 'balance' => null, 'currency' => '', 'raw' => '', 'error' => 'PHP cURL extension is required.'];
    }
    if ($username === '' || $password === '') {
        return ['success' => false, 'balance' => null, 'currency' => '', 'raw' => '', 'error' => 'Ego SMS username and password are required.'];
    }

    $url = trim((string)(getenv('EGO_SMS_API_URL') ?: 'https://comms.egosms.co/api/v1/json/'));
    $jsonPayload = json_encode([
        'method'     => 'Balance',
        'userdata'   => [
            'username' => $username,
            'password' => $password,
        ],
        'walletType' => $walletType,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonPayload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Content-Length: ' . strlen($jsonPayload),
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => 'ThamaniSchoolPortal/1.0',
    ]);

    $raw      = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    unset($ch);

    if ($curlErr || $raw === false) {
        return ['success' => false, 'balance' => null, 'currency' => '', 'raw' => '', 'error' => 'Network error connecting to Ego SMS: ' . $curlErr];
    }

    $decoded   = json_decode($raw, true);
    $apiStatus = strtoupper((string)($decoded['Status'] ?? $decoded['status'] ?? ''));
    if ($apiStatus === 'OK' || isset($decoded['Balance']) || isset($decoded['balance'])) {
        $bal = $decoded['Balance'] ?? $decoded['balance'] ?? $decoded['Message'] ?? '0';
        return [
            'success'  => true,
            'balance'  => $bal,
            'currency' => $decoded['Currency'] ?? $decoded['currency'] ?? 'UGX',
            'raw'      => $raw,
            'error'    => '',
        ];
    }

    $errMsg = $decoded['Message'] ?? $decoded['message'] ?? "HTTP {$httpCode}: " . substr((string)$raw, 0, 240);
    return ['success' => false, 'balance' => null, 'currency' => '', 'raw' => (string)$raw, 'error' => $errMsg];
}

// ─────────────────────────────────────────────────────────────────────────────
// Credentials helper
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Fetch Ego SMS API credentials from school_settings (with .env fallback).
 * Returns array: ['username' => '', 'password' => '', 'sender_id' => 'THAMANI', 'auto_fee_receipts' => false]
 */
function get_egosms_credentials($db): array {
    $keys   = ['ego_sms_username', 'ego_sms_password', 'ego_sms_sender_id', 'ego_sms_auto_fee_receipts'];
    $result = [
        'username'          => trim((string)(getenv('EGO_SMS_USERNAME') ?: '')),
        'password'          => trim((string)(getenv('EGO_SMS_PASSWORD') ?: '')),
        'sender_id'         => trim((string)(getenv('EGO_SMS_SENDER_ID') ?: 'THAMANI')) ?: 'THAMANI',
        'auto_fee_receipts' => false,
    ];

    foreach ($keys as $k) {
        $stmt = thamani_db_prepare($db, "SELECT setting_value FROM school_settings WHERE setting_key = ? LIMIT 1");
        if ($stmt) {
            thamani_db_stmt_bind_param($stmt, 's', $k);
            thamani_db_stmt_execute($stmt);
            $res = thamani_db_stmt_get_result($stmt);
            $row = $res ? thamani_db_fetch_assoc($res) : null;
            thamani_db_stmt_close($stmt);
            $shortKey = str_replace('ego_sms_', '', $k);
            if ($shortKey === 'sender_id') {
                if (!empty($row['setting_value'])) {
                    $result['sender_id'] = $row['setting_value'];
                }
            } elseif ($shortKey === 'auto_fee_receipts') {
                $result['auto_fee_receipts'] = (($row['setting_value'] ?? '0') === '1');
            } else {
                if (isset($row['setting_value']) && $row['setting_value'] !== '') {
                    $result[$shortKey] = $row['setting_value'];
                }
            }
        }
    }

    return $result;
}

/**
 * Optionally send a payment receipt SMS to a student's guardian when a verified bank payment arrives.
 */
function egosms_send_fee_receipt($db, array $student, float $amount, float $balance, string $bankCode, string $receiptNo): array {
    $creds = get_egosms_credentials($db);
    if (empty($creds['auto_fee_receipts']) || empty($creds['username']) || empty($creds['password'])) {
        return ['sent' => false, 'skipped' => true, 'reason' => 'Auto fee receipt SMS disabled or credentials incomplete.'];
    }
    $phone = normalize_ug_phone((string)($student['guardian_phone'] ?? ''));
    if (!is_valid_ug_phone($phone)) {
        return ['sent' => false, 'skipped' => true, 'reason' => 'Student has no valid guardian phone number.'];
    }
    $name = $student['full_name'] ?? 'Student';
    $msg = sprintf(
        "Thamani High School: Fees payment of UGX %s for %s received via %s (Ref: %s). Outstanding balance: UGX %s. Thank you.",
        number_format($amount),
        $name,
        $bankCode,
        $receiptNo,
        number_format(max(0.0, $balance))
    );
    $res = egosms_send($phone, $msg, $creds['username'], $creds['password'], $creds['sender_id'] ?: 'THAMANI');
    log_sms_batch($db, 0, 'system', $res['sent_count'], 'Fee Receipt', $msg, $res['success'] ? 'SUCCESS' : 'FAILED', $res['raw'] ?: $res['error']);
    return ['sent' => $res['success'], 'skipped' => false, 'error' => $res['error']];
}

// ─────────────────────────────────────────────────────────────────────────────
// SMS batch logger
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Write a record to sms_logs after a bulk send attempt.
 */
function log_sms_batch(
    $db,
    int    $senderId,
    string $senderRole,
    int    $recipientCount,
    string $categoryFilter,
    string $message,
    string $status,
    string $apiResponse
): void {
    $batchId = bin2hex(random_bytes(8));
    // Truncate apiResponse to avoid storing huge payloads
    $apiResponseTrunc = mb_substr($apiResponse, 0, 1000, 'UTF-8');

    $stmt = thamani_db_prepare(
        $db,
        "INSERT INTO sms_logs
             (batch_id, sender_id, sender_role, recipient_count,
              category_filter, message, status, api_response, sent_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    if ($stmt) {
        thamani_db_stmt_bind_param(
            $stmt, 'sisissss',
            $batchId, $senderId, $senderRole, $recipientCount,
            $categoryFilter, $message, $status, $apiResponseTrunc
        );
        thamani_db_stmt_execute($stmt);
        thamani_db_stmt_close($stmt);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// DB quoting helper (for inline SELECT-only lookups)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Safely quote a scalar value for inline SQL (used only for SELECT read-only queries).
 * Prefer prepared statements wherever possible.
 */
function thamani_db_quote($db, string $val): string {
    if ($db instanceof ThamaniPolyfillConn && $db->pdo) {
        // PDO::quote adds surrounding quotes itself
        $quoted = $db->pdo->quote($val);
        return ($quoted !== false) ? $quoted : "'" . str_replace("'", "''", $val) . "'";
    }
    if (method_exists($db, 'real_escape_string')) {
        return "'" . $db->real_escape_string($val) . "'";
    }
    return "'" . addslashes($val) . "'";
}
