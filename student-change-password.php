<?php
require_once 'conn.php';
require_once 'auth_student.php';
$db = $GLOBALS['conn'];
require_student_login();

$errors = [];
$success = false;
$isForced = !empty($_SESSION['student_must_change_password']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($current === '' || $new === '' || $confirm === '') {
        $errors[] = 'Please complete all password fields.';
    } elseif (strlen($new) < 8) {
        $errors[] = 'Your new password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $errors[] = 'The new passwords do not match.';
    }

    if (empty($errors)) {
        $stmt = thamani_db_prepare($db, 'SELECT password_hash FROM students WHERE id = ? LIMIT 1');
        thamani_db_stmt_bind_param($stmt, 'i', $_SESSION['student_id']);
        thamani_db_stmt_execute($stmt);
        $result = thamani_db_stmt_get_result($stmt);
        $student = $result ? thamani_db_fetch_assoc($result) : null;
        thamani_db_stmt_close($stmt);

        if (!$student || empty($student['password_hash']) || !password_verify($current, $student['password_hash'])) {
            $errors[] = 'The current password is incorrect.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $update = thamani_db_prepare($db, 'UPDATE students SET password_hash = ?, must_change_password = 0 WHERE id = ?');
            thamani_db_stmt_bind_param($update, 'si', $hash, $_SESSION['student_id']);
            $saved = thamani_db_stmt_execute($update);
            thamani_db_stmt_close($update);

            if (!$saved) {
                $errors[] = 'The password could not be updated. Please try again.';
            } else {
                $_SESSION['student_must_change_password'] = 0;
                header('Location: student-dashboard.php');
                exit;
            }
        }
    }
}

$errorHtml = '';
if (!empty($errors)) {
    $items = '';
    foreach ($errors as $error) {
        $items .= '<li>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    $errorHtml = '<div class="error"><ul>' . $items . '</ul></div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Thamani Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <main class="w-full max-w-md bg-white rounded-2xl shadow-xl border-t-4 border-green-800 p-8">
        <h1 class="text-2xl font-bold text-green-900">Change your password</h1>
        <p class="text-sm text-gray-500 mt-2 mb-6">
            <?= $isForced ? 'Set a personal password before opening your student dashboard.' : 'Update your student portal password.' ?>
        </p>
        <?= $errorHtml ?>
        <form method="post" class="space-y-4">
            <input type="password" name="current_password" required placeholder="Current password" class="w-full px-4 py-3 border rounded-lg">
            <input type="password" name="new_password" required placeholder="New password" class="w-full px-4 py-3 border rounded-lg">
            <input type="password" name="confirm_password" required placeholder="Confirm new password" class="w-full px-4 py-3 border rounded-lg">
            <button type="submit" class="w-full py-3 bg-green-800 text-white font-bold rounded-lg">Save password</button>
        </form>
        <a href="student_logout.php" class="block text-center text-sm text-gray-500 mt-5">Sign out</a>
    </main>
    <style>.error { margin-bottom: 1.25rem; padding: .75rem 1rem; color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; border-radius: .5rem; font-size: .875rem; }</style>
</body>
</html>
