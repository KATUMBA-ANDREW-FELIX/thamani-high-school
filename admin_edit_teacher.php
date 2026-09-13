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
$isClassTeacher = !empty($_POST['is_class_teacher']) ? 1 : 0;
$classTeacherOf = $isClassTeacher ? trim($_POST['class_teacher_of'] ?? '') : null;
$isActive       = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
$resetPassword  = !empty($_POST['reset_password']);

$classesTaughtRaw = $_POST['classes_taught'] ?? [];
if (is_array($classesTaughtRaw)) {
    $classesTaught = implode(', ', array_filter(array_map('trim', $classesTaughtRaw)));
} else {
    $classesTaught = trim((string)$classesTaughtRaw);
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
    $stmt = mysqli_prepare($conn, $checkSql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssi", $staffId, $email, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = 'Another teacher with this Staff ID or Email already exists.';
        }
        mysqli_stmt_close($stmt);
    }
}

if (!empty($errors)) {
    $_SESSION['admin_flash'] = ['type' => 'error', 'message' => implode(' ', $errors)];
    header('Location: admin_dashboard.php');
    exit;
}

if ($resetPassword) {
    $defaultPassword = 'Admin@2026';
    $hash = password_hash($defaultPassword, PASSWORD_BCRYPT);
    $updateSql = "UPDATE teachers 
                  SET staff_id = ?, full_name = ?, email = ?, department = ?, is_class_teacher = ?, class_teacher_of = ?, classes_taught = ?, is_active = ?, password_hash = ?, must_change_password = 1
                  WHERE id = ?";
    $upStmt = mysqli_prepare($conn, $updateSql);
    if ($upStmt) {
        mysqli_stmt_bind_param($upStmt, "ssssissisi", $staffId, $fullName, $email, $department, $isClassTeacher, $classTeacherOf, $classesTaught, $isActive, $hash, $id);
        $res = mysqli_stmt_execute($upStmt);
        mysqli_stmt_close($upStmt);
    }
} else {
    $updateSql = "UPDATE teachers 
                  SET staff_id = ?, full_name = ?, email = ?, department = ?, is_class_teacher = ?, class_teacher_of = ?, classes_taught = ?, is_active = ?
                  WHERE id = ?";
    $upStmt = mysqli_prepare($conn, $updateSql);
    if ($upStmt) {
        mysqli_stmt_bind_param($upStmt, "ssssissii", $staffId, $fullName, $email, $department, $isClassTeacher, $classTeacherOf, $classesTaught, $isActive, $id);
        $res = mysqli_stmt_execute($upStmt);
        mysqli_stmt_close($upStmt);
    }
}

$_SESSION['admin_flash'] = [
    'type' => 'success',
    'message' => "Teacher record for \"{$fullName}\" (Staff ID: {$staffId}) updated successfully!" . ($resetPassword ? " Password reset to Admin@2026." : "")
];

header('Location: admin_dashboard.php');
exit;
