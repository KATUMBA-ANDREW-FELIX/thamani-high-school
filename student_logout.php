<?php
session_start();

unset(
    $_SESSION['student_id'],
    $_SESSION['student_name'],
    $_SESSION['student_class'],
    $_SESSION['student_stream'],
    $_SESSION['student_must_change_password'],
    $_SESSION['student_logged_in_at']
);

header('Location: student-login.php');
exit;