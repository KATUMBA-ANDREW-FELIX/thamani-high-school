<?php
/**
 * Thamani High School - Unified Login Handler (Teacher + Admin)
 * ----------------------------------------------------------
 * - Role toggle in the HTML sends either 'teacher' or 'admin'
 * - Authenticates against the right table
 * - Sets separate session keys for each role
 * - Redirects to the appropriate dashboard
 */

session_start();

// ---------- Already logged in? Route them away ----------
if (!empty($_SESSION['teacher_id'])) {
    if (!empty($_SESSION['must_change_password'])) {
        header('Location: teacher-change-password.php');
    } else {
        header('Location: teacher_dashboard.php');
    }
    exit;
}
if (!empty($_SESSION['admin_id'])) {
    header('Location: admin_dashboard.php');
    exit;
}

$errors       = [];
$active_role  = 'teacher';
$prefilled_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $active_role  = ($_POST['role'] ?? 'teacher') === 'admin' ? 'admin' : 'teacher';
    $identifier   = trim($_POST['login_id'] ?? '');
    $password     = $_POST['login_password'] ?? '';
    $prefilled_id = $identifier;

    // ---------- Shared presence validation ----------
    if ($identifier === '') {
        $errors[] = $active_role === 'admin'
            ? 'Please enter your Admin ID or Email address.'
            : 'Please enter your Staff ID or Email address.';
    } elseif (mb_strlen($identifier) > 150) {
        $errors[] = 'Identifier is too long.';
    }

    if ($password === '') {
        $errors[] = 'Please enter your password.';
    }

    if (empty($errors)) {
        require_once 'conn.php';

        // ============================================================
        // TEACHER LOGIN
        // ============================================================
        if ($active_role === 'teacher') {
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
                    session_regenerate_id(true);

                    $_SESSION['teacher_id']           = (int)$teacher['id'];
                    $_SESSION['teacher_staff_id']     = $teacher['staff_id'];
                    $_SESSION['teacher_name']         = $teacher['full_name'];
                    $_SESSION['teacher_email']        = $teacher['email'];
                    $_SESSION['teacher_department']   = $teacher['department'];
                    $_SESSION['must_change_password'] = (int)$teacher['must_change_password'];
                    $_SESSION['teacher_logged_in_at'] = time();

                    $upd = mysqli_prepare($conn, "UPDATE teachers SET last_login = NOW() WHERE id = ?");
                    if ($upd) {
                        mysqli_stmt_bind_param($upd, "i", $teacher['id']);
                        mysqli_stmt_execute($upd);
                        mysqli_stmt_close($upd);
                    }

                    if (!empty($_SESSION['must_change_password'])) {
                        header('Location: teacher-change-password.php');
                    } else {
                        header('Location: teacher_dashboard.php');
                    }
                    exit;
                }
            }
        }

        // ============================================================
        // ADMIN LOGIN
        // ============================================================
        else {
            $sql = "SELECT id, admin_id, full_name, email,
                           password_hash, must_change_password, is_active
                    FROM admins
                    WHERE admin_id = ? OR email = ?
                    LIMIT 1";

            $stmt = mysqli_prepare($conn, $sql);

            if ($stmt === false) {
                error_log('[Admin Login Prepare] ' . mysqli_error($conn));
                $errors[] = 'A system error occurred. Please try again later.';
            } else {
                mysqli_stmt_bind_param($stmt, "ss", $identifier, $identifier);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $admin  = $result ? mysqli_fetch_assoc($result) : null;
                mysqli_stmt_close($stmt);

                if (!$admin) {
                    $errors[] = 'Invalid credentials. Please check your Admin ID and password.';
                } elseif ((int)$admin['is_active'] !== 1) {
                    $errors[] = 'This admin account has been disabled.';
                } elseif (!password_verify($password, $admin['password_hash'])) {
                    $errors[] = 'Invalid credentials. Please check your Admin ID and password.';
                } else {
                    session_regenerate_id(true);

                    $_SESSION['admin_id']                   = (int)$admin['id'];
                    $_SESSION['admin_admin_id']             = $admin['admin_id'];
                    $_SESSION['admin_name']                 = $admin['full_name'];
                    $_SESSION['admin_email']                = $admin['email'];
                    $_SESSION['admin_must_change_password'] = (int)$admin['must_change_password'];
                    $_SESSION['admin_logged_in_at']         = time();

                    $upd = mysqli_prepare($conn, "UPDATE admins SET last_login = NOW() WHERE id = ?");
                    if ($upd) {
                        mysqli_stmt_bind_param($upd, "i", $admin['id']);
                        mysqli_stmt_execute($upd);
                        mysqli_stmt_close($upd);
                    }

                    header('Location: admin_dashboard.php');
                    exit;
                }
            }
        }
    }
}

// ---------- Build error HTML for the template ----------
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