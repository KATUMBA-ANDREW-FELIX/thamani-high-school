<?php
/**
 * Thamani Academy - Teacher Password Change
 * ------------------------------------------
 * - First login forces this page (must_change_password = 1)
 * - Also reachable voluntarily via teacher_dashboard.php
 * - Validates new password strength, updates hash, clears the flag
 */

require_once 'auth_teacher.php';
require_teacher_login();

$isForced      = !empty($_SESSION['must_change_password']);
$voluntary     = isset($_GET['voluntary']) || !$isForced;
$errors        = [];
$success       = false;
$prefilled_id  = $_SESSION['teacher_staff_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // ---------- Validate ----------
    if ($current === '') {
        $errors[] = 'Please enter your current password.';
    }
    if ($new === '') {
        $errors[] = 'Please enter a new password.';
    } elseif (strlen($new) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $new)) {
        $errors[] = 'New password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $new)) {
        $errors[] = 'New password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $new)) {
        $errors[] = 'New password must contain at least one number.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $new)) {
        $errors[] = 'New password must contain at least one special character.';
    }
    if ($new !== '' && $new === $current) {
        $errors[] = 'New password must be different from your current password.';
    }
    if ($new !== $confirm) {
        $errors[] = 'New password and confirmation do not match.';
    }

    // ---------- Update ----------
    if (empty($errors)) {
        require_once 'conn.php';

        // Fetch current hash for verification
        $sel = mysqli_prepare($conn, "SELECT password_hash FROM teachers WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($sel, "i", $_SESSION['teacher_id']);
        mysqli_stmt_execute($sel);
        $res  = mysqli_stmt_get_result($sel);
        $row  = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($sel);

        if (!$row) {
            $errors[] = 'Account not found. Please log in again.';
        } elseif (!password_verify($current, $row['password_hash'])) {
            $errors[] = 'Your current password is incorrect.';
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);

            $upd = mysqli_prepare($conn,
                "UPDATE teachers SET password_hash = ?, must_change_password = 0 WHERE id = ?");
            mysqli_stmt_bind_param($upd, "si", $newHash, $_SESSION['teacher_id']);

            if (mysqli_stmt_execute($upd)) {
                mysqli_stmt_close($upd);
                $_SESSION['must_change_password'] = 0;

                if ($isForced) {
                    // First-login change → straight to dashboard
                    header('Location: teacher_dashboard.php');
                    exit;
                } else {
                    // Voluntary change → return to dashboard after saving
                    header('Location: teacher_dashboard.php');
                    exit;
                }
            } else {
                error_log('[Teacher Change Password] ' . mysqli_stmt_error($upd));
                mysqli_stmt_close($upd);
                $errors[] = 'A system error occurred. Please try again later.';
            }
        }
    }
}

