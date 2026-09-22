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
$isClassTeacher = !empty($_POST['is_class_teacher']) ? 1 : 0;
$classTeacherOf = $isClassTeacher ? trim($_POST['class_teacher_of'] ?? '') : null;

// Handle array or comma-separated string for classes_taught
$classesTaughtRaw = $_POST['classes_taught'] ?? [];
if (is_array($classesTaughtRaw)) {
    $classesTaught = implode(', ', array_filter(array_map('trim', $classesTaughtRaw)));
} else {
    $classesTaught = trim((string)$classesTaughtRaw);
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
    header('Location: admin_dashboard.php');
    exit;
}

$defaultPassword = 'Admin@2026';
$hash = password_hash($defaultPassword, PASSWORD_BCRYPT);
$now  = date('Y-m-d H:i:s');

$insertSql = "INSERT INTO teachers (staff_id, full_name, email, department, is_class_teacher, class_teacher_of, classes_taught, password_hash, must_change_password, is_active, created_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, ?)";

$insStmt = thamani_db_prepare($conn, $insertSql);

if ($insStmt) {
    thamani_db_stmt_bind_param($insStmt, "ssssissss", $staffId, $fullName, $email, $department, $isClassTeacher, $classTeacherOf, $classesTaught, $hash, $now);
    if (thamani_db_stmt_execute($insStmt)) {
        thamani_db_stmt_close($insStmt);
        $_SESSION['admin_flash'] = [
            'type' => 'success',
            'message' => "Teacher \"{$fullName}\" (Staff ID: {$staffId}) registered successfully! Default password is set to: Admin@2026"
        ];
    } else {
        error_log('[Admin Add Teacher Execute] ' . thamani_db_stmt_error($insStmt));
        thamani_db_stmt_close($insStmt);
        $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Failed to save teacher to database.'];
    }
} else {
    $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Database prepare statement failed.'];
}

header('Location: admin_dashboard.php');
exit;
