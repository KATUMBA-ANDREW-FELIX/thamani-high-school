<?php
/**
 * Thamani High School - Bank Integration & School Fees Actions Controller
 * -----------------------------------------------------------------------
 * Handles class fee configuration, student fee balance recalculations,
 * bank API credentials management, real Bank API polling & connection tests,
 * single-student live fee checks, and verified Bank Webhook notifications
 * (SchoolPay, Centenary Bank, Stanbic FlexiPay, Equity, MTN MoMo, Airtel Money).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/bank_api_helper.php';

// Check if request is a Bank Webhook (API Call)
$isWebhook = isset($_GET['webhook']) || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json') && !isset($_SESSION['admin_id']));

if (!$isWebhook) {
    require_once __DIR__ . '/auth_admin.php';
    require_admin_login();
}

$redirectUrl = 'admin_dashboard.php?tab=tab-admin-fees#tab-admin-fees';

// =========================================================================
// 1. VERIFIED INBOUND BANK WEBHOOK (SchoolPay, Centenary, Stanbic, Equity, MoMo)
// =========================================================================
if ($isWebhook) {
    header('Content-Type: application/json');
    $rawInput = file_get_contents('php://input') ?: '';
    $data     = json_decode($rawInput, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $bankCode = strtoupper(trim((string)($_GET['bank'] ?? $data['bank_code'] ?? $data['bankCode'] ?? 'CENTENARY')));

    // Verify bank exists, is active, and passes webhook secret/HMAC signature check
    $verify = bank_api_verify_webhook($conn, $bankCode, $rawInput, $data);
    if (!$verify['ok']) {
        http_response_code($verify['bank'] === null ? 404 : 401);
        echo json_encode(['status' => 'ERROR', 'message' => $verify['error']]);
        exit;
    }

    // Normalize single or batch payment payload
    $normalizedList = bank_api_normalize_transactions($data, $bankCode);
    if (empty($normalizedList)) {
        http_response_code(400);
        echo json_encode([
            'status'  => 'ERROR',
            'message' => 'Invalid payment payload. Mandatory fields: amount (or amountPaid) and pay_code / lin_number / studentCode / student_id.',
        ]);
        exit;
    }

    $results = [];
    $processedCount = 0;
    $duplicateCount = 0;
    $errorMessages  = [];

    foreach ($normalizedList as $tx) {
        if ($tx['student_identifier'] === '' && $tx['student_id'] <= 0) {
            $errorMessages[] = 'Missing student identifier (pay_code / lin_number / student_id).';
            continue;
        }
        $rec = bank_api_record_transaction($conn, $tx, true);
        $results[] = $rec;
        if ($rec['ok']) {
            if ($rec['duplicate']) {
                $duplicateCount++;
            } else {
                $processedCount++;
            }
        } else {
            $errorMessages[] = $rec['error'];
        }
    }

    if ($processedCount === 0 && $duplicateCount === 0) {
        http_response_code(422);
        echo json_encode([
            'status'  => 'ERROR',
            'message' => implode(' ', $errorMessages) ?: 'Unable to process payment.',
        ]);
        exit;
    }

    $first = $results[0];
    $summary = $first['fee_summary'] ?? null;
    echo json_encode([
        'status'            => 'SUCCESS',
        'message'           => $duplicateCount > 0 && $processedCount === 0
            ? 'Transaction already processed (idempotent).'
            : "Processed {$processedCount} payment(s) via {$bankCode}" . ($duplicateCount > 0 ? " ({$duplicateCount} duplicate skipped)" : '') . '.',
        'processed_count'   => $processedCount,
        'duplicate_count'   => $duplicateCount,
        'already_processed' => ($duplicateCount > 0 && $processedCount === 0),
        'transaction_ref'   => $first['transaction_ref'] ?? '',
        'receipt_number'    => $first['receipt_number'] ?? '',
        'total_fee'         => $summary['total_fee'] ?? null,
        'paid_amount'       => $summary['paid_amount'] ?? null,
        'balance'           => $summary['balance'] ?? null,
        'fee_status'        => $summary['status'] ?? null,
        'sms_receipt_sent'  => (bool)($first['sms_sent'] ?? false),
    ]);
    exit;
}

// =========================================================================
// 2. ADMIN FORM & AJAX ACTIONS
// =========================================================================
$action   = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));
$wantsJson = (($_POST['response_format'] ?? $_GET['response_format'] ?? '') === 'json')
    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
    || in_array($action, ['check_student_fees_api', 'test_bank_connection'], true);

if ($wantsJson) {
    header('Content-Type: application/json');
}

switch ($action) {
    case 'test_bank_connection':
        $bankCode = strtoupper(trim((string)($_POST['bank_code'] ?? $_GET['bank_code'] ?? '')));
        if ($bankCode === '') {
            echo json_encode(['success' => false, 'error' => 'Bank code is required.']);
            exit;
        }
        $bankRow = null;
        $bStmt = thamani_db_prepare($conn, "SELECT * FROM bank_integrations WHERE UPPER(bank_code) = UPPER(?) LIMIT 1");
        if ($bStmt) {
            thamani_db_stmt_bind_param($bStmt, 's', $bankCode);
            thamani_db_stmt_execute($bStmt);
            $bRes = thamani_db_stmt_get_result($bStmt);
            if ($bRes && $r = thamani_db_fetch_assoc($bRes)) $bankRow = $r;
            thamani_db_stmt_close($bStmt);
        }
        if (!$bankRow) {
            echo json_encode(['success' => false, 'error' => "Bank '{$bankCode}' not found."]);
            exit;
        }
        $testRes = bank_api_test_connection($bankRow);
        echo json_encode($testRes);
        exit;

    case 'check_student_fees_api':
        $studentId = (int)($_POST['student_id'] ?? $_GET['student_id'] ?? 0);
        $pollBanks = (($_POST['poll_banks'] ?? $_GET['poll_banks'] ?? '1') !== '0');
        if ($studentId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Valid student_id is required.']);
            exit;
        }
        $checkRes = bank_api_check_student_fees($conn, $studentId, $pollBanks);
        echo json_encode($checkRes);
        exit;

    case 'simulate_sandbox_payment':
        $bankCode  = strtoupper(trim((string)($_POST['bank_code'] ?? 'SCHOOLPAY')));
        $studentId = (int)($_POST['student_id'] ?? 0);
        $amount    = (float)($_POST['amount'] ?? 0);

        $bankRow = null;
        $bStmt = thamani_db_prepare($conn, "SELECT * FROM bank_integrations WHERE UPPER(bank_code) = UPPER(?) LIMIT 1");
        if ($bStmt) {
            thamani_db_stmt_bind_param($bStmt, 's', $bankCode);
            thamani_db_stmt_execute($bStmt);
            $bRes = thamani_db_stmt_get_result($bStmt);
            if ($bRes && $r = thamani_db_fetch_assoc($bRes)) $bankRow = $r;
            thamani_db_stmt_close($bStmt);
        }
        if (!$bankRow) {
            $_SESSION['admin_flash'] = ['type' => 'error', 'message' => "Bank integration '{$bankCode}' not found."];
            break;
        }
        if (strtolower((string)($bankRow['environment'] ?? 'sandbox')) !== 'sandbox') {
            $_SESSION['admin_flash'] = ['type' => 'error', 'message' => "Test payment simulation is ONLY allowed for banks in Sandbox mode. Switch {$bankCode} to Sandbox to test."];
            break;
        }
        if ($studentId <= 0 || $amount <= 0) {
            $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Select a student and enter a positive UGX amount for the sandbox test payment.'];
            break;
        }

        $txRef     = $bankCode . '-SANDBOX-' . date('YmdHis') . '-' . random_int(100, 999);
        $receiptNo = 'SBX-' . random_int(100000, 999999);
        $rec = bank_api_record_transaction($conn, [
            'bank_code'       => $bankCode,
            'transaction_ref' => $txRef,
            'student_id'      => $studentId,
            'amount'          => $amount,
            'payment_channel' => $bankRow['bank_name'] . ' (Sandbox Webhook Test)',
            'receipt_number'  => $receiptNo,
            'status'          => 'SUCCESS',
            'raw_payload'     => ['mode' => 'sandbox_test', 'bank_code' => $bankCode, 'timestamp' => date('c')],
        ], true);

        if ($rec['ok']) {
            $bal = number_format((float)($rec['fee_summary']['balance'] ?? 0));
            $smsNote = $rec['sms_sent'] ? ' Guardian SMS receipt sent via Ego SMS.' : '';
            $_SESSION['admin_flash'] = [
                'type'    => 'success',
                'message' => "[SANDBOX TEST] Recorded UGX " . number_format($amount) . " via {$bankCode} (Receipt: {$receiptNo}). Updated balance: UGX {$bal}.{$smsNote}",
            ];
        } else {
            $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Sandbox test failed: ' . $rec['error']];
        }
        break;

    case 'lock_fees_structure':
        thamani_set_setting($conn, 'class_fees_locked', '1');
        $_SESSION['admin_flash'] = [
            'type'    => 'success',
            'message' => 'Class Fee Structure has been LOCKED & finalized. Class fees are now read-only across all portals.',
        ];
        break;

    case 'unlock_fees_structure':
        thamani_set_setting($conn, 'class_fees_locked', '0');
        $_SESSION['admin_flash'] = [
            'type'    => 'info',
            'message' => 'Class Fee Structure has been UNLOCKED for editing.',
        ];
        break;

    case 'pull_all_bank_feeds':
        // 1. Poll every active bank integration that has an API endpoint configured
        $banksPolled    = 0;
        $banksSucceeded = 0;
        $newTxCount     = 0;
        $dupTxCount     = 0;
        $newAmountTotal = 0.0;
        $bankErrors     = [];

        $bRes = thamani_db_query($conn, "SELECT * FROM bank_integrations WHERE is_active = 1 ORDER BY id ASC");
        if ($bRes) {
            while ($bk = thamani_db_fetch_assoc($bRes)) {
                if (trim((string)($bk['api_endpoint'] ?? '')) === '') continue;
                $banksPolled++;
                $sync = bank_api_pull_bank_transactions($conn, $bk);
                if ($sync['success']) {
                    $banksSucceeded++;
                    $newTxCount     += $sync['new_count'];
                    $dupTxCount     += $sync['dup_count'];
                    $newAmountTotal += $sync['total_added'];
                } else {
                    $bankErrors[] = $bk['bank_code'] . ': ' . $sync['error'];
                }
            }
        }

        // 2. Recalculate EVERY student's fee ledger from verified bank_transactions
        $reconciledCount = 0;
        $totalPaidSum    = 0.0;
        $resSt = thamani_db_query($conn, "SELECT id, full_name, lin_number, pay_code, class_level, stream, guardian_name, guardian_phone FROM students ORDER BY full_name ASC");
        if ($resSt) {
            while ($st = thamani_db_fetch_assoc($resSt)) {
                $summary = bank_api_recalculate_student_fee($conn, $st);
                $reconciledCount++;
                $totalPaidSum += (float)$summary['paid_amount'];
            }
        }

        $msg = "Ledger reconciled for {$reconciledCount} student(s) (Total Verified Paid: UGX " . number_format($totalPaidSum) . "). ";
        if ($banksPolled === 0) {
            $msg .= "No active Bank API endpoints configured to poll.";
        } elseif ($banksSucceeded > 0) {
            $msg .= "Polled {$banksSucceeded}/{$banksPolled} Bank API(s): {$newTxCount} new payment(s) imported (UGX " . number_format($newAmountTotal) . "), {$dupTxCount} already reconciled.";
        } else {
            $msg .= "Could not reach live Bank API endpoints (" . implode(' | ', array_slice($bankErrors, 0, 2)) . "). Verify your Bank API URLs and credentials in Bank Settings, or receive payments via Webhooks.";
        }

        $_SESSION['admin_flash'] = [
            'type'    => ($banksSucceeded > 0 || $banksPolled === 0) ? 'success' : 'info',
            'message' => $msg,
        ];
        break;

    case 'sync_bank_api':
        $bankCode = strtoupper(trim((string)($_POST['bank_code'] ?? 'CENTENARY')));
        $bankRow  = null;
        $bStmt = thamani_db_prepare($conn, "SELECT * FROM bank_integrations WHERE UPPER(bank_code) = UPPER(?) LIMIT 1");
        if ($bStmt) {
            thamani_db_stmt_bind_param($bStmt, 's', $bankCode);
            thamani_db_stmt_execute($bStmt);
            $bRes = thamani_db_stmt_get_result($bStmt);
            if ($bRes && $r = thamani_db_fetch_assoc($bRes)) $bankRow = $r;
            thamani_db_stmt_close($bStmt);
        }

        if (!$bankRow) {
            $_SESSION['admin_flash'] = ['type' => 'error', 'message' => "Bank integration '{$bankCode}' not found."];
            break;
        }
        if ((int)($bankRow['is_active'] ?? 0) !== 1) {
            $_SESSION['admin_flash'] = ['type' => 'error', 'message' => "Bank '{$bankCode}' is disabled. Activate it first before syncing."];
            break;
        }

        $sync = bank_api_pull_bank_transactions($conn, $bankRow);
        if ($sync['success']) {
            $_SESSION['admin_flash'] = [
                'type'    => 'success',
                'message' => "Bank API sync complete for {$bankCode}! Imported {$sync['new_count']} new payment(s) totaling UGX " . number_format($sync['total_added']) . " ({$sync['dup_count']} duplicate(s) skipped" . ($sync['unmatched'] > 0 ? ", {$sync['unmatched']} unmatched" : '') . ").",
            ];
        } else {
            $_SESSION['admin_flash'] = [
                'type'    => 'error',
                'message' => "Bank API sync failed for {$bankCode}: {$sync['error']}",
            ];
        }
        break;

    case 'set_class_fee':
        $isLocked = thamani_get_setting($conn, 'class_fees_locked', '0') === '1';
        if ($isLocked) {
            $_SESSION['admin_flash'] = [
                'type'    => 'error',
                'message' => 'Class Fee Structure is currently LOCKED. Please unlock the fee structure before making adjustments.',
            ];
            break;
        }
        $classLevel = trim((string)($_POST['class_level'] ?? ''));
        $feeAmount  = (float)($_POST['fee_amount'] ?? 0);

        if ($classLevel === '' || $feeAmount < 0) {
            $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Please select a valid class and enter a non-negative fee amount.'];
            break;
        }

        thamani_set_class_fee($conn, $classLevel, $feeAmount);

        $studentsInClass = [];
        $stmt = thamani_db_prepare($conn, "SELECT id, class_level, pay_code FROM students WHERE class_level = ?");
        if ($stmt) {
            thamani_db_stmt_bind_param($stmt, 's', $classLevel);
            thamani_db_stmt_execute($stmt);
            $res = thamani_db_stmt_get_result($stmt);
            if ($res) {
                while ($row = thamani_db_fetch_assoc($res)) {
                    $studentsInClass[] = $row;
                }
            }
            thamani_db_stmt_close($stmt);
        }

        $updatedCount = 0;
        foreach ($studentsInClass as $st) {
            $sid = (int)$st['id'];
            $fCheck = thamani_db_prepare($conn, "SELECT paid_amount FROM student_fees WHERE student_id = ? LIMIT 1");
            $paid = 0.0;
            $hasRecord = false;
            if ($fCheck) {
                thamani_db_stmt_bind_param($fCheck, 'i', $sid);
                thamani_db_stmt_execute($fCheck);
                $fRes = thamani_db_stmt_get_result($fCheck);
                if ($fRes && $fr = thamani_db_fetch_assoc($fRes)) {
                    $paid = (float)$fr['paid_amount'];
                    $hasRecord = true;
                }
                thamani_db_stmt_close($fCheck);
            }

            $bal    = max(0.0, $feeAmount - $paid);
            $status = ($bal <= 0 && $feeAmount > 0) ? 'PAID' : ($paid > 0 ? 'PARTIAL' : 'UNPAID');

            if ($hasRecord) {
                $uStmt = thamani_db_prepare($conn, "UPDATE student_fees SET total_fee = ?, balance = ?, status = ?, class_level = ?, updated_at = CURRENT_TIMESTAMP WHERE student_id = ?");
                if ($uStmt) {
                    thamani_db_stmt_bind_param($uStmt, 'ddssi', $feeAmount, $bal, $status, $classLevel, $sid);
                    thamani_db_stmt_execute($uStmt);
                    thamani_db_stmt_close($uStmt);
                    $updatedCount++;
                }
            } else {
                $pc = (string)($st['pay_code'] ?? '');
                $iStmt = thamani_db_prepare($conn, "INSERT INTO student_fees (student_id, class_level, term, total_fee, paid_amount, balance, status, pay_code) VALUES (?, ?, 'Term III 2026', ?, ?, ?, ?, ?)");
                if ($iStmt) {
                    thamani_db_stmt_bind_param($iStmt, 'isdddss', $sid, $classLevel, $feeAmount, $paid, $bal, $status, $pc);
                    thamani_db_stmt_execute($iStmt);
                    thamani_db_stmt_close($iStmt);
                    $updatedCount++;
                }
            }
        }

        $_SESSION['admin_flash'] = [
            'type'    => 'success',
            'message' => "Successfully set class fee of UGX " . number_format($feeAmount) . " for {$classLevel}. Updated {$updatedCount} student fee accounts.",
        ];
        break;

    case 'save_student_fee':
        $_SESSION['admin_flash'] = [
            'type'    => 'error',
            'message' => 'Individual student fee adjustments are disabled. Fees are assigned uniformly per class level.',
        ];
        break;

    case 'record_manual_payment':
        $_SESSION['admin_flash'] = [
            'type'    => 'error',
            'message' => 'Manual payment recording is disabled. Payments must be processed and synchronized directly via connected Bank APIs or verified Webhook feeds.',
        ];
        break;

    case 'toggle_bank_status':
        $bankCode  = strtoupper(trim((string)($_POST['bank_code'] ?? '')));
        $newStatus = (int)($_POST['status'] ?? 0);

        if ($bankCode === '') {
            $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Invalid bank code.'];
            break;
        }

        $stmt = thamani_db_prepare($conn, "UPDATE bank_integrations SET is_active = ? WHERE bank_code = ?");
        if ($stmt) {
            thamani_db_stmt_bind_param($stmt, 'is', $newStatus, $bankCode);
            thamani_db_stmt_execute($stmt);
            thamani_db_stmt_close($stmt);

            $statusText = $newStatus === 1 ? 'ACTIVATED & CONNECTED' : 'DEACTIVATED';
            $_SESSION['admin_flash'] = ['type' => 'success', 'message' => "Bank API {$bankCode} status updated to {$statusText}."];
        }
        break;

    case 'add_bank_integration':
        $bankCode = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', trim((string)($_POST['bank_code'] ?? ''))));
        $bankName = trim((string)($_POST['bank_name'] ?? ''));
        $account  = trim((string)($_POST['account_number'] ?? ''));
        $endpoint = trim((string)($_POST['api_endpoint'] ?? ''));
        $apiKey   = trim((string)($_POST['api_key'] ?? ''));
        $secret   = trim((string)($_POST['secret_key'] ?? ''));
        $webhook  = trim((string)($_POST['webhook_url'] ?? ''));
        if ($webhook === '' && $bankCode !== '') {
            $webhook = bank_api_default_webhook_url($bankCode);
        }
        $whSec    = trim((string)($_POST['webhook_secret'] ?? ''));
        $env      = in_array(strtolower(trim((string)($_POST['environment'] ?? 'sandbox'))), ['production', 'sandbox'], true)
            ? strtolower(trim((string)$_POST['environment']))
            : 'sandbox';
        $active   = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        if ($bankCode === '' || $bankName === '') {
            $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Bank Code and Bank Name are required.'];
            break;
        }

        $chk = thamani_db_prepare($conn, "SELECT id FROM bank_integrations WHERE bank_code = ? LIMIT 1");
        if ($chk) {
            thamani_db_stmt_bind_param($chk, 's', $bankCode);
            thamani_db_stmt_execute($chk);
            $res = thamani_db_stmt_get_result($chk);
            if ($res && thamani_db_fetch_assoc($res)) {
                $_SESSION['admin_flash'] = ['type' => 'error', 'message' => "Bank Code '{$bankCode}' already exists. Please use a unique bank code."];
                thamani_db_stmt_close($chk);
                break;
            }
            thamani_db_stmt_close($chk);
        }

        $ins = thamani_db_prepare($conn, "INSERT INTO bank_integrations (bank_code, bank_name, account_number, api_endpoint, api_key, secret_key, webhook_url, webhook_secret, environment, is_active, last_sync_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
        if ($ins) {
            thamani_db_stmt_bind_param($ins, 'sssssssssi', $bankCode, $bankName, $account, $endpoint, $apiKey, $secret, $webhook, $whSec, $env, $active);
            thamani_db_stmt_execute($ins);
            thamani_db_stmt_close($ins);

            $_SESSION['admin_flash'] = ['type' => 'success', 'message' => "Successfully added new Bank / Webhook Integration: {$bankName} ({$bankCode})."];
        }
        break;

    case 'save_bank_config':
        $bankCode = strtoupper(trim((string)($_POST['bank_code'] ?? '')));
        $bankName = trim((string)($_POST['bank_name'] ?? ''));
        $account  = trim((string)($_POST['account_number'] ?? ''));
        $endpoint = trim((string)($_POST['api_endpoint'] ?? ''));
        $apiKey   = trim((string)($_POST['api_key'] ?? ''));
        $secret   = trim((string)($_POST['secret_key'] ?? ''));
        $webhook  = trim((string)($_POST['webhook_url'] ?? ''));
        $whSec    = trim((string)($_POST['webhook_secret'] ?? ''));
        $env      = in_array(strtolower(trim((string)($_POST['environment'] ?? 'sandbox'))), ['production', 'sandbox'], true)
            ? strtolower(trim((string)$_POST['environment']))
            : 'sandbox';

        if ($bankCode === '') {
            $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Bank code is required.'];
            break;
        }

        // Preserve existing secrets if left blank during edit
        $existing = null;
        $eStmt = thamani_db_prepare($conn, "SELECT secret_key, webhook_secret FROM bank_integrations WHERE bank_code = ? LIMIT 1");
        if ($eStmt) {
            thamani_db_stmt_bind_param($eStmt, 's', $bankCode);
            thamani_db_stmt_execute($eStmt);
            $eRes = thamani_db_stmt_get_result($eStmt);
            if ($eRes && $r = thamani_db_fetch_assoc($eRes)) $existing = $r;
            thamani_db_stmt_close($eStmt);
        }
        if ($secret === '' && $existing) $secret = (string)($existing['secret_key'] ?? '');
        if ($whSec === '' && $existing)  $whSec  = (string)($existing['webhook_secret'] ?? '');

        $stmt = thamani_db_prepare($conn, "UPDATE bank_integrations SET bank_name = COALESCE(NULLIF(?, ''), bank_name), account_number = ?, api_endpoint = ?, api_key = ?, secret_key = ?, webhook_url = ?, webhook_secret = ?, environment = ?, last_sync_at = CURRENT_TIMESTAMP WHERE bank_code = ?");
        if ($stmt) {
            thamani_db_stmt_bind_param($stmt, 'sssssssss', $bankName, $account, $endpoint, $apiKey, $secret, $webhook, $whSec, $env, $bankCode);
            thamani_db_stmt_execute($stmt);
            thamani_db_stmt_close($stmt);

            $_SESSION['admin_flash'] = ['type' => 'success', 'message' => "API credentials, endpoints & webhook updated for Bank: {$bankCode}."];
        }
        break;

    case 'delete_bank_integration':
        $bankCode = strtoupper(trim((string)($_POST['bank_code'] ?? '')));
        if ($bankCode === '') {
            $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Invalid bank code for deletion.'];
            break;
        }

        $del = thamani_db_prepare($conn, "DELETE FROM bank_integrations WHERE bank_code = ?");
        if ($del) {
            thamani_db_stmt_bind_param($del, 's', $bankCode);
            thamani_db_stmt_execute($del);
            thamani_db_stmt_close($del);

            $_SESSION['admin_flash'] = ['type' => 'success', 'message' => "Deleted Bank API / Webhook Integration '{$bankCode}'."];
        }
        break;

    case 'execute_annual_class_transition':
        $targetTerm = trim((string)($_POST['target_term'] ?? 'Term I 2027'));
        $gradYear   = (int)($_POST['graduation_year'] ?? date('Y'));

        $promotionMap = [
            'Senior 5' => 'Senior 6',
            'Senior 4' => 'Senior 5',
            'Senior 3' => 'Senior 4',
            'Senior 2' => 'Senior 3',
            'Senior 1' => 'Senior 2',
        ];

        // 1. Process Senior 6 Candidates -> Alumni & Status = 'Graduated'
        $s6Res = thamani_db_query($conn, "SELECT id, full_name, guardian_phone, guardian_email FROM students WHERE class_level = 'Senior 6' AND status != 'Graduated'");
        $graduatedCount = 0;
        if ($s6Res) {
            while ($s6 = thamani_db_fetch_assoc($s6Res)) {
                $stId    = (int)$s6['id'];
                $stName  = thamani_db_real_escape_string($conn, $s6['full_name']);
                $stPhone = thamani_db_real_escape_string($conn, $s6['guardian_phone'] ?: '');
                $stEmail = thamani_db_real_escape_string($conn, $s6['guardian_email'] ?: '');

                thamani_db_query($conn, "UPDATE students SET status = 'Graduated' WHERE id = {$stId}");

                $alCheck = thamani_db_query($conn, "SELECT id FROM alumni WHERE name = '{$stName}' AND year = {$gradYear} LIMIT 1");
                if ($alCheck && !thamani_db_fetch_assoc($alCheck)) {
                    thamani_db_query($conn, "INSERT INTO alumni (name, year, profession, phone, email) VALUES ('{$stName}', {$gradYear}, 'High School Graduate', '{$stPhone}', '{$stEmail}')");
                }
                $graduatedCount++;
            }
        }

        // 2. Promote S1-S5 continuing students
        $promotedCount = 0;
        foreach ($promotionMap as $oldClass => $newClass) {
            $stRes = thamani_db_query($conn, "SELECT id, class_level, pay_code FROM students WHERE class_level = '{$oldClass}' AND status != 'Graduated'");
            if ($stRes) {
                while ($st = thamani_db_fetch_assoc($stRes)) {
                    $stId        = (int)$st['id'];
                    $escNewClass = thamani_db_real_escape_string($conn, $newClass);

                    thamani_db_query($conn, "UPDATE students SET class_level = '{$escNewClass}' WHERE id = {$stId}");

                    $newFee = (str_contains($newClass, 'Senior 5') || str_contains($newClass, 'Senior 6')) ? 950000.0 : 850000.0;
                    if (function_exists('thamani_get_class_fee')) {
                        $newFee = thamani_get_class_fee($conn, $newClass);
                    }

                    $fRes = thamani_db_query($conn, "SELECT total_fee, paid_amount, balance FROM student_fees WHERE student_id = {$stId} LIMIT 1");
                    $oldBal = 0.0;
                    $fr = null;
                    if ($fRes && ($fr = thamani_db_fetch_assoc($fRes))) {
                        $oldBal = max(0.0, (float)($fr['balance'] ?? 0.0));
                    }

                    $totalFeeNew = $newFee + $oldBal;
                    $balNew      = $totalFeeNew;
                    $statusNew   = $balNew <= 0 ? 'PAID' : 'UNPAID';
                    $escTerm     = thamani_db_real_escape_string($conn, $targetTerm);

                    if ($fr) {
                        thamani_db_query($conn, "UPDATE student_fees SET class_level = '{$escNewClass}', term = '{$escTerm}', total_fee = {$totalFeeNew}, paid_amount = 0.0, balance = {$balNew}, status = '{$statusNew}', updated_at = CURRENT_TIMESTAMP WHERE student_id = {$stId}");
                    } else {
                        $escPc = thamani_db_real_escape_string($conn, $st['pay_code'] ?: '');
                        thamani_db_query($conn, "INSERT INTO student_fees (student_id, class_level, term, total_fee, paid_amount, balance, status, pay_code) VALUES ({$stId}, '{$escNewClass}', '{$escTerm}', {$totalFeeNew}, 0.0, {$balNew}, '{$statusNew}', '{$escPc}')");
                    }
                    $promotedCount++;
                }
            }
        }

        $_SESSION['admin_flash'] = [
            'type'    => 'success',
            'message' => "Annual Class Transition Executed Successfully for {$targetTerm}! Promoted {$promotedCount} continuing students and transitioned {$graduatedCount} Senior 6 candidates to Alumni.",
        ];
        break;

    default:
        if ($wantsJson) {
            echo json_encode(['success' => false, 'error' => 'Unknown fees action.']);
            exit;
        }
        $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Unknown fees action.'];
        break;
}

header("Location: {$redirectUrl}");
exit;
