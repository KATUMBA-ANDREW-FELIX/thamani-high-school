<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!defined('THAMANI_STUDENT_DEFAULT_PASSWORD')) {
    define('THAMANI_STUDENT_DEFAULT_PASSWORD', 'Admin@2026');
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

function verify_student_login_password($connection, array &$student, string $password): bool {
    $storedHash = (string)($student['password_hash'] ?? '');
    if ($storedHash !== '') {
        return password_verify($password, $storedHash);
    }

    if ($password !== THAMANI_STUDENT_DEFAULT_PASSWORD || (int)($student['id'] ?? 0) <= 0) {
        return false;
    }

    $temporaryHash = password_hash($password, PASSWORD_DEFAULT);
    $update = thamani_db_prepare(
        $connection,
        "UPDATE students
         SET password_hash = ?, must_change_password = 1
         WHERE id = ? AND status = 'Enrolled' AND account_active = 1
           AND (password_hash IS NULL OR password_hash = '')"
    );
    if (!$update) {
        error_log('[Student Login Password Initialization] ' . thamani_db_error($connection));
        return false;
    }

    $studentId = (int)$student['id'];
    thamani_db_stmt_bind_param($update, 'si', $temporaryHash, $studentId);
    $updated = thamani_db_stmt_execute($update);
    if (!$updated) {
        error_log('[Student Login Password Initialization] ' . thamani_db_stmt_error($update));
    }
    thamani_db_stmt_close($update);

    if (!$updated) {
        return false;
    }

    $check = thamani_db_prepare($connection, 'SELECT password_hash, must_change_password FROM students WHERE id = ? LIMIT 1');
    if (!$check) {
        error_log('[Student Login Password Verification] ' . thamani_db_error($connection));
        return false;
    }

    thamani_db_stmt_bind_param($check, 'i', $studentId);
    $verified = thamani_db_stmt_execute($check);
    if (!$verified) {
        error_log('[Student Login Password Verification] ' . thamani_db_stmt_error($check));
    }
    $result = $verified ? thamani_db_stmt_get_result($check) : false;
    $updatedStudent = $result ? thamani_db_fetch_assoc($result) : null;
    thamani_db_stmt_close($check);

    $updatedHash = (string)($updatedStudent['password_hash'] ?? '');
    if ($updatedHash === '' || !password_verify($password, $updatedHash)) {
        return false;
    }

    $student['password_hash'] = $updatedStudent['password_hash'];
    $student['must_change_password'] = (int)$updatedStudent['must_change_password'];
    return true;
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
