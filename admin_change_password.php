<?php
/**
 * Thamani High School - Admin Password Change Handler
 */

require_once 'auth_admin.php';
require_admin_login();
require_once 'conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_dashboard.php');
    exit;
}

$adminId         = (int)$_SESSION['admin_id'];
$currentPassword = $_POST['current_password'] ?? '';
$newPassword     = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

$errors = [];

if ($currentPassword === '') {
    $errors[] = 'Current password is required.';
}
if ($newPassword === '') {
    $errors[] = 'New password is required.';
} elseif (mb_strlen($newPassword) < 6) {
    $errors[] = 'New password must be at least 6 characters long.';
}
if ($newPassword !== $confirmPassword) {
    $errors[] = 'New password and confirmation do not match.';
}

if (empty($errors)) {
    // Fetch current password hash
    $stmt = mysqli_prepare($conn, "SELECT password_hash FROM admins WHERE id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $adminId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
            $errors[] = 'Incorrect current password.';
        }
    } else {
        $errors[] = 'Database query failed.';
    }
}

if (!empty($errors)) {
    $_SESSION['admin_flash'] = ['type' => 'error', 'message' => implode(' ', $errors)];
    header('Location: admin_dashboard.php');
    exit;
}

$newHash = password_hash($newPassword, PASSWORD_BCRYPT);

$updStmt = mysqli_prepare($conn, "UPDATE admins SET password_hash = ?, must_change_password = 0 WHERE id = ?");
if ($updStmt) {
    mysqli_stmt_bind_param($updStmt, "si", $newHash, $adminId);
    if (mysqli_stmt_execute($updStmt)) {
        mysqli_stmt_close($updStmt);
        $_SESSION['admin_flash'] = ['type' => 'success', 'message' => 'Your admin password was changed successfully!'];
    } else {
        mysqli_stmt_close($updStmt);
        $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Failed to update admin password.'];
    }
}

header('Location: admin_dashboard.php');
exit;
