<?php
// admin_sms_actions.php — Thamani High School · Bulk SMS Action Handler
require_once 'conn.php';
require_once 'auth_admin.php';
require_once 'auth_teacher.php';
require_once 'sms_helper.php';
require_once 'bitlink_pay_helper.php';

// Allow admins or active teachers explicitly granted SMS permission.
$isAdmin   = false;
$isSmsTeacher = false;
$actorId   = 0;
$actorRole = '';

if (!empty($_SESSION['admin_id'])) {
    $isAdmin   = true;
    $actorId   = (int)$_SESSION['admin_id'];
    $actorRole = 'admin';
} elseif (!empty($_SESSION['teacher_id'])) {
    $tId = (int)($_SESSION['teacher_id'] ?? 0);
    // Check can_send_sms flag
    $chkStmt = thamani_db_prepare($GLOBALS['conn'], "SELECT can_send_sms FROM teachers WHERE id = ? AND is_active = 1 LIMIT 1");
    if ($chkStmt) {
        thamani_db_stmt_bind_param($chkStmt, 'i', $tId);
        thamani_db_stmt_execute($chkStmt);
        $chkRes = thamani_db_stmt_get_result($chkStmt);
        $chkRow = $chkRes ? thamani_db_fetch_assoc($chkRes) : null;
        thamani_db_stmt_close($chkStmt);
        if ($chkRow && !empty($chkRow['can_send_sms'])) {
            $isSmsTeacher = true;
            $actorId   = $tId;
            $actorRole = 'teacher';
        }
    }
}

if (!$isAdmin && !$isSmsTeacher) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$db     = $GLOBALS['conn'];
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['sms_csrf_token'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Your session expired. Reload the SMS page and try again.']);
        exit;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Save Ego SMS API Credentials (admin only)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'save_sms_credentials') {
    if (!$isAdmin) { echo json_encode(['success'=>false,'error'=>'Admin only']); exit; }

    $username        = trim($_POST['ego_username'] ?? '');
    $password        = trim($_POST['ego_password'] ?? '');
    $autoFeeReceipts = (!empty($_POST['ego_auto_fee_receipts']) && $_POST['ego_auto_fee_receipts'] !== '0') ? '1' : '0';
    if ($password === '') {
        $existingCredentials = get_egosms_credentials($db);
        $password = $existingCredentials['password'] ?? '';
    }

    $saved = true;
    foreach ([
        'ego_sms_username'          => $username,
        'ego_sms_password'          => $password,
        'ego_sms_auto_fee_receipts' => $autoFeeReceipts,
    ] as $key => $val) {
        $saved = thamani_set_setting($db, $key, $val) && $saved;
    }
    echo json_encode(['success' => $saved, 'message' => $saved ? 'Ego SMS credentials saved.' : 'Unable to save SMS credentials.']);
    exit;
}

