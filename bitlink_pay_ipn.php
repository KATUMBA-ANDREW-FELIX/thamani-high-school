<?php
/**
 * Thamani High School - bitlink pay IPN Callback Handler (Yo! Uganda Instant Notification)
 * ----------------------------------------------------------------------------------------
 * Handles form-encoded Instant Payment Notifications (IPN) and Failure callbacks
 * from Yo! Payments Uganda, verifying the RSA-SHA1 signature when YO_PAYMENTS_PUBLIC_KEY
 * is configured, crediting SMS units, and dispatching bitlink pay email/SMS receipts.
 */

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/bitlink_pay_helper.php';

header('Content-Type: text/plain; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('METHOD_NOT_ALLOWED');
}

$type = strtolower(trim((string)($_GET['type'] ?? 'success')));

if ($type === 'failure') {
    $failedRef = trim((string)($_POST['failed_transaction_reference'] ?? ''));
    if ($failedRef === '') {
        http_response_code(400);
        exit('MISSING_REFERENCE');
    }
    $stmt = thamani_db_prepare($conn, "SELECT id, status FROM sms_topup_orders WHERE external_reference = ? LIMIT 1");
    if ($stmt) {
        thamani_db_stmt_bind_param($stmt, 's', $failedRef);
        thamani_db_stmt_execute($stmt);
        $res = thamani_db_stmt_get_result($stmt);
        $row = $res ? thamani_db_fetch_assoc($res) : null;
        thamani_db_stmt_close($stmt);
        if ($row && strtoupper((string)$row['status']) !== 'PAID') {
            $u = thamani_db_prepare($conn, "UPDATE sms_topup_orders SET status = 'FAILED', provider_status = 'FAILED', failure_reason = 'Mobile Money payment failed or was cancelled.', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            if ($u) {
                $oid = (int)$row['id'];
                thamani_db_stmt_bind_param($u, 'i', $oid);
                thamani_db_stmt_execute($u);
                thamani_db_stmt_close($u);
            }
        }
    }
    exit('OK');
}

// Success IPN verification (fields in Yo! spec order)
$ipnFields = ['date_time', 'amount', 'narrative', 'network_ref', 'external_ref', 'msisdn'];
$data = [];
foreach ($ipnFields as $f) {
    $data[$f] = (string)($_POST[$f] ?? '');
    if ($data[$f] === '') {
        http_response_code(400);
        exit("REJECTED:missing_{$f}");
    }
}

$pubKeyPath = bitlink_pay_env('YO_PAYMENTS_PUBLIC_KEY', '');
$allowUnsigned = in_array(strtolower(bitlink_pay_env('YO_PAYMENTS_ALLOW_UNSIGNED_IPN', '0')), ['1', 'true', 'yes'], true);

if ($pubKeyPath !== '' && is_readable($pubKeyPath)) {
    $sigB64 = trim((string)($_POST['signature'] ?? ''));
    if ($sigB64 === '') {
        http_response_code(400);
        exit('REJECTED:missing_signature');
    }
    $signedPayload = $data['date_time'] . $data['amount'] . $data['narrative'] . $data['network_ref'] . $data['external_ref'] . $data['msisdn'];
    $pem = file_get_contents($pubKeyPath);
    $pubKey = @openssl_pkey_get_public($pem);
    $sigBin = base64_decode($sigB64, true);
    if (!$pubKey || $sigBin === false || openssl_verify($signedPayload, $sigBin, $pubKey, OPENSSL_ALGO_SHA1) !== 1) {
        http_response_code(400);
        exit('REJECTED:bad_signature');
    }
} elseif (!$allowUnsigned && strtolower(bitlink_pay_env('YO_PAYMENTS_MODE', 'production')) === 'production' && $pubKeyPath !== '') {
    http_response_code(400);
    exit('REJECTED:public_key_unreadable');
}

$extRef = $data['external_ref'];
$stmt = thamani_db_prepare($conn, "SELECT * FROM sms_topup_orders WHERE external_reference = ? LIMIT 1");
$order = null;
if ($stmt) {
    thamani_db_stmt_bind_param($stmt, 's', $extRef);
    thamani_db_stmt_execute($stmt);
    $res = thamani_db_stmt_get_result($stmt);
    $order = $res ? thamani_db_fetch_assoc($res) : null;
    thamani_db_stmt_close($stmt);
}

if (!$order) {
    http_response_code(404);
    exit('UNKNOWN_REFERENCE');
}

$notifiedAmount = (int)round((float)$data['amount']);
if ($notifiedAmount !== (int)$order['total_ugx']) {
    http_response_code(400);
    exit('AMOUNT_MISMATCH');
}

bitlink_pay_finalize_order($conn, (int)$order['id'], [
    'Status'               => 'OK',
    'TransactionStatus'    => 'SUCCEEDED',
    'TransactionReference' => $data['network_ref'],
    'ExternalReference'    => $data['external_ref'],
    'StatusMessage'        => $data['narrative'],
    '_ipn'                 => $data,
]);

exit('OK');
