<?php
/**
 * Thamani High School - Student Attendance Processor
 * --------------------------------------------------
 * - Class Teachers can record/update daily attendance for students in their assigned class.
 * - Admins can also record attendance for any class.
 */

require_once 'auth_teacher.php';
require_teacher_login();
require_password_changed();

require_once 'conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: students_view.php');
    exit;
}

$teacher = current_teacher();
$isAdmin = !empty($_SESSION['admin_id']);

$studentId      = (int)($_POST['student_id'] ?? 0);
$classLevel     = trim($_POST['class_level'] ?? '');
$attendanceDate = trim($_POST['attendance_date'] ?? date('Y-m-d'));
$status         = trim($_POST['status'] ?? 'Present');
$remarks        = trim($_POST['remarks'] ?? '');

$allowedStatuses = ['Present', 'Absent', 'Late', 'Excused'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'Present';
}

$errors = [];

if ($studentId <= 0) {
    $errors[] = 'Invalid student record.';
}

// Security Check: Only the assigned Class Teacher for this class (or Admin) can record attendance
if (!$isAdmin) {
    if (empty($teacher['is_class_teacher']) || trim($teacher['class_teacher_of']) !== $classLevel) {
        $errors[] = "Permission Denied: Only the assigned Class Teacher for {$classLevel} can record class attendance.";
    }
}

if (!empty($errors)) {
    $_SESSION['teacher_flash'] = ['type' => 'error', 'message' => implode(' ', $errors)];
    header('Location: students_view.php?class=' . urlencode($classLevel));
    exit;
}

// Check existing attendance record for today
$checkSql = "SELECT id FROM student_attendance WHERE student_id = ? AND attendance_date = ? LIMIT 1";
$checkStmt = thamani_db_prepare($conn, $checkSql);
$existingId = 0;

if ($checkStmt) {
    thamani_db_stmt_bind_param($checkStmt, "is", $studentId, $attendanceDate);
    thamani_db_stmt_execute($checkStmt);
    $res = thamani_db_stmt_get_result($checkStmt);
    if ($res && $r = thamani_db_fetch_assoc($res)) {
        $existingId = (int)$r['id'];
    }
    thamani_db_stmt_close($checkStmt);
}

$teacherId = (int)($teacher['id'] ?? 0);

if ($existingId > 0) {
    $upSql = "UPDATE student_attendance SET status = ?, remarks = ?, recorded_by_teacher_id = ? WHERE id = ?";
    $upStmt = thamani_db_prepare($conn, $upSql);
    if ($upStmt) {
        thamani_db_stmt_bind_param($upStmt, "ssii", $status, $remarks, $teacherId, $existingId);
        thamani_db_stmt_execute($upStmt);
        thamani_db_stmt_close($upStmt);
    }
} else {
    $insSql = "INSERT INTO student_attendance (student_id, class_level, attendance_date, status, recorded_by_teacher_id, remarks)
               VALUES (?, ?, ?, ?, ?, ?)";
    $insStmt = thamani_db_prepare($conn, $insSql);
    if ($insStmt) {
        thamani_db_stmt_bind_param($insStmt, "isssis", $studentId, $classLevel, $attendanceDate, $status, $teacherId, $remarks);
        thamani_db_stmt_execute($insStmt);
        thamani_db_stmt_close($insStmt);
    }
}

$_SESSION['teacher_flash'] = [
    'type' => 'success',
    'message' => "Attendance for date {$attendanceDate} updated to \"{$status}\" successfully."
];

header('Location: students_view.php?class=' . urlencode($classLevel));
exit;
