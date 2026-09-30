<?php
/**
 * Teacher session guard helpers.
 * Include this at the very top of every teacher-protected page.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Require that a teacher is logged in. Redirects to login otherwise. */
function require_teacher_login(): void {
    if (empty($_SESSION['teacher_id'])) {
        header('Location: teacher-login.php');
        exit;
    }
}

/** Require that the teacher has already changed their default password. */
function require_password_changed(): void {
    if (!empty($_SESSION['must_change_password'])) {
        header('Location: teacher-change-password.php');
        exit;
    }
}

/** Returns basic info about the logged-in teacher. */
function current_teacher(): array {
    global $conn;
    $id = (int)($_SESSION['teacher_id'] ?? 0);
    $tData = [];
    if ($id > 0 && !empty($conn)) {
        $stmt = thamani_db_prepare($conn, "SELECT id, staff_id, full_name, email, department, is_class_teacher, class_teacher_of, class_teacher_stream, classes_taught, can_view_enrollments, can_manage_duty_roster FROM teachers WHERE id = ? LIMIT 1");
        if ($stmt) {
            thamani_db_stmt_bind_param($stmt, "i", $id);
            thamani_db_stmt_execute($stmt);
            $res = thamani_db_stmt_get_result($stmt);
            if ($res && $r = thamani_db_fetch_assoc($res)) {
                $tData = $r;
            }
            thamani_db_stmt_close($stmt);
        }
    }

    return [
        'id'                     => $id ?: null,
        'staff_id'               => $tData['staff_id']               ?? ($_SESSION['teacher_staff_id']               ?? null),
        'name'                   => $tData['full_name']              ?? ($_SESSION['teacher_name']                   ?? null),
        'email'                  => $tData['email']                  ?? ($_SESSION['teacher_email']                  ?? null),
        'department'             => $tData['department']             ?? ($_SESSION['teacher_department']             ?? null),
        'is_class_teacher'       => (int)($tData['is_class_teacher'] ?? ($_SESSION['teacher_is_class_teacher']       ?? 0)),
        'class_teacher_of'       => $tData['class_teacher_of']       ?? ($_SESSION['teacher_class_teacher_of']       ?? ''),
        'class_teacher_stream'   => $tData['class_teacher_stream']   ?? ($_SESSION['teacher_class_teacher_stream']   ?? 'Stream A'),
        'classes_taught'         => $tData['classes_taught']         ?? ($_SESSION['teacher_classes_taught']         ?? ''),
        'can_view_enrollments'   => (int)($tData['can_view_enrollments']   ?? ($_SESSION['teacher_can_view_enrollments']   ?? 0)),
        'can_manage_duty_roster' => (int)($tData['can_manage_duty_roster'] ?? ($_SESSION['teacher_can_manage_duty_roster'] ?? 0)),
    ];
}