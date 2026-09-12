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
    return [
        'id'         => $_SESSION['teacher_id']         ?? null,
        'staff_id'   => $_SESSION['teacher_staff_id']   ?? null,
        'name'       => $_SESSION['teacher_name']       ?? null,
        'email'      => $_SESSION['teacher_email']      ?? null,
        'department' => $_SESSION['teacher_department'] ?? null,
    ];
}