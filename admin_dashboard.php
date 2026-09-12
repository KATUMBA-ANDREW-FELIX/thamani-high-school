<?php
require_once 'auth_admin.php';
require_admin_login();

$admin = current_admin();
$hour  = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

require_once 'conn.php';

// ---------- Stat counters ----------
function safeCount($conn, string $sql): int {
    $res = mysqli_query($conn, $sql);
    if (!$res) return 0;
    $row = mysqli_fetch_assoc($res);
    return $row ? (int)reset($row) : 0;
}

$totalStudents  = safeCount($conn, "SELECT COUNT(*) FROM students");
$totalAlumni    = safeCount($conn, "SELECT COUNT(*) FROM alumni");
$totalBooks     = safeCount($conn, "SELECT COUNT(*) FROM library_resources WHERE is_active = 1");
$totalPhotos    = safeCount($conn, "SELECT COUNT(*) FROM gallery_photos WHERE is_active = 1");
$totalCalendar  = safeCount($conn, "SELECT COUNT(*) FROM calendar_documents WHERE is_active = 1");
$pendingStudents= safeCount($conn, "SELECT COUNT(*) FROM students WHERE status = 'Pending'");
$totalTeachers  = safeCount($conn, "SELECT COUNT(*) FROM teachers");

// ---------- Recent activity ----------
$recentStudents = [];
$rs = mysqli_query($conn, "SELECT full_name, class_level, stream, status, registered_at FROM students ORDER BY registered_at DESC LIMIT 5");
if ($rs) while ($r = mysqli_fetch_assoc($rs)) $recentStudents[] = $r;

$recentAlumni = [];
$ra = mysqli_query($conn, "SELECT name, year, profession, email, id FROM alumni ORDER BY id DESC LIMIT 10");
if ($ra) while ($r = mysqli_fetch_assoc($ra)) $recentAlumni[] = $r;

// ---------- Flash messages ----------
$flashHtml = '';
if (!empty($_SESSION['admin_flash'])) {
    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
    $colorMap = [
        'success' => ['bg-green-50', 'border-green-200', 'text-green-800'],
        'error'   => ['bg-red-50',   'border-red-200',   'text-red-800'],
        'info'    => ['bg-blue-50',  'border-blue-200',  'text-blue-800'],
    ];
    [$bg, $border, $text] = $colorMap[$flash['type']] ?? $colorMap['info'];
    $safeMsg = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
    $flashHtml = <<<HTML
        <div class="mb-6 {$bg} border {$border} {$text} px-5 py-4 rounded-xl text-sm font-semibold flex items-start gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <span>{$safeMsg}</span>
        </div>
    HTML;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - THAMANI HIGH SCHOOL - Kakiri</title>
    <link rel="icon" type="image/ico" href="favicon.ico" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { brand: {
                slate: '#1F2937', charcoal: '#111827', gold: '#D4AF37',
                darkGold: '#B8860B', lightGold: '#FEF3C7', lightGrey: '#F3F4F6',
                green: '#1F2937', darkGreen: '#111827', lightGreen: '#F3F4F6',
                maroon: '#800000', lightMaroon: '#FDF2F2'
            }}}}
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .tab-btn.active {
            background-color: #1F2937 !important;
            color: #D4AF37 !important;
            border-color: #D4AF37 !important;
            box-shadow: 0 4px 12px rgba(31, 41, 55, 0.15);
        }
        .tab-btn.active i { color: #D4AF37 !important; }
        #page-loader {
            position: fixed; inset: 0; background: #1F2937;
            display: flex; align-items: center; justify-content: center;
            z-index: 9999; transition: opacity .3s ease, visibility .3s ease;
        }
        #page-loader.hidden { opacity: 0; visibility: hidden; pointer-events: none; display: none !important; }
    </style>
