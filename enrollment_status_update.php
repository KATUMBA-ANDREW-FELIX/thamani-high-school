<?php
/**
 * Thamani High School - Change Student Enrollment Status
 * ---------------------------------------------------
 * - Admin only
 * - Updates the `status` field on a student record
 * - Returns to enrollment_view.php with a flash message
 */

require_once 'auth_admin.php';
require_admin_login();

require_once 'conn.php';

// ---------- Only accept POST ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: enrollment_view.php');
    exit;
}

$studentId = (int)($_POST['student_id'] ?? 0);
$newStatus = trim($_POST['new_status'] ?? '');

$allowedStatuses = ['Pending', 'Enrolled', 'Rejected'];

// ---------- Validate input ----------
if ($studentId <= 0) {
    $_SESSION['enroll_flash'] = ['type' => 'error', 'message' => 'Invalid student selected.'];
    header('Location: enrollment_view.php');
    exit;
}

if (!in_array($newStatus, $allowedStatuses, true)) {
    $_SESSION['enroll_flash'] = ['type' => 'error', 'message' => 'Invalid status value.'];
    header('Location: enrollment_view.php');
    exit;
}

// ---------- Confirm student exists ----------
$check = mysqli_prepare($conn, "SELECT id, full_name, status FROM students WHERE id = ? LIMIT 1");

if ($check === false) {
    error_log('[Enrollment Status Check] ' . mysqli_error($conn));
    $_SESSION['enroll_flash'] = ['type' => 'error', 'message' => 'System error. Please try again.'];
    header('Location: enrollment_view.php');
    exit;
}

mysqli_stmt_bind_param($check, "i", $studentId);
mysqli_stmt_execute($check);
$res = mysqli_stmt_get_result($check);
$student = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($check);

if (!$student) {
    $_SESSION['enroll_flash'] = ['type' => 'error', 'message' => 'Student not found.'];
    header('Location: enrollment_view.php');
    exit;
}

// ---------- Skip if no change ----------
if ($student['status'] === $newStatus) {
    $_SESSION['enroll_flash'] = [
        'type'    => 'info',
        'message' => "{$student['full_name']} is already marked as {$newStatus}.",
    ];
    header('Location: enrollment_view.php');
    exit;
}

// ---------- Update the status ----------
$upd = mysqli_prepare($conn, "UPDATE students SET status = ? WHERE id = ?");

if ($upd === false) {
    error_log('[Enrollment Status Prepare] ' . mysqli_error($conn));
    $_SESSION['enroll_flash'] = ['type' => 'error', 'message' => 'System error while updating.'];
    header('Location: enrollment_view.php');
    exit;
}

mysqli_stmt_bind_param($upd, "si", $newStatus, $studentId);

if (mysqli_stmt_execute($upd)) {
    mysqli_stmt_close($upd);
    $_SESSION['enroll_flash'] = [
        'type'    => 'success',
        'message' => "{$student['full_name']} has been marked as {$newStatus}.",
    ];
} else {
    error_log('[Enrollment Status Execute] ' . mysqli_stmt_error($upd));
    mysqli_stmt_close($upd);
    $_SESSION['enroll_flash'] = [
        'type'    => 'error',
        'message' => 'Could not update the student status.',
    ];
}

header('Location: enrollment_view.php');
exit;