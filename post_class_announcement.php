<?php
/**
 * Thamani High School - Post Class Announcement / Timetable Handler
 * Accessible by Elevated Class Teachers.
 */

require_once 'auth_teacher.php';
require_teacher_login();
require_password_changed();
require_once 'conn.php';

$teacher = current_teacher();

if (empty($teacher['is_class_teacher'])) {
    $_SESSION['teacher_flash'] = ['type' => 'error', 'message' => 'Access denied. Only designated Class Teachers can post class announcements.'];
    header('Location: teacher_dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: teacher_dashboard.php');
    exit;
}

$title      = trim($_POST['title'] ?? '');
$classLevel = trim($_POST['class_level'] ?? $teacher['class_teacher_of']);
$content    = trim($_POST['content'] ?? '');

$errors = [];
if ($title === '') {
    $errors[] = 'Announcement title is required.';
}
if ($classLevel === '') {
    $errors[] = 'Class level is required.';
}
if ($content === '') {
    $errors[] = 'Announcement content is required.';
}

if (!empty($errors)) {
    $_SESSION['teacher_flash'] = ['type' => 'error', 'message' => implode(' ', $errors)];
    header('Location: teacher_dashboard.php');
    exit;
}

$postedByTeacherId = (int)$teacher['id'];
$postedByName      = $teacher['name'];

$sql = "INSERT INTO class_announcements (title, content, class_level, posted_by_teacher_id, posted_by_name) 
        VALUES (?, ?, ?, ?, ?)";
$stmt = thamani_db_prepare($conn, $sql);

if ($stmt) {
    thamani_db_stmt_bind_param($stmt, "sssis", $title, $content, $classLevel, $postedByTeacherId, $postedByName);
    $res = thamani_db_stmt_execute($stmt);
    thamani_db_stmt_close($stmt);

    if ($res) {
        $_SESSION['teacher_flash'] = [
            'type' => 'success',
            'message' => "Class announcement \"{$title}\" for {$classLevel} posted successfully!"
        ];
    } else {
        $_SESSION['teacher_flash'] = [
            'type' => 'error',
            'message' => "Failed to post announcement: " . thamani_db_error($conn)
        ];
    }
} else {
    $_SESSION['teacher_flash'] = [
        'type' => 'error',
        'message' => "Database error preparing query."
    ];
}

header('Location: teacher_dashboard.php');
exit;
