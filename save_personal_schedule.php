<?php
/**
 * Thamani High School - Save Teacher Personal Teaching Schedule
 * -----------------------------------------------------------
 * Allows individual teachers to add, view, update, and manage their custom schedule.
 */

require_once 'auth_teacher.php';
require_teacher_login();
require_password_changed();

require_once 'conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: teacher_dashboard.php');
    exit;
}

$teacher   = current_teacher();
$teacherId = (int)($teacher['id'] ?? 0);

$id         = (int)($_POST['id'] ?? 0);
$dayOfWeek  = trim($_POST['day_of_week'] ?? 'Monday');
$startTime  = trim($_POST['start_time'] ?? '');
$endTime    = trim($_POST['end_time'] ?? '');
$subject    = trim($_POST['subject'] ?? '');
$classLevel = trim($_POST['class_level'] ?? '');
$stream     = trim($_POST['stream'] ?? 'All Streams');
$roomNo     = trim($_POST['room_no'] ?? '');
$notes      = trim($_POST['notes'] ?? '');

$redirectUrl = trim($_POST['redirect_to'] ?? 'teacher_dashboard.php?tab=tab-teacher-schedule#tab-teacher-schedule');

$errors = [];
if ($subject === '') {
    $errors[] = 'Subject name is required.';
}
if ($startTime === '' || $endTime === '') {
    $errors[] = 'Start time and end time are required.';
}
if ($classLevel === '') {
    $errors[] = 'Class / Form level is required.';
}

if (!empty($errors)) {
    $_SESSION['teacher_flash'] = ['type' => 'error', 'message' => implode(' ', $errors)];
    header('Location: ' . $redirectUrl);
    exit;
}

if ($id > 0) {
    // Check ownership
    $checkSql = "SELECT id FROM teacher_personal_schedules WHERE id = ? AND teacher_id = ? LIMIT 1";
    $checkStmt = thamani_db_prepare($conn, $checkSql);
    $owned = false;
    if ($checkStmt) {
        thamani_db_stmt_bind_param($checkStmt, "ii", $id, $teacherId);
        thamani_db_stmt_execute($checkStmt);
        $res = thamani_db_stmt_get_result($checkStmt);
        if ($res && thamani_db_fetch_assoc($res)) {
            $owned = true;
        }
        thamani_db_stmt_close($checkStmt);
    }
    
    if (!$owned) {
        $_SESSION['teacher_flash'] = ['type' => 'error', 'message' => 'Permission denied: Schedule entry not found or belongs to another teacher.'];
        header('Location: ' . $redirectUrl);
        exit;
    }

    $upSql = "UPDATE teacher_personal_schedules 
              SET day_of_week = ?, start_time = ?, end_time = ?, subject = ?, class_level = ?, stream = ?, room_no = ?, notes = ? 
              WHERE id = ? AND teacher_id = ?";
    $stmt = thamani_db_prepare($conn, $upSql);
    if ($stmt) {
        thamani_db_stmt_bind_param($stmt, "ssssssssii", $dayOfWeek, $startTime, $endTime, $subject, $classLevel, $stream, $roomNo, $notes, $id, $teacherId);
        thamani_db_stmt_execute($stmt);
        thamani_db_stmt_close($stmt);
        $_SESSION['teacher_flash'] = ['type' => 'success', 'message' => "Teaching schedule entry for \"{$subject}\" updated successfully!"];
    } else {
        $_SESSION['teacher_flash'] = ['type' => 'error', 'message' => 'Database error while updating schedule.'];
    }
} else {
    $insSql = "INSERT INTO teacher_personal_schedules (teacher_id, day_of_week, start_time, end_time, subject, class_level, stream, room_no, notes)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = thamani_db_prepare($conn, $insSql);
    if ($stmt) {
        thamani_db_stmt_bind_param($stmt, "issssssss", $teacherId, $dayOfWeek, $startTime, $endTime, $subject, $classLevel, $stream, $roomNo, $notes);
        thamani_db_stmt_execute($stmt);
        thamani_db_stmt_close($stmt);
        $_SESSION['teacher_flash'] = ['type' => 'success', 'message' => "New teaching schedule entry for \"{$subject}\" added successfully!"];
    } else {
        $_SESSION['teacher_flash'] = ['type' => 'error', 'message' => 'Database error while saving schedule.'];
    }
}

header('Location: ' . $redirectUrl);
exit;
