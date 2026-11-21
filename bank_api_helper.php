<?php
/**
 * Thamani High School - Bank API & School Fees Reconciliation Helper
 * ------------------------------------------------------------------
 * Provides real HTTP API polling, single-student live fee balance checking,
 * webhook signature verification, idempotent transaction recording,
 * and accurate student_fees ledger recalculation across Ugandan banks
 * & aggregators (SchoolPay, Centenary, Stanbic FlexiPay, Equity, MTN MoMo, Airtel).
 */

if (!function_exists('bank_api_default_webhook_url')) {
    /**
     * Build the dynamic inbound webhook URL for a bank code based on the active host.
     */
    function bank_api_default_webhook_url(string $bankCode): string {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
        $scheme = $https ? 'https' : 'http';
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost:8080'));
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        return "{$scheme}://{$host}{$scriptDir}/admin_fees_actions.php?webhook=1&bank=" . urlencode(strtoupper($bankCode));
    }
}

if (!function_exists('bank_api_verify_webhook')) {
    /**
     * Verify an inbound bank webhook request against the bank_integrations.webhook_secret.
     * Supports:
     *   - Header: X-Webhook-Secret / X-Bank-Secret / X-Api-Secret
     *   - Header: Authorization: Bearer <webhook_secret>
     *   - Header: X-Signature / X-Bank-Signature / X-Hub-Signature-256 (HMAC-SHA256 of raw body)
     *
     * @return array{ok:bool, error:string, bank:array|null}
     */
    function bank_api_verify_webhook($conn, string $bankCode, string $rawInput, array $parsedData = []): array {
        $bankRow = null;
        $stmt = thamani_db_prepare($conn, "SELECT * FROM bank_integrations WHERE UPPER(bank_code) = UPPER(?) LIMIT 1");
        if ($stmt) {
            thamani_db_stmt_bind_param($stmt, 's', $bankCode);
            thamani_db_stmt_execute($stmt);
            $res = thamani_db_stmt_get_result($stmt);
            if ($res && $r = thamani_db_fetch_assoc($res)) {
                $bankRow = $r;
            }
            thamani_db_stmt_close($stmt);
        }

        if (!$bankRow) {
            return ['ok' => false, 'error' => "Unknown bank integration '{$bankCode}'.", 'bank' => null];
        }
        if ((int)($bankRow['is_active'] ?? 0) !== 1) {
            return ['ok' => false, 'error' => "Bank integration '{$bankCode}' is currently inactive.", 'bank' => $bankRow];
        }

        $secret = trim((string)($bankRow['webhook_secret'] ?? ''));
        $env    = strtolower(trim((string)($bankRow['environment'] ?? 'sandbox')));

        if ($secret === '') {
            if ($env === 'production') {
                return [
                    'ok'    => false,
                    'error' => "Webhook signing secret is required for production bank '{$bankCode}'. Configure webhook_secret in Bank Settings.",
                    'bank'  => $bankRow,
                ];
            }
            // Sandbox mode without secret configured is allowed for local testing
            return ['ok' => true, 'error' => '', 'bank' => $bankRow];
        }

        // Gather candidate auth headers
        $headerSecret = trim((string)(
            $_SERVER['HTTP_X_WEBHOOK_SECRET']
            ?? $_SERVER['HTTP_X_BANK_SECRET']
            ?? $_SERVER['HTTP_X_API_SECRET']
            ?? ''
        ));
        $authHeader = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if ($headerSecret === '' && stripos($authHeader, 'Bearer ') === 0) {
            $headerSecret = trim(substr($authHeader, 7));
        }

        if ($headerSecret !== '' && hash_equals($secret, $headerSecret)) {
            return ['ok' => true, 'error' => '', 'bank' => $bankRow];
        }

        // Check HMAC-SHA256 signature headers
        $sigHeader = trim((string)(
            $_SERVER['HTTP_X_SIGNATURE']
            ?? $_SERVER['HTTP_X_BANK_SIGNATURE']
            ?? $_SERVER['HTTP_X_HUB_SIGNATURE_256']
            ?? ''
        ));
        if ($sigHeader !== '') {
            $sigClean = preg_replace('/^sha256=/i', '', $sigHeader);
            $expected = hash_hmac('sha256', $rawInput, $secret);
            if (hash_equals(strtolower($expected), strtolower($sigClean))) {
                return ['ok' => true, 'error' => '', 'bank' => $bankRow];
            }
        }

        // Also support SchoolPay-style MD5 requestHash inside JSON payload if present
        $reqHash = trim((string)($parsedData['requestHash'] ?? $parsedData['hash'] ?? ''));
        if ($reqHash !== '' && !empty($bankRow['account_number'])) {
            $today = date('Y-m-d');
            $candidate1 = md5($bankRow['account_number'] . $today . $secret);
            $candidate2 = md5(($parsedData['transaction_ref'] ?? $parsedData['transactionId'] ?? '') . $secret);
            if (hash_equals(strtolower($candidate1), strtolower($reqHash)) || hash_equals(strtolower($candidate2), strtolower($reqHash))) {
                return ['ok' => true, 'error' => '', 'bank' => $bankRow];
            }
        }

        return ['ok' => false, 'error' => 'Invalid or missing webhook signature/secret.', 'bank' => $bankRow];
    }
}

