<?php
/**
 * Thamani High School - Unified School Portal Login
 * --------------------------------------------------
 * - Single, automatic sign-in point for Students, Teachers, and Administrators.
 * - Automatically detects role based on user ID or Email:
 *     - Admin ID (e.g. ADM-2026-001) or Email -> Admin Control Panel
 *     - Teacher Staff ID (e.g. TSC-2026-001) or Email -> Teacher Portal
 *     - Student LIN (e.g. LIN-2026-003) or Full Name -> Student Portal
 */

session_start();

// If already logged in, redirect to respective dashboard
if (!empty($_SESSION['admin_id'])) {
    header('Location: admin_dashboard.php');
    exit;
}
if (!empty($_SESSION['teacher_id'])) {
    if (!empty($_SESSION['must_change_password'])) {
        header('Location: teacher-change-password.php');
    } else {
        header('Location: teacher_dashboard.php');
    }
    exit;
}
if (!empty($_SESSION['student_id'])) {
    if (!empty($_SESSION['student_must_change_password'])) {
        header('Location: student-change-password.php');
    } else {
        header('Location: student-dashboard.php');
    }
    exit;
}

require_once 'conn.php';
$conn = $GLOBALS['conn'];

$errors = [];
$prefilled_identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $prefilled_identifier = $identifier;

    if ($identifier === '') {
        $errors[] = 'Please enter your ID (Admin ID, Staff ID, LIN) or Email.';
    }
    if ($password === '') {
        $errors[] = 'Please enter your password.';
    }

    if (empty($errors)) {
        $authenticated = false;

        // ----------------------------------------------------
        // STEP 1: Check Administrators Table
        // ----------------------------------------------------
        $sqlAdmin = "SELECT id, admin_id, full_name, email, password_hash, must_change_password, is_active FROM admins WHERE admin_id = ? OR email = ? LIMIT 1";
        $stmtAdmin = thamani_db_prepare($conn, $sqlAdmin);
        if ($stmtAdmin) {
            thamani_db_stmt_bind_param($stmtAdmin, "ss", $identifier, $identifier);
            thamani_db_stmt_execute($stmtAdmin);
            $resAdmin = thamani_db_stmt_get_result($stmtAdmin);
            $admin = $resAdmin ? thamani_db_fetch_assoc($resAdmin) : null;
            thamani_db_stmt_close($stmtAdmin);

            if ($admin && (int)$admin['is_active'] === 1 && password_verify($password, $admin['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id']               = (int)$admin['id'];
                $_SESSION['admin_code']             = $admin['admin_id'];
                $_SESSION['admin_name']             = $admin['full_name'];
                $_SESSION['admin_email']            = $admin['email'];
                $_SESSION['must_change_password']   = (int)$admin['must_change_password'];
                $_SESSION['admin_logged_in_at']     = time();

                $upd = thamani_db_prepare($conn, "UPDATE admins SET last_login = NOW() WHERE id = ?");
                if ($upd) {
                    thamani_db_stmt_bind_param($upd, "i", $admin['id']);
                    thamani_db_stmt_execute($upd);
                    thamani_db_stmt_close($upd);
                }

                header('Location: admin_dashboard.php');
                exit;
            }
        }

        // ----------------------------------------------------
        // STEP 2: Check Teachers Table
        // ----------------------------------------------------
        $sqlTeacher = "SELECT id, staff_id, full_name, email, department, is_class_teacher, class_teacher_of, classes_taught, can_view_enrollments, password_hash, must_change_password, is_active FROM teachers WHERE staff_id = ? OR email = ? LIMIT 1";
        $stmtTeacher = thamani_db_prepare($conn, $sqlTeacher);
        if ($stmtTeacher) {
            thamani_db_stmt_bind_param($stmtTeacher, "ss", $identifier, $identifier);
            thamani_db_stmt_execute($stmtTeacher);
            $resTeacher = thamani_db_stmt_get_result($stmtTeacher);
            $teacher = $resTeacher ? thamani_db_fetch_assoc($resTeacher) : null;
            thamani_db_stmt_close($stmtTeacher);

            if ($teacher && (int)$teacher['is_active'] === 1 && password_verify($password, $teacher['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['teacher_id']               = (int)$teacher['id'];
                $_SESSION['teacher_staff_id']         = $teacher['staff_id'];
                $_SESSION['teacher_name']             = $teacher['full_name'];
                $_SESSION['teacher_email']            = $teacher['email'];
                $_SESSION['teacher_department']       = $teacher['department'];
                $_SESSION['teacher_is_class_teacher'] = (int)($teacher['is_class_teacher'] ?? 0);
                $_SESSION['teacher_class_teacher_of'] = $teacher['class_teacher_of'] ?? '';
                $_SESSION['teacher_classes_taught']   = $teacher['classes_taught'] ?? '';
                $_SESSION['teacher_can_view_enrollments'] = (int)($teacher['can_view_enrollments'] ?? 0);
                $_SESSION['must_change_password']     = (int)$teacher['must_change_password'];
                $_SESSION['teacher_logged_in_at']     = time();

                $upd = thamani_db_prepare($conn, "UPDATE teachers SET last_login = NOW() WHERE id = ?");
                if ($upd) {
                    thamani_db_stmt_bind_param($upd, "i", $teacher['id']);
                    thamani_db_stmt_execute($upd);
                    thamani_db_stmt_close($upd);
                }

                if (!empty($_SESSION['must_change_password'])) {
                    header('Location: teacher-change-password.php');
                } else {
                    header('Location: teacher_dashboard.php');
                }
                exit;
            }
        }

        // ----------------------------------------------------
        // STEP 3: Check Enrolled Students Table
        // ----------------------------------------------------
        $sqlStudent = "SELECT id, full_name, lin_number, class_level, stream, password_hash, must_change_password, account_active FROM students WHERE (lin_number = ? OR full_name = ? OR guardian_email = ?) AND status = 'Enrolled' LIMIT 1";
        $stmtStudent = thamani_db_prepare($conn, $sqlStudent);
        if ($stmtStudent) {
            thamani_db_stmt_bind_param($stmtStudent, "sss", $identifier, $identifier, $identifier);
            thamani_db_stmt_execute($stmtStudent);
            $resStudent = thamani_db_stmt_get_result($stmtStudent);
            $student = $resStudent ? thamani_db_fetch_assoc($resStudent) : null;
            thamani_db_stmt_close($stmtStudent);

            if ($student && (int)$student['account_active'] === 1 && !empty($student['password_hash']) && password_verify($password, $student['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['student_id']                   = (int)$student['id'];
                $_SESSION['student_name']                 = $student['full_name'];
                $_SESSION['student_class']                = $student['class_level'];
                $_SESSION['student_stream']               = $student['stream'];
                $_SESSION['student_must_change_password'] = (int)$student['must_change_password'];
                $_SESSION['student_logged_in_at']         = time();

                $upd = thamani_db_prepare($conn, "UPDATE students SET last_login = NOW() WHERE id = ?");
                if ($upd) {
                    thamani_db_stmt_bind_param($upd, "i", $student['id']);
                    thamani_db_stmt_execute($upd);
                    thamani_db_stmt_close($upd);
                }

                if (!empty($_SESSION['student_must_change_password'])) {
                    header('Location: student-change-password.php');
                } else {
                    header('Location: student-dashboard.php');
                }
                exit;
            }
        }

        $errors[] = 'Invalid credentials. Please check your ID / Email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unified Portal Login - THAMANI HIGH SCHOOL</title>
    <link rel="icon" type="image/ico" href="favicon.ico" />
    <link rel="stylesheet" href="css/tailwind.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        #page-loader {
            position: fixed; inset: 0; background: #1F2937;
            display: flex; align-items: center; justify-content: center;
            z-index: 9999; transition: opacity .3s ease, visibility .3s ease;
        }
        #page-loader.hidden { opacity: 0; visibility: hidden; pointer-events: none; display: none !important; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans flex flex-col min-h-screen">

    <div id="page-loader">
        <div class="text-center">
            <img src="thamani-logo.png" alt="Thamani High School" class="h-20 w-auto mx-auto mb-4 animate-pulse" onerror="this.style.display='none'">
            <div class="w-12 h-12 border-4 border-brand-gold border-t-transparent rounded-full animate-spin mx-auto"></div>
        </div>
    </div>
    <script>
        setTimeout(function() {
            var l = document.getElementById('page-loader');
            if (l) { l.classList.add('hidden'); l.style.display = 'none'; }
        }, 50);
    </script>

    <!-- Top Announcement Bar -->
    <div class="bg-brand-maroon text-white text-xs py-2 px-4 text-center font-medium">
        <div class="max-w-7xl mx-auto w-full flex justify-between items-center">
            <span>📍 THAMANI HIGH SCHOOL - Kakiri Main Campus, Wakiso District, Uganda</span>
            <span class="hidden sm:inline">📞 Enquiries: +256 414 123 456 | ✉️ info@thamani.ac.ug</span>
            <span class="bg-brand-gold text-brand-green px-2.5 py-0.5 rounded font-bold uppercase tracking-wider text-[10px]">Unified Portal Login</span>
        </div>
    </div>

    <!-- Main Navigation Bar -->
    <nav class="sticky top-0 z-50 bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20 items-center">
                <a href="home.php" class="flex items-center gap-3">
                    <img class="h-12 w-auto" src="thamani-logo.png" alt="Logo" onerror="this.src='favicon.svg'">
                    <div class="flex flex-col">
                        <span class="text-2xl font-bold tracking-tight text-brand-green">Thamani High School</span>
                        <span class="text-[10px] font-semibold text-brand-maroon tracking-widest uppercase">Unified Portal Login</span>
                    </div>
                </a>
                <div>
                    <a href="home.php" class="px-4 py-2 text-sm font-bold text-gray-700 bg-gray-100 rounded-lg hover:bg-brand-green hover:text-white transition-colors flex items-center gap-1.5">
                        <i data-lucide="globe" class="w-4 h-4"></i> Main Website
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- MAIN LOGIN CARD -->
    <main class="flex-grow flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full bg-white rounded-3xl p-8 shadow-2xl border border-gray-100 space-y-6">
            <div class="text-center">
                <div class="w-16 h-16 rounded-2xl bg-brand-green/10 text-brand-green flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="shield-check" class="w-8 h-8 text-brand-green"></i>
                </div>
                <h2 class="text-2xl font-bold text-brand-green">Portal Sign In</h2>
                <p class="text-xs text-gray-500 mt-1">Students, Teachers, and Admins can sign in using their attached ID or Email.</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 text-xs font-semibold p-4 rounded-xl flex items-start gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0 mt-0.5"></i>
                    <ul class="list-disc list-inside space-y-0.5">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="login.php" method="post" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">User ID or Email Address <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <i data-lucide="user" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                        <input type="text" name="identifier" required value="<?= htmlspecialchars($prefilled_identifier) ?>"
                               placeholder="e.g. ADM-2026-001, TSC-2026-001, LIN-2026-003 or email"
                               class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-brand-green focus:outline-none">
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">Admin ID, Staff ID, Student LIN, or Registered Email.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Password <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <i data-lucide="key-round" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                        <input type="password" name="password" required placeholder="••••••••"
                               class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-brand-green focus:outline-none">
                    </div>
                </div>

                <button type="submit" class="w-full py-3.5 bg-brand-green text-white font-extrabold rounded-xl text-sm hover:bg-brand-darkGreen transition-colors shadow-lg flex items-center justify-center gap-2">
                    <i data-lucide="log-in" class="w-4 h-4 text-brand-gold"></i> Sign In to Portal
                </button>
            </form>

            <div class="pt-4 border-t border-gray-100 text-center text-xs text-gray-500 space-y-1">
                <div>Default Accounts Password: <code class="bg-gray-100 px-1.5 py-0.5 rounded font-mono font-bold text-gray-800">Admin@2026</code></div>
                <div>Encountering issues? Contact ICT Office: <a href="mailto:ict@thamani.ac.ug" class="text-brand-green font-bold hover:underline">ict@thamani.ac.ug</a></div>
            </div>
        </div>
    </main>

    <footer class="bg-brand-green text-white py-6 border-t-4 border-brand-gold text-center text-xs">
        © 2026 Thamani High School - Kakiri. All rights reserved.
    </footer>

    <script>lucide.createIcons();</script>
</body>
</html>