</head>
<body class="bg-gray-50/70 text-gray-900 font-sans flex flex-col min-h-screen">

    <div id="page-loader">
        <div class="text-center">
            <img src="thamani-logo.png" alt="Thamani High School"
                 class="h-20 w-auto mx-auto mb-4 animate-pulse" onerror="this.style.display='none'">
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
    <div class="bg-gray-950 text-white text-xs py-2.5 px-6 border-b border-brand-gold/30">
        <div class="max-w-7xl mx-auto w-full flex justify-between items-center">
            <span class="flex items-center gap-2">📍 THAMANI HIGH SCHOOL - Kakiri Main Campus, Wakiso District, Uganda</span>
            <span class="hidden sm:inline text-gray-300">📞 Enquiries: +256 414 123 456 | ✉️ info@thamani.ac.ug</span>
            <span class="bg-brand-gold text-gray-950 px-3 py-0.5 rounded-full font-extrabold uppercase tracking-wider text-[10px] shadow-sm">Admin Session</span>
        </div>
    </div>

    <!-- Main Navigation Bar -->
    <nav class="sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-gray-200/80 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20 items-center">
                <div class="flex items-center">
                    <a href="admin_dashboard.php" class="-ml-3 flex-shrink-0 flex items-center gap-3 group">
                        <img class="h-12 w-auto transition-transform group-hover:scale-105" src="thamani-logo.png" alt="Thamani High School Logo" onerror="this.src='favicon.svg'">
                        <div class="flex flex-col">
                            <span class="text-2xl font-black tracking-tight text-gray-900">Thamani High School</span>
                            <span class="text-[11px] font-bold text-brand-gold tracking-widest uppercase flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 inline-block animate-ping"></span>
                                Admin Control Panel
                            </span>
                        </div>
                    </a>
                </div>
                <div class="hidden lg:flex items-center gap-3 ml-auto">
                    <a href="admin_dashboard.php" class="nav-link whitespace-nowrap px-3.5 py-2.5 rounded-xl text-xs font-extrabold transition-all text-gray-900 bg-white border border-gray-300 shadow-sm hover:bg-gray-900 hover:text-white hover:border-gray-900">Dashboard</a>
                    <a href="enrollment_view.php" class="nav-link whitespace-nowrap px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all text-gray-700 hover:bg-gray-900 hover:text-white">Enrollment</a>
                    <a href="alumni_view.php" class="nav-link whitespace-nowrap px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all text-gray-700 hover:bg-gray-900 hover:text-white">Alumni</a>
                    <button type="button" onclick="switchAdminTab('tab-admin-teachers');" class="nav-link whitespace-nowrap px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all text-gray-700 hover:bg-gray-900 hover:text-white">Teachers</button>
                    <button type="button" onclick="openModal('modal-upload-gallery');" class="nav-link whitespace-nowrap px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all text-gray-700 hover:bg-gray-900 hover:text-white">Gallery</button>
                    <button type="button" onclick="openModal('modal-upload-calendar');" class="nav-link whitespace-nowrap px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all text-gray-700 hover:bg-gray-900 hover:text-white">Calendar & Fees</button>
                </div>
                <div class="hidden md:flex items-center gap-3 flex-shrink-0">
                    <div class="hidden xl:flex flex-col text-right mr-2">
                        <span class="text-[11px] text-gray-400 font-semibold uppercase tracking-wider">Administrator</span>
                        <span class="text-xs font-bold text-gray-900"><?= htmlspecialchars($admin['name']) ?></span>
                    </div>
                    <button type="button" onclick="openModal('modal-admin-change-password');" class="px-3.5 py-2 rounded-xl text-xs font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors flex items-center gap-1.5 border border-gray-200">
                        <i data-lucide="key-round" class="w-3.5 h-3.5 text-amber-600"></i> Password
                    </button>
                    <a href="admin_logout.php"
                       class="px-4 py-2 rounded-xl text-xs font-bold text-gray-900 bg-brand-gold hover:bg-yellow-400 transition-all flex items-center gap-1.5 shadow-sm active:scale-95">
                        <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Logout
                    </a>
                </div>
                <div class="lg:hidden flex items-center">
                    <button onclick="toggleMobileMenu();" class="inline-flex items-center justify-center p-2 rounded-xl text-gray-700 hover:text-gray-900 hover:bg-gray-100 focus:outline-none">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                </div>
            </div>
        </div>
        <div id="mobile-menu" class="hidden lg:hidden bg-white border-t border-gray-200 px-4 pt-3 pb-5 space-y-2">
            <a href="admin_dashboard.php" class="block px-4 py-2.5 rounded-xl text-sm font-bold text-gray-900 bg-white border border-gray-300 hover:bg-gray-900 hover:text-white">Dashboard</a>
            <a href="enrollment_view.php" class="block px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-800 hover:bg-gray-900 hover:text-white">Enrollment</a>
            <a href="alumni_view.php" class="block px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-800 hover:bg-gray-900 hover:text-white">Alumni</a>
            <button type="button" onclick="openModal('modal-upload-gallery');" class="block w-full text-left px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-800 hover:bg-gray-900 hover:text-white">Gallery</button>
            <button type="button" onclick="openModal('modal-upload-calendar');" class="block w-full text-left px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-800 hover:bg-gray-900 hover:text-white">Calendar & Fees</button>
            <div class="pt-3 border-t border-gray-100 flex flex-col gap-2">
                <a href="admin_logout.php" class="w-full py-2.5 rounded-xl font-bold text-gray-900 bg-brand-gold text-center">Logout</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow">
        <section class="py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Welcome Banner -->
            <div class="flex flex-wrap justify-between items-center mb-10 gap-6 bg-gradient-to-r from-gray-950 via-slate-900 to-gray-950 text-white p-8 md:p-10 rounded-3xl shadow-xl border-b-4 border-brand-gold">
                <div class="max-w-2xl">
                    <span class="bg-brand-gold text-gray-950 px-4 py-1 rounded-full text-xs font-black uppercase tracking-wider inline-block mb-2 shadow-sm">Admin Control Panel</span>
                    <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight text-white"><?= $greeting ?>, <?= htmlspecialchars($admin['name']) ?></h1>
                    <p class="text-sm text-gray-300 mt-2 leading-relaxed">Manage school operations seamlessly: student enrollments, teacher directory, alumni network, digital library, and academic schedules.</p>
                    <div class="flex items-center gap-4 text-xs text-gray-400 mt-4 pt-3 border-t border-gray-800">
                        <span>Admin ID: <strong class="text-amber-400 font-mono"><?= htmlspecialchars($admin['admin_id']) ?></strong></span>
                        <span>·</span>
                        <span>Last login: <strong class="text-gray-200"><?= date('d M Y, g:ia', $_SESSION['admin_logged_in_at']) ?></strong></span>
                    </div>
                </div>
                <div class="flex gap-3 flex-wrap items-center">
                    <button onclick="openModal('modal-add-teacher');" class="px-5 py-3 bg-brand-gold text-gray-950 font-black rounded-xl text-xs flex items-center gap-2 shadow-lg hover:bg-yellow-400 transition-all hover:-translate-y-0.5 active:scale-95">
                        <i data-lucide="user-plus" class="w-4 h-4"></i> Add Teacher
                    </button>
                    <button onclick="openModal('modal-upload-calendar');" class="px-5 py-3 bg-gray-800 text-white border border-amber-500/30 hover:bg-gray-700 font-bold rounded-xl text-xs flex items-center gap-2 shadow-md transition-all hover:-translate-y-0.5">
                        <i data-lucide="calendar-plus" class="w-4 h-4 text-amber-400"></i> Upload Calendar/Fees
                    </button>
                    <button onclick="openModal('modal-upload-gallery');" class="px-5 py-3 bg-white text-gray-900 font-bold rounded-xl text-xs flex items-center gap-2 shadow-md hover:bg-gray-100 transition-all">
                        <i data-lucide="image-plus" class="w-4 h-4 text-amber-600"></i> Add Gallery Photo
                    </button>
                </div>
            </div>

            <?= $flashHtml ?>

            <!-- Overview Stat Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-10">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200/80 hover:border-amber-400/50 hover:shadow-md transition-all">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 flex items-center justify-center">
                            <i data-lucide="users" class="w-6 h-6"></i>
                        </div>
                        <span class="text-[11px] font-extrabold text-gray-400 uppercase tracking-wider">Students</span>
                    </div>
                    <div class="text-3xl font-black text-gray-900"><?= $totalStudents ?></div>
                    <div class="text-xs text-gray-500 mt-2 flex justify-between items-center">
                        <span><?= $pendingStudents ?> pending</span>
                        <a href="enrollment_view.php" class="font-extrabold text-amber-600 hover:underline">View →</a>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200/80 hover:border-amber-400/50 hover:shadow-md transition-all">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-2xl bg-slate-100 text-gray-900 flex items-center justify-center">
                            <i data-lucide="user-cog" class="w-6 h-6"></i>
                        </div>
                        <span class="text-[11px] font-extrabold text-gray-400 uppercase tracking-wider">Teachers</span>
                    </div>
                    <div class="text-3xl font-black text-gray-900"><?= $totalTeachers ?></div>
                    <div class="text-xs text-gray-500 mt-2 flex justify-between items-center">
                        <span>Staff</span>
                        <button type="button" onclick="switchAdminTab('tab-admin-teachers');" class="font-extrabold text-amber-600 hover:underline">Manage →</button>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200/80 hover:border-amber-400/50 hover:shadow-md transition-all">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 flex items-center justify-center">
                            <i data-lucide="graduation-cap" class="w-6 h-6"></i>
                        </div>
                        <span class="text-[11px] font-extrabold text-gray-400 uppercase tracking-wider">Alumni</span>
                    </div>
                    <div class="text-3xl font-black text-gray-900"><?= $totalAlumni ?></div>
                    <div class="text-xs text-gray-500 mt-2 flex justify-between items-center">
                        <span>Registered</span>
                        <a href="alumni_view.php" class="font-extrabold text-amber-600 hover:underline">View →</a>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200/80 hover:border-amber-400/50 hover:shadow-md transition-all">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-2xl bg-slate-100 text-gray-900 flex items-center justify-center">
                            <i data-lucide="book-open" class="w-6 h-6"></i>
                        </div>
                        <span class="text-[11px] font-extrabold text-gray-400 uppercase tracking-wider">Library</span>
                    </div>
                    <div class="text-3xl font-black text-gray-900"><?= $totalBooks ?></div>
                    <div class="text-xs text-gray-500 mt-2 flex justify-between items-center">
                        <span>Resources</span>
                        <a href="library.php" class="font-extrabold text-amber-600 hover:underline">View →</a>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200/80 hover:border-amber-400/50 hover:shadow-md transition-all">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 flex items-center justify-center">
                            <i data-lucide="image" class="w-6 h-6"></i>
                        </div>
                        <span class="text-[11px] font-extrabold text-gray-400 uppercase tracking-wider">Gallery</span>
                    </div>
                    <div class="text-3xl font-black text-gray-900"><?= $totalPhotos ?></div>
                    <div class="text-xs text-gray-500 mt-2 flex justify-between items-center">
                        <span>Photos</span>
                        <button type="button" onclick="switchAdminTab('tab-admin-gallery');" class="font-extrabold text-amber-600 hover:underline">View →</button>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation Bar -->
            <div class="bg-gray-200/70 p-1.5 rounded-2xl flex flex-wrap gap-2 mb-10 border border-gray-300/60 shadow-inner">
                <button onclick="switchAdminTab('tab-admin-overview');" id="btn-tab-admin-overview" class="tab-btn active px-6 py-3 rounded-xl text-xs font-black tracking-wide uppercase transition-all flex items-center gap-2">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Overview
                </button>
                <button onclick="switchAdminTab('tab-admin-teachers');" id="btn-tab-admin-teachers" class="tab-btn px-6 py-3 rounded-xl text-xs font-bold text-gray-700 hover:text-gray-900 hover:bg-white/70 transition-all flex items-center gap-2">
                    <i data-lucide="user-cog" class="w-4 h-4"></i> Teachers & Staff
                </button>
                <button onclick="switchAdminTab('tab-admin-enrollment');" id="btn-tab-admin-enrollment" class="tab-btn px-6 py-3 rounded-xl text-xs font-bold text-gray-700 hover:text-gray-900 hover:bg-white/70 transition-all flex items-center gap-2">
                    <i data-lucide="user-plus" class="w-4 h-4"></i> Enrollments
                </button>
                <button onclick="switchAdminTab('tab-admin-alumni');" id="btn-tab-admin-alumni" class="tab-btn px-6 py-3 rounded-xl text-xs font-bold text-gray-700 hover:text-gray-900 hover:bg-white/70 transition-all flex items-center gap-2">
                    <i data-lucide="graduation-cap" class="w-4 h-4"></i> Alumni
                </button>
                <button onclick="switchAdminTab('tab-admin-calendar');" id="btn-tab-admin-calendar" class="tab-btn px-6 py-3 rounded-xl text-xs font-bold text-gray-700 hover:text-gray-900 hover:bg-white/70 transition-all flex items-center gap-2">
                    <i data-lucide="calendar-days" class="w-4 h-4"></i> Calendar & Fees
                </button>
                <button onclick="switchAdminTab('tab-admin-gallery');" id="btn-tab-admin-gallery" class="tab-btn px-6 py-3 rounded-xl text-xs font-bold text-gray-700 hover:text-gray-900 hover:bg-white/70 transition-all flex items-center gap-2">
                    <i data-lucide="image" class="w-4 h-4"></i> Gallery
                </button>
            </div>

            <!-- TAB 1: OVERVIEW -->
            <div id="tab-admin-overview" class="admin-tab-content space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- Recent Enrollments -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold text-brand-green flex items-center gap-2">
                                <i data-lucide="user-plus" class="w-5 h-5"></i> Latest Enrollments
                            </h3>
                            <a href="enrollment.php" class="text-xs font-bold text-brand-maroon hover:underline">See all →</a>
                        </div>
                        <?php if (empty($recentStudents)): ?>
                            <p class="text-sm text-gray-500 py-8 text-center">No enrollments yet.</p>
                        <?php else: ?>
                            <ul class="divide-y divide-gray-100">
                                <?php foreach ($recentStudents as $s): ?>
                                    <li class="py-3 flex justify-between items-center gap-3">
                                        <div class="min-w-0">
                                            <div class="font-bold text-brand-green truncate"><?= htmlspecialchars($s['full_name']) ?></div>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($s['class_level']) ?> · Stream <?= htmlspecialchars($s['stream']) ?></div>
                                        </div>
                                        <span class="text-xs font-bold px-2 py-1 rounded whitespace-nowrap
                                            <?= $s['status'] === 'Enrolled' ? 'bg-green-100 text-green-800' : ($s['status'] === 'Rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                            <?= htmlspecialchars($s['status']) ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Alumni -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold text-brand-green flex items-center gap-2">
                                <i data-lucide="graduation-cap" class="w-5 h-5"></i> Latest Alumni
                            </h3>
                            <a href="alumni_view.php" class="text-xs font-bold text-brand-maroon hover:underline">See all →</a>
                        </div>
                        <?php if (empty($recentAlumni)): ?>
                            <p class="text-sm text-gray-500 py-8 text-center">No alumni registrations yet.</p>
                        <?php else: ?>
                            <ul class="divide-y divide-gray-100">
                                <?php foreach ($recentAlumni as $a): ?>
                                    <li class="py-3">
                                        <div class="font-bold text-brand-green truncate"><?= htmlspecialchars($a['name']) ?></div>
                                        <div class="text-xs text-gray-500">Class of <?= htmlspecialchars((string)$a['year']) ?> · <?= htmlspecialchars($a['profession']) ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- Quick Actions -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-brand-green mb-4 flex items-center gap-2">
                        <i data-lucide="zap" class="w-5 h-5"></i> Quick Actions
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <button onclick="openModal('modal-add-teacher');" class="p-5 rounded-xl border border-gray-100 hover:border-brand-maroon/30 hover:shadow-md transition-all group text-left">
                            <i data-lucide="user-plus" class="w-8 h-8 text-purple-700 mb-3"></i>
                            <div class="font-bold text-gray-800 group-hover:text-purple-700">Add New Teacher</div>
                            <div class="text-xs text-gray-500 mt-1">Default password: Admin@2026</div>
                        </button>
                        <button onclick="openModal('modal-admin-change-password');" class="p-5 rounded-xl border border-gray-100 hover:border-brand-maroon/30 hover:shadow-md transition-all group text-left">
                            <i data-lucide="key-round" class="w-8 h-8 text-brand-maroon mb-3"></i>
                            <div class="font-bold text-gray-800 group-hover:text-brand-maroon">Change Password</div>
                            <div class="text-xs text-gray-500 mt-1">Update admin credentials</div>
                        </button>
                        <button onclick="openModal('modal-upload-calendar');" class="p-5 rounded-xl border border-gray-100 hover:border-brand-maroon/30 hover:shadow-md transition-all group text-left">
                            <i data-lucide="calendar-plus" class="w-8 h-8 text-brand-gold mb-3"></i>
                            <div class="font-bold text-gray-800 group-hover:text-brand-maroon">Upload Calendar/Fees</div>
                            <div class="text-xs text-gray-500 mt-1">Publish document</div>
                        </button>
                        <button onclick="openModal('modal-upload-gallery');" class="p-5 rounded-xl border border-gray-100 hover:border-brand-maroon/30 hover:shadow-md transition-all group text-left">
                            <i data-lucide="image-plus" class="w-8 h-8 text-blue-600 mb-3"></i>
                            <div class="font-bold text-gray-800 group-hover:text-brand-maroon">Add Gallery Photo</div>
                            <div class="text-xs text-gray-500 mt-1">Upload photo</div>
                        </button>
                        <a href="enrollment.php" class="p-5 rounded-xl border border-gray-100 hover:border-brand-maroon/30 hover:shadow-md transition-all group">
                            <i data-lucide="user-check" class="w-8 h-8 text-brand-green mb-3"></i>
                            <div class="font-bold text-gray-800 group-hover:text-brand-maroon">Review Enrollments</div>
                            <div class="text-xs text-gray-500 mt-1"><?= $pendingStudents ?> pending</div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- TAB: TEACHERS & STAFF -->
            <div id="tab-admin-teachers" class="admin-tab-content hidden space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex flex-wrap justify-between items-center mb-4 gap-3">
                        <div>
                            <h3 class="text-lg font-bold text-brand-green flex items-center gap-2">
                                <i data-lucide="user-cog" class="w-5 h-5"></i> Teachers & Academic Staff Roster
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">New teachers default password is <code class="bg-amber-100 text-brand-maroon px-1.5 py-0.5 rounded font-mono font-bold">Admin@2026</code>. They can log in to view attendance and classes.</p>
                        </div>
                        <button onclick="openModal('modal-add-teacher');" class="px-4 py-2 bg-brand-maroon text-white font-bold rounded-lg text-xs hover:bg-red-900 shadow flex items-center gap-1.5">
                            <i data-lucide="user-plus" class="w-4 h-4"></i> Add New Teacher
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-100 text-gray-700 font-bold border-b border-gray-200">
                                    <th class="p-3.5">Staff ID</th>
                                    <th class="p-3.5">Full Name</th>
                                    <th class="p-3.5">Email</th>
                                    <th class="p-3.5">Department</th>
                                    <th class="p-3.5">Status</th>
                                    <th class="p-3.5">Registered</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php
                                $teachRes = mysqli_query($conn, "SELECT staff_id, full_name, email, department, is_active, created_at FROM teachers ORDER BY created_at DESC");
                                if ($teachRes && mysqli_num_rows($teachRes) > 0):
                                    while ($t = mysqli_fetch_assoc($teachRes)):
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-3.5 font-mono text-xs font-bold text-brand-maroon"><?= htmlspecialchars($t['staff_id']) ?></td>
                                        <td class="p-3.5 font-bold text-brand-green"><?= htmlspecialchars($t['full_name']) ?></td>
                                        <td class="p-3.5 text-xs text-gray-600"><?= htmlspecialchars($t['email']) ?></td>
                                        <td class="p-3.5 text-xs font-semibold text-gray-700"><?= htmlspecialchars($t['department'] ?: 'Academic') ?></td>
                                        <td class="p-3.5">
                                            <span class="text-xs font-bold px-2 py-1 rounded <?= (int)$t['is_active'] === 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= (int)$t['is_active'] === 1 ? 'Active' : 'Disabled' ?>
                                            </span>
                                        </td>
                                        <td class="p-3.5 text-xs text-gray-500 font-mono"><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                                    </tr>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                    <tr><td colspan="7" class="p-8 text-center text-gray-500 text-sm">No teachers registered yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: RECENT ENROLLMENTS -->
            <div id="tab-admin-enrollment" class="admin-tab-content hidden space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-brand-green flex items-center gap-2">
                            <i data-lucide="user-plus" class="w-5 h-5"></i> Recently Enrolled Students
                        </h3>
                        <a href="enrollment.php" class="px-4 py-2 bg-brand-green text-white font-bold rounded-lg text-xs hover:bg-brand-darkGreen shadow flex items-center gap-1.5">
                            <i data-lucide="external-link" class="w-4 h-4"></i> Full Enrollment Portal
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-100 text-gray-700 font-bold border-b border-gray-200">
                                    <th class="p-3.5">Student Name</th>
                                    <th class="p-3.5">Class & Stream</th>
                                    <th class="p-3.5">Status</th>
                                    <th class="p-3.5">Registered</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php
                                $enrollRes = mysqli_query($conn, "SELECT full_name, class_level, stream, status, registered_at FROM students ORDER BY registered_at DESC LIMIT 15");
                                if ($enrollRes && mysqli_num_rows($enrollRes) > 0):
                                    while ($e = mysqli_fetch_assoc($enrollRes)):
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-3.5 font-bold text-brand-green"><?= htmlspecialchars($e['full_name']) ?></td>
                                        <td class="p-3.5"><?= htmlspecialchars($e['class_level']) ?> · <?= htmlspecialchars($e['stream']) ?></td>
                                        <td class="p-3.5">
                                            <span class="text-xs font-bold px-2 py-1 rounded
                                                <?= $e['status'] === 'Enrolled' ? 'bg-green-100 text-green-800' : ($e['status'] === 'Rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                                <?= htmlspecialchars($e['status']) ?>
                                            </span>
                                        </td>
                                        <td class="p-3.5 text-xs text-gray-500 font-mono"><?= date('d M Y', strtotime($e['registered_at'])) ?></td>
                                    </tr>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                    <tr><td colspan="4" class="p-8 text-center text-gray-500 text-sm">No student enrollments yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: RECENT ALUMNI -->
            <div id="tab-admin-alumni" class="admin-tab-content hidden space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-brand-green flex items-center gap-2">
                            <i data-lucide="graduation-cap" class="w-5 h-5"></i> Recently Registered Alumni
                        </h3>
                        <a href="alumni_view.php" class="px-4 py-2 bg-brand-green text-white font-bold rounded-lg text-xs hover:bg-brand-darkGreen shadow flex items-center gap-1.5">
                            <i data-lucide="external-link" class="w-4 h-4"></i> Full Alumni Registry
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-100 text-gray-700 font-bold border-b border-gray-200">
                                    <th class="p-3.5">Name</th>
                                    <th class="p-3.5">Year</th>
                                    <th class="p-3.5">Profession</th>
                                    <th class="p-3.5">Email</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php
                                $alumRes = mysqli_query($conn, "SELECT name, year, profession, email FROM alumni ORDER BY id DESC LIMIT 15");
                                if ($alumRes && mysqli_num_rows($alumRes) > 0):
                                    while ($a = mysqli_fetch_assoc($alumRes)):
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-3.5 font-bold text-brand-green"><?= htmlspecialchars($a['name']) ?></td>
                                        <td class="p-3.5">
                                            <span class="px-2.5 py-1 rounded bg-brand-lightGreen text-brand-green font-bold text-xs"><?= htmlspecialchars((string)$a['year']) ?></span>
                                        </td>
                                        <td class="p-3.5 text-gray-700"><?= htmlspecialchars($a['profession']) ?></td>
                                        <td class="p-3.5"><a href="mailto:<?= htmlspecialchars($a['email']) ?>" class="text-brand-maroon hover:underline"><?= htmlspecialchars($a['email']) ?></a></td>
                                    </tr>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                    <tr><td colspan="4" class="p-8 text-center text-gray-500 text-sm">No alumni registrations yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 4: CALENDAR & FEES -->
            <div id="tab-admin-calendar" class="admin-tab-content hidden space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-lg font-bold text-brand-green flex items-center gap-2">
                                <i data-lucide="calendar-days" class="w-5 h-5"></i> Published Calendar & Fees Documents
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">All documents uploaded by admins, visible to teachers, students & parents.</p>
                        </div>
                        <button onclick="openModal('modal-upload-calendar');" class="px-4 py-2 bg-brand-maroon text-white font-bold rounded-lg text-xs hover:bg-red-900 shadow flex items-center gap-1.5">
                            <i data-lucide="plus" class="w-4 h-4"></i> Upload Document
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-100 text-gray-700 font-bold border-b border-gray-200">
                                    <th class="p-3.5">Title</th>
                                    <th class="p-3.5">Type</th>
                                    <th class="p-3.5">Size</th>
                                    <th class="p-3.5">Uploaded</th>
                                    <th class="p-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php
                                $calRes = mysqli_query($conn, "SELECT id, title, doc_type, file_path, file_name, file_size, uploaded_at FROM calendar_documents WHERE is_active = 1 ORDER BY uploaded_at DESC");
                                if ($calRes && mysqli_num_rows($calRes) > 0):
                                    while ($c = mysqli_fetch_assoc($calRes)):
                                        $sz = (int)$c['file_size'];
                                        $szFmt = $sz >= 1048576 ? round($sz/1048576,1).' MB' : ($sz >= 1024 ? round($sz/1024,1).' KB' : $sz.' B');
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-3.5 font-bold text-brand-green"><?= htmlspecialchars($c['title']) ?></td>
                                        <td class="p-3.5"><span class="text-xs font-bold uppercase px-2 py-1 rounded bg-gray-100 text-gray-700"><?= htmlspecialchars($c['doc_type']) ?></span></td>
                                        <td class="p-3.5 text-xs text-gray-500"><?= $szFmt ?></td>
                                        <td class="p-3.5 text-xs text-gray-500 font-mono"><?= date('d M Y', strtotime($c['uploaded_at'])) ?></td>
                                        <td class="p-3.5 text-right">
                                            <a href="<?= htmlspecialchars($c['file_path']) ?>" target="_blank"
                                               class="inline-flex items-center gap-1 px-3 py-1.5 bg-brand-green text-white font-bold rounded text-xs hover:bg-brand-darkGreen">
                                                <i data-lucide="download" class="w-3 h-3"></i> Open
                                            </a>
                                        </td>
                                    </tr>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                    <tr><td colspan="5" class="p-8 text-center text-gray-500 text-sm">No calendar or fees documents uploaded yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 5: GALLERY -->
            <div id="tab-admin-gallery" class="admin-tab-content hidden space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-lg font-bold text-brand-green flex items-center gap-2">
                                <i data-lucide="image" class="w-5 h-5"></i> Gallery Photos
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">All photos published to the public gallery.</p>
                        </div>
                        <button onclick="openModal('modal-upload-gallery');" class="px-4 py-2 bg-brand-maroon text-white font-bold rounded-lg text-xs hover:bg-red-900 shadow flex items-center gap-1.5">
                            <i data-lucide="plus" class="w-4 h-4"></i> Upload Photo
                        </button>
                    </div>
                    <?php
                    $galRes = mysqli_query($conn, "SELECT id, title, caption, category, file_path, uploaded_at FROM gallery_photos WHERE is_active = 1 ORDER BY uploaded_at DESC LIMIT 24");
                    if ($galRes && mysqli_num_rows($galRes) > 0):
                    ?>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            <?php while ($g = mysqli_fetch_assoc($galRes)): ?>
                                <div class="bg-white rounded-xl overflow-hidden border border-gray-100 hover:shadow-md transition-shadow group">
                                    <img src="<?= htmlspecialchars($g['file_path']) ?>" alt="<?= htmlspecialchars($g['title']) ?>"
                                         class="w-full h-40 object-cover bg-gray-100" loading="lazy">
                                    <div class="p-3">
                                        <div class="text-sm font-bold text-brand-green truncate"><?= htmlspecialchars($g['title']) ?></div>
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            <span class="uppercase font-bold"><?= htmlspecialchars($g['category']) ?></span> ·
                                            <?= date('d M Y', strtotime($g['uploaded_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-gray-500 py-12 text-center">No gallery photos uploaded yet.</p>
                    <?php endif; ?>
                </div>
            </div>

        </section>
    </main>

    <!-- MODAL: UPLOAD CALENDAR / FEES -->
    <div id="modal-upload-calendar" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-brand-maroon flex items-center gap-2">
                    <i data-lucide="calendar-plus" class="w-6 h-6"></i> Upload Calendar / Fees
                </h3>
                <button onclick="closeModal('modal-upload-calendar');" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
          <form action="admin_upload_calendar.php" method="post" enctype="multipart/form-data" class="space-y-4">
    <input type="hidden" name="redirect_to" value="admin_dashboard.php">
    <div>
        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Document Title <span class="text-brand-maroon">*</span></label>          
        <input type="text" name="title" required maxlength="255" placeholder="e.g. Term III 2026 Fee Structure" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:outline-none">
                </div>


                
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Type <span class="text-brand-maroon">*</span></label>
                    <select name="doc_type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:outline-none">
                        <option value="calendar">Academic Calendar</option>
                        <option value="fees">Fee Structure</option>
                        <option value="timetable">Timetable</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Description <span class="text-gray-400 font-normal normal-case">(optional)</span></label>
                    <textarea name="description" rows="2" maxlength="500" placeholder="Brief description..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:outline-none resize-none"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">File <span class="text-brand-maroon">*</span></label>
                    <input type="file" name="doc_file" required accept=".pdf,.doc,.docx,.xls,.xlsx"
                           class="w-full text-xs text-gray-600 border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-brand-maroon">
                    <p class="text-[11px] text-gray-500 mt-1">Allowed: PDF, DOC, DOCX, XLS, XLSX. Max: 20 MB.</p>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 py-3 bg-brand-maroon text-white font-bold rounded-lg hover:bg-red-900 text-sm flex items-center justify-center gap-2">
                        <i data-lucide="upload" class="w-4 h-4"></i> Upload
                    </button>
                    <button type="button" onclick="closeModal('modal-upload-calendar');" class="w-32 py-3 border-2 border-gray-200 text-gray-600 font-bold rounded-lg hover:bg-gray-50 text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: UPLOAD GALLERY PHOTO -->
    <div id="modal-upload-gallery" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-brand-maroon flex items-center gap-2">
                    <i data-lucide="image-plus" class="w-6 h-6"></i> Upload Gallery Photo
                </h3>
                <button onclick="closeModal('modal-upload-gallery');" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            
           <form action="admin_upload_gallery.php" method="post" enctype="multipart/form-data" class="space-y-4">
    <input type="hidden" name="redirect_to" value="admin_dashboard.php">
    <div>
        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Photo Title <span class="text-brand-maroon">*</span></label>
                 
        <input type="text" name="title" required maxlength="255" placeholder="e.g. Cultural Gala 2026" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Category <span class="text-brand-maroon">*</span></label>
                    <select name="category" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:outline-none">
                        <option value="cultural">Cultural</option>
                        <option value="sports">Sports</option>
                        <option value="campus">Campus Life</option>
                        <option value="academic">Academic</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Caption <span class="text-gray-400 font-normal normal-case">(optional)</span></label>
                    <textarea name="caption" rows="2" maxlength="500" placeholder="Short caption..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:outline-none resize-none"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Image File <span class="text-brand-maroon">*</span></label>
                    <input type="file" name="photo_file" required accept="image/jpeg,image/png,image/webp,image/gif"
                           class="w-full text-xs text-gray-600 border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-brand-maroon">
                    <p class="text-[11px] text-gray-500 mt-1">Allowed: JPG, PNG, WEBP, GIF. Max: 8 MB.</p>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 py-3 bg-brand-maroon text-white font-bold rounded-lg hover:bg-red-900 text-sm flex items-center justify-center gap-2">
                        <i data-lucide="upload" class="w-4 h-4"></i> Upload Photo
                    </button>
                    <button type="button" onclick="closeModal('modal-upload-gallery');" class="w-32 py-3 border-2 border-gray-200 text-gray-600 font-bold rounded-lg hover:bg-gray-50 text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: ADD NEW TEACHER -->
    <div id="modal-add-teacher" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-brand-maroon flex items-center gap-2">
                    <i data-lucide="user-plus" class="w-6 h-6"></i> Add New Teacher / Staff
                </h3>
                <button onclick="closeModal('modal-add-teacher');" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <div class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded-lg text-xs mb-4">
                ℹ️ Default login password for new teachers is <strong class="font-mono text-brand-maroon text-sm">Admin@2026</strong>.
            </div>
            <form action="admin_add_teacher.php" method="post" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Teacher ID <span class="text-brand-maroon">*</span></label>
                    <input type="text" name="teacher_id" required value="TSC-2026-<?= sprintf('%03d', rand(2, 999)) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-brand-maroon focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Full Name <span class="text-brand-maroon">*</span></label>
                    <input type="text" name="full_name" required placeholder="e.g. Mr. Okello Joseph" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Email Address <span class="text-brand-maroon">*</span></label>
                    <input type="email" name="email" required placeholder="e.g. okello@thamani.ac.ug" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Subject Taught</label>
                    <input type="text" name="subject" placeholder="e.g. Mathematics & Physics" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Phone Number</label>
                    <input type="tel" name="phone" placeholder="e.g. +256 700 000 000" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:outline-none">
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 py-3 bg-brand-maroon text-white font-bold rounded-lg hover:bg-red-900 text-sm flex items-center justify-center gap-2">
                        <i data-lucide="user-plus" class="w-4 h-4"></i> Add Teacher
                    </button>
                    <button type="button" onclick="closeModal('modal-add-teacher');" class="w-32 py-3 border-2 border-gray-200 text-gray-600 font-bold rounded-lg hover:bg-gray-50 text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: ADMIN CHANGE PASSWORD -->
    <div id="modal-admin-change-password" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-brand-maroon flex items-center gap-2">
                    <i data-lucide="key-round" class="w-6 h-6"></i> Change Admin Password
                </h3>
                <button onclick="closeModal('modal-admin-change-password');" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <form action="admin_change_password.php" method="post" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Current Password <span class="text-brand-maroon">*</span></label>
                    <input type="password" name="current_password" required placeholder="Enter current password" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">New Password <span class="text-brand-maroon">*</span></label>
                    <input type="password" name="new_password" required minlength="6" placeholder="Enter new password (min 6 chars)" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Confirm New Password <span class="text-brand-maroon">*</span></label>
                    <input type="password" name="confirm_password" required minlength="6" placeholder="Confirm new password" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:outline-none">
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 py-3 bg-brand-maroon text-white font-bold rounded-lg hover:bg-red-900 text-sm flex items-center justify-center gap-2">
                        <i data-lucide="lock" class="w-4 h-4"></i> Update Password
                    </button>
                    <button type="button" onclick="closeModal('modal-admin-change-password');" class="w-32 py-3 border-2 border-gray-200 text-gray-600 font-bold rounded-lg hover:bg-gray-50 text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="bg-brand-green text-white pt-16 pb-10 mt-auto border-t-4 border-brand-gold">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-10 mb-12">
                <div>
                    <h3 class="text-2xl font-bold mb-4 flex items-center gap-2">
                        <img src="thamani-logo.png" class="h-10 w-auto" alt="Logo" onerror="this.src='favicon.svg'">
                        <span>Thamani High School</span>
                    </h3>
                    <p class="text-gray-300 text-sm leading-relaxed mb-6">Empowering the next generation of Ugandan leaders through excellence in education, culture, and character building.</p>
                    <div class="flex space-x-5 text-brand-gold">
                        <i data-lucide="facebook" class="w-6 h-6 cursor-pointer hover:text-white transition-colors"></i>
                        <i data-lucide="twitter" class="w-6 h-6 cursor-pointer hover:text-white transition-colors"></i>
                        <i data-lucide="instagram" class="w-6 h-6 cursor-pointer hover:text-white transition-colors"></i>
                    </div>
                </div>
                <div>
                    <h3 class="text-2xl font-bold mb-4">Quick Links</h3>
                    <ul class="space-y-3 text-gray-300 text-sm">
                        <li><a href="admin_dashboard.php" class="hover:text-brand-gold transition-colors">Admin Dashboard</a></li>
                        <li><a href="enrollment.php" class="hover:text-brand-gold transition-colors">Enrollment Portal</a></li>
                        <li><a href="alumni_view.php" class="hover:text-brand-gold transition-colors">Alumni Registry</a></li>
                        <li><button type="button" onclick="openModal('modal-upload-gallery');" class="hover:text-brand-gold transition-colors">Gallery</button></li>
                        <li><button type="button" onclick="openModal('modal-upload-calendar');" class="hover:text-brand-gold transition-colors">Calendar & Fees</button></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-2xl font-bold mb-4">Contact Us</h3>
                    <ul class="space-y-4 text-gray-300 text-sm">
                        <li class="flex items-center gap-3"><i data-lucide="map-pin" class="w-5 h-5 text-brand-gold"></i><span>Plot 45, Education Road, Kampala, Uganda</span></li>
                        <li class="flex items-center gap-3"><i data-lucide="phone" class="w-5 h-5 text-brand-gold"></i><span>+256 414 123 456</span></li>
                        <li class="flex items-center gap-3"><i data-lucide="mail" class="w-5 h-5 text-brand-gold"></i><span>info@thamani.ac.ug</span></li>
                    </ul>
                </div>
            </div>
            <div class="pt-8 border-t border-brand-darkGreen text-center text-gray-400 text-sm flex justify-between items-center">
                <span>© 2026 Thamani High School. All rights reserved.</span>
                <span>Admin · <?= htmlspecialchars($admin['admin_id']) ?></span>
            </div>
        </div>
    </footer>

    <script src="app.js"></script>
    <script>
        lucide.createIcons();
        const hideLoader = () => document.getElementById('page-loader')?.classList.add('hidden');
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', hideLoader);
        } else {
            hideLoader();
        }
        setTimeout(hideLoader, 100);

        function toggleMobileMenu() {
            document.getElementById('mobile-menu')?.classList.toggle('hidden');
        }

        function openModal(id)  { document.getElementById(id)?.classList.remove('hidden'); }
        function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }

        function switchAdminTab(tabId) {
            document.querySelectorAll('.admin-tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active', 'bg-brand-maroon', 'text-white');
                btn.classList.add('text-gray-700');
            });

            document.getElementById(tabId)?.classList.remove('hidden');
            const btn = document.getElementById('btn-' + tabId);
            if (btn) {
                btn.classList.add('active', 'bg-brand-maroon', 'text-white');
                btn.classList.remove('text-gray-700');
            }
            lucide.createIcons();
        }
    </script>
</body>
</html>