if (!function_exists('bank_api_find_student')) {
    /**
     * Find a student by pay_code, lin_number, or numeric student_id.
     */
    function bank_api_find_student($conn, string $identifier = '', int $studentId = 0): ?array {
        $identifier = trim($identifier);
        if ($identifier !== '') {
            $st = thamani_db_prepare(
                $conn,
                "SELECT id, full_name, lin_number, pay_code, class_level, stream, guardian_name, guardian_phone
                 FROM students
                 WHERE LOWER(COALESCE(pay_code, '')) = LOWER(?) OR LOWER(COALESCE(lin_number, '')) = LOWER(?)
                 LIMIT 1"
            );
            if ($st) {
                thamani_db_stmt_bind_param($st, 'ss', $identifier, $identifier);
                thamani_db_stmt_execute($st);
                $res = thamani_db_stmt_get_result($st);
                $row = $res ? thamani_db_fetch_assoc($res) : null;
                thamani_db_stmt_close($st);
                if ($row) return $row;
            }
        }

        if ($studentId > 0) {
            $st = thamani_db_prepare(
                $conn,
                "SELECT id, full_name, lin_number, pay_code, class_level, stream, guardian_name, guardian_phone
                 FROM students
                 WHERE id = ?
                 LIMIT 1"
            );
            if ($st) {
                thamani_db_stmt_bind_param($st, 'i', $studentId);
                thamani_db_stmt_execute($st);
                $res = thamani_db_stmt_get_result($st);
                $row = $res ? thamani_db_fetch_assoc($res) : null;
                thamani_db_stmt_close($st);
                if ($row) return $row;
            }
        }

        return null;
    }
}

