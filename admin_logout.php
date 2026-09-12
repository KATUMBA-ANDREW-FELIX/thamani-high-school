<?php
session_start();

// Only clear admin session keys — leave teacher session intact
unset(
    $_SESSION['admin_id'],
    $_SESSION['admin_admin_id'],
    $_SESSION['admin_name'],
    $_SESSION['admin_email'],
    $_SESSION['admin_must_change_password'],
    $_SESSION['admin_logged_in_at']
);

header('Location: teacher-login.php?role=admin');
exit;