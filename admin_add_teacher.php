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

$fullName   = trim($_POST['full_name'] ?? '');
$staffId    = trim($_POST['staff_id'] ?? $_POST['teacher_id'] ?? '');
$email      = trim($_POST['email'] ?? '');
$department = trim($_POST['department'] ?? $_POST['subject'] ?? '');

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
    $stmt = mysqli_prepare($conn, $checkSql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $staffId, $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = 'A teacher with this Staff ID or Email is already registered.';
        }
        mysqli_stmt_close($stmt);
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

$insertSql = "INSERT INTO teachers (staff_id, full_name, email, department, password_hash, must_change_password, is_active, created_at)
              VALUES (?, ?, ?, ?, ?, 1, 1, ?)";

$insStmt = mysqli_prepare($conn, $insertSql);

if ($insStmt) {
    mysqli_stmt_bind_param($insStmt, "ssssss", $staffId, $fullName, $email, $department, $hash, $now);
    if (mysqli_stmt_execute($insStmt)) {
        mysqli_stmt_close($insStmt);
        $_SESSION['admin_flash'] = [
            'type' => 'success',
            'message' => "Teacher \"{$fullName}\" (Staff ID: {$staffId}) registered successfully! Default password is set to: Admin@2026"
        ];
    } else {
        error_log('[Admin Add Teacher Execute] ' . mysqli_stmt_error($insStmt));
        mysqli_stmt_close($insStmt);
        $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Failed to save teacher to database.'];
    }
} else {
    $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Database prepare statement failed.'];
}

header('Location: admin_dashboard.php');
exit;