if (!function_exists('bank_api_recalculate_student_fee')) {
    /**
     * Recalculate a student's fee ledger strictly from the SUM of SUCCESS bank_transactions.
     * Guarantees student_fees.paid_amount and balance can never drift out of sync.
     */
    function bank_api_recalculate_student_fee($conn, array $student): array {
        $studentId  = (int)$student['id'];
        $linNum     = trim((string)($student['lin_number'] ?? ''));
        $payCode    = trim((string)($student['pay_code'] ?? ''));
        $classLevel = trim((string)($student['class_level'] ?? 'Senior 1')) ?: 'Senior 1';

        // Existing fee row (if any)
        $feeRow = null;
        $fStmt = thamani_db_prepare($conn, "SELECT * FROM student_fees WHERE student_id = ? LIMIT 1");
        if ($fStmt) {
            thamani_db_stmt_bind_param($fStmt, 'i', $studentId);
            thamani_db_stmt_execute($fStmt);
            $fRes = thamani_db_stmt_get_result($fStmt);
            if ($fRes && $fr = thamani_db_fetch_assoc($fRes)) {
                $feeRow = $fr;
            }
            thamani_db_stmt_close($fStmt);
        }

        $classRate = function_exists('thamani_get_class_fee')
            ? thamani_get_class_fee($conn, $classLevel)
            : ((str_contains($classLevel, 'Senior 5') || str_contains($classLevel, 'Senior 6') || str_contains($classLevel, 'A-Level')) ? 950000.0 : 850000.0);

        $totalFee = ($feeRow && (float)$feeRow['total_fee'] > 0) ? (float)$feeRow['total_fee'] : $classRate;

        // Sum all verified SUCCESS transactions for this student
        $paidSum = 0.0;
        $txStmt = thamani_db_prepare(
            $conn,
            "SELECT COALESCE(SUM(amount), 0) AS sum_paid
             FROM bank_transactions
             WHERE UPPER(COALESCE(status, 'SUCCESS')) = 'SUCCESS'
               AND (student_id = ? OR (lin_number != '' AND lin_number = ?))"
        );
        if ($txStmt) {
            thamani_db_stmt_bind_param($txStmt, 'is', $studentId, $linNum);
            thamani_db_stmt_execute($txStmt);
            $txRes = thamani_db_stmt_get_result($txStmt);
            if ($txRes && $tr = thamani_db_fetch_assoc($txRes)) {
                $paidSum = (float)($tr['sum_paid'] ?? 0.0);
            }
            thamani_db_stmt_close($txStmt);
        }

        $balance = max(0.0, $totalFee - $paidSum);
        $status  = ($balance <= 0 && $totalFee > 0) ? 'PAID' : ($paidSum > 0 ? 'PARTIAL' : 'UNPAID');
        $term    = ($feeRow && !empty($feeRow['term'])) ? $feeRow['term'] : 'Term III 2026';

        if ($feeRow) {
            $uStmt = thamani_db_prepare(
                $conn,
                "UPDATE student_fees
                 SET total_fee = ?, paid_amount = ?, balance = ?, status = ?, class_level = ?, pay_code = COALESCE(NULLIF(?, ''), pay_code), updated_at = CURRENT_TIMESTAMP
                 WHERE student_id = ?"
            );
            if ($uStmt) {
                thamani_db_stmt_bind_param($uStmt, 'dddsssi', $totalFee, $paidSum, $balance, $status, $classLevel, $payCode, $studentId);
                thamani_db_stmt_execute($uStmt);
                thamani_db_stmt_close($uStmt);
            }
        } else {
            $iStmt = thamani_db_prepare(
                $conn,
                "INSERT INTO student_fees (student_id, class_level, term, total_fee, paid_amount, balance, status, pay_code)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            if ($iStmt) {
                thamani_db_stmt_bind_param($iStmt, 'issdddss', $studentId, $classLevel, $term, $totalFee, $paidSum, $balance, $status, $payCode);
                thamani_db_stmt_execute($iStmt);
                thamani_db_stmt_close($iStmt);
            }
        }

        return [
            'student_id'  => $studentId,
            'class_level' => $classLevel,
            'term'        => $term,
            'total_fee'   => $totalFee,
            'paid_amount' => $paidSum,
            'balance'     => $balance,
            'status'      => $status,
            'pay_code'    => $payCode,
        ];
    }
}

