<?php
/**
 * Thamani Academy - Teacher Login Handler
 * ----------------------------------------
 * - Verifies staff ID/email + password against teachers table
 * - Uses password_verify (bcrypt) + mysqli prepared statements
 * - Sets session, then redirects to:
 *     - teacher-change-password.php  (if must_change_password = 1)
 *     - teacher_dashboard.php        (otherwise)
 * - Renders teacher-login.html with server-side error injection
 *
 * Requires: conn.php exposing a mysqli connection named $conn
 */

session_start();

// If already logged in, route them past the login page
if (!empty($_SESSION['teacher_id'])) {
    if (!empty($_SESSION['must_change_password'])) {
        header('Location: teacher-change-password.php');
    } else {
        header('Location: teacher_dashboard.php');
    }
    exit;
}

$errors      = [];
$active_role = 'teacher';
$prefilled_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $active_role  = ($_POST['role'] ?? 'teacher') === 'admin' ? 'admin' : 'teacher';
    $identifier   = trim($_POST['login_id'] ?? '');
    $password     = $_POST['login_password'] ?? '';
    $prefilled_id = $identifier;

    if ($active_role === 'admin') {
        // Admin login is handled in the next phase — show a clear message
        $errors[] = 'Admin login is not yet enabled. Please use the Teacher tab.';
    } else {
        // ---------- Validate presence ----------
        if ($identifier === '') {
            $errors[] = 'Please enter your Staff ID or Email address.';
        } elseif (mb_strlen($identifier) > 150) {
            $errors[] = 'Staff ID or Email is too long.';
        }

        if ($password === '') {
            $errors[] = 'Please enter your password.';
        }

        // ---------- Authenticate ----------
        if (empty($errors)) {
            require_once 'conn.php';

            $sql = "SELECT id, staff_id, full_name, email, department,
                           password_hash, must_change_password, is_active
                    FROM teachers
                    WHERE staff_id = ? OR email = ?
                    LIMIT 1";

            $stmt = mysqli_prepare($conn, $sql);

            if ($stmt === false) {
                error_log('[Teacher Login Prepare] ' . mysqli_error($conn));
                $errors[] = 'A system error occurred. Please try again later.';
            } else {
                mysqli_stmt_bind_param($stmt, "ss", $identifier, $identifier);
                mysqli_stmt_execute($stmt);
                $result  = mysqli_stmt_get_result($stmt);
                $teacher = $result ? mysqli_fetch_assoc($result) : null;
                mysqli_stmt_close($stmt);

                if (!$teacher) {
                    $errors[] = 'Invalid credentials. Please check your Staff ID and password.';
                } elseif ((int)$teacher['is_active'] !== 1) {
                    $errors[] = 'Your account has been disabled. Please contact the administrator.';
                } elseif (!password_verify($password, $teacher['password_hash'])) {
                    $errors[] = 'Invalid credentials. Please check your Staff ID and password.';
                } else {
                    // ---------- Success: set session ----------
                    session_regenerate_id(true);

                    $_SESSION['teacher_id']              = (int)$teacher['id'];
                    $_SESSION['teacher_staff_id']        = $teacher['staff_id'];
                    $_SESSION['teacher_name']            = $teacher['full_name'];
                    $_SESSION['teacher_email']           = $teacher['email'];
                    $_SESSION['teacher_department']      = $teacher['department'];
                    $_SESSION['must_change_password']    = (int)$teacher['must_change_password'];
                    $_SESSION['teacher_logged_in_at']    = time();

                    // Update last_login timestamp
                    $upd = mysqli_prepare($conn, "UPDATE teachers SET last_login = NOW() WHERE id = ?");
                    if ($upd) {
                        mysqli_stmt_bind_param($upd, "i", $teacher['id']);
                        mysqli_stmt_execute($upd);
                        mysqli_stmt_close($upd);
                    }

                    // ---------- Route them ----------
                    if (!empty($_SESSION['must_change_password'])) {
                        header('Location: teacher-change-password.php');
                    } else {
                        header('Location: teacher_dashboard.php');
                    }
                    exit;
                }
            }
        }
    }
}

// ---------- Build server-side error HTML ----------
$errorHtml = '';
if (!empty($errors)) {
    $items = '';
    foreach ($errors as $e) {
        $items .= '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    $errorHtml = <<<HTML
        <div class="bg-red-50 border border-red-200 text-red-700 text-xs font-semibold px-4 py-3 rounded-lg flex items-start gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <ul class="list-disc list-inside space-y-0.5">{$items}</ul>
        </div>
    HTML;
}

// ---------- Render template ----------
ob_start();
require './teacher-login.html';
$html = ob_get_clean();

$html = str_replace('<!-- LOGIN_ERROR_HTML -->', $errorHtml, $html);

echo $html;