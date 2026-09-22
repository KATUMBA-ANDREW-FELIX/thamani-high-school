<?php
/**
 * Thamani Academy - Student Login
 * --------------------------------
 * - Checks students.status = 'Enrolled'
 * - Verifies password_hash via password_verify
 * - Forces password change on first login
 */

session_start();

// Already logged in? Route them through.
if (!empty($_SESSION['student_id'])) {
    if (!empty($_SESSION['student_must_change_password'])) {
        header('Location: student-change-password.php');
    } else {
        header('Location: student-dashboard.php');
    }
    exit;
}

require_once 'conn.php';
$db = $GLOBALS['conn'];

$errors = [];
$prefilled_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name     = trim($_POST['student_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $prefilled_name = $name;

    if ($name === '')      $errors[] = 'Please enter your full name.';
    if ($password === '')  $errors[] = 'Please enter your password.';
    if (mb_strlen($name) > 150) $errors[] = 'Name is too long.';

    if (empty($errors)) {
        $sql = "SELECT id, full_name, class_level, stream,
                       password_hash, must_change_password, account_active
                FROM students
                WHERE full_name = ? AND status = 'Enrolled'
                LIMIT 2";

        $stmt = thamani_db_prepare($db, $sql);

        if ($stmt === false) {
            error_log('[Student Login Prepare] ' . thamani_db_error($db));
            $errors[] = 'A system error occurred. Please try again.';
        } else {
                      thamani_db_stmt_bind_param($stmt, "s", $name);

            if (!thamani_db_stmt_execute($stmt)) {
                error_log('[Student Login Execute] ' . thamani_db_stmt_error($stmt));
                $errors[] = 'A system error occurred. Please try again.';
            } else {

            $matches = [];
            
            if (function_exists('mysqli_stmt_get_result')) {
                $res = thamani_db_stmt_get_result($stmt);
                if ($res) while ($r = thamani_db_fetch_assoc($res)) $matches[] = $r;
            } else {
                // Fallback for servers without mysqlnd
                mysqli_stmt_bind_result($stmt, $rId, $rName, $rClass, $rStream,
                                        $rHash, $rMust, $rActive);
                while (mysqli_stmt_fetch($stmt)) {
                    $matches[] = [
                        'id' => $rId, 'full_name' => $rName,
                        'class_level' => $rClass, 'stream' => $rStream,
                        'password_hash' => $rHash,
                        'must_change_password' => $rMust,
                        'account_active' => $rActive,
                    ];
                }
            }
            thamani_db_stmt_close($stmt);

            if (count($matches) === 0) {
                $errors[] = 'Invalid credentials. Please check your name and password.';
            } elseif (count($matches) > 1) {
                $errors[] = 'Multiple students share that name. Please contact the ICT office to have your account linked to a unique username.';
            } else {
                $student = $matches[0];

                if ((int)$student['account_active'] !== 1) {
                    $errors[] = 'Your account has been disabled. Please contact the administration.';
                } elseif (empty($student['password_hash'])) {
                    $errors[] = 'Your account has no password set. Please contact the ICT office.';
                } elseif (!password_verify($password, $student['password_hash'])) {
                    $errors[] = 'Invalid credentials. Please check your name and password.';
                } else {
                    // Success
                    session_regenerate_id(true);

                    $_SESSION['student_id']                   = (int)$student['id'];
                    $_SESSION['student_name']                 = $student['full_name'];
                    $_SESSION['student_class']                = $student['class_level'];
                    $_SESSION['student_stream']               = $student['stream'];
                    $_SESSION['student_must_change_password'] = (int)$student['must_change_password'];
                    $_SESSION['student_logged_in_at']         = time();

                    // Update last_login
                    $upd = thamani_db_prepare($db, "UPDATE students SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
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
        }
    }
}
}

$errorHtml = '';
if (!empty($errors)) {
    $items = '';
    foreach ($errors as $e) {
        $items .= '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    $errorHtml = <<<HTML
        <div class="bg-red-50 border border-red-200 text-red-700 text-xs font-semibold px-4 py-3 rounded-lg flex items-start gap-2 mb-5">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <ul class="list-disc list-inside space-y-0.5">{$items}</ul>
        </div>
    HTML;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - THAMANI ACADEMY - Kakiri</title>
    <link rel="icon" type="image/ico" href="favicon.ico" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { brand: {
                green: '#1A472A', maroon: '#800000', gold: '#D4AF37',
                lightGreen: '#E8F5E9', darkGreen: '#0F2D1A', lightMaroon: '#FDF2F2'
            }}}}
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        #page-loader {
            position: fixed; inset: 0; background: #1A472A;
            display: flex; align-items: center; justify-content: center;
            z-index: 9999; transition: opacity .5s ease, visibility .5s ease;
        }
        #page-loader.hidden { opacity: 0; visibility: hidden; }
        .login-bg {
            background-image: linear-gradient(rgba(15,45,26,.88), rgba(26,71,42,.88)),
                              url('public/assets/aigc/images/thamani-academy-hero_1788694592_000.png');
            background-size: cover; background-position: center;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans flex flex-col min-h-screen">

    <div id="page-loader">
        <div class="text-center">
            <img src="thamani-logo.png" alt="Thamani Academy"
                 class="h-20 w-auto mx-auto mb-4 animate-pulse" onerror="this.style.display='none'">
            <div class="w-12 h-12 border-4 border-brand-gold border-t-transparent rounded-full animate-spin mx-auto"></div>
        </div>
    </div>

    <div class="bg-brand-maroon text-white text-xs py-2 px-4 text-center font-medium">
        <div class="max-w-7xl mx-auto w-full flex justify-between items-center">
            <span>📍 THAMANI ACADEMY - Kakiri Main Campus, Wakiso District, Uganda</span>
            <span class="hidden sm:inline">📞 Enquiries: +256 414 123 456 | ✉️ info@thamaniacademy.ac.ug</span>
            <span class="bg-brand-gold text-brand-green px-2.5 py-0.5 rounded font-bold uppercase tracking-wider text-[10px]">Term III 2026 Active</span>
        </div>
    </div>

    <nav class="sticky top-0 z-50 bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <a href="index.php" class="flex items-center gap-3">
                    <img class="h-12 w-auto" src="thamani-logo.png" alt="Logo" onerror="this.src='favicon.svg'">
                    <div class="flex flex-col">
                        <span class="text-2xl font-bold tracking-tight text-brand-green">Thamani Academy</span>
                        <span class="text-[10px] font-semibold text-brand-maroon tracking-widest uppercase">Kakiri - Uganda</span>
                    </div>
                </a>
                <div class="hidden md:flex items-center space-x-3">
                    <a href="teacher-login.php" class="px-4 py-2 rounded-md text-sm font-bold text-white bg-brand-maroon transition-transform hover:scale-105 shadow flex items-center gap-1.5">
                        <i data-lucide="graduation-cap" class="w-4 h-4"></i> Teacher / Admin
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow login-bg flex items-center justify-center py-16 px-4">
        <div class="w-full max-w-md">

            <div class="bg-white rounded-2xl shadow-2xl border-t-4 border-brand-green overflow-hidden">
                <div class="px-8 pt-8 pb-6 text-center border-b border-gray-100">
                    <div class="w-20 h-20 mx-auto rounded-full flex items-center justify-center mb-4 bg-brand-green/10 text-brand-green">
                        <i data-lucide="user" class="w-10 h-10"></i>
                    </div>
                    <span class="bg-brand-green text-white px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest">Student Portal</span>
                    <h1 class="text-2xl font-bold text-brand-green mt-3">Student Login</h1>
                    <p class="text-gray-500 text-sm mt-1">Sign in with your full name and password.</p>
                </div>

                <form method="post" class="p-8 space-y-5" novalidate>
                    <?= $errorHtml ?>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Full Name</label>
                        <div class="relative">
                            <i data-lucide="user" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                            <input type="text" name="student_name" required autocomplete="username"
                                   value="<?= htmlspecialchars($prefilled_name, ENT_QUOTES) ?>"
                                   placeholder="e.g. Kato Brian Mukasa"
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-green focus:border-brand-green outline-none transition-all">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Password</label>
                        <div class="relative">
                            <i data-lucide="lock" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                            <input type="password" name="password" id="password" required autocomplete="current-password"
                                   placeholder="Enter your password"
                                   class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-green focus:border-brand-green outline-none transition-all">
                            <button type="button" onclick="togglePwd(this)"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit"
                            class="w-full py-3.5 bg-brand-green text-white font-bold rounded-lg hover:bg-brand-darkGreen transition-all text-sm shadow hover:shadow-lg flex items-center justify-center gap-2">
                        <i data-lucide="log-in" class="w-4 h-4"></i> Sign In
                    </button>

                    <div class="pt-4 border-t border-gray-100 text-center text-xs text-gray-500">
                        Forgot password? Contact the ICT office on
                        <span class="font-semibold text-brand-maroon">+256 414 123 456</span>.
                    </div>
                </form>
            </div>

            <div class="mt-6 bg-white/95 backdrop-blur rounded-2xl shadow-lg border border-gray-100 p-5 flex items-start gap-3">
                <i data-lucide="info" class="w-5 h-5 text-brand-gold flex-shrink-0 mt-0.5"></i>
                <div class="text-xs text-gray-600 leading-relaxed">
                    <p class="font-bold text-brand-green mb-1">First time logging in?</p>
                    <p>Use the default password given by the administration. You'll be asked to set your own password immediately.</p>
                </div>
            </div>

        </div>
    </main>

    <footer class="bg-brand-green text-white pt-10 pb-8 mt-auto border-t-4 border-brand-gold">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-300 text-sm">
            © 2026 Thamani Academy. All rights reserved.
        </div>
    </footer>

    <script>
        lucide.createIcons();
        window.addEventListener('load', () => {
            setTimeout(() => document.getElementById('page-loader')?.classList.add('hidden'), 400);
        });
        function togglePwd(btn) {
            const input = document.getElementById('password');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.innerHTML = `<i data-lucide="${show ? 'eye-off' : 'eye'}" class="w-4 h-4"></i>`;
            lucide.createIcons();
        }
    </script>
</body>
</html>