if (!function_exists('bank_api_record_transaction')) {
    /**
     * Idempotently record a bank payment transaction and recalculate the student's fee account.
     *
     * @return array{ok:bool, duplicate:bool, error:string, transaction_ref:string, receipt_number:string, fee_summary:array|null, sms_sent:bool}
     */
    function bank_api_record_transaction($conn, array $tx, bool $sendSmsReceipt = false): array {
        $bankCode   = strtoupper(trim((string)($tx['bank_code'] ?? 'BANK')));
        $txRef      = trim((string)($tx['transaction_ref'] ?? ''));
        $amount     = (float)($tx['amount'] ?? 0);
        $channel    = trim((string)($tx['payment_channel'] ?? 'Bank API'));
        $receiptNo  = trim((string)($tx['receipt_number'] ?? ''));
        $rawPayload = is_string($tx['raw_payload'] ?? null) ? $tx['raw_payload'] : json_encode($tx['raw_payload'] ?? $tx, JSON_UNESCAPED_UNICODE);
        $txStatus   = strtoupper(trim((string)($tx['status'] ?? 'SUCCESS')));
        $identifier = trim((string)($tx['student_identifier'] ?? $tx['pay_code'] ?? $tx['lin_number'] ?? ''));
        $studentId  = (int)($tx['student_id'] ?? 0);

        if ($amount <= 0) {
            return ['ok' => false, 'duplicate' => false, 'error' => 'Invalid transaction amount.', 'transaction_ref' => $txRef, 'receipt_number' => $receiptNo, 'fee_summary' => null, 'sms_sent' => false];
        }
        if ($txRef === '') {
            $txRef = $bankCode . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        }
        if ($receiptNo === '') {
            $receiptNo = 'REC-' . strtoupper(substr(md5($txRef), 0, 8));
        }

        // Idempotency check: skip if transaction_ref already exists
        $chk = thamani_db_prepare($conn, "SELECT id, student_id, receipt_number FROM bank_transactions WHERE transaction_ref = ? LIMIT 1");
        if ($chk) {
            thamani_db_stmt_bind_param($chk, 's', $txRef);
            thamani_db_stmt_execute($chk);
            $cRes = thamani_db_stmt_get_result($chk);
            $existing = $cRes ? thamani_db_fetch_assoc($cRes) : null;
            thamani_db_stmt_close($chk);
            if ($existing) {
                $existingStudent = bank_api_find_student($conn, $identifier, (int)$existing['student_id']);
                $summary = $existingStudent ? bank_api_recalculate_student_fee($conn, $existingStudent) : null;
                return [
                    'ok'              => true,
                    'duplicate'       => true,
                    'error'           => '',
                    'transaction_ref' => $txRef,
                    'receipt_number'  => $existing['receipt_number'] ?: $receiptNo,
                    'fee_summary'     => $summary,
                    'sms_sent'        => false,
                ];
            }
        }

        $student = bank_api_find_student($conn, $identifier, $studentId);
        if (!$student) {
            return ['ok' => false, 'duplicate' => false, 'error' => "Student '{$identifier}' not found in registry.", 'transaction_ref' => $txRef, 'receipt_number' => $receiptNo, 'fee_summary' => null, 'sms_sent' => false];
        }

        $sId   = (int)$student['id'];
        $sName = (string)$student['full_name'];
        $sLin  = (string)($student['lin_number'] ?? '');

        $ins = thamani_db_prepare(
            $conn,
            "INSERT INTO bank_transactions
                (transaction_ref, student_id, lin_number, student_name, bank_code, payment_channel, amount, receipt_number, raw_payload, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$ins) {
            return ['ok' => false, 'duplicate' => false, 'error' => 'Failed to prepare bank_transactions insert.', 'transaction_ref' => $txRef, 'receipt_number' => $receiptNo, 'fee_summary' => null, 'sms_sent' => false];
        }

        thamani_db_stmt_bind_param($ins, 'sissssdsss', $txRef, $sId, $sLin, $sName, $bankCode, $channel, $amount, $receiptNo, $rawPayload, $txStatus);
        $inserted = thamani_db_stmt_execute($ins);
        $insertErr = thamani_db_stmt_error($ins);
        thamani_db_stmt_close($ins);

        if (!$inserted) {
            return ['ok' => false, 'duplicate' => false, 'error' => 'Database error recording transaction: ' . $insertErr, 'transaction_ref' => $txRef, 'receipt_number' => $receiptNo, 'fee_summary' => null, 'sms_sent' => false];
        }

        $feeSummary = bank_api_recalculate_student_fee($conn, $student);

        // Update bank last_sync_at timestamp
        $uBk = thamani_db_prepare($conn, "UPDATE bank_integrations SET last_sync_at = CURRENT_TIMESTAMP WHERE UPPER(bank_code) = UPPER(?)");
        if ($uBk) {
            thamani_db_stmt_bind_param($uBk, 's', $bankCode);
            thamani_db_stmt_execute($uBk);
            thamani_db_stmt_close($uBk);
        }

        // Optional auto-SMS receipt via Ego SMS
        $smsSent = false;
        if ($sendSmsReceipt && $txStatus === 'SUCCESS') {
            if (!function_exists('egosms_send_fee_receipt') && file_exists(__DIR__ . '/sms_helper.php')) {
                require_once __DIR__ . '/sms_helper.php';
            }
            if (function_exists('egosms_send_fee_receipt')) {
                $smsRes = egosms_send_fee_receipt($conn, $student, $amount, (float)$feeSummary['balance'], $bankCode, $receiptNo);
                $smsSent = !empty($smsRes['sent']);
            }
        }

        return [
            'ok'              => true,
            'duplicate'       => false,
            'error'           => '',
            'transaction_ref' => $txRef,
            'receipt_number'  => $receiptNo,
            'fee_summary'     => $feeSummary,
            'sms_sent'        => $smsSent,
        ];
    }
}

if (!function_exists('bank_api_normalize_transactions')) {
    /**
     * Normalize varied Ugandan Bank / SchoolPay / FlexiPay / Mobile Money JSON responses
     * into a uniform list of transaction arrays.
     */
    function bank_api_normalize_transactions(array $decoded, string $bankCode): array {
        $items = [];
        if (isset($decoded['transactions']) && is_array($decoded['transactions'])) {
            $items = $decoded['transactions'];
        } elseif (isset($decoded['payments']) && is_array($decoded['payments'])) {
            $items = $decoded['payments'];
        } elseif (isset($decoded['data']) && is_array($decoded['data'])) {
            // Either data is a list of items or an object containing transactions/payments
            if (isset($decoded['data']['transactions']) && is_array($decoded['data']['transactions'])) {
                $items = $decoded['data']['transactions'];
            } elseif (isset($decoded['data']['payments']) && is_array($decoded['data']['payments'])) {
                $items = $decoded['data']['payments'];
            } elseif (array_is_list($decoded['data'])) {
                $items = $decoded['data'];
            } elseif (!empty($decoded['data'])) {
                $items = [$decoded['data']];
            }
        } elseif (isset($decoded['records']) && is_array($decoded['records'])) {
            $items = $decoded['records'];
        } elseif (array_is_list($decoded)) {
            $items = $decoded;
        } elseif (isset($decoded['amount']) || isset($decoded['amountPaid'])) {
            $items = [$decoded];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $amount = (float)($item['amount'] ?? $item['amountPaid'] ?? $item['paidAmount'] ?? $item['transactionAmount'] ?? 0);
            if ($amount <= 0) continue;

            $status = strtoupper(trim((string)($item['status'] ?? $item['paymentStatus'] ?? 'SUCCESS')));
            if (in_array($status, ['COMPLETED', 'PAID', 'OK', 'SETTLED', 'SUCCESSFUL'], true)) {
                $status = 'SUCCESS';
            }

            $txRef = trim((string)(
                $item['transaction_ref']
                ?? $item['transactionId']
                ?? $item['transactionRef']
                ?? $item['schoolpayReceiptNumber']
                ?? $item['reference']
                ?? $item['referenceNumber']
                ?? ''
            ));
            $receiptNo = trim((string)(
                $item['receipt_number']
                ?? $item['receiptNumber']
                ?? $item['schoolpayReceiptNumber']
                ?? $item['referenceNumber']
                ?? $txRef
            ));
            $identifier = trim((string)(
                $item['pay_code']
                ?? $item['studentPaymentCode']
                ?? $item['studentCode']
                ?? $item['lin_number']
                ?? $item['student_lin']
                ?? $item['studentRegNumber']
                ?? $item['accountReference']
                ?? ''
            ));
            $channel = trim((string)(
                $item['payment_channel']
                ?? $item['paymentChannel']
                ?? $item['sourceChannel']
                ?? $item['channel']
                ?? ($bankCode . ' API')
            ));

            $normalized[] = [
                'bank_code'          => $bankCode,
                'transaction_ref'    => $txRef,
                'student_identifier' => $identifier,
                'student_id'         => (int)($item['student_id'] ?? 0),
                'amount'             => $amount,
                'payment_channel'    => $channel,
                'receipt_number'     => $receiptNo,
                'status'             => $status,
                'raw_payload'        => $item,
            ];
        }

        return $normalized;
    }
}

if (!function_exists('bank_api_http_request')) {
    /**
     * Execute an authenticated HTTPS request to a configured Bank API endpoint (PHP 8.5 safe).
     *
     * @return array{ok:bool, http_code:int, latency_ms:int, decoded:array|null, raw:string, error:string}
     */
    function bank_api_http_request(array $bankRow, array $queryParams = [], ?array $postBody = null): array {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'http_code' => 0, 'latency_ms' => 0, 'decoded' => null, 'raw' => '', 'error' => 'PHP cURL extension is required for Bank API calls.'];
        }

        $endpoint = trim((string)($bankRow['api_endpoint'] ?? ''));
        if ($endpoint === '' || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'http_code' => 0, 'latency_ms' => 0, 'decoded' => null, 'raw' => '', 'error' => 'Bank API endpoint URL is not configured or invalid.'];
        }

        $bankCode  = strtoupper(trim((string)($bankRow['bank_code'] ?? '')));
        $accountNo = trim((string)($bankRow['account_number'] ?? ''));
        $apiKey    = trim((string)($bankRow['api_key'] ?? ''));
        $secretKey = trim((string)($bankRow['secret_key'] ?? ''));

        // Append query params cleanly
        if (!empty($queryParams)) {
            $sep = str_contains($endpoint, '?') ? '&' : '?';
            $endpoint .= $sep . http_build_query($queryParams);
        }

        $headers = [
            'Accept: application/json',
            'X-Bank-Code: ' . $bankCode,
        ];
        if ($accountNo !== '') {
            $headers[] = 'X-Account-Number: ' . $accountNo;
        }
        if ($apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
            $headers[] = 'X-API-Key: ' . $apiKey;
        }
        if ($secretKey !== '') {
            $headers[] = 'X-Client-Secret: ' . $secretKey;
            // SchoolPay-compatible MD5 request hash header
            $today = date('Y-m-d');
            $headers[] = 'X-Request-Hash: ' . md5($accountNo . $today . $secretKey);
        }

        $ch = curl_init($endpoint);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'ThamaniSchoolPortal-BankSync/2.0',
        ];
        if ($postBody !== null) {
            $json = json_encode($postBody, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($json);
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = $json;
        }
        curl_setopt_array($ch, $opts);

        $t0       = microtime(true);
        $raw      = curl_exec($ch);
        $latency  = (int)round((microtime(true) - $t0) * 1000);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        unset($ch);

        if ($curlErr || $raw === false) {
            return ['ok' => false, 'http_code' => $httpCode, 'latency_ms' => $latency, 'decoded' => null, 'raw' => '', 'error' => 'Connection error: ' . $curlErr];
        }

        $decoded = json_decode($raw, true);
        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = is_array($decoded) ? ($decoded['message'] ?? $decoded['error'] ?? "HTTP {$httpCode}") : "HTTP {$httpCode}";
            return ['ok' => false, 'http_code' => $httpCode, 'latency_ms' => $latency, 'decoded' => is_array($decoded) ? $decoded : null, 'raw' => $raw, 'error' => "Bank API returned HTTP {$httpCode}: {$msg}"];
        }

        return ['ok' => true, 'http_code' => $httpCode, 'latency_ms' => $latency, 'decoded' => is_array($decoded) ? $decoded : [], 'raw' => $raw, 'error' => ''];
    }
}

