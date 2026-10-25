<?php
session_start();

unset(
    $_SESSION['alumni_editor_id'],
    $_SESSION['alumni_editor_name'],
    $_SESSION['alumni_editor_email'],
    $_SESSION['alumni_editor_logged_in_at'],
    $_SESSION['alumni_editor_csrf']
);

header('Location: login.php');
exit;
