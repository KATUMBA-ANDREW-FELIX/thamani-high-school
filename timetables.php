<?php
/**
 * Thamani High School - Public Class & Stream Timetable Viewer
 * Accessible to students, parents, and visitors (No login required).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('format_thamani_date')) {
    function format_thamani_date($value, string $format = 'd M Y'): string {
        if ($value === null || $value === '') {
            return 'N/A';
        }

        $timestamp = strtotime((string)$value);
        return $timestamp !== false ? date($format, $timestamp) : 'N/A';
    }
}
require_once 'conn.php';

$isStudent = !empty($_SESSION['student_id']);
$isTeacher = !empty($_SESSION['teacher_id']);
$isAdmin   = !empty($_SESSION['admin_id']);
$isLoggedIn = $isStudent || $isTeacher || $isAdmin;

if (!$isLoggedIn) {
    header('Location: login.php');
    exit;
}

$canSendSms = false;
if ($isTeacher) {
    $permission = thamani_db_prepare($conn, "SELECT id FROM teachers WHERE id = ? AND is_active = 1 AND can_send_sms = 1 LIMIT 1");
    if ($permission) {
        $teacherId = (int)$_SESSION['teacher_id'];
        thamani_db_stmt_bind_param($permission, 'i', $teacherId);
        thamani_db_stmt_execute($permission);
        $permissionResult = thamani_db_stmt_get_result($permission);
        $canSendSms = (bool)($permissionResult && thamani_db_fetch_assoc($permissionResult));
        thamani_db_stmt_close($permission);
    }
}

$dashboardLink = 'home.php';
$logoutLink    = '#';
$viewerName    = '';

if ($isStudent) {
    $dashboardLink = 'student-dashboard.php';
    $logoutLink    = 'student_logout.php';
    $viewerName    = $_SESSION['student_name'] ?? 'Student';
} elseif ($isTeacher) {
    $dashboardLink = 'teacher_dashboard.php';
    $logoutLink    = 'teacher_logout.php';
    $viewerName    = $_SESSION['teacher_name'] ?? 'Teacher';
} elseif ($isAdmin) {
    $dashboardLink = 'admin_dashboard.php';
    $logoutLink    = 'admin_logout.php';
    $viewerName    = $_SESSION['admin_name'] ?? 'Admin';
}

$classFilter  = trim($_GET['class'] ?? 'Senior 4');
$streamFilter = trim($_GET['stream'] ?? 'All Streams');

// Fetch timetables
$timetables = [];
$sql = "SELECT id, title, class_level, stream, schedule_json, file_name, file_path, file_size, created_at 
        FROM class_timetables 
        WHERE is_active = 1 ";

$params = [];
$types = "";

if ($classFilter !== 'ALL') {
    $sql .= "AND (class_level = ? OR class_level = 'ALL') ";
    $params[] = $classFilter;
    $types .= "s";
}
if ($streamFilter !== 'ALL') {
    $sql .= "AND (stream = ? OR stream = 'All Streams' OR stream = 'ALL') ";
    $params[] = $streamFilter;
    $types .= "s";
}

$sql .= "ORDER BY created_at DESC";

$stmt = thamani_db_prepare($conn, $sql);
if ($stmt) {
    if (!empty($params)) {
        thamani_db_stmt_bind_param($stmt, $types, ...$params);
    }
    thamani_db_stmt_execute($stmt);
    $res = thamani_db_stmt_get_result($stmt);
    if ($res) {
        while ($row = thamani_db_fetch_assoc($res)) {
            $timetables[] = $row;
        }
    }
    thamani_db_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Timetables & Schedules - THAMANI HIGH SCHOOL</title>
    <link rel="icon" type="image/ico" href="favicon.ico" />
    <link rel="stylesheet" href="css/tailwind.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gray-50 text-gray-900 font-sans flex flex-col min-h-screen">

    <!-- Top Announcement Bar -->
    <div class="bg-brand-maroon text-white text-xs py-2 px-4 text-center font-medium">
        <div class="max-w-7xl mx-auto w-full flex justify-between items-center">
            <span>📍 THAMANI HIGH SCHOOL - Kakiri Main Campus, Wakiso District, Uganda</span>
            <span class="hidden sm:inline">📞 Enquiries: +256 414 123 456 | ✉️ info@thamani.ac.ug</span>
            <span class="bg-brand-gold text-brand-green px-2.5 py-0.5 rounded font-bold uppercase tracking-wider text-[10px]">Term III 2026 Timetables</span>
        </div>
    </div>

    <!-- Main Navigation Bar -->
    <nav class="sticky top-0 z-50 bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20 items-center">
                <div class="flex items-center">
                    <a href="home.php" class="flex-shrink-0 flex items-center gap-3">
                        <img class="h-12 w-auto" src="thamani-logo.png" alt="Thamani High School Logo" onerror="this.src='favicon.svg'">
                        <div class="flex flex-col">
                            <span class="text-2xl font-bold tracking-tight text-brand-green">Thamani High School</span>
                            <span class="text-[10px] font-semibold text-brand-maroon tracking-widest uppercase">Class Timetables & Routines</span>
                        </div>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden lg:flex items-center space-x-1 font-medium text-sm">
                    <?php if ($isTeacher): ?>
                        <a href="students_view.php" class="nav-link px-3 py-2 rounded-md transition-colors text-gray-700 hover:text-brand-green">Student Roster</a>
                        <a href="timetables.php" class="nav-link px-3 py-2 rounded-md transition-colors text-gray-700 hover:text-brand-green">Class Timetables</a>
                        <?php if ($canSendSms): ?>
                            <a href="sms.php" class="nav-link px-3 py-2 rounded-md text-sm font-bold transition-colors text-rose-700 hover:text-rose-900">SMS Messaging</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Right-side Actions: Smart Dashboard/Logout (Shown only when logged in) -->
                <?php if ($isLoggedIn): ?>
                    <div class="hidden md:flex items-center space-x-2">
                        <span class="text-xs text-gray-600 hidden xl:inline font-medium">
                            Signed in as <strong class="text-brand-green"><?= htmlspecialchars($viewerName) ?></strong>
                        </span>
                        <a href="<?= $dashboardLink ?>"
                           class="px-3.5 py-2 rounded-md text-xs font-bold text-white bg-brand-green hover:bg-brand-darkGreen transition-colors shadow flex items-center gap-1.5">
                            <i data-lucide="layout-dashboard" class="w-4 h-4 text-brand-gold"></i> Back to Dashboard
                        </a>
                        <a href="<?= $logoutLink ?>"
                           class="px-3.5 py-2 rounded-md text-xs font-bold text-white bg-brand-maroon hover:bg-red-900 transition-colors shadow flex items-center gap-1.5">
                            <i data-lucide="log-out" class="w-4 h-4"></i> Logout
                        </a>
                    </div>
                <?php endif; ?>

                <div class="lg:hidden flex items-center">
                    <button type="button" onclick="toggleMobileMenu();" class="inline-flex items-center justify-center p-2 rounded-md text-gray-700 hover:text-brand-green hover:bg-gray-100 focus:outline-none" aria-label="Toggle navigation menu">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                </div>
            </div>
        </div>
        <div id="mobile-menu" class="hidden lg:hidden bg-white border-t border-gray-200 px-4 pt-2 pb-4 space-y-2">
            <?php if ($isTeacher): ?>
                <a href="students_view.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Student Roster</a>
                <a href="timetables.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Class Timetables</a>
                <?php if ($canSendSms): ?>
                    <a href="sms.php" class="block px-3 py-2 rounded-md text-base font-bold text-rose-700 hover:bg-rose-50">SMS Messaging</a>
                <?php endif; ?>
            <?php endif; ?>
            <div class="pt-2 border-t border-gray-100 flex flex-col gap-2">
                <a href="<?= htmlspecialchars($dashboardLink, ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Back to Dashboard</a>
                <a href="<?= htmlspecialchars($logoutLink, ENT_QUOTES, 'UTF-8') ?>" class="w-full py-2.5 rounded-md font-bold text-white bg-brand-maroon text-center">Logout</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">

        <!-- Banner -->
        <div class="mb-8 p-8 rounded-2xl bg-brand-green text-white shadow-lg flex flex-wrap justify-between items-center gap-4 border-b-4 border-brand-gold">
            <div>
                <span class="bg-brand-gold text-brand-green px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider">Official School Timetables</span>
                <h1 class="text-3xl font-extrabold mt-2 flex items-center gap-2">
                    <i data-lucide="clock" class="w-8 h-8 text-brand-gold"></i> Class & Stream Schedule Portal
                </h1>
                <p class="text-sm text-gray-200 mt-1">View weekly class schedules, period subject distributions (Monday – Sunday), and global school routine programs.</p>
            </div>
        </div>

        <!-- Filter Controls -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-8">
            <form method="get" action="timetables.php" class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Select Class / Form</label>
                    <select name="class" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-brand-green">
                        <option value="ALL" <?= $classFilter === 'ALL' ? 'selected' : '' ?>>All Classes</option>
                        <option value="Senior 1" <?= $classFilter === 'Senior 1' ? 'selected' : '' ?>>Senior 1 / Form 1</option>
                        <option value="Senior 2" <?= $classFilter === 'Senior 2' ? 'selected' : '' ?>>Senior 2 / Form 2</option>
                        <option value="Senior 3" <?= $classFilter === 'Senior 3' ? 'selected' : '' ?>>Senior 3 / Form 3</option>
                        <option value="Senior 4" <?= $classFilter === 'Senior 4' ? 'selected' : '' ?>>Senior 4 / Form 4</option>
                        <option value="Senior 5" <?= $classFilter === 'Senior 5' ? 'selected' : '' ?>>Senior 5 / Form 5</option>
                        <option value="Senior 6" <?= $classFilter === 'Senior 6' ? 'selected' : '' ?>>Senior 6 / Form 6</option>
                    </select>
                </div>

                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Select Stream</label>
                    <select name="stream" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-brand-green">
                        <option value="ALL" <?= $streamFilter === 'ALL' ? 'selected' : '' ?>>All Streams</option>
                        <option value="All Streams" <?= $streamFilter === 'All Streams' ? 'selected' : '' ?>>Entire Class Level</option>
                        <option value="North" <?= $streamFilter === 'North' ? 'selected' : '' ?>>Stream North</option>
                        <option value="South" <?= $streamFilter === 'South' ? 'selected' : '' ?>>Stream South</option>
                        <option value="East" <?= $streamFilter === 'East' ? 'selected' : '' ?>>Stream East</option>
                        <option value="West" <?= $streamFilter === 'West' ? 'selected' : '' ?>>Stream West</option>
                    </select>
                </div>

                <button type="submit" class="px-6 py-2.5 bg-brand-green text-white font-extrabold rounded-xl text-sm hover:bg-brand-darkGreen shadow flex items-center gap-2">
                    <i data-lucide="filter" class="w-4 h-4"></i> Filter Timetable
                </button>
            </form>
        </div>

        <!-- Timetable Results -->
        <?php if (!empty($timetables)): ?>
            <div class="space-y-10">
                <?php foreach ($timetables as $tt): ?>
                    <?php 
                        $slots = json_decode($tt['schedule_json'] ?: '[]', true) ?: [];
                    ?>
                    <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 space-y-6">
                        <div class="flex flex-wrap justify-between items-center gap-4 border-b border-gray-100 pb-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 rounded-full bg-brand-lightGreen text-brand-green font-extrabold text-xs">
                                        <?= htmlspecialchars($tt['class_level']) ?> (<?= htmlspecialchars($tt['stream']) ?>)
                                    </span>
                                    <span class="text-xs font-mono text-gray-400">Published: <?= format_thamani_date($tt['created_at'] ?? null) ?></span>
                                </div>
                                <h2 class="text-2xl font-bold text-gray-900 mt-1"><?= htmlspecialchars($tt['title']) ?></h2>
                            </div>

                            <?php if (!empty($tt['file_path'])): ?>
                                <a href="<?= htmlspecialchars($tt['file_path']) ?>" target="_blank" download class="px-5 py-2.5 bg-brand-gold text-brand-green font-bold rounded-xl text-xs shadow hover:bg-yellow-400 flex items-center gap-2">
                                    <i data-lucide="download" class="w-4 h-4"></i> Download Official Document (<?= htmlspecialchars($tt['file_name'] ?: 'PDF/Doc') ?>)
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($slots)): ?>
                            <div class="overflow-x-auto border border-gray-200 rounded-2xl shadow-inner">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr class="bg-brand-green text-white font-bold">
                                            <th class="p-3.5 min-w-[150px]">Time Slot</th>
                                            <th class="p-3.5 min-w-[110px]">Monday</th>
                                            <th class="p-3.5 min-w-[110px]">Tuesday</th>
                                            <th class="p-3.5 min-w-[110px]">Wednesday</th>
                                            <th class="p-3.5 min-w-[110px]">Thursday</th>
                                            <th class="p-3.5 min-w-[110px]">Friday</th>
                                            <th class="p-3.5 min-w-[110px]">Saturday</th>
                                            <th class="p-3.5 min-w-[110px]">Sunday</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($slots as $s): ?>
                                            <?php if (!empty($s['is_global_program'])): ?>
                                                <tr class="bg-amber-100/90 font-bold text-amber-950">
                                                    <td class="p-3.5 font-mono text-xs text-amber-900 border-r border-amber-200/50"><?= htmlspecialchars($s['time']) ?></td>
                                                    <td colspan="7" class="p-3.5 text-center uppercase tracking-wider text-amber-950 bg-amber-200/60 font-extrabold">
                                                        ⭐ <?= htmlspecialchars($s['program_title'] ?: 'Global School Program') ?> (All Classes)
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="p-3.5 font-bold text-gray-700 bg-gray-50 font-mono border-r border-gray-200"><?= htmlspecialchars($s['time']) ?></td>
                                                    <td class="p-3.5 <?= !empty($s['mon']) ? 'font-bold text-brand-green bg-green-50/50' : 'text-gray-400' ?>"><?= htmlspecialchars($s['mon'] ?: '—') ?></td>
                                                    <td class="p-3.5 <?= !empty($s['tue']) ? 'font-bold text-brand-green bg-green-50/50' : 'text-gray-400' ?>"><?= htmlspecialchars($s['tue'] ?: '—') ?></td>
                                                    <td class="p-3.5 <?= !empty($s['wed']) ? 'font-bold text-brand-green bg-green-50/50' : 'text-gray-400' ?>"><?= htmlspecialchars($s['wed'] ?: '—') ?></td>
                                                    <td class="p-3.5 <?= !empty($s['thu']) ? 'font-bold text-brand-green bg-green-50/50' : 'text-gray-400' ?>"><?= htmlspecialchars($s['thu'] ?: '—') ?></td>
                                                    <td class="p-3.5 <?= !empty($s['fri']) ? 'font-bold text-brand-green bg-green-50/50' : 'text-gray-400' ?>"><?= htmlspecialchars($s['fri'] ?: '—') ?></td>
                                                    <td class="p-3.5 <?= !empty($s['sat']) ? 'font-semibold text-gray-800 bg-yellow-50/40' : 'text-gray-400' ?>"><?= htmlspecialchars($s['sat'] ?: '—') ?></td>
                                                    <td class="p-3.5 <?= !empty($s['sun']) ? 'font-semibold text-gray-800 bg-blue-50/40' : 'text-gray-400' ?>"><?= htmlspecialchars($s['sun'] ?: '—') ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="p-8 text-center text-gray-500 bg-gray-50 rounded-xl">
                                Detailed matrix available via document download attachment.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white p-16 rounded-2xl shadow-sm border border-gray-100 text-center space-y-3">
                <i data-lucide="clock" class="w-12 h-12 text-gray-300 mx-auto"></i>
                <h3 class="text-xl font-bold text-gray-800">No Timetables Found</h3>
                <p class="text-gray-500 text-sm">No timetables have been published for the selected class and stream yet.</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-brand-green text-white pt-12 pb-8 mt-auto border-t-4 border-brand-gold">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-gray-300">
            <p>© 2026 Thamani High School - Kakiri Campus. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function toggleMobileMenu() {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        }

        lucide.createIcons();
    </script>
</body>
</html>
