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

/** @var mysqli $conn */
require_once __DIR__ . '/conn.php';

// ---------- Only accept POST ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: enrollment_view.php');
    exit;
}

$studentId = (int)($_POST['student_id'] ?? 0);
$newStatus = trim($_POST['new_status'] ?? '');

$allowedStatuses = ['Pending', 'Enrolled', 'Rejected'];

$redirectTo = $_POST['redirect_to'] ?? 'admin_dashboard.php';
if (!in_array($redirectTo, ['admin_dashboard.php', 'enrollment_view.php'], true)) {
    $redirectTo = 'admin_dashboard.php';
}

$setFlash = function($type, $msg) use ($redirectTo) {
    $payload = ['type' => $type, 'message' => $msg];
    $_SESSION['admin_flash']  = $payload;
    $_SESSION['enroll_flash'] = $payload;
    header('Location: ' . $redirectTo);
    exit;
};

// ---------- Validate input ----------
if ($studentId <= 0) {
    $setFlash('error', 'Invalid student selected.');
}

if (!in_array($newStatus, $allowedStatuses, true)) {
    $setFlash('error', 'Invalid status value.');
}

// ---------- Confirm student exists ----------
$check = thamani_db_prepare($conn, "SELECT id, full_name, status FROM students WHERE id = ? LIMIT 1");

if ($check === false) {
    error_log('[Enrollment Status Check] ' . thamani_db_error($conn));
    $setFlash('error', 'System error. Please try again.');
}

thamani_db_stmt_bind_param($check, "i", $studentId);
thamani_db_stmt_execute($check);
$res = thamani_db_stmt_get_result($check);
$student = $res ? thamani_db_fetch_assoc($res) : null;
thamani_db_stmt_close($check);

if (!$student) {
    $setFlash('error', 'Student not found.');
}

// ---------- Skip if no change ----------
if ($student['status'] === $newStatus) {
    $setFlash('info', "{$student['full_name']} is already marked as {$newStatus}.");
}

// ---------- Update the status ----------
$upd = thamani_db_prepare($conn, "UPDATE students SET status = ? WHERE id = ?");

if ($upd === false) {
    error_log('[Enrollment Status Prepare] ' . thamani_db_error($conn));
    $setFlash('error', 'System error while updating.');
}

thamani_db_stmt_bind_param($upd, "si", $newStatus, $studentId);

if (thamani_db_stmt_execute($upd)) {
    thamani_db_stmt_close($upd);
    $setFlash('success', "{$student['full_name']} has been marked as {$newStatus}.");
} else {
    error_log('[Enrollment Status Execute] ' . thamani_db_stmt_error($upd));
    thamani_db_stmt_close($upd);
    $setFlash('error', 'Could not update the student status.');
}