if ($action === 'get_sms_settings') {
    if (!$isAdmin) { echo json_encode(['success'=>false,'error'=>'Admin only']); exit; }
    $creds = get_egosms_credentials($db);
    echo json_encode([
        'success'             => true,
        'username'            => $creds['username'] ?? '',
        'password_configured' => !empty($creds['password']),
        'auto_fee_receipts'   => !empty($creds['auto_fee_receipts']),
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Check Live Ego SMS Account Balance (admin only)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'check_sms_balance') {
    if (!$isAdmin) { echo json_encode(['success'=>false,'error'=>'Admin only']); exit; }
    $creds = get_egosms_credentials($db);
    if (empty($creds['username']) || empty($creds['password'])) {
        echo json_encode(['success'=>false,'error'=>'Ego SMS credentials not configured. Please save your API username and password first.']);
        exit;
    }
    $walletType = trim($_POST['wallet_type'] ?? $_GET['wallet_type'] ?? 'Local');
    $res = egosms_check_balance($creds['username'], $creds['password'], $walletType ?: 'Local');
    echo json_encode($res);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Test SMS Credentials (send test to own number)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'test_sms_credentials') {
    if (!$isAdmin) { echo json_encode(['success'=>false,'error'=>'Admin only']); exit; }
    $testPhone = normalize_ug_phone(trim($_POST['test_phone'] ?? ''));
    if (!is_valid_ug_phone($testPhone)) {
        echo json_encode(['success'=>false,'error'=>'Invalid test phone number.']); exit;
    }
    $creds = get_egosms_credentials($db);
    if (empty($creds['username']) || empty($creds['password'])) {
        echo json_encode(['success'=>false,'error'=>'Ego SMS credentials not configured. Please save credentials first.']); exit;
    }
    $res = egosms_send($testPhone, 'Test SMS from Thamani High School Portal. If you received this, your Ego SMS integration is working correctly.', $creds['username'], $creds['password']);
    echo json_encode($res);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Save / Upsert a contact
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'save_contact') {
    if (!$isAdmin) { echo json_encode(['success'=>false,'error'=>'Admin only']); exit; }

    $contactId  = (int)($_POST['contact_id'] ?? 0);
    $fullName   = trim($_POST['full_name']  ?? '');
    $phone      = normalize_ug_phone(trim($_POST['phone'] ?? ''));
    $category   = trim($_POST['category']   ?? 'Other');
    $classLevel = trim($_POST['class_level'] ?? '');
    $stream     = trim($_POST['stream']     ?? '');
    $notes      = trim($_POST['notes']      ?? '');

    $validCategories = ['Parents','Teachers','Staff','Alumni','Other'];
    if (!in_array($category, $validCategories, true)) $category = 'Other';
    if (empty($fullName) || !is_valid_ug_phone($phone)) {
        echo json_encode(['success'=>false,'error'=>'Name and valid phone number (256XXXXXXXXX) are required.']); exit;
    }

    if ($contactId > 0) {
        // Edit
        $stmt = thamani_db_prepare($db, "UPDATE sms_contacts SET full_name=?, phone=?, category=?, class_level=?, stream=?, notes=? WHERE id=?");
        if ($stmt) {
            thamani_db_stmt_bind_param($stmt, 'ssssssi', $fullName, $phone, $category, $classLevel, $stream, $notes, $contactId);
            $ok = thamani_db_stmt_execute($stmt);
            thamani_db_stmt_close($stmt);
            echo json_encode(['success'=>$ok]);
        } else { echo json_encode(['success'=>false,'error'=>'Unable to prepare contact update.']); }
    } else {
        // Insert
        $stmt = thamani_db_prepare($db, "INSERT INTO sms_contacts (full_name, phone, category, class_level, stream, notes, is_active, created_by, created_at) VALUES (?,?,?,?,?,?,1,?,NOW())");
        if ($stmt) {
            thamani_db_stmt_bind_param($stmt, 'ssssssi', $fullName, $phone, $category, $classLevel, $stream, $notes, $actorId);
            $ok = thamani_db_stmt_execute($stmt);
            thamani_db_stmt_close($stmt);
            echo json_encode(['success'=>$ok]);
        } else { echo json_encode(['success'=>false,'error'=>'Unable to prepare contact insert.']); }
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Delete a contact
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'delete_contact') {
    if (!$isAdmin) { echo json_encode(['success'=>false,'error'=>'Admin only']); exit; }
    $cid = (int)($_POST['contact_id'] ?? 0);
    if (!$cid) { echo json_encode(['success'=>false,'error'=>'Invalid contact ID']); exit; }
    $stmt = thamani_db_prepare($db, "DELETE FROM sms_contacts WHERE id = ?");
    if ($stmt) {
        thamani_db_stmt_bind_param($stmt, 'i', $cid);
        $ok = thamani_db_stmt_execute($stmt);
        thamani_db_stmt_close($stmt);
        echo json_encode(['success'=>$ok]);
    } else {
        echo json_encode(['success'=>false,'error'=>'Unable to prepare contact deletion.']);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Toggle contact active status
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'toggle_contact') {
    if (!$isAdmin) { echo json_encode(['success'=>false,'error'=>'Admin only']); exit; }
    $cid = (int)($_POST['contact_id'] ?? 0);
    $stmt = thamani_db_prepare($db, "UPDATE sms_contacts SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success'=>false,'error'=>'Unable to prepare contact status update.']);
        exit;
    }
    thamani_db_stmt_bind_param($stmt, 'i', $cid);
    $updated = thamani_db_stmt_execute($stmt);
    thamani_db_stmt_close($stmt);
    if (!$updated) {
        echo json_encode(['success'=>false,'error'=>'Unable to update contact status.']);
        exit;
    }

    // Return new state
    $s2 = thamani_db_prepare($db, "SELECT is_active FROM sms_contacts WHERE id = ?");
    if (!$s2) {
        echo json_encode(['success'=>false,'error'=>'Unable to verify contact status.']);
        exit;
    }
    thamani_db_stmt_bind_param($s2, 'i', $cid);
    thamani_db_stmt_execute($s2);
    $r = thamani_db_stmt_get_result($s2);
    $row = $r ? thamani_db_fetch_assoc($r) : null;
    thamani_db_stmt_close($s2);
    if (!$row) {
        echo json_encode(['success'=>false,'error'=>'Contact not found.']);
        exit;
    }
    echo json_encode(['success'=>true,'is_active'=>(int)$row['is_active']]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Auto-import contacts from students (parents) or teachers
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'import_contacts') {
    if (!$isAdmin) { echo json_encode(['success'=>false,'error'=>'Admin only']); exit; }
    $source = trim($_POST['source'] ?? 'parents');
    $imported = 0; $skipped = 0;

    if ($source === 'parents') {
        $res = thamani_db_query($db, "SELECT guardian_name, guardian_phone, class_level, stream FROM students WHERE status = 'Enrolled' AND guardian_phone IS NOT NULL AND guardian_phone <> ''");
        if ($res) {
            while ($row = thamani_db_fetch_assoc($res)) {
                $phone = normalize_ug_phone($row['guardian_phone'] ?? '');
                if (!is_valid_ug_phone($phone)) { $skipped++; continue; }
                $name  = trim($row['guardian_name'] ?? 'Parent/Guardian');
                $cls   = $row['class_level'] ?? '';
                $str   = $row['stream'] ?? '';
                $cat   = 'Parents';
                // Upsert by phone
                $exist = null;
                $chk = thamani_db_prepare($db, "SELECT id FROM sms_contacts WHERE phone = ? LIMIT 1");
                if ($chk) {
                    thamani_db_stmt_bind_param($chk, 's', $phone);
                    thamani_db_stmt_execute($chk);
                    $cr  = thamani_db_stmt_get_result($chk);
                    $exist = $cr ? thamani_db_fetch_assoc($cr) : null;
                    thamani_db_stmt_close($chk);
                }
                if (!empty($exist)) { $skipped++; continue; }
                $ins = thamani_db_prepare($db, "INSERT INTO sms_contacts (full_name, phone, category, class_level, stream, is_active, created_by, created_at) VALUES (?,?,?,?,?,1,?,NOW())");
                if ($ins) {
                    thamani_db_stmt_bind_param($ins, 'sssssi', $name, $phone, $cat, $cls, $str, $actorId);
                    thamani_db_stmt_execute($ins);
                    thamani_db_stmt_close($ins);
                    $imported++;
                }
            }
        }
    } elseif ($source === 'teachers') {
        $res = thamani_db_query($db, "SELECT full_name, phone, email FROM teachers WHERE is_active = 1");
        if ($res) {
            while ($row = thamani_db_fetch_assoc($res)) {
                $phone = normalize_ug_phone($row['phone'] ?? '');
                if (!is_valid_ug_phone($phone)) { $skipped++; continue; }
                $name  = trim($row['full_name'] ?? 'Teacher');
                $cat   = 'Teachers';
                $exist = null;
                $chk   = thamani_db_prepare($db, "SELECT id FROM sms_contacts WHERE phone = ? LIMIT 1");
                if ($chk) {
                    thamani_db_stmt_bind_param($chk, 's', $phone);
                    thamani_db_stmt_execute($chk);
                    $cr  = thamani_db_stmt_get_result($chk);
                    $exist = $cr ? thamani_db_fetch_assoc($cr) : null;
                    thamani_db_stmt_close($chk);
                }
                if (!empty($exist)) { $skipped++; continue; }
                $ins = thamani_db_prepare($db, "INSERT INTO sms_contacts (full_name, phone, category, is_active, created_by, created_at) VALUES (?,?,?,1,?,NOW())");
                if ($ins) {
                    thamani_db_stmt_bind_param($ins, 'sssi', $name, $phone, $cat, $actorId);
                    thamani_db_stmt_execute($ins);
                    thamani_db_stmt_close($ins);
                    $imported++;
                }
            }
        }
    }

    echo json_encode(['success'=>true,'imported'=>$imported,'skipped'=>$skipped]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Fetch contacts list (JSON, for JS table rendering)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'get_contacts') {
    $cat = trim($_GET['category'] ?? 'All');
    $res = null;
    if ($cat === 'All' || empty($cat)) {
        $res = thamani_db_query($db, "SELECT id, full_name, phone, category, class_level, stream, notes, is_active FROM sms_contacts ORDER BY category ASC, full_name ASC");
    } else {
        $stmt = thamani_db_prepare($db, "SELECT id, full_name, phone, category, class_level, stream, notes, is_active FROM sms_contacts WHERE category = ? ORDER BY full_name ASC");
        if ($stmt) {
            thamani_db_stmt_bind_param($stmt, 's', $cat);
            thamani_db_stmt_execute($stmt);
            $res = thamani_db_stmt_get_result($stmt);
            thamani_db_stmt_close($stmt);
        }
    }
    $contacts = [];
    if ($res) while ($r = thamani_db_fetch_assoc($res)) $contacts[] = $r;
    echo json_encode(['success'=>true,'contacts'=>$contacts,'count'=>count($contacts)]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Preview recipients before send
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'preview_recipients') {
    $cat        = trim($_POST['category'] ?? 'All');
    $individual = (int)($_POST['individual_id'] ?? 0);
    $res  = null;
    $rows = [];

    if ($individual > 0) {
        $stmt = thamani_db_prepare($db, "SELECT id, full_name, phone FROM sms_contacts WHERE id = ? AND is_active = 1 LIMIT 1");
        if ($stmt) {
            thamani_db_stmt_bind_param($stmt, 'i', $individual);
            thamani_db_stmt_execute($stmt);
            $res = thamani_db_stmt_get_result($stmt);
            thamani_db_stmt_close($stmt);
        }
    } elseif ($cat === 'All') {
        $res = thamani_db_query($db, "SELECT id, full_name, phone FROM sms_contacts WHERE is_active = 1 ORDER BY full_name ASC");
    } else {
        $stmt = thamani_db_prepare($db, "SELECT id, full_name, phone FROM sms_contacts WHERE category = ? AND is_active = 1 ORDER BY full_name ASC");
        if ($stmt) {
            thamani_db_stmt_bind_param($stmt, 's', $cat);
            thamani_db_stmt_execute($stmt);
            $res = thamani_db_stmt_get_result($stmt);
            thamani_db_stmt_close($stmt);
        }
    }

    if ($res) while ($r = thamani_db_fetch_assoc($res)) $rows[] = $r;
    echo json_encode(['success'=>true,'recipients'=>$rows,'total'=>count($rows)]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Send Bulk SMS
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'send_bulk_sms') {
    $message    = trim($_POST['message'] ?? '');
    $cat        = trim($_POST['category'] ?? 'All');
    $individual = (int)($_POST['individual_id'] ?? 0);

    if (empty($message)) { echo json_encode(['success'=>false,'error'=>'Message body is required.']); exit; }
    $messageLength = function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') : preg_match_all('/./us', $message);
    if ($messageLength === false || $messageLength > 1600) { echo json_encode(['success'=>false,'error'=>'Message too long (max 1600 characters).']); exit; }

    $creds = get_egosms_credentials($db);
    if (empty($creds['username']) || empty($creds['password'])) {
        echo json_encode(['success'=>false,'error'=>'Ego SMS credentials not configured. Go to SMS Settings first.']); exit;
    }

    // Fetch target phones
    $rows = [];
    if ($individual > 0) {
        $stmt = thamani_db_prepare($db, "SELECT phone, full_name FROM sms_contacts WHERE id = ? AND is_active = 1 LIMIT 1");
        if ($stmt) {
            thamani_db_stmt_bind_param($stmt, 'i', $individual);
            thamani_db_stmt_execute($stmt);
            $res = thamani_db_stmt_get_result($stmt);
            if ($res) while ($r = thamani_db_fetch_assoc($res)) $rows[] = $r;
            thamani_db_stmt_close($stmt);
        }
        $catLabel = 'Individual';
    } elseif ($cat === 'All') {
        $res = thamani_db_query($db, "SELECT phone, full_name FROM sms_contacts WHERE is_active = 1");
        if ($res) while ($r = thamani_db_fetch_assoc($res)) $rows[] = $r;
        $catLabel = 'All';
    } else {
        $stmt = thamani_db_prepare($db, "SELECT phone, full_name FROM sms_contacts WHERE category = ? AND is_active = 1");
        if ($stmt) {
            thamani_db_stmt_bind_param($stmt, 's', $cat);
            thamani_db_stmt_execute($stmt);
            $res = thamani_db_stmt_get_result($stmt);
            if ($res) while ($r = thamani_db_fetch_assoc($res)) $rows[] = $r;
            thamani_db_stmt_close($stmt);
        }
        $catLabel = $cat;
    }

    if (empty($rows)) { echo json_encode(['success'=>false,'error'=>'No active contacts found for selected recipients.']); exit; }

    $phones = array_column($rows, 'phone');

    // Ego SMS max 1000 per request — chunk if needed
    $chunks   = array_chunk($phones, 1000);
    $totalSent = 0; $totalFailed = []; $lastRaw = ''; $lastError = ''; $lastTracking = ''; $totalCost = 0;
    $overallOk = true;

    foreach ($chunks as $chunk) {
        $res2 = egosms_send($chunk, $message, $creds['username'], $creds['password']);
        $totalSent  += $res2['sent_count'];
        $totalFailed = array_merge($totalFailed, $res2['failed'] ?? []);
        $lastRaw     = $res2['raw'];
        $totalCost  += (int)($res2['cost'] ?? 0);
        if (!empty($res2['tracking'])) {
            $lastTracking = $res2['tracking'];
        }
        if (!$res2['success']) {
            $overallOk = false;
            if (!empty($res2['error'])) {
                $lastError = $res2['error'];
            }
        }
    }

    // Determine log status
    if ($overallOk && empty($totalFailed)) {
        $logStatus = 'SUCCESS';
    } elseif ($totalSent > 0) {
        $logStatus = 'PARTIAL';
    } else {
        $logStatus = 'FAILED';
    }

    log_sms_batch($db, $actorId, $actorRole, $totalSent, $catLabel, $message, $logStatus, $lastRaw ?: $lastError);
    if ($totalSent > 0) {
        bitlink_pay_adjust_wallet_balance($db, -$totalSent);
    }

    $failReason = $lastError !== '' ? $lastError : 'Check your Ego SMS credentials and account balance.';
    echo json_encode([
        'success'       => $totalSent > 0,
        'sent_count'    => $totalSent,
        'total_targets' => count($phones),
        'failed_count'  => count($totalFailed),
        'status'        => $logStatus,
        'tracking'      => $lastTracking,
        'cost'          => $totalCost,
        'error'         => $totalSent > 0 ? '' : $failReason,
        'message'       => $totalSent > 0
            ? "✅ SMS sent to {$totalSent} of " . count($phones) . " contacts." . ($totalCost > 0 ? " (Cost: {$totalCost} units)" : '')
            : "❌ Failed to send SMS: {$failReason}",
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Send School Fees Balance Reminder SMS (admin only)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'send_fee_reminder_sms') {
    if (!$isAdmin) { echo json_encode(['success'=>false,'error'=>'Admin only']); exit; }

    $studentId = (int)($_POST['student_id'] ?? 0);
    $creds = get_egosms_credentials($db);
    if (empty($creds['username']) || empty($creds['password'])) {
        echo json_encode(['success'=>false,'error'=>'Ego SMS credentials not configured. Please configure Ego SMS in the SMS Centre tab first.']);
        exit;
    }

    $targets = [];
    if ($studentId > 0) {
        $st = thamani_db_prepare($db, "SELECT s.id, s.full_name, s.class_level, s.lin_number, s.pay_code, s.guardian_name, s.guardian_phone, COALESCE(sf.total_fee, 0) AS total_fee, COALESCE(sf.paid_amount, 0) AS paid_amount, COALESCE(sf.balance, 0) AS balance FROM students s LEFT JOIN student_fees sf ON sf.student_id = s.id WHERE s.id = ? LIMIT 1");
        if ($st) {
            thamani_db_stmt_bind_param($st, 'i', $studentId);
            thamani_db_stmt_execute($st);
            $r = thamani_db_stmt_get_result($st);
            if ($r && $row = thamani_db_fetch_assoc($r)) $targets[] = $row;
            thamani_db_stmt_close($st);
        }
    } else {
        $r = thamani_db_query($db, "SELECT s.id, s.full_name, s.class_level, s.lin_number, s.pay_code, s.guardian_name, s.guardian_phone, COALESCE(sf.total_fee, 0) AS total_fee, COALESCE(sf.paid_amount, 0) AS paid_amount, COALESCE(sf.balance, 0) AS balance FROM students s LEFT JOIN student_fees sf ON sf.student_id = s.id WHERE s.status = 'Enrolled' ORDER BY s.full_name ASC");
        if ($r) {
            while ($row = thamani_db_fetch_assoc($r)) {
                $tot = (float)$row['total_fee'] > 0 ? (float)$row['total_fee'] : thamani_get_class_fee($db, (string)$row['class_level']);
                $bal = max(0.0, $tot - (float)$row['paid_amount']);
                if ($bal > 0) {
                    $row['total_fee'] = $tot;
                    $row['balance'] = $bal;
                    $targets[] = $row;
                }
            }
        }
    }

    if (empty($targets)) {
        echo json_encode(['success'=>false,'error'=>'No students with outstanding fee balances found.']);
        exit;
    }

    $sentCount = 0;
    $skippedCount = 0;
    $lastError = '';
    $lastRaw = '';
    foreach ($targets as $t) {
        $tot = (float)$t['total_fee'] > 0 ? (float)$t['total_fee'] : thamani_get_class_fee($db, (string)$t['class_level']);
        $paid = (float)$t['paid_amount'];
        $bal = max(0.0, $tot - $paid);
        if ($bal <= 0) { $skippedCount++; continue; }

        $phone = normalize_ug_phone((string)($t['guardian_phone'] ?? ''));
        if (!is_valid_ug_phone($phone)) { $skippedCount++; continue; }

        $payCode = $t['pay_code'] ?: ($t['lin_number'] ?: 'Student ID ' . $t['id']);
        $msg = sprintf(
            "Thamani High School Fees Reminder: Dear Parent/Guardian of %s (%s), Paid: UGX %s, Balance: UGX %s. Pay via SchoolPay/Bank using PayCode: %s. Thank you.",
            $t['full_name'],
            $t['class_level'],
            number_format($paid),
            number_format($bal),
            $payCode
        );
        $res2 = egosms_send($phone, $msg, $creds['username'], $creds['password']);
        $lastRaw = $res2['raw'];
        if ($res2['success']) {
            $sentCount++;
        } else {
            $lastError = $res2['error'];
        }
    }

    $logStatus = $sentCount > 0 ? ($lastError === '' ? 'SUCCESS' : 'PARTIAL') : 'FAILED';
    log_sms_batch($db, $actorId, $actorRole, $sentCount, $studentId > 0 ? 'Fee Reminder (Single)' : 'Fee Reminders (Unpaid)', 'Automated school fees balance reminder', $logStatus, $lastRaw ?: $lastError);
    if ($sentCount > 0) {
        bitlink_pay_adjust_wallet_balance($db, -$sentCount);
    }

    echo json_encode([
        'success' => $sentCount > 0,
        'sent_count' => $sentCount,
        'skipped_count' => $skippedCount,
        'error' => $sentCount > 0 ? '' : ($lastError ?: 'No valid guardian phone numbers found or Ego SMS rejected the request.'),
        'message' => $sentCount > 0
            ? "✅ Sent fee reminder SMS to {$sentCount} guardian(s)" . ($skippedCount > 0 ? " ({$skippedCount} skipped without valid phone/balance)." : '.')
            : "❌ Could not send fee reminder SMS: " . ($lastError ?: 'No valid guardian phones found.'),
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Fetch SMS logs
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'get_sms_logs') {
    if (!$isAdmin) { echo json_encode(['success'=>false,'error'=>'Admin only']); exit; }
    $limit = min((int)($_GET['limit'] ?? 50), 200);
    $limit = max(1, $limit);
    $res = thamani_db_query($db, "SELECT id, batch_id, sender_role, recipient_count, category_filter, SUBSTR(message, 1, 120) as message_preview, status, sent_at FROM sms_logs ORDER BY sent_at DESC LIMIT {$limit}");
    $logs = [];
    if ($res) while ($r = thamani_db_fetch_assoc($res)) $logs[] = $r;
    echo json_encode(['success'=>true,'logs'=>$logs]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Fetch Portal Credential Targets (Parents/Students, Teachers, Alumni)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'get_credential_targets') {
    if (!$isAdmin) { echo json_encode(['success'=>false,'error'=>'Admin only']); exit; }

    // Map teacher phones from sms_contacts as fallback when teachers.phone is blank
    $contactTeacherPhones = [];
    $ctRes = thamani_db_query($db, "SELECT full_name, phone FROM sms_contacts WHERE category = 'Teachers' AND is_active = 1");
    if ($ctRes) {
        while ($cr = thamani_db_fetch_assoc($ctRes)) {
            $key = strtolower(trim((string)($cr['full_name'] ?? '')));
            if ($key !== '' && !empty($cr['phone'])) {
                $contactTeacherPhones[$key] = $cr['phone'];
            }
        }
    }

    $students = [];
    $sRes = thamani_db_query($db, "SELECT id, full_name, class_level, stream, lin_number, pay_code, guardian_name, guardian_phone FROM students WHERE status = 'Enrolled' ORDER BY class_level ASC, full_name ASC");
    if ($sRes) {
        while ($sr = thamani_db_fetch_assoc($sRes)) {
            $norm = normalize_ug_phone((string)($sr['guardian_phone'] ?? ''));
            $sr['normalized_phone'] = is_valid_ug_phone($norm) ? $norm : '';
            $students[] = $sr;
        }
    }

    $teachers = [];
    $tRes = thamani_db_query($db, "SELECT id, staff_id, full_name, email, phone, department FROM teachers WHERE is_active = 1 ORDER BY full_name ASC");
    if ($tRes) {
        while ($tr = thamani_db_fetch_assoc($tRes)) {
            $rawP = trim((string)($tr['phone'] ?? ''));
            if ($rawP === '') {
                $lookupKey = strtolower(trim((string)($tr['full_name'] ?? '')));
                $rawP = $contactTeacherPhones[$lookupKey] ?? '';
            }
            $norm = normalize_ug_phone($rawP);
            $tr['phone'] = $rawP;
            $tr['normalized_phone'] = is_valid_ug_phone($norm) ? $norm : '';
            $teachers[] = $tr;
        }
    }

    $alumni = [];
    $aRes = thamani_db_query($db, "SELECT id, name, year, email, phone, is_page_editor, editor_active FROM alumni ORDER BY id DESC");
    if ($aRes) {
        while ($ar = thamani_db_fetch_assoc($aRes)) {
            $norm = normalize_ug_phone((string)($ar['phone'] ?? ''));
            $ar['normalized_phone'] = is_valid_ug_phone($norm) ? $norm : '';
            $alumni[] = $ar;
        }
    }

    echo json_encode([
        'success'  => true,
        'students' => $students,
        'teachers' => $teachers,
        'alumni'   => $alumni,
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Send Portal Login Credentials via Bulk / Individual SMS (Admin only)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'send_portal_credentials') {
    if (!$isAdmin) { echo json_encode(['success'=>false,'error'=>'Admin only']); exit; }

    $creds = get_egosms_credentials($db);
    if (empty($creds['username']) || empty($creds['password'])) {
        echo json_encode(['success'=>false,'error'=>'Ego SMS credentials are not configured in .env (EGO_SMS_USERNAME / EGO_SMS_PASSWORD).']);
        exit;
    }

    $targetGroup   = trim((string)($_POST['target_group'] ?? 'parents')); // parents | teachers | other
    $tempPassword  = trim((string)($_POST['temp_password'] ?? 'Admin@2026'));
    if ($tempPassword === '') $tempPassword = 'Admin@2026';
    $syncPassword  = (($_POST['sync_password'] ?? '1') === '1');

    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
    $scheme = $https ? 'https' : 'http';
    $host   = trim((string)($_SERVER['HTTP_HOST'] ?? 'thamaniacademy.work.gd'));
    $baseDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $portalUrl = "{$scheme}://{$host}{$baseDir}/login.php";

    $batchItems  = [];
    $skipped     = 0;
    $updatedPwds = 0;
    $catLabel    = 'Credentials';

    // 1. PARENTS / STUDENTS PORTAL CREDENTIALS
    if ($targetGroup === 'parents') {
        $studentId  = (int)($_POST['student_id'] ?? 0);
        $classLevel = trim((string)($_POST['class_level'] ?? 'ALL'));
        $rows = [];

        if ($studentId > 0) {
            $st = thamani_db_prepare($db, "SELECT id, full_name, class_level, lin_number, pay_code, guardian_name, guardian_phone, password_hash FROM students WHERE id = ? LIMIT 1");
            if ($st) {
                thamani_db_stmt_bind_param($st, 'i', $studentId);
                thamani_db_stmt_execute($st);
                $r = thamani_db_stmt_get_result($st);
                if ($r && $row = thamani_db_fetch_assoc($r)) $rows[] = $row;
                thamani_db_stmt_close($st);
            }
            $catLabel = 'Credentials (Parent)';
        } elseif ($classLevel !== '' && $classLevel !== 'ALL') {
            $st = thamani_db_prepare($db, "SELECT id, full_name, class_level, lin_number, pay_code, guardian_name, guardian_phone, password_hash FROM students WHERE status = 'Enrolled' AND class_level = ? ORDER BY full_name ASC");
            if ($st) {
                thamani_db_stmt_bind_param($st, 's', $classLevel);
                thamani_db_stmt_execute($st);
                $r = thamani_db_stmt_get_result($st);
                if ($r) while ($row = thamani_db_fetch_assoc($r)) $rows[] = $row;
                thamani_db_stmt_close($st);
            }
            $catLabel = "Credentials ({$classLevel} Parents)";
        } else {
            $r = thamani_db_query($db, "SELECT id, full_name, class_level, lin_number, pay_code, guardian_name, guardian_phone, password_hash FROM students WHERE status = 'Enrolled' ORDER BY class_level ASC, full_name ASC");
            if ($r) while ($row = thamani_db_fetch_assoc($r)) $rows[] = $row;
            $catLabel = 'Credentials (All Parents)';
        }

        if (empty($rows)) {
            echo json_encode(['success'=>false,'error'=>'No matching enrolled students found.']);
            exit;
        }

        $newHash = password_hash($tempPassword, PASSWORD_DEFAULT);
        foreach ($rows as $row) {
            $phone = normalize_ug_phone((string)($row['guardian_phone'] ?? ''));
            $lin   = trim((string)($row['lin_number'] ?? ''));
            if (!is_valid_ug_phone($phone) || $lin === '') {
                $skipped++;
                continue;
            }

            // Initialize or reset student portal password so credentials in SMS are guaranteed valid
            if ($syncPassword || empty($row['password_hash'])) {
                $uStmt = thamani_db_prepare($db, "UPDATE students SET password_hash = ?, must_change_password = 1, account_active = 1 WHERE id = ?");
                if ($uStmt) {
                    $sid = (int)$row['id'];
                    thamani_db_stmt_bind_param($uStmt, 'si', $newHash, $sid);
                    if (thamani_db_stmt_execute($uStmt)) $updatedPwds++;
                    thamani_db_stmt_close($uStmt);
                }
            }

            $gName = trim((string)($row['guardian_name'] ?: 'Parent/Guardian'));
            $sName = trim((string)$row['full_name']);
            $cLvl  = trim((string)$row['class_level']);
            $pCode = trim((string)($row['pay_code'] ?? ''));
            $pcPart = $pCode !== '' ? " Fees PayCode: {$pCode}." : '';

            $msg = "Thamani High School Portal: Dear {$gName}, access {$sName}'s ({$cLvl}) portal at {$portalUrl} . Login ID (LIN): {$lin} , Password: {$tempPassword} .{$pcPart} Change password on first login.";
            $batchItems[] = ['phone' => $phone, 'message' => $msg];
        }
    }
    // 2. TEACHERS PORTAL CREDENTIALS
    elseif ($targetGroup === 'teachers') {
        $teacherId = (int)($_POST['teacher_id'] ?? 0);

        // Fallback phones from sms_contacts
        $contactTeacherPhones = [];
        $ctRes = thamani_db_query($db, "SELECT full_name, phone FROM sms_contacts WHERE category = 'Teachers' AND is_active = 1");
        if ($ctRes) {
            while ($cr = thamani_db_fetch_assoc($ctRes)) {
                $k = strtolower(trim((string)($cr['full_name'] ?? '')));
                if ($k !== '' && !empty($cr['phone'])) $contactTeacherPhones[$k] = $cr['phone'];
            }
        }

        $rows = [];
        if ($teacherId > 0) {
            $st = thamani_db_prepare($db, "SELECT id, staff_id, full_name, email, phone FROM teachers WHERE id = ? AND is_active = 1 LIMIT 1");
            if ($st) {
                thamani_db_stmt_bind_param($st, 'i', $teacherId);
                thamani_db_stmt_execute($st);
                $r = thamani_db_stmt_get_result($st);
                if ($r && $row = thamani_db_fetch_assoc($r)) $rows[] = $row;
                thamani_db_stmt_close($st);
            }
            $catLabel = 'Credentials (Teacher)';
        } else {
            $r = thamani_db_query($db, "SELECT id, staff_id, full_name, email, phone FROM teachers WHERE is_active = 1 ORDER BY full_name ASC");
            if ($r) while ($row = thamani_db_fetch_assoc($r)) $rows[] = $row;
            $catLabel = 'Credentials (All Teachers)';
        }

        if (empty($rows)) {
            echo json_encode(['success'=>false,'error'=>'No active teachers found.']);
            exit;
        }

        $newHash = password_hash($tempPassword, PASSWORD_DEFAULT);
        foreach ($rows as $row) {
            $rawPhone = trim((string)($row['phone'] ?? ''));
            if ($rawPhone === '') {
                $k = strtolower(trim((string)($row['full_name'] ?? '')));
                $rawPhone = $contactTeacherPhones[$k] ?? '';
            }
            $phone = normalize_ug_phone($rawPhone);
            if (!is_valid_ug_phone($phone)) {
                $skipped++;
                continue;
            }

            if ($syncPassword) {
                $uStmt = thamani_db_prepare($db, "UPDATE teachers SET password_hash = ?, must_change_password = 1 WHERE id = ?");
                if ($uStmt) {
                    $tid = (int)$row['id'];
                    thamani_db_stmt_bind_param($uStmt, 'si', $newHash, $tid);
                    if (thamani_db_stmt_execute($uStmt)) $updatedPwds++;
                    thamani_db_stmt_close($uStmt);
                }
            }

            $tName   = trim((string)$row['full_name']);
            $staffId = trim((string)$row['staff_id']);
            $tEmail  = trim((string)$row['email']);
            $msg = "Thamani High School Staff Portal: Dear {$tName}, sign in at {$portalUrl} . Staff ID: {$staffId} (or Email: {$tEmail}), Password: {$tempPassword} . Please change your password on first login.";
            $batchItems[] = ['phone' => $phone, 'message' => $msg];
        }
    }
    // 3. OTHER PERSON / ALUMNI EDITOR / CUSTOM RECIPIENT
    elseif ($targetGroup === 'other') {
        $recipientName = trim((string)($_POST['recipient_name'] ?? 'User'));
        $rawPhone      = trim((string)($_POST['recipient_phone'] ?? ''));
        $loginId       = trim((string)($_POST['login_identifier'] ?? ''));
        $roleLabel     = trim((string)($_POST['role_label'] ?? 'Portal Access'));
        $alumnusId     = (int)($_POST['alumnus_id'] ?? 0);
        $makeAlumniEditor = (($_POST['grant_alumni_editor'] ?? '0') === '1');

        $phone = normalize_ug_phone($rawPhone);
        if (!is_valid_ug_phone($phone)) {
            echo json_encode(['success'=>false,'error'=>'Please provide a valid Ugandan mobile number (e.g. 0772 123 456).']);
            exit;
        }
        if ($loginId === '') {
            echo json_encode(['success'=>false,'error'=>'Please enter the Login ID or Email for this person.']);
            exit;
        }

        // If granting Alumni Page Editor access, update the alumni record password hash & editor flags
        if ($makeAlumniEditor) {
            $newHash = password_hash($tempPassword, PASSWORD_DEFAULT);
            if ($alumnusId > 0) {
                $uAl = thamani_db_prepare($db, "UPDATE alumni SET email = COALESCE(NULLIF(?, ''), email), phone = COALESCE(NULLIF(?, ''), phone), editor_password_hash = ?, is_page_editor = 1, editor_active = 1 WHERE id = ?");
                if ($uAl) {
                    thamani_db_stmt_bind_param($uAl, 'sssi', $loginId, $phone, $newHash, $alumnusId);
                    if (thamani_db_stmt_execute($uAl)) $updatedPwds++;
                    thamani_db_stmt_close($uAl);
                }
            } else {
                $uAl = thamani_db_prepare($db, "UPDATE alumni SET editor_password_hash = ?, is_page_editor = 1, editor_active = 1 WHERE LOWER(email) = LOWER(?)");
                if ($uAl) {
                    thamani_db_stmt_bind_param($uAl, 'ss', $newHash, $loginId);
                    if (thamani_db_stmt_execute($uAl)) $updatedPwds++;
                    thamani_db_stmt_close($uAl);
                }
            }
        }

        $nameDisp = $recipientName !== '' ? $recipientName : 'User';
        $msg = "Thamani High School ({$roleLabel}): Dear {$nameDisp}, sign in at {$portalUrl} . Login ID: {$loginId} , Password: {$tempPassword} .";
        $batchItems[] = ['phone' => $phone, 'message' => $msg];
        $catLabel = "Credentials ({$roleLabel})";
    } else {
        echo json_encode(['success'=>false,'error'=>'Invalid target group.']);
        exit;
    }

    if (empty($batchItems)) {
        echo json_encode([
            'success' => false,
            'error'   => "No recipients with valid Ugandan phone numbers were found ({$skipped} skipped without a valid phone number). For teachers, add their phone in Edit Teacher or import them into the Contact Book.",
        ]);
        exit;
    }

    $sendRes = egosms_send_personalized($batchItems, $creds['username'], $creds['password']);
    $logStatus = $sendRes['success']
        ? (empty($sendRes['failed']) ? 'SUCCESS' : 'PARTIAL')
        : 'FAILED';

    log_sms_batch(
        $db,
        $actorId,
        $actorRole,
        (int)$sendRes['sent_count'],
        $catLabel,
        "Dispatched portal login credentials to {$sendRes['sent_count']} recipient(s)",
        $logStatus,
        $sendRes['raw'] ?: $sendRes['error']
    );
    if (!empty($sendRes['sent_count'])) {
        bitlink_pay_adjust_wallet_balance($db, -(int)$sendRes['sent_count']);
    }

    echo json_encode([
        'success'          => $sendRes['success'],
        'sent_count'       => $sendRes['sent_count'],
        'skipped_count'    => $skipped,
        'passwords_synced' => $updatedPwds,
        'error'            => $sendRes['error'],
        'message'          => $sendRes['success']
            ? "✅ Sent portal login credentials to {$sendRes['sent_count']} recipient(s)"
                . ($updatedPwds > 0 ? " ({$updatedPwds} portal password(s) synchronized)" : '')
                . ($skipped > 0 ? ", {$skipped} skipped (missing valid phone)" : '') . '.'
            : "❌ Failed to send credentials SMS: " . ($sendRes['error'] ?: 'Check Ego SMS credentials and balance.'),
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Get SMS Credit Wallet & bitlink pay Top-Up Config / History
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'get_sms_wallet') {
    $balance = bitlink_pay_get_wallet_balance($db);
    $cfg     = bitlink_pay_config();
    $orders  = [];
    $res = thamani_db_query($db, "SELECT id, order_number, sms_units, subtotal_ugx, collection_fee_ugx, clearing_fee_ugx, total_ugx, payer_phone, receipt_email, provider_reference, status, created_at FROM sms_topup_orders ORDER BY id DESC LIMIT 20");
    if ($res) {
        while ($r = thamani_db_fetch_assoc($res)) {
            $orders[] = $r;
        }
    }
    echo json_encode([
        'success'        => true,
        'wallet_balance' => $balance,
        'config'         => $cfg,
        'orders'         => $orders,
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Initiate "Buy More SMS" Top-Up via bitlink pay (Yo! Uganda Mobile Money)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'initiate_sms_topup') {
    $smsUnits     = (int)($_POST['sms_units'] ?? 0);
    $payerPhone   = trim((string)($_POST['payer_phone'] ?? ''));
    $payerName    = trim((string)($_POST['payer_name'] ?? 'Thamani High School')) ?: 'Thamani High School';
    $cfg          = bitlink_pay_config();
    $receiptEmail = trim((string)($_POST['receipt_email'] ?? $cfg['thamani_receipt_email'])) ?: $cfg['thamani_receipt_email'];

    if ($smsUnits < 20 || $smsUnits > 500000) {
        echo json_encode(['success' => false, 'error' => 'Please enter between 20 and 500,000 SMS credits to purchase.']);
        exit;
    }

    $normPhone = normalize_ug_phone($payerPhone);
    if (!is_valid_ug_phone($normPhone)) {
        echo json_encode(['success' => false, 'error' => 'Enter a valid Ugandan Mobile Money phone number (e.g. 0772 123 456 or 0701 123 456).']);
        exit;
    }

    if ($receiptEmail !== '' && !filter_var($receiptEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Enter a valid email address for the bitlink pay receipt.']);
        exit;
    }

    $fees        = bitlink_pay_calculate_fees($smsUnits);
    $orderNumber = 'THM-SMS-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
    $externalRef = $orderNumber . '-' . strtoupper(bin2hex(random_bytes(3)));

    $ins = thamani_db_prepare(
        $db,
        "INSERT INTO sms_topup_orders
            (order_number, sms_units, unit_price_ugx, subtotal_ugx, collection_fee_ugx, clearing_fee_ugx, total_ugx,
             payer_name, payer_phone, receipt_email, external_reference, status, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
    );
    if (!$ins) {
        echo json_encode(['success' => false, 'error' => 'Unable to create SMS top-up order.']);
        exit;
    }
    thamani_db_stmt_bind_param(
        $ins,
        'siiiiiissssi',
        $orderNumber,
        $fees['sms_units'],
        $fees['unit_price_ugx'],
        $fees['subtotal_ugx'],
        $fees['collection_fee_ugx'],
        $fees['clearing_fee_ugx'],
        $fees['total_ugx'],
        $payerName,
        $normPhone,
        $receiptEmail,
        $externalRef,
        $actorId
    );
    $created = thamani_db_stmt_execute($ins);
    thamani_db_stmt_close($ins);

    if (!$created) {
        echo json_encode(['success' => false, 'error' => 'Database error saving SMS top-up order.']);
        exit;
    }

    // Lookup inserted order ID
    $orderId = (int)thamani_db_insert_id($db);
    if ($orderId <= 0) {
        $lk = thamani_db_prepare($db, "SELECT id FROM sms_topup_orders WHERE order_number = ? LIMIT 1");
        if ($lk) {
            thamani_db_stmt_bind_param($lk, 's', $orderNumber);
            thamani_db_stmt_execute($lk);
            $lr = thamani_db_stmt_get_result($lk);
            if ($lr && $lrow = thamani_db_fetch_assoc($lr)) $orderId = (int)$lrow['id'];
            thamani_db_stmt_close($lk);
        }
    }

    $narrative = "bitlink pay · Thamani SMS Top-Up {$orderNumber} ({$fees['sms_units']} SMS)";
    $collect   = bitlink_pay_collect_funds($normPhone, (int)$fees['total_ugx'], $narrative, $externalRef);

    if (!$collect['ok']) {
        $errReason = (string)($collect['error'] ?? 'Mobile Money prompt failed.');
        $rawStr    = json_encode($collect['raw'] ?? ['error' => $errReason], JSON_UNESCAPED_UNICODE);
        $uFail = thamani_db_prepare($db, "UPDATE sms_topup_orders SET status = 'FAILED', provider_status = 'FAILED', failure_reason = ?, raw_response = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        if ($uFail) {
            thamani_db_stmt_bind_param($uFail, 'ssi', $errReason, $rawStr, $orderId);
            thamani_db_stmt_execute($uFail);
            thamani_db_stmt_close($uFail);
        }
        echo json_encode(['success' => false, 'error' => $errReason, 'order_number' => $orderNumber]);
        exit;
    }

    $provRef  = (string)($collect['transaction_reference'] ?? '');
    $provStat = strtoupper((string)($collect['transaction_status'] ?? 'PENDING'));
    $rawStr   = json_encode($collect['raw'] ?? [], JSON_UNESCAPED_UNICODE);

    $uPend = thamani_db_prepare($db, "UPDATE sms_topup_orders SET provider_reference = ?, provider_status = ?, raw_response = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    if ($uPend) {
        thamani_db_stmt_bind_param($uPend, 'sssi', $provRef, $provStat, $rawStr, $orderId);
        thamani_db_stmt_execute($uPend);
        thamani_db_stmt_close($uPend);
    }

    if (in_array($provStat, ['SUCCEEDED', 'SUCCESS'], true)) {
        bitlink_pay_finalize_order($db, $orderId, $collect);
        $newBal = bitlink_pay_get_wallet_balance($db);
        echo json_encode([
            'success'        => true,
            'order_number'   => $orderNumber,
            'payment_status' => 'PAID',
            'wallet_balance' => $newBal,
            'message'        => "✅ Payment confirmed via bitlink pay! Added " . number_format($fees['sms_units']) . " SMS credits. Receipt emailed to {$cfg['thamani_receipt_email']}.",
        ]);
        exit;
    }

    echo json_encode([
        'success'        => true,
        'order_number'   => $orderNumber,
        'payment_status' => 'PENDING',
        'wallet_balance' => bitlink_pay_get_wallet_balance($db),
        'message'        => "📲 Approve the bitlink pay Mobile Money prompt of UGX " . number_format($fees['total_ugx']) . " on {$normPhone}...",
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Poll Pending bitlink pay SMS Top-Up Order
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'poll_sms_topup') {
    $orderNumber = trim((string)($_POST['order_number'] ?? $_GET['order_number'] ?? ''));
    if ($orderNumber === '') {
        echo json_encode(['success' => false, 'error' => 'Order number is required.']);
        exit;
    }

    $stmt = thamani_db_prepare($db, "SELECT * FROM sms_topup_orders WHERE order_number = ? LIMIT 1");
    $order = null;
    if ($stmt) {
        thamani_db_stmt_bind_param($stmt, 's', $orderNumber);
        thamani_db_stmt_execute($stmt);
        $res = thamani_db_stmt_get_result($stmt);
        $order = $res ? thamani_db_fetch_assoc($res) : null;
        thamani_db_stmt_close($stmt);
    }
    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Top-up order not found.']);
        exit;
    }

    $curStatus = strtoupper((string)$order['status']);
    $cfg       = bitlink_pay_config();

    if ($curStatus === 'PENDING') {
        $poll = bitlink_pay_check_status((string)$order['provider_reference'], (string)$order['external_reference']);
        if (!empty($poll['ok'])) {
            $txStat = strtoupper((string)($poll['transaction_status'] ?? 'PENDING'));
            if (in_array($txStat, ['SUCCEEDED', 'SUCCESS'], true)) {
                $order = bitlink_pay_finalize_order($db, (int)$order['id'], $poll) ?: $order;
                $curStatus = 'PAID';
            } elseif (in_array($txStat, ['FAILED', 'FAILURE', 'CANCELLED', 'CANCELED'], true)) {
                $reason = (string)($poll['status_message'] ?: 'The Mobile Money payment failed or was declined.');
                $rawStr = json_encode($poll['raw'] ?? [], JSON_UNESCAPED_UNICODE);
                $oid    = (int)$order['id'];
                $uFail  = thamani_db_prepare($db, "UPDATE sms_topup_orders SET status = 'FAILED', provider_status = ?, failure_reason = ?, raw_response = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                if ($uFail) {
                    thamani_db_stmt_bind_param($uFail, 'sssi', $txStat, $reason, $rawStr, $oid);
                    thamani_db_stmt_execute($uFail);
                    thamani_db_stmt_close($uFail);
                }
                $curStatus = 'FAILED';
                $order['failure_reason'] = $reason;
            }
        }
    }

    $bal = bitlink_pay_get_wallet_balance($db);
    echo json_encode([
        'success'        => true,
        'order_number'   => $orderNumber,
        'payment_status' => $curStatus,
        'wallet_balance' => $bal,
        'failure_reason' => $order['failure_reason'] ?? '',
        'message'        => $curStatus === 'PAID'
            ? "✅ Payment confirmed via bitlink pay! Added " . number_format((int)$order['sms_units']) . " SMS credits. Receipt emailed to {$cfg['thamani_receipt_email']}."
            : ($curStatus === 'FAILED'
                ? "❌ Payment failed: " . ($order['failure_reason'] ?: 'Transaction declined.')
                : "⏳ Waiting for Mobile Money PIN approval on {$order['payer_phone']}..."),
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Unknown action
// ─────────────────────────────────────────────────────────────────────────────
echo json_encode(['success' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);
exit;
