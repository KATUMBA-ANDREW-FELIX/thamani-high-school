<?php
// sms_helper.php — Thamani High School · Ego SMS Integration Helper

/**
 * Normalize a Ugandan phone number to 256XXXXXXXXX format (no + or 00).
 * Handles: 0772123456, +256772123456, 256772123456, 772123456
 */
function normalize_ug_phone(string $phone): string {
    $phone = preg_replace('/\D/', '', trim($phone)); // strip non-digits
    if ($phone === null) return '';
    if (strlen($phone) === 9) $phone = '256' . $phone;             // 7XXXXXXXX
    if (strlen($phone) === 10 && $phone[0] === '0') $phone = '256' . substr($phone, 1); // 07XXXXXXXX
    if (substr($phone, 0, 2) === '00') $phone = substr($phone, 2); // 00256…
    // Already 256XXXXXXXXX (12 digits)
    return $phone;
}

/**
 * Validate that a normalized phone looks like a Ugandan number.
 */
function is_valid_ug_phone(string $phone): bool {
    return (bool)preg_match('/^256[7][0-9]{8}$/', $phone);
}

/**
 * Send SMS via Ego SMS (Pahappa Comms) JSON API.
 *
 * @param  string|array $recipients  Single phone string or array of 256XXXXXXXXX numbers
 * @param  string       $message     SMS body (up to 1600 chars / 10 parts)
 * @param  string       $username    Ego SMS API username
 * @param  string       $password    Ego SMS API password
 * @param  string       $senderId    Registered sender ID (max 11 chars)
 * @return array  ['success' => bool, 'sent_count' => int, 'failed' => [], 'raw' => string, 'error' => string]
 */
function egosms_send($recipients, string $message, string $username, string $password, string $senderId = 'THAMANI'): array {
    $phones = is_array($recipients) ? $recipients : [$recipients];
    $phones = array_values(array_filter(array_map('normalize_ug_phone', $phones)));

    $failed = [];
    $valid  = [];
    foreach ($phones as $p) {
        if (is_valid_ug_phone($p)) {
            $valid[] = $p;
        } else {
            $failed[] = $p;
        }
    }

    if (empty($valid)) {
        return ['success' => false, 'sent_count' => 0, 'failed' => $failed, 'raw' => '', 'error' => 'No valid phone numbers to send to.'];
    }

    // Ego SMS API endpoint
    $url = 'https://comms.egosms.co/api/v1/sms/send';

    $payload = [
        'username' => $username,
        'password' => $password,
        'to'       => implode(',', $valid),   // comma-separated list accepted
        'message'  => $message,
        'sender'   => $senderId,
    ];

    if (!function_exists('curl_init')) {
        return ['success' => false, 'sent_count' => 0, 'failed' => $failed, 'raw' => '', 'error' => 'The PHP cURL extension is required to send SMS.'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $raw === false) {
        return ['success' => false, 'sent_count' => 0, 'failed' => $failed, 'raw' => '', 'error' => 'cURL error: ' . $curlErr];
    }

    $decoded = json_decode($raw, true);
    // Ego SMS returns {"status":"success"} or {"status":"error","message":"..."}
    $apiSuccess = $httpCode === 200 && isset($decoded['status']) && strtolower($decoded['status']) === 'success';

    return [
        'success'    => $apiSuccess,
        'sent_count' => $apiSuccess ? count($valid) : 0,
        'failed'     => $failed,
        'raw'        => $raw,
        'error'      => $apiSuccess ? '' : ($decoded['message'] ?? "HTTP {$httpCode}: {$raw}"),
    ];
}

/**
 * Fetch Ego SMS credentials from school_settings table.
 * Returns ['username'=>'', 'password'=>'', 'sender_id'=>'THAMANI'] or null on failure.
 */
function get_egosms_credentials($db): ?array {
    $keys   = ['ego_sms_username', 'ego_sms_password', 'ego_sms_sender_id'];
    $result = [];
    foreach ($keys as $k) {
        $res = thamani_db_query($db, "SELECT setting_value FROM school_settings WHERE setting_key = " . thamani_db_quote($db, $k));
        $row = $res ? thamani_db_fetch_assoc($res) : null;
        $shortKey = str_replace('ego_sms_', '', $k);
        $result[$shortKey] = $row['setting_value'] ?? '';
    }
    return $result;
}

/**
 * Log an SMS batch to sms_logs.
 */
function log_sms_batch($db, int $senderId, string $senderRole, int $recipientCount, string $categoryFilter, string $message, string $status, string $apiResponse): void {
    $batchId = bin2hex(random_bytes(8));
    $stmt = thamani_db_prepare($db,
        "INSERT INTO sms_logs (batch_id, sender_id, sender_role, recipient_count, category_filter, message, status, api_response, sent_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    if ($stmt) {
        thamani_db_stmt_bind_param($stmt, 'sisissss',
            $batchId, $senderId, $senderRole, $recipientCount,
            $categoryFilter, $message, $status, $apiResponse
        );
        thamani_db_stmt_execute($stmt);
        thamani_db_stmt_close($stmt);
    }
}

/**
 * Helper: safely quote a string for an inline SQL query (for SELECT-only lookups).
 * Uses the driver-aware escape approach.
 */
function thamani_db_quote($db, string $val): string {
    // Works for both MySQLi and polyfill PDO conn
    if (method_exists($db, 'real_escape_string')) {
        return "'" . $db->real_escape_string($val) . "'";
    }
    if ($db instanceof ThamaniPolyfillConn) {
        return "'" . str_replace("'", "''", $val) . "'";
    }
    return "'" . addslashes($val) . "'";
}
