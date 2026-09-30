<?php
/**
 * Thamani High School - Student Stream Attendance Processor
 * --------------------------------------------------
 * - Class Teachers can record/update daily batch attendance for students in their assigned class and stream.
 * - Admins can also record attendance for any class & stream.
 */

require_once 'auth_teacher.php';
require_teacher_login();
require_password_changed();

require_once 'conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: teacher_dashboard.php');
    exit;
}

$teacher = current_teacher();
$teacherId = (int)($teacher['id'] ?? 0);
$isAdmin = !empty($_SESSION['admin_id']);

$isBatch = !empty($_POST['is_batch']);
$attendanceDate = trim($_POST['attendance_date'] ?? date('Y-m-d'));
$classLevel = trim($_POST['class_level'] ?? '');
$stream = trim($_POST['stream'] ?? '');
$redirectUrl = trim($_POST['redirect_to'] ?? 'teacher_dashboard.php?tab=tab-teacher-class-attendance#tab-teacher-class-attendance');

$allowedStatuses = ['Present', 'Absent', 'Late', 'Excused'];

if ($isBatch) {
    $attendanceData = $_POST['attendance'] ?? []; // student_id => status
    $remarksData = $_POST['remarks'] ?? [];       // student_id => remark

    if (empty($attendanceData) || !is_array($attendanceData)) {
        $_SESSION['teacher_flash'] = ['type' => 'error', 'message' => 'No attendance records were submitted.'];
        header('Location: ' . $redirectUrl);
        exit;
    }

    // Security Check: Only the assigned Class Teacher for this class & stream (or Admin) can record attendance
    if (!$isAdmin) {
        $ctClass = trim($teacher['class_teacher_of'] ?? '');
        if (empty($teacher['is_class_teacher']) || $ctClass !== $classLevel) {
            $_SESSION['teacher_flash'] = ['type' => 'error', 'message' => "Permission Denied: Only the assigned Class Teacher for {$classLevel} can record class stream attendance."];
            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    $savedCount = 0;
    foreach ($attendanceData as $studentIdRaw => $statusRaw) {
        $studentId = (int)$studentIdRaw;
        $status = trim((string)$statusRaw);
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'Present';
        }
        $remark = trim((string)($remarksData[$studentId] ?? ''));

        if ($studentId <= 0) continue;

        // Check if attendance already exists for student & date
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

        if ($existingId > 0) {
            $upSql = "UPDATE student_attendance SET status = ?, remarks = ?, recorded_by_teacher_id = ? WHERE id = ?";
            $upStmt = thamani_db_prepare($conn, $upSql);
            if ($upStmt) {
                thamani_db_stmt_bind_param($upStmt, "ssii", $status, $remark, $teacherId, $existingId);
                thamani_db_stmt_execute($upStmt);
                thamani_db_stmt_close($upStmt);
                $savedCount++;
            }
        } else {
            $insSql = "INSERT INTO student_attendance (student_id, class_level, attendance_date, status, recorded_by_teacher_id, remarks)
                       VALUES (?, ?, ?, ?, ?, ?)";
            $insStmt = thamani_db_prepare($conn, $insSql);
            if ($insStmt) {
                thamani_db_stmt_bind_param($insStmt, "isssis", $studentId, $classLevel, $attendanceDate, $status, $teacherId, $remark);
                thamani_db_stmt_execute($insStmt);
                thamani_db_stmt_close($insStmt);
                $savedCount++;
            }
        }
    }

    $streamInfo = $stream !== '' ? " ({$stream})" : '';
    $_SESSION['teacher_flash'] = [
        'type' => 'success',
        'message' => "Stream attendance roll call for {$classLevel}{$streamInfo} on {$attendanceDate} saved successfully! ({$savedCount} student records updated)."
    ];
    header('Location: ' . $redirectUrl);
    exit;

} else {
    // Single student attendance (legacy fallback from students_view.php)
    $studentId      = (int)($_POST['student_id'] ?? 0);
    $status         = trim($_POST['status'] ?? 'Present');
    $remarks        = trim($_POST['remarks'] ?? '');
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'Present';
    }

    $errors = [];
    if ($studentId <= 0) {
        $errors[] = 'Invalid student record.';
    }

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
}
