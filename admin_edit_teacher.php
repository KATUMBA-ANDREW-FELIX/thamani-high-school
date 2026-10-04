<?php
/**
 * Thamani High School - Admin Edit Registered Teacher Details & Elevated Roles
 */

require_once 'auth_admin.php';
require_admin_login();
require_once 'conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_dashboard.php');
    exit;
}

$id             = (int)($_POST['id'] ?? 0);
$fullName       = trim($_POST['full_name'] ?? '');
$staffId        = trim($_POST['staff_id'] ?? '');
$email          = trim($_POST['email'] ?? '');
$department     = trim($_POST['department'] ?? '');
$isClassTeacher      = !empty($_POST['is_class_teacher']) ? 1 : 0;
$classTeacherOf      = $isClassTeacher ? trim($_POST['class_teacher_of'] ?? '') : null;
$classTeacherStream  = $isClassTeacher ? trim($_POST['class_teacher_stream'] ?? 'Stream A') : 'Stream A';
$canViewEnrollments  = !empty($_POST['can_view_enrollments']) ? 1 : 0;
$canManageDutyRoster = !empty($_POST['can_manage_duty_roster']) ? 1 : 0;
$isActive            = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
$resetPassword       = !empty($_POST['reset_password']);

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

if ($id <= 0) {
    $errors[] = 'Invalid teacher ID.';
}
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
    // Check duplicate staff_id or email for ANOTHER teacher
    $checkSql = "SELECT id FROM teachers WHERE (staff_id = ? OR email = ?) AND id != ? LIMIT 1";
    $stmt = thamani_db_prepare($conn, $checkSql);
    if ($stmt) {
        thamani_db_stmt_bind_param($stmt, "ssi", $staffId, $email, $id);
        thamani_db_stmt_execute($stmt);
        thamani_db_stmt_store_result($stmt);
        if (thamani_db_stmt_num_rows($stmt) > 0) {
            $errors[] = 'Another teacher with this Staff ID or Email already exists.';
        }
        thamani_db_stmt_close($stmt);
    }
}

if (!empty($errors)) {
    $_SESSION['admin_flash'] = ['type' => 'error', 'message' => implode(' ', $errors)];
    header('Location: admin_dashboard.php?tab=tab-admin-teachers#tab-admin-teachers');
    exit;
}

if ($resetPassword) {
    $defaultPassword = 'Admin@2026';
    $hash = password_hash($defaultPassword, PASSWORD_BCRYPT);
    $updateSql = "UPDATE teachers 
                  SET staff_id = ?, full_name = ?, email = ?, department = ?, is_class_teacher = ?, class_teacher_of = ?, class_teacher_stream = ?, classes_taught = ?, can_view_enrollments = ?, can_manage_duty_roster = ?, is_active = ?, password_hash = ?, must_change_password = 1
                  WHERE id = ?";
    $upStmt = thamani_db_prepare($conn, $updateSql);
    if ($upStmt) {
        thamani_db_stmt_bind_param($upStmt, "ssssisssiiisi", $staffId, $fullName, $email, $department, $isClassTeacher, $classTeacherOf, $classTeacherStream, $classesTaught, $canViewEnrollments, $canManageDutyRoster, $isActive, $hash, $id);
        $res = thamani_db_stmt_execute($upStmt);
        thamani_db_stmt_close($upStmt);
    }
} else {
    $updateSql = "UPDATE teachers 
                  SET staff_id = ?, full_name = ?, email = ?, department = ?, is_class_teacher = ?, class_teacher_of = ?, class_teacher_stream = ?, classes_taught = ?, can_view_enrollments = ?, can_manage_duty_roster = ?, is_active = ?
                  WHERE id = ?";
    $upStmt = thamani_db_prepare($conn, $updateSql);
    if ($upStmt) {
        thamani_db_stmt_bind_param($upStmt, "ssssisssiiii", $staffId, $fullName, $email, $department, $isClassTeacher, $classTeacherOf, $classTeacherStream, $classesTaught, $canViewEnrollments, $canManageDutyRoster, $isActive, $id);
        $res = thamani_db_stmt_execute($upStmt);
        thamani_db_stmt_close($upStmt);
    }
}

// AUTOMATIC SUBJECT ALLOCATION SYNC ON EDIT
$autoCount = 0;
if ($id > 0 && !empty($subjectsList) && !empty($classesList)) {
    foreach ($subjectsList as $sub) {
        foreach ($classesList as $cls) {
            foreach ($streamsList as $strm) {
                $chkA = thamani_db_prepare($conn, "SELECT id FROM teacher_subject_assignments WHERE teacher_id = ? AND subject = ? AND class_level = ? AND stream = ? LIMIT 1");
                if ($chkA) {
                    thamani_db_stmt_bind_param($chkA, "isss", $id, $sub, $cls, $strm);
                    thamani_db_stmt_execute($chkA);
                    $resA = thamani_db_stmt_get_result($chkA);
                    $existsA = $resA ? thamani_db_fetch_assoc($resA) : null;
                    thamani_db_stmt_close($chkA);

                    if (!$existsA) {
                        $insA = thamani_db_prepare($conn, "INSERT INTO teacher_subject_assignments (teacher_id, subject, class_level, stream) VALUES (?, ?, ?, ?)");
                        if ($insA) {
                            thamani_db_stmt_bind_param($insA, "isss", $id, $sub, $cls, $strm);
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

$allocMsg = $autoCount > 0 ? " Synced {$autoCount} new Subject Allocations per Class & Stream." : "";
$_SESSION['admin_flash'] = [
    'type' => 'success',
    'message' => "Teacher record for \"{$fullName}\" (Staff ID: {$staffId}) updated successfully!{$allocMsg}" . ($resetPassword ? " Password reset to Admin@2026." : "")
];

header('Location: admin_dashboard.php?tab=tab-admin-teachers#tab-admin-teachers');
exit;
