<?php
/**
 * Admin session guard helpers.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_admin_login(): void {
    if (empty($_SESSION['admin_id'])) {
        header('Location: teacher-login.php?role=admin');
        exit;
    }
}

function require_admin_password_changed(): void {
    if (!empty($_SESSION['admin_must_change_password'])) {
        // For now we don't have a password change flow for admins — just let them in.
        // Later: header('Location: admin-change-password.php');
    }
}

function current_admin(): array {
    return [
        'id'       => (string)($_SESSION['admin_id'] ?? ''),
        'admin_id' => (string)($_SESSION['admin_code'] ?? $_SESSION['admin_admin_id'] ?? 'ADM-2026-001'),
        'name'     => (string)($_SESSION['admin_name'] ?? 'System Administrator'),
        'email'    => (string)($_SESSION['admin_email'] ?? 'admin@thamani.ac.ug'),
    ];
}