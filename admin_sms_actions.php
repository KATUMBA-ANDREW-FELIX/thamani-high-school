<?php
// admin_sms_actions.php — Thamani High School · Bulk SMS Action Handler
require_once 'conn.php';
require_once 'auth_admin.php';
require_once 'auth_teacher.php';
require_once 'sms_helper.php';

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

    $username  = trim($_POST['ego_username'] ?? '');
    $password  = trim($_POST['ego_password'] ?? '');
    if ($password === '') {
        $existingCredentials = get_egosms_credentials($db);
        $password = $existingCredentials['password'] ?? '';
    }
    $senderId  = preg_replace('/[^A-Za-z0-9\-]/', '', trim($_POST['ego_sender_id'] ?? 'THAMANI'));
    $senderId  = substr($senderId ?: 'THAMANI', 0, 11);

    $saved = true;
    // Driver-aware upsert: PostgreSQL uses ON CONFLICT, SQLite uses INSERT OR REPLACE
    $isPostgres = ($db instanceof ThamaniPolyfillConn && $db->driver === 'pgsql');

    foreach ([
        'ego_sms_username'  => $username,
        'ego_sms_password'  => $password,
        'ego_sms_sender_id' => $senderId,
    ] as $key => $val) {
        if ($isPostgres) {
            $sql = "INSERT INTO school_settings (setting_key, setting_value, updated_at)
                        VALUES (?, ?, NOW())
                        ON CONFLICT(setting_key)
                        DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = NOW()";
        } else {
            $sql = "INSERT OR REPLACE INTO school_settings (setting_key, setting_value, updated_at) VALUES (?, ?, datetime('now'))";
        }
        $stmt = thamani_db_prepare($db, $sql);
        if (!$stmt) { $saved = false; continue; }
        thamani_db_stmt_bind_param($stmt, 'ss', $key, $val);
        $saved = thamani_db_stmt_execute($stmt) && $saved;
        thamani_db_stmt_close($stmt);
    }
    echo json_encode(['success' => $saved, 'message' => $saved ? 'Ego SMS credentials saved.' : 'Unable to save SMS credentials.']);
    exit;
}

if ($action === 'get_sms_settings') {
    if (!$isAdmin) { echo json_encode(['success'=>false,'error'=>'Admin only']); exit; }
    $creds = get_egosms_credentials($db);
    echo json_encode([
        'success' => true,
        'username' => $creds['username'] ?? '',
        'sender_id' => $creds['sender_id'] ?: 'THAMANI',
        'password_configured' => !empty($creds['password']),
    ]);
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
    $res = egosms_send($testPhone, 'Test SMS from Thamani High School Portal. If you received this, your Ego SMS integration is working correctly.', $creds['username'], $creds['password'], $creds['sender_id'] ?: 'THAMANI');
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
        $res = thamani_db_query($db, "SELECT id, full_name, phone, category, class_level, stream, is_active FROM sms_contacts ORDER BY category ASC, full_name ASC");
    } else {
        $stmt = thamani_db_prepare($db, "SELECT id, full_name, phone, category, class_level, stream, is_active FROM sms_contacts WHERE category = ? ORDER BY full_name ASC");
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
    $totalSent = 0; $totalFailed = []; $lastRaw = '';
    $overallOk = true;

    foreach ($chunks as $chunk) {
        $res2 = egosms_send($chunk, $message, $creds['username'], $creds['password'], $creds['sender_id'] ?: 'THAMANI');
        $totalSent  += $res2['sent_count'];
        $totalFailed = array_merge($totalFailed, $res2['failed'] ?? []);
        $lastRaw     = $res2['raw'];
        if (!$res2['success']) {
            $overallOk = false;
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

    log_sms_batch($db, $actorId, $actorRole, $totalSent, $catLabel, $message, $logStatus, $lastRaw);

    echo json_encode([
        'success'       => $totalSent > 0,
        'sent_count'    => $totalSent,
        'total_targets' => count($phones),
        'failed_count'  => count($totalFailed),
        'status'        => $logStatus,
        'message'       => $totalSent > 0
            ? "✅ SMS sent to {$totalSent} of " . count($phones) . " contacts."
            : "❌ Failed to send SMS. Check your Ego SMS credentials and account balance.",
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
// Unknown action
// ─────────────────────────────────────────────────────────────────────────────
echo json_encode(['success' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);
exit;