$teacher     = current_teacher();
$pageTitle   = $isForced ? 'Set Your Password' : 'Change Your Password';
$headingText = $isForced
    ? 'Welcome, ' . htmlspecialchars($teacher['name']) . ' — set a new password to continue.'
    : 'Change your account password.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - THAMANI ACADEMY - Kakiri</title>
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
        .strength-bar { transition: width .3s ease, background-color .3s ease; }
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

    <main class="flex-grow flex items-center justify-center py-16 px-4">
        <div class="w-full max-w-lg">

            <div class="bg-white rounded-2xl shadow-2xl border-t-4 border-brand-maroon overflow-hidden">

                <div class="bg-white px-8 pt-8 pb-6 text-center border-b border-gray-100">
                    <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-4 bg-brand-maroon/10 text-brand-maroon">
                        <i data-lucide="<?= $isForced ? 'key-round' : 'shield-check' ?>" class="w-8 h-8"></i>
                    </div>
                    <?php if ($isForced): ?>
                        <span class="bg-brand-maroon text-white px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest">First Login — Action Required</span>
                    <?php else: ?>
                        <span class="bg-brand-green text-white px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest">Account Security</span>
                    <?php endif; ?>
                    <h1 class="text-2xl font-bold text-brand-green mt-3"><?= htmlspecialchars($pageTitle) ?></h1>
                    <p class="text-gray-500 text-sm mt-2"><?= $headingText ?></p>
                </div>

                <form method="post" class="p-8 space-y-5" novalidate>

                    <?php if (!empty($errors)): ?>
                        <div class="bg-red-50 border border-red-200 text-red-800 text-xs font-semibold px-4 py-3 rounded-lg flex items-start gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <ul class="list-disc list-inside space-y-0.5">
                                <?php foreach ($errors as $e): ?>
                                    <li><?= htmlspecialchars($e, ENT_QUOTES) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-lg flex items-start gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <span>Password updated successfully.</span>
                        </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Staff ID</label>
                        <input type="text" value="<?= htmlspecialchars($prefilled_id ?? '', ENT_QUOTES) ?>" readonly
                               class="w-full px-4 py-3 border border-gray-200 bg-gray-50 text-gray-500 rounded-lg text-sm cursor-not-allowed">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">
                            <?= $isForced ? 'Default Password' : 'Current Password' ?> <span class="text-brand-maroon">*</span>
                        </label>
                        <div class="relative">
                            <i data-lucide="lock" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                            <input type="password" name="current_password" id="current_password" required autocomplete="current-password"
                                   placeholder="<?= $isForced ? 'Enter the default password given by admin' : 'Enter your current password' ?>"
                                   class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:border-brand-maroon outline-none transition-all">
                            <button type="button" onclick="togglePwd('current_password', this);"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">New Password <span class="text-brand-maroon">*</span></label>
                        <div class="relative">
                            <i data-lucide="key-round" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                            <input type="password" name="new_password" id="new_password" required autocomplete="new-password"
                                   oninput="updateStrength(this.value);"
                                   placeholder="At least 8 characters"
                                   class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:border-brand-maroon outline-none transition-all">
                            <button type="button" onclick="togglePwd('new_password', this);"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>

                        <!-- Strength meter -->
                        <div class="mt-2">
                            <div class="h-1.5 w-full bg-gray-200 rounded-full overflow-hidden">
                                <div id="strength-bar" class="strength-bar h-full w-0 bg-red-400"></div>
                            </div>
                            <p id="strength-label" class="text-[11px] text-gray-500 mt-1">Must contain uppercase, lowercase, number, and symbol.</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Confirm New Password <span class="text-brand-maroon">*</span></label>
                        <div class="relative">
                            <i data-lucide="lock" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                            <input type="password" name="confirm_password" id="confirm_password" required autocomplete="new-password"
                                   placeholder="Re-enter new password"
                                   class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:border-brand-maroon outline-none transition-all">
                            <button type="button" onclick="togglePwd('confirm_password', this);"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>

                    <div class="pt-2 flex flex-col sm:flex-row gap-3">
                        <button type="submit"
                                class="flex-1 py-3.5 bg-brand-maroon text-white font-bold rounded-lg hover:bg-red-900 transition-colors text-sm shadow flex items-center justify-center gap-2">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            <?= $isForced ? 'Set Password & Continue' : 'Update Password' ?>
                        </button>

                        <?php if (!$isForced): ?>
                            <a href="teacher_dashboard.php"
                               class="sm:w-40 py-3.5 text-center border-2 border-gray-200 text-gray-600 font-bold rounded-lg hover:bg-gray-50 transition-colors text-sm">
                                Cancel
                            </a>
                        <?php else: ?>
                            <a href="teacher_logout.php"
                               class="sm:w-40 py-3.5 text-center border-2 border-gray-200 text-gray-600 font-bold rounded-lg hover:bg-gray-50 transition-colors text-sm">
                                Logout
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

            </div>

            <p class="text-center text-xs text-gray-500 mt-6">
                Logged in as <strong><?= htmlspecialchars($teacher['name']) ?></strong> (<?= htmlspecialchars($teacher['staff_id']) ?>)
            </p>

        </div>
    </main>

    <script>
        lucide.createIcons();
        window.addEventListener('load', () => {
            setTimeout(() => document.getElementById('page-loader')?.classList.add('hidden'), 400);
        });

        function togglePwd(id, btn) {
            const el = document.getElementById(id);
            const show = el.type === 'password';
            el.type = show ? 'text' : 'password';
            btn.innerHTML = `<i data-lucide="${show ? 'eye-off' : 'eye'}" class="w-4 h-4"></i>`;
            lucide.createIcons();
        }

        function updateStrength(val) {
            const bar = document.getElementById('strength-bar');
            const label = document.getElementById('strength-label');
            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[a-z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            const pct = (score / 5) * 100;
            bar.style.width = pct + '%';

            if (score <= 2)      { bar.style.backgroundColor = '#EF4444'; label.textContent = 'Weak password'; }
            else if (score === 3){ bar.style.backgroundColor = '#F59E0B'; label.textContent = 'Fair password'; }
            else if (score === 4){ bar.style.backgroundColor = '#10B981'; label.textContent = 'Good password'; }
            else                 { bar.style.backgroundColor = '#1A472A'; label.textContent = 'Strong password'; }
        }
    </script>
</body>
</html>