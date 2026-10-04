<?php
/**
 * Thamani High School - Admin Add / Register New Teacher
 */

require_once 'auth_admin.php';
require_admin_login();
require_once 'conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_dashboard.php');
    exit;
}

$fullName       = trim($_POST['full_name'] ?? '');
$staffId        = trim($_POST['staff_id'] ?? $_POST['teacher_id'] ?? '');
$email          = trim($_POST['email'] ?? '');
$department     = trim($_POST['department'] ?? $_POST['subject'] ?? '');
$isClassTeacher      = !empty($_POST['is_class_teacher']) ? 1 : 0;
$classTeacherOf      = $isClassTeacher ? trim($_POST['class_teacher_of'] ?? '') : null;
$classTeacherStream  = $isClassTeacher ? trim($_POST['class_teacher_stream'] ?? 'Stream A') : 'Stream A';
$canViewEnrollments  = !empty($_POST['can_view_enrollments']) ? 1 : 0;
$canManageDutyRoster = !empty($_POST['can_manage_duty_roster']) ? 1 : 0;

// Handle array or comma-separated string for classes_taught
$classesTaughtRaw = $_POST['classes_taught'] ?? [];
$classesList = [];
if (is_array($classesTaughtRaw)) {
    $classesList = array_values(array_filter(array_map('trim', $classesTaughtRaw)));
} else {
    $classesList = array_values(array_filter(array_map('trim', explode(',', (string)$classesTaughtRaw))));
}
$classesTaught = implode(', ', $classesList);

// Handle array or comma-separated string for subjects_taught
$subjectsTaughtRaw = $_POST['subjects_taught'] ?? [];
$subjectsList = [];
if (is_array($subjectsTaughtRaw)) {
    $subjectsList = array_values(array_filter(array_map('trim', $subjectsTaughtRaw)));
} else {
    $subjectsList = array_values(array_filter(array_map('trim', explode(',', (string)$subjectsTaughtRaw))));
}
if ($department !== '' && !in_array($department, $subjectsList, true)) {
    $subjectsList[] = $department;
}

// Handle array or comma-separated string for streams_taught
$streamsTaughtRaw = $_POST['streams_taught'] ?? ['Stream A', 'Stream B'];
$streamsList = [];
if (is_array($streamsTaughtRaw)) {
    $streamsList = array_values(array_filter(array_map('trim', $streamsTaughtRaw)));
} else {
    $streamsList = array_values(array_filter(array_map('trim', explode(',', (string)$streamsTaughtRaw))));
}
if (empty($streamsList) || in_array('All Streams', $streamsList, true)) {
    $streamsList = ['Stream A', 'Stream B'];
}

$errors = [];

if ($fullName === '') {
    $errors[] = 'Full name is required.';
}
if ($staffId === '') {
    $errors[] = 'Staff ID is required.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email address is required.';
}

if (empty($errors)) {
    // Check duplicate staff_id or email
    $checkSql = "SELECT id FROM teachers WHERE staff_id = ? OR email = ? LIMIT 1";
    $stmt = thamani_db_prepare($conn, $checkSql);
    if ($stmt) {
        thamani_db_stmt_bind_param($stmt, "ss", $staffId, $email);
        thamani_db_stmt_execute($stmt);
        thamani_db_stmt_store_result($stmt);
        if (thamani_db_stmt_num_rows($stmt) > 0) {
            $errors[] = 'A teacher with this Staff ID or Email is already registered.';
        }
        thamani_db_stmt_close($stmt);
    }
}

if (!empty($errors)) {
    $_SESSION['admin_flash'] = ['type' => 'error', 'message' => implode(' ', $errors)];
    header('Location: admin_dashboard.php?tab=tab-admin-teachers#tab-admin-teachers');
    exit;
}

$defaultPassword = 'Admin@2026';
$hash = password_hash($defaultPassword, PASSWORD_BCRYPT);
$now  = date('Y-m-d H:i:s');

$insertSql = "INSERT INTO teachers (staff_id, full_name, email, department, is_class_teacher, class_teacher_of, class_teacher_stream, classes_taught, can_view_enrollments, can_manage_duty_roster, password_hash, must_change_password, is_active, created_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, ?)";

$insStmt = thamani_db_prepare($conn, $insertSql);

if ($insStmt) {
    thamani_db_stmt_bind_param($insStmt, "ssssisssiisss", $staffId, $fullName, $email, $department, $isClassTeacher, $classTeacherOf, $classTeacherStream, $classesTaught, $canViewEnrollments, $canManageDutyRoster, $hash, $now);
    if (thamani_db_stmt_execute($insStmt)) {
        thamani_db_stmt_close($insStmt);

        // Fetch inserted teacher ID
        $teacherId = 0;
        $stmtNew = thamani_db_prepare($conn, "SELECT id FROM teachers WHERE staff_id = ? LIMIT 1");
        if ($stmtNew) {
            thamani_db_stmt_bind_param($stmtNew, "s", $staffId);
            thamani_db_stmt_execute($stmtNew);
            $resNew = thamani_db_stmt_get_result($stmtNew);
            if ($resNew && $rNew = thamani_db_fetch_assoc($resNew)) {
                $teacherId = (int)$rNew['id'];
            }
            thamani_db_stmt_close($stmtNew);
        }

        // AUTOMATIC SUBJECT ALLOCATION PER CLASS & STREAM
        $autoCount = 0;
        if ($teacherId > 0 && !empty($subjectsList) && !empty($classesList)) {
            foreach ($subjectsList as $sub) {
                foreach ($classesList as $cls) {
                    foreach ($streamsList as $strm) {
                        $chkA = thamani_db_prepare($conn, "SELECT id FROM teacher_subject_assignments WHERE teacher_id = ? AND subject = ? AND class_level = ? AND stream = ? LIMIT 1");
                        if ($chkA) {
                            thamani_db_stmt_bind_param($chkA, "isss", $teacherId, $sub, $cls, $strm);
                            thamani_db_stmt_execute($chkA);
                            $resA = thamani_db_stmt_get_result($chkA);
                            $existsA = $resA ? thamani_db_fetch_assoc($resA) : null;
                            thamani_db_stmt_close($chkA);

                            if (!$existsA) {
                                $insA = thamani_db_prepare($conn, "INSERT INTO teacher_subject_assignments (teacher_id, subject, class_level, stream) VALUES (?, ?, ?, ?)");
                                if ($insA) {
                                    thamani_db_stmt_bind_param($insA, "isss", $teacherId, $sub, $cls, $strm);
                                    thamani_db_stmt_execute($insA);
                                    thamani_db_stmt_close($insA);
                                    $autoCount++;
                                }
                            }
                        }
                    }
                }
            }
        }

        $allocMsg = $autoCount > 0 ? " Automatically generated {$autoCount} Subject Teacher Allocations per Class & Stream!" : "";
        $_SESSION['admin_flash'] = [
            'type' => 'success',
            'message' => "Teacher \"{$fullName}\" (Staff ID: {$staffId}) registered successfully!{$allocMsg} Default password is set to: Admin@2026"
        ];
    } else {
        error_log('[Admin Add Teacher Execute] ' . thamani_db_stmt_error($insStmt));
        thamani_db_stmt_close($insStmt);
        $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Failed to save teacher to database.'];
    }
} else {
    $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Database prepare statement failed.'];
}

header('Location: admin_dashboard.php?tab=tab-admin-teachers#tab-admin-teachers');
exit;