if (!function_exists('bank_api_test_connection')) {
    /**
     * Test connectivity and authentication against a configured bank integration.
     */
    function bank_api_test_connection(array $bankRow): array {
        $params = [
            'account_number' => $bankRow['account_number'] ?? '',
            'from_date'      => date('Y-m-d'),
            'to_date'        => date('Y-m-d'),
            'ping'           => '1',
        ];
        if (strtoupper((string)($bankRow['bank_code'] ?? '')) === 'SCHOOLPAY' && !empty($bankRow['secret_key'])) {
            $params['hash'] = md5(($bankRow['account_number'] ?? '') . date('Y-m-d') . $bankRow['secret_key']);
        }
        $res = bank_api_http_request($bankRow, $params);
        return [
            'success'    => $res['ok'],
            'http_code'  => $res['http_code'],
            'latency_ms' => $res['latency_ms'],
            'error'      => $res['error'],
            'message'    => $res['ok']
                ? "Connected to {$bankRow['bank_name']} (HTTP {$res['http_code']} in {$res['latency_ms']}ms)."
                : "Could not reach {$bankRow['bank_name']}: {$res['error']}",
        ];
    }
}

if (!function_exists('bank_api_pull_bank_transactions')) {
    /**
     * Pull transactions from a single configured Bank API endpoint for a date range
     * (or for a specific student if $student is provided) and reconcile them into the DB.
     */
    function bank_api_pull_bank_transactions($conn, array $bankRow, ?array $student = null, ?string $fromDate = null, ?string $toDate = null): array {
        $bankCode = strtoupper(trim((string)($bankRow['bank_code'] ?? '')));
        $fromDate = $fromDate ?: date('Y-m-d', strtotime('-90 days'));
        $toDate   = $toDate ?: date('Y-m-d');

        $params = [
            'account_number' => $bankRow['account_number'] ?? '',
            'from_date'      => $fromDate,
            'to_date'        => $toDate,
        ];
        if ($student) {
            if (!empty($student['pay_code'])) $params['pay_code'] = $student['pay_code'];
            if (!empty($student['lin_number'])) $params['lin_number'] = $student['lin_number'];
            $params['student_code'] = $student['pay_code'] ?: ($student['lin_number'] ?: (string)$student['id']);
        }
        if ($bankCode === 'SCHOOLPAY' && !empty($bankRow['secret_key'])) {
            $params['school_code'] = $bankRow['account_number'] ?? '';
            $params['hash'] = md5(($bankRow['account_number'] ?? '') . $toDate . $bankRow['secret_key']);
        }

        $res = bank_api_http_request($bankRow, $params);
        if (!$res['ok']) {
            return [
                'success'     => false,
                'bank_code'   => $bankCode,
                'new_count'   => 0,
                'dup_count'   => 0,
                'unmatched'   => 0,
                'total_added' => 0.0,
                'error'       => $res['error'],
            ];
        }

        $txList = bank_api_normalize_transactions($res['decoded'] ?? [], $bankCode);
        $newCount   = 0;
        $dupCount   = 0;
        $unmatched  = 0;
        $totalAdded = 0.0;

        foreach ($txList as $tx) {
            if ($student && empty($tx['student_identifier']) && empty($tx['student_id'])) {
                $tx['student_id'] = (int)$student['id'];
                $tx['student_identifier'] = $student['pay_code'] ?: ($student['lin_number'] ?? '');
            }
            $rec = bank_api_record_transaction($conn, $tx, true);
            if ($rec['ok']) {
                if ($rec['duplicate']) {
                    $dupCount++;
                } else {
                    $newCount++;
                    $totalAdded += (float)$tx['amount'];
                }
            } else {
                $unmatched++;
            }
        }

        // Mark bank last_sync_at
        $uBk = thamani_db_prepare($conn, "UPDATE bank_integrations SET last_sync_at = CURRENT_TIMESTAMP WHERE UPPER(bank_code) = UPPER(?)");
        if ($uBk) {
            thamani_db_stmt_bind_param($uBk, 's', $bankCode);
            thamani_db_stmt_execute($uBk);
            thamani_db_stmt_close($uBk);
        }

        return [
            'success'     => true,
            'bank_code'   => $bankCode,
            'new_count'   => $newCount,
            'dup_count'   => $dupCount,
            'unmatched'   => $unmatched,
            'total_added' => $totalAdded,
            'error'       => '',
        ];
    }
}

