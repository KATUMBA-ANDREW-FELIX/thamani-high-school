<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function require_student_login() {
    if (empty($_SESSION['student_id'])) {
        header('Location: student-login.php');
        exit;
    }
}

function require_student_or_admin_login() {
    if (empty($_SESSION['student_id']) && empty($_SESSION['admin_id'])) {
        header('Location: student-login.php');
        exit;
    }
}

function require_student_password_changed() {
    if (!empty($_SESSION['student_must_change_password'])) {
        header('Location: student-change-password.php');
        exit;
    }
}

function current_student() {
    return [
        'id' => (int)($_SESSION['student_id'] ?? 0),
        'name' => (string)($_SESSION['student_name'] ?? ''),
        'class' => (string)($_SESSION['student_class'] ?? ''),
        'stream' => (string)($_SESSION['student_stream'] ?? ''),
    ];
}