if (!function_exists('bank_api_check_student_fees')) {
    /**
     * Check a single student's live fees across all active Bank API integrations
     * and return their up-to-date fee summary and transaction receipts.
     */
    function bank_api_check_student_fees($conn, int $studentId, bool $pollBanks = true): array {
        $student = bank_api_find_student($conn, '', $studentId);
        if (!$student) {
            return ['success' => false, 'error' => 'Student account not found.'];
        }

        $banksChecked  = [];
        $bankErrors    = [];
        $newPayments   = 0;
        $newAmountSum  = 0.0;

        if ($pollBanks) {
            $bRes = thamani_db_query($conn, "SELECT * FROM bank_integrations WHERE is_active = 1 ORDER BY id ASC");
            if ($bRes) {
                while ($bk = thamani_db_fetch_assoc($bRes)) {
                    $endpoint = trim((string)($bk['api_endpoint'] ?? ''));
                    if ($endpoint === '') continue;
                    $sync = bank_api_pull_bank_transactions($conn, $bk, $student);
                    $banksChecked[] = $bk['bank_code'];
                    if ($sync['success']) {
                        $newPayments  += $sync['new_count'];
                        $newAmountSum += $sync['total_added'];
                    } else {
                        $bankErrors[$bk['bank_code']] = $sync['error'];
                    }
                }
            }
        }

        // Always recalculate student_fees from bank_transactions ledger
        $summary = bank_api_recalculate_student_fee($conn, $student);

        // Fetch latest transactions for this student
        $transactions = [];
        $linNum = trim((string)($student['lin_number'] ?? ''));
        $txStmt = thamani_db_prepare(
            $conn,
            "SELECT transaction_ref, bank_code, payment_channel, amount, receipt_number, status, payment_date, created_at
             FROM bank_transactions
             WHERE student_id = ? OR (lin_number != '' AND lin_number = ?)
             ORDER BY id DESC
             LIMIT 50"
        );
        if ($txStmt) {
            thamani_db_stmt_bind_param($txStmt, 'is', $studentId, $linNum);
            thamani_db_stmt_execute($txStmt);
            $txRes = thamani_db_stmt_get_result($txStmt);
            if ($txRes) {
                while ($r = thamani_db_fetch_assoc($txRes)) {
                    $transactions[] = $r;
                }
            }
            thamani_db_stmt_close($txStmt);
        }

        return [
            'success'            => true,
            'student'            => [
                'id'          => (int)$student['id'],
                'full_name'   => $student['full_name'],
                'class_level' => $student['class_level'],
                'stream'      => $student['stream'] ?? '',
                'lin_number'  => $student['lin_number'] ?? '',
                'pay_code'    => $student['pay_code'] ?? '',
            ],
            'fee_summary'        => $summary,
            'transactions'       => $transactions,
            'banks_checked'      => $banksChecked,
            'new_payments_found' => $newPayments,
            'new_amount_added'   => $newAmountSum,
            'bank_errors'        => $bankErrors,
            'checked_at'         => date('d M Y, g:ia'),
        ];
    }
}
