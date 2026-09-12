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

$totalStudents   = safeCount($conn, "SELECT COUNT(*) FROM students");
$totalAlumni     = safeCount($conn, "SELECT COUNT(*) FROM alumni");
$totalBooks      = safeCount($conn, "SELECT COUNT(*) FROM library_resources WHERE is_active = 1");
$totalPhotos     = safeCount($conn, "SELECT COUNT(*) FROM gallery_photos WHERE is_active = 1");
$totalCalendar   = safeCount($conn, "SELECT COUNT(*) FROM calendar_documents WHERE is_active = 1");
$pendingStudents = safeCount($conn, "SELECT COUNT(*) FROM students WHERE status = 'Pending'");
$enrolledStudents= safeCount($conn, "SELECT COUNT(*) FROM students WHERE status = 'Enrolled'");
$rejectedStudents= safeCount($conn, "SELECT COUNT(*) FROM students WHERE status = 'Rejected'");
$totalTeachers   = safeCount($conn, "SELECT COUNT(*) FROM teachers");

// ---------- Datasets ----------
$allStudents = [];
$rs = mysqli_query($conn, "SELECT id, full_name, date_of_birth, gender, nationality, lin_number, previous_school, class_level, stream, guardian_name, guardian_relationship, guardian_phone, guardian_email, guardian_address, guardian_occupation, emergency_name, emergency_phone, medical_notes, status, registered_at FROM students ORDER BY registered_at DESC");
if ($rs) while ($r = mysqli_fetch_assoc($rs)) $allStudents[] = $r;

$allAlumni = [];
$ra = mysqli_query($conn, "SELECT id, name, year, profession, phone, email FROM alumni ORDER BY id DESC");
if ($ra) while ($r = mysqli_fetch_assoc($ra)) $allAlumni[] = $r;

$allTeachers = [];
$rt = mysqli_query($conn, "SELECT id, staff_id, full_name, email, department, is_active, created_at FROM teachers ORDER BY created_at DESC");
if ($rt) while ($r = mysqli_fetch_assoc($rt)) $allTeachers[] = $r;

$allCalendar = [];
$rc = mysqli_query($conn, "SELECT id, title, doc_type, description, file_path, file_name, file_size, uploaded_at FROM calendar_documents WHERE is_active = 1 ORDER BY uploaded_at DESC");
if ($rc) while ($r = mysqli_fetch_assoc($rc)) $allCalendar[] = $r;

$allGallery = [];
$rg = mysqli_query($conn, "SELECT id, title, caption, category, file_path, uploaded_at FROM gallery_photos WHERE is_active = 1 ORDER BY uploaded_at DESC");
if ($rg) while ($r = mysqli_fetch_assoc($rg)) $allGallery[] = $r;

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
        <div class="mb-6 {$bg} border {$border} {$text} px-5 py-4 rounded-xl text-sm font-semibold flex items-start gap-3 shadow-sm">
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
    <link rel="stylesheet" href="css/tailwind.min.css">
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

    <!-- Main Clean Header Navigation Bar -->
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
                
                <!-- Clean, Light Action Controls -->
                <div class="hidden md:flex items-center gap-3 flex-shrink-0">
                    <button onclick="switchAdminTab('tab-admin-overview');" class="px-4 py-2.5 rounded-xl text-xs font-black text-gray-900 bg-white border border-gray-300 shadow-sm hover:bg-gray-900 hover:text-white transition-all flex items-center gap-1.5">
                        <i data-lucide="layout-dashboard" class="w-4 h-4 text-amber-500"></i> Dashboard
                    </button>

                    <div class="hidden xl:flex flex-col text-right px-3 border-l border-gray-200">
                        <span class="text-[10px] text-gray-400 font-extrabold uppercase tracking-wider">Administrator</span>
                        <span class="text-xs font-bold text-gray-900"><?= htmlspecialchars($admin['name']) ?></span>
                    </div>

                    <button type="button" onclick="openModal('modal-admin-change-password');" class="px-3.5 py-2.5 rounded-xl text-xs font-bold text-gray-800 bg-gray-100 hover:bg-gray-200 transition-colors flex items-center gap-1.5 border border-gray-200 shadow-sm">
                        <i data-lucide="key-round" class="w-3.5 h-3.5 text-amber-600"></i> Password
                    </button>

                    <a href="admin_logout.php"
                       class="px-4 py-2.5 rounded-xl text-xs font-extrabold text-gray-950 bg-brand-gold hover:bg-yellow-400 transition-all flex items-center gap-1.5 shadow-sm active:scale-95">
                        <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Logout
                    </a>
                </div>

                <div class="md:hidden flex items-center">
                    <button onclick="toggleMobileMenu();" class="inline-flex items-center justify-center p-2 rounded-xl text-gray-700 hover:text-gray-900 hover:bg-gray-100 focus:outline-none">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                </div>
            </div>
        </div>
        <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-gray-200 px-4 pt-3 pb-5 space-y-2">
            <button onclick="switchAdminTab('tab-admin-overview'); toggleMobileMenu();" class="w-full text-left px-4 py-2.5 rounded-xl text-sm font-bold text-gray-900 bg-gray-50 border border-gray-200">Dashboard</button>
            <button onclick="openModal('modal-admin-change-password'); toggleMobileMenu();" class="w-full text-left px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-800 hover:bg-gray-100">Change Password</button>
            <div class="pt-3 border-t border-gray-100">
                <a href="admin_logout.php" class="w-full py-2.5 rounded-xl font-bold text-gray-950 bg-brand-gold text-center block shadow">Logout</a>
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
                        <button type="button" onclick="switchAdminTab('tab-admin-enrollment');" class="font-extrabold text-amber-600 hover:underline">View →</button>
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
                        <button type="button" onclick="switchAdminTab('tab-admin-alumni');" class="font-extrabold text-amber-600 hover:underline">View →</button>
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

            <!-- Dynamic Tab Bar Navigation (Main Workstation Navigation) -->
            <div class="bg-gray-200/70 p-1.5 rounded-2xl flex flex-wrap gap-2 mb-10 border border-gray-300/60 shadow-inner">
                <button onclick="switchAdminTab('tab-admin-overview');" id="btn-tab-admin-overview" class="tab-btn active px-6 py-3 rounded-xl text-xs font-black tracking-wide uppercase transition-all flex items-center gap-2">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Overview
                </button>
                <button onclick="switchAdminTab('tab-admin-teachers');" id="btn-tab-admin-teachers" class="tab-btn px-6 py-3 rounded-xl text-xs font-bold text-gray-700 hover:text-gray-900 hover:bg-white/70 transition-all flex items-center gap-2">
                    <i data-lucide="user-cog" class="w-4 h-4"></i> Teachers & Staff
                </button>
                <button onclick="switchAdminTab('tab-admin-enrollment');" id="btn-tab-admin-enrollment" class="tab-btn px-6 py-3 rounded-xl text-xs font-bold text-gray-700 hover:text-gray-900 hover:bg-white/70 transition-all flex items-center gap-2">
                    <i data-lucide="user-plus" class="w-4 h-4"></i> Enrollments (<?= $totalStudents ?>)
                </button>
                <button onclick="switchAdminTab('tab-admin-alumni');" id="btn-tab-admin-alumni" class="tab-btn px-6 py-3 rounded-xl text-xs font-bold text-gray-700 hover:text-gray-900 hover:bg-white/70 transition-all flex items-center gap-2">
                    <i data-lucide="graduation-cap" class="w-4 h-4"></i> Alumni (<?= $totalAlumni ?>)
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

                    <!-- Recent Enrollments Card -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <i data-lucide="user-plus" class="w-5 h-5 text-amber-600"></i> Latest Enrollments
                            </h3>
                            <button onclick="switchAdminTab('tab-admin-enrollment');" class="text-xs font-bold text-amber-600 hover:underline">See all →</button>
                        </div>
                        <?php if (empty($recentStudents)): ?>
                            <p class="text-sm text-gray-500 py-8 text-center">No enrollments yet.</p>
                        <?php else: ?>
                            <ul class="divide-y divide-gray-100">
                                <?php foreach ($recentStudents as $s): ?>
                                    <li class="py-3 flex justify-between items-center gap-3">
                                        <div class="min-w-0">
                                            <div class="font-bold text-gray-900 truncate"><?= htmlspecialchars($s['full_name']) ?></div>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($s['class_level']) ?> · Stream <?= htmlspecialchars($s['stream']) ?></div>
                                        </div>
                                        <span class="text-xs font-bold px-2.5 py-1 rounded-full whitespace-nowrap
                                            <?= $s['status'] === 'Enrolled' ? 'bg-green-100 text-green-800' : ($s['status'] === 'Rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                            <?= htmlspecialchars($s['status']) ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Alumni Card -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <i data-lucide="graduation-cap" class="w-5 h-5 text-amber-600"></i> Latest Alumni
                            </h3>
                            <button onclick="switchAdminTab('tab-admin-alumni');" class="text-xs font-bold text-amber-600 hover:underline">See all →</button>
                        </div>
                        <?php if (empty($recentAlumni)): ?>
                            <p class="text-sm text-gray-500 py-8 text-center">No alumni registrations yet.</p>
                        <?php else: ?>
                            <ul class="divide-y divide-gray-100">
                                <?php foreach ($recentAlumni as $a): ?>
                                    <li class="py-3">
                                        <div class="font-bold text-gray-900 truncate"><?= htmlspecialchars($a['name']) ?></div>
                                        <div class="text-xs text-gray-500">Class of <?= htmlspecialchars((string)$a['year']) ?> · <?= htmlspecialchars($a['profession']) ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- Quick Actions Grid -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <i data-lucide="zap" class="w-5 h-5 text-amber-600"></i> Quick Actions
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <button onclick="openModal('modal-add-teacher');" class="p-5 rounded-xl border border-gray-200 hover:border-amber-400 hover:shadow-md transition-all group text-left bg-gray-50/50">
                            <i data-lucide="user-plus" class="w-7 h-7 text-amber-600 mb-3"></i>
                            <div class="font-bold text-gray-900 group-hover:text-amber-600 text-sm">Add Teacher</div>
                            <div class="text-xs text-gray-500 mt-1">Register new staff</div>
                        </button>

                        <button onclick="openModal('modal-register-alumni');" class="p-5 rounded-xl border border-gray-200 hover:border-amber-400 hover:shadow-md transition-all group text-left bg-gray-50/50">
                            <i data-lucide="user-check" class="w-7 h-7 text-amber-600 mb-3"></i>
                            <div class="font-bold text-gray-900 group-hover:text-amber-600 text-sm">Register Alumni</div>
                            <div class="text-xs text-gray-500 mt-1">Add graduate student</div>
                        </button>

                        <button onclick="openModal('modal-admin-change-password');" class="p-5 rounded-xl border border-gray-200 hover:border-amber-400 hover:shadow-md transition-all group text-left bg-gray-50/50">
                            <i data-lucide="key-round" class="w-7 h-7 text-gray-800 mb-3"></i>
                            <div class="font-bold text-gray-900 text-sm">Change Password</div>
                            <div class="text-xs text-gray-500 mt-1">Update credentials</div>
                        </button>

                        <button onclick="openModal('modal-upload-calendar');" class="p-5 rounded-xl border border-gray-200 hover:border-amber-400 hover:shadow-md transition-all group text-left bg-gray-50/50">
                            <i data-lucide="calendar-plus" class="w-7 h-7 text-amber-600 mb-3"></i>
                            <div class="font-bold text-gray-900 group-hover:text-amber-600 text-sm">Upload Calendar</div>
                            <div class="text-xs text-gray-500 mt-1">Publish circular/fees</div>
                        </button>

                        <button onclick="openModal('modal-upload-gallery');" class="p-5 rounded-xl border border-gray-200 hover:border-amber-400 hover:shadow-md transition-all group text-left bg-gray-50/50">
                            <i data-lucide="image-plus" class="w-7 h-7 text-amber-600 mb-3"></i>
                            <div class="font-bold text-gray-900 group-hover:text-amber-600 text-sm">Add Photo</div>
                            <div class="text-xs text-gray-500 mt-1">Public gallery image</div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB 2: TEACHERS & STAFF -->
            <div id="tab-admin-teachers" class="admin-tab-content hidden space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex flex-wrap justify-between items-center mb-6 gap-4 border-b border-gray-100 pb-4">
                        <div>
                            <h3 class="text-xl font-black text-gray-900 flex items-center gap-2">
                                <i data-lucide="user-cog" class="w-6 h-6 text-amber-600"></i> Teachers & Academic Staff Roster
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">Registered teachers can log in with their Staff ID and default password (<code class="bg-amber-100 text-amber-900 px-1.5 py-0.5 rounded font-mono font-bold">Admin@2026</code>).</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                                <input type="text" id="search-teachers" onkeyup="filterTeachers();" placeholder="Search teacher or ID..." class="pl-9 pr-4 py-2 border border-gray-300 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none w-64">
                            </div>
                            <button onclick="openModal('modal-add-teacher');" class="px-4 py-2 bg-gray-900 text-amber-400 font-extrabold rounded-xl text-xs hover:bg-gray-800 shadow flex items-center gap-1.5">
                                <i data-lucide="user-plus" class="w-4 h-4"></i> Add Teacher
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse" id="table-teachers">
                            <thead>
                                <tr class="bg-gray-100 text-gray-700 font-bold border-b border-gray-200">
                                    <th class="p-3.5">Staff ID</th>
                                    <th class="p-3.5">Full Name</th>
                                    <th class="p-3.5">Email</th>
                                    <th class="p-3.5">Department</th>
                                    <th class="p-3.5">Status</th>
                                    <th class="p-3.5">Registered</th>
                                    <th class="p-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (!empty($allTeachers)): ?>
                                    <?php foreach ($allTeachers as $t): ?>
                                        <tr class="hover:bg-gray-50 teacher-row">
                                            <td class="p-3.5 font-mono text-xs font-bold text-amber-700 search-target"><?= htmlspecialchars($t['staff_id']) ?></td>
                                            <td class="p-3.5 font-bold text-gray-900 search-target"><?= htmlspecialchars($t['full_name']) ?></td>
                                            <td class="p-3.5 text-xs text-gray-600 search-target"><?= htmlspecialchars($t['email']) ?></td>
                                            <td class="p-3.5 text-xs font-semibold text-gray-700 search-target"><?= htmlspecialchars($t['department'] ?: 'Academic') ?></td>
                                            <td class="p-3.5">
                                                <span class="text-xs font-bold px-2.5 py-1 rounded-full <?= (int)$t['is_active'] === 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                    <?= (int)$t['is_active'] === 1 ? 'Active' : 'Disabled' ?>
                                                </span>
                                            </td>
                                            <td class="p-3.5 text-xs text-gray-500 font-mono"><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                                            <td class="p-3.5 text-right whitespace-nowrap">
                                                <form action="delete_item.php" method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete teacher record for <?= htmlspecialchars(addslashes($t['full_name'])) ?>?');">
                                                    <input type="hidden" name="type" value="teacher">
                                                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                                    <input type="hidden" name="redirect_to" value="admin_dashboard.php">
                                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-600 text-white font-bold rounded-xl text-xs hover:bg-red-700 transition-colors shadow-sm">
                                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="p-8 text-center text-gray-500 text-sm">No teachers registered yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: STUDENT ENROLLMENTS (FULL MANAGEMENT) -->
            <div id="tab-admin-enrollment" class="admin-tab-content hidden space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex flex-wrap justify-between items-center mb-6 gap-4 border-b border-gray-100 pb-4">
                        <div>
                            <h3 class="text-xl font-black text-gray-900 flex items-center gap-2">
                                <i data-lucide="users" class="w-6 h-6 text-amber-600"></i> Student Enrollment Registry
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">Review application details, filter by class or admission status, and update student status.</p>
                        </div>
                        <div class="flex items-center gap-3 flex-wrap">
                            <!-- Status Filter Buttons -->
                            <div class="flex bg-gray-100 p-1 rounded-xl gap-1 border border-gray-200">
                                <button onclick="setStudentStatusFilter('ALL');" id="status-btn-all" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-white text-gray-900 shadow-sm">All (<?= $totalStudents ?>)</button>
                                <button onclick="setStudentStatusFilter('Pending');" id="status-btn-pending" class="px-3 py-1.5 rounded-lg text-xs font-bold text-amber-700 hover:bg-white/50">Pending (<?= $pendingStudents ?>)</button>
                                <button onclick="setStudentStatusFilter('Enrolled');" id="status-btn-enrolled" class="px-3 py-1.5 rounded-lg text-xs font-bold text-green-700 hover:bg-white/50">Enrolled (<?= $enrolledStudents ?>)</button>
                                <button onclick="setStudentStatusFilter('Rejected');" id="status-btn-rejected" class="px-3 py-1.5 rounded-lg text-xs font-bold text-red-700 hover:bg-white/50">Rejected (<?= $rejectedStudents ?>)</button>
                            </div>

                            <!-- Search Input -->
                            <div class="relative">
                                <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                                <input type="text" id="search-students" onkeyup="filterStudents();" placeholder="Search student or LIN..." class="pl-9 pr-4 py-2 border border-gray-300 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none w-56">
                            </div>

                            <a href="enrollment.php" class="px-4 py-2 bg-amber-500 text-gray-950 font-extrabold rounded-xl text-xs hover:bg-amber-400 shadow flex items-center gap-1.5">
                                <i data-lucide="plus" class="w-4 h-4"></i> New Student
                            </a>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse" id="table-students">
                            <thead>
                                <tr class="bg-gray-100 text-gray-700 font-bold border-b border-gray-200">
                                    <th class="p-3.5">#</th>
                                    <th class="p-3.5">Student Name</th>
                                    <th class="p-3.5">Class & Stream</th>
                                    <th class="p-3.5">LIN / UNEB Index</th>
                                    <th class="p-3.5">Guardian Contact</th>
                                    <th class="p-3.5">Status</th>
                                    <th class="p-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (!empty($allStudents)): ?>
                                    <?php foreach ($allStudents as $i => $s): 
                                        $sStatus = $s['status'] ?: 'Pending';
                                        $statusClass = $sStatus === 'Enrolled' ? 'bg-green-100 text-green-800' : ($sStatus === 'Rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800');
                                    ?>
                                        <tr class="hover:bg-gray-50 student-row" data-status="<?= htmlspecialchars($sStatus) ?>">
                                            <td class="p-3.5 font-mono text-xs text-gray-400"><?= $i + 1 ?></td>
                                            <td class="p-3.5">
                                                <div class="font-bold text-gray-900 search-target"><?= htmlspecialchars($s['full_name']) ?></div>
                                                <div class="text-xs text-gray-500"><?= htmlspecialchars($s['gender'] ?? '') ?> · <?= htmlspecialchars($s['nationality'] ?? 'Ugandan') ?></div>
                                            </td>
                                            <td class="p-3.5">
                                                <span class="px-2.5 py-1 rounded bg-gray-100 text-gray-800 font-bold text-xs">
                                                    <?= htmlspecialchars($s['class_level']) ?> · <?= htmlspecialchars($s['stream']) ?>
                                                </span>
                                            </td>
                                            <td class="p-3.5 font-mono text-xs text-amber-700 font-bold search-target"><?= htmlspecialchars($s['lin_number']) ?></td>
                                            <td class="p-3.5">
                                                <div class="text-sm font-medium text-gray-900 search-target"><?= htmlspecialchars($s['guardian_name']) ?></div>
                                                <div class="text-xs text-gray-500 search-target"><?= htmlspecialchars($s['guardian_phone']) ?></div>
                                            </td>
                                            <td class="p-3.5">
                                                <span class="text-xs font-bold px-2.5 py-1 rounded-full uppercase <?= $statusClass ?>">
                                                    <?= htmlspecialchars($sStatus) ?>
                                                </span>
                                            </td>
                                            <td class="p-3.5 text-right whitespace-nowrap">
                                                <button onclick='showStudentModal(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) ?>)'
                                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-gray-900 text-amber-400 font-bold rounded-xl text-xs hover:bg-gray-800 transition-colors shadow-sm">
                                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i> Full Profile
                                                </button>
                                                <form action="delete_item.php" method="post" class="inline ml-1" onsubmit="return confirm('Are you sure you want to delete student record for <?= htmlspecialchars(addslashes($s['full_name'])) ?>?');">
                                                    <input type="hidden" name="type" value="student">
                                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                                    <input type="hidden" name="redirect_to" value="admin_dashboard.php">
                                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-600 text-white font-bold rounded-xl text-xs hover:bg-red-700 transition-colors shadow-sm">
                                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="p-12 text-center text-gray-500 text-sm">No student enrollments registered yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 4: ALUMNI NETWORK -->
            <div id="tab-admin-alumni" class="admin-tab-content hidden space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex flex-wrap justify-between items-center mb-6 gap-4 border-b border-gray-100 pb-4">
                        <div>
                            <h3 class="text-xl font-black text-gray-900 flex items-center gap-2">
                                <i data-lucide="graduation-cap" class="w-6 h-6 text-amber-600"></i> Alumni Registry
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">Directory of Thamani High School past graduates and alumni members.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                                <input type="text" id="search-alumni" onkeyup="filterAlumni();" placeholder="Search alumni or year..." class="pl-9 pr-4 py-2 border border-gray-300 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none w-64">
                            </div>
                            <button onclick="openModal('modal-register-alumni');" class="px-4 py-2 bg-gray-900 text-amber-400 font-extrabold rounded-xl text-xs hover:bg-gray-800 shadow flex items-center gap-1.5">
                                <i data-lucide="user-check" class="w-4 h-4"></i> Add Alumni
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse" id="table-alumni">
                            <thead>
                                <tr class="bg-gray-100 text-gray-700 font-bold border-b border-gray-200">
                                    <th class="p-3.5">Full Name</th>
                                    <th class="p-3.5">Graduation Year</th>
                                    <th class="p-3.5">Profession / Field</th>
                                    <th class="p-3.5">Phone Number</th>
                                    <th class="p-3.5">Email Address</th>
                                    <th class="p-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (!empty($allAlumni)): ?>
                                    <?php foreach ($allAlumni as $a): ?>
                                        <tr class="hover:bg-gray-50 alumni-row">
                                            <td class="p-3.5 font-bold text-gray-900 search-target"><?= htmlspecialchars($a['name']) ?></td>
                                            <td class="p-3.5">
                                                <span class="px-2.5 py-1 rounded bg-amber-100 text-amber-900 font-extrabold text-xs search-target"><?= htmlspecialchars((string)$a['year']) ?></span>
                                            </td>
                                            <td class="p-3.5 text-gray-700 search-target"><?= htmlspecialchars($a['profession']) ?></td>
                                            <td class="p-3.5 text-xs text-gray-600 font-mono search-target"><?= htmlspecialchars($a['phone'] ?: '—') ?></td>
                                            <td class="p-3.5"><a href="mailto:<?= htmlspecialchars($a['email']) ?>" class="text-amber-700 font-medium hover:underline search-target"><?= htmlspecialchars($a['email']) ?></a></td>
                                            <td class="p-3.5 text-right whitespace-nowrap">
                                                <form action="delete_item.php" method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete alumni record for <?= htmlspecialchars(addslashes($a['name'])) ?>?');">
                                                    <input type="hidden" name="type" value="alumni">
                                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                                    <input type="hidden" name="redirect_to" value="admin_dashboard.php">
                                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-600 text-white font-bold rounded-xl text-xs hover:bg-red-700 transition-colors shadow-sm">
                                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="p-8 text-center text-gray-500 text-sm">No alumni members registered yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 5: CALENDAR & FEES -->
            <div id="tab-admin-calendar" class="admin-tab-content hidden space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-4">
                        <div>
                            <h3 class="text-xl font-black text-gray-900 flex items-center gap-2">
                                <i data-lucide="calendar-days" class="w-6 h-6 text-amber-600"></i> Published Calendar & Fees Documents
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">All official circulars, timetables, and fee structures visible to parents, teachers, and students.</p>
                        </div>
                        <button onclick="openModal('modal-upload-calendar');" class="px-4 py-2 bg-gray-900 text-amber-400 font-extrabold rounded-xl text-xs hover:bg-gray-800 shadow flex items-center gap-1.5">
                            <i data-lucide="plus" class="w-4 h-4"></i> Upload Document
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-100 text-gray-700 font-bold border-b border-gray-200">
                                    <th class="p-3.5">Document Title</th>
                                    <th class="p-3.5">Category</th>
                                    <th class="p-3.5">File Size</th>
                                    <th class="p-3.5">Uploaded Date</th>
                                    <th class="p-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (!empty($allCalendar)): ?>
                                    <?php foreach ($allCalendar as $c): 
                                        $sz = (int)$c['file_size'];
                                        $szFmt = $sz >= 1048576 ? round($sz/1048576,1).' MB' : ($sz >= 1024 ? round($sz/1024,1).' KB' : $sz.' B');
                                    ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="p-3.5 font-bold text-gray-900"><?= htmlspecialchars($c['title']) ?></td>
                                            <td class="p-3.5"><span class="text-xs font-bold uppercase px-2.5 py-1 rounded bg-gray-100 text-gray-700"><?= htmlspecialchars($c['doc_type']) ?></span></td>
                                            <td class="p-3.5 text-xs text-gray-500 font-mono"><?= $szFmt ?></td>
                                            <td class="p-3.5 text-xs text-gray-500 font-mono"><?= date('d M Y', strtotime($c['uploaded_at'])) ?></td>
                                            <td class="p-3.5 text-right whitespace-nowrap">
                                                <a href="<?= htmlspecialchars($c['file_path']) ?>" target="_blank"
                                                   class="inline-flex items-center gap-1 px-3.5 py-1.5 bg-gray-900 text-white font-bold rounded-xl text-xs hover:bg-gray-800 transition-colors">
                                                    <i data-lucide="download" class="w-3.5 h-3.5 text-amber-400"></i> Open File
                                                </a>
                                                <form action="delete_item.php" method="post" class="inline ml-1" onsubmit="return confirm('Are you sure you want to delete document <?= htmlspecialchars(addslashes($c['title'])) ?>?');">
                                                    <input type="hidden" name="type" value="calendar">
                                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                                    <input type="hidden" name="redirect_to" value="admin_dashboard.php">
                                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-600 text-white font-bold rounded-xl text-xs hover:bg-red-700 transition-colors shadow-sm">
                                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="p-8 text-center text-gray-500 text-sm">No calendar or fee documents uploaded yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 6: GALLERY -->
            <div id="tab-admin-gallery" class="admin-tab-content hidden space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-4">
                        <div>
                            <h3 class="text-xl font-black text-gray-900 flex items-center gap-2">
                                <i data-lucide="image" class="w-6 h-6 text-amber-600"></i> School Photo Gallery
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">High resolution photos published to the public school gallery.</p>
                        </div>
                        <button onclick="openModal('modal-upload-gallery');" class="px-4 py-2 bg-gray-900 text-amber-400 font-extrabold rounded-xl text-xs hover:bg-gray-800 shadow flex items-center gap-1.5">
                            <i data-lucide="plus" class="w-4 h-4"></i> Upload Photo
                        </button>
                    </div>
                    <?php if (!empty($allGallery)): ?>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            <?php foreach ($allGallery as $g): ?>
                                <div class="bg-white rounded-xl overflow-hidden border border-gray-200 hover:shadow-md transition-shadow group">
                                    <img src="<?= htmlspecialchars($g['file_path']) ?>" alt="<?= htmlspecialchars($g['title']) ?>"
                                         class="w-full h-44 object-cover bg-gray-100" loading="lazy">
                                    <div class="p-3.5">
                                        <div class="text-sm font-bold text-gray-900 truncate"><?= htmlspecialchars($g['title']) ?></div>
                                        <div class="text-xs text-gray-500 mt-1 flex justify-between items-center mb-2">
                                            <span class="uppercase font-extrabold text-amber-700"><?= htmlspecialchars($g['category']) ?></span>
                                            <span><?= date('d M Y', strtotime($g['uploaded_at'])) ?></span>
                                        </div>
                                        <div class="pt-2 border-t border-gray-100 flex justify-end">
                                            <form action="delete_item.php" method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete photo <?= htmlspecialchars(addslashes($g['title'])) ?>?');">
                                                <input type="hidden" name="type" value="gallery">
                                                <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                                                <input type="hidden" name="redirect_to" value="admin_dashboard.php">
                                                <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1 bg-red-600 text-white font-bold rounded-lg text-xs hover:bg-red-700 transition-colors shadow-sm">
                                                    <i data-lucide="trash-2" class="w-3 h-3"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-gray-500 py-12 text-center">No gallery photos uploaded yet.</p>
                    <?php endif; ?>
                </div>
            </div>

        </section>
    </main>

    <!-- MODAL 1: ADD TEACHER -->
    <div id="modal-add-teacher" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-gray-100 pb-3">
                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <i data-lucide="user-plus" class="w-5 h-5 text-amber-600"></i> Register New Teacher
                </h3>
                <button onclick="closeModal('modal-add-teacher');" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <form action="admin_add_teacher.php" method="post" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Full Name <span class="text-amber-600">*</span></label>
                    <input type="text" name="full_name" required placeholder="e.g. Dr. Sarah Namubiru" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Staff ID <span class="text-amber-600">*</span></label>
                    <input type="text" name="staff_id" required placeholder="e.g. TSC-2026-009" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Email Address <span class="text-amber-600">*</span></label>
                    <input type="email" name="email" required placeholder="teacher@thamani.ac.ug" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Department</label>
                    <input type="text" name="department" placeholder="e.g. Science & Technology" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div class="bg-amber-50 p-3 rounded-lg border border-amber-200 text-xs text-amber-900 font-medium">
                    Default password will be automatically assigned as: <strong class="font-mono">Admin@2026</strong>.
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 py-3 bg-gray-900 text-amber-400 font-extrabold rounded-xl hover:bg-gray-800 text-xs flex items-center justify-center gap-2 shadow">
                        <i data-lucide="user-check" class="w-4 h-4"></i> Save Teacher
                    </button>
                    <button type="button" onclick="closeModal('modal-add-teacher');" class="w-28 py-3 border-2 border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 text-xs">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: REGISTER ALUMNI -->
    <div id="modal-register-alumni" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-gray-100 pb-3">
                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <i data-lucide="graduation-cap" class="w-5 h-5 text-amber-600"></i> Add Alumni Record
                </h3>
                <button onclick="closeModal('modal-register-alumni');" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <form action="register_alumni.php" method="post" onsubmit="handleAdminAddAlumni(event);" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Full Name <span class="text-amber-600">*</span></label>
                    <input type="text" id="alumni_name" required placeholder="e.g. Grace Nabukenya" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Graduation Year <span class="text-amber-600">*</span></label>
                    <input type="text" id="alumni_year" required placeholder="e.g. 2024" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Profession / Field <span class="text-amber-600">*</span></label>
                    <input type="text" id="alumni_profession" required placeholder="e.g. Software Engineer" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Phone Number <span class="text-amber-600">*</span></label>
                    <input type="text" id="alumni_phone" required placeholder="e.g. +256 700 123 456" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Email Address</label>
                    <input type="email" id="alumni_email" placeholder="alumni@example.com" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 py-3 bg-gray-900 text-amber-400 font-extrabold rounded-xl hover:bg-gray-800 text-xs flex items-center justify-center gap-2 shadow">
                        <i data-lucide="check" class="w-4 h-4"></i> Save Alumni
                    </button>
                    <button type="button" onclick="closeModal('modal-register-alumni');" class="w-28 py-3 border-2 border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 text-xs">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: UPLOAD CALENDAR / FEES -->
    <div id="modal-upload-calendar" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-gray-100 pb-3">
                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <i data-lucide="calendar-plus" class="w-5 h-5 text-amber-600"></i> Upload Calendar / Fees Document
                </h3>
                <button onclick="closeModal('modal-upload-calendar');" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <form action="admin_upload_calendar.php" method="post" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="redirect_to" value="admin_dashboard.php">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Title <span class="text-amber-600">*</span></label>
                    <input type="text" name="title" required placeholder="e.g. Term III Fee Structure" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Document Category</label>
                    <select name="doc_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <option value="calendar">Academic Calendar</option>
                        <option value="fees">Fee Structure</option>
                        <option value="timetable">Timetable</option>
                        <option value="other">Other Circular</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Document File (PDF / Word / Excel) <span class="text-amber-600">*</span></label>
                    <input type="file" name="doc_file" required accept=".pdf,.doc,.docx,.xls,.xlsx" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs">
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 py-3 bg-gray-900 text-amber-400 font-extrabold rounded-xl hover:bg-gray-800 text-xs flex items-center justify-center gap-2 shadow">
                        <i data-lucide="upload" class="w-4 h-4"></i> Upload Document
                    </button>
                    <button type="button" onclick="closeModal('modal-upload-calendar');" class="w-28 py-3 border-2 border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 text-xs">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 4: UPLOAD GALLERY PHOTO -->
    <div id="modal-upload-gallery" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-gray-100 pb-3">
                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <i data-lucide="image-plus" class="w-5 h-5 text-amber-600"></i> Upload Gallery Photo
                </h3>
                <button onclick="closeModal('modal-upload-gallery');" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <form action="admin_upload_gallery.php" method="post" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="redirect_to" value="admin_dashboard.php">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Title <span class="text-amber-600">*</span></label>
                    <input type="text" name="title" required placeholder="e.g. Science Fair 2026" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Category</label>
                    <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <option value="campus">Campus</option>
                        <option value="sports">Sports</option>
                        <option value="academic">Academic</option>
                        <option value="cultural">Cultural</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Photo Image <span class="text-amber-600">*</span></label>
                    <input type="file" name="photo_file" required accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs">
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 py-3 bg-gray-900 text-amber-400 font-extrabold rounded-xl hover:bg-gray-800 text-xs flex items-center justify-center gap-2 shadow">
                        <i data-lucide="upload" class="w-4 h-4"></i> Upload Photo
                    </button>
                    <button type="button" onclick="closeModal('modal-upload-gallery');" class="w-28 py-3 border-2 border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 text-xs">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 5: ADMIN CHANGE PASSWORD -->
    <div id="modal-admin-change-password" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-gray-100 pb-3">
                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <i data-lucide="key-round" class="w-5 h-5 text-amber-600"></i> Change Admin Password
                </h3>
                <button onclick="closeModal('modal-admin-change-password');" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <form action="admin_change_password.php" method="post" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Current Password <span class="text-amber-600">*</span></label>
                    <input type="password" name="current_password" required placeholder="Enter current password" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">New Password <span class="text-amber-600">*</span></label>
                    <input type="password" name="new_password" required minlength="6" placeholder="Enter new password (min 6 chars)" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Confirm New Password <span class="text-amber-600">*</span></label>
                    <input type="password" name="confirm_password" required minlength="6" placeholder="Confirm new password" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 py-3 bg-gray-900 text-amber-400 font-extrabold rounded-xl hover:bg-gray-800 text-xs flex items-center justify-center gap-2 shadow">
                        <i data-lucide="lock" class="w-4 h-4"></i> Update Password
                    </button>
                    <button type="button" onclick="closeModal('modal-admin-change-password');" class="w-28 py-3 border-2 border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 text-xs">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 6: FULL STUDENT DETAILS & STATUS MANAGEMENT -->
    <div id="modal-student" class="hidden fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-3xl w-full shadow-2xl max-h-[92vh] overflow-y-auto">
            <!-- Modal Header -->
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-100 flex justify-between items-center z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-700 flex items-center justify-center font-bold">
                        <i data-lucide="user" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 id="modal-student-name" class="text-lg font-black text-gray-900">Student Profile</h3>
                        <p id="modal-student-sub" class="text-xs text-gray-500">Full admission record details</p>
                    </div>
                </div>
                <button onclick="closeModal('modal-student')" class="p-2 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div id="modal-student-body" class="p-6 space-y-6"></div>

            <!-- Modal Footer: Status Change Actions -->
            <div class="sticky bottom-0 bg-white border-t border-gray-100 px-6 py-4 flex flex-wrap gap-2 justify-end">
                <form method="post" action="enrollment_status_update.php" class="inline">
                    <input type="hidden" name="redirect_to" value="admin_dashboard.php">
                    <input type="hidden" name="student_id" id="status-student-id" value="">
                    <input type="hidden" name="new_status" value="Enrolled">
                    <button type="submit" class="px-4 py-2.5 bg-green-600 text-white font-extrabold rounded-xl text-xs hover:bg-green-700 transition-colors flex items-center gap-1.5 shadow-sm">
                        <i data-lucide="check-circle" class="w-4 h-4"></i> Mark Enrolled
                    </button>
                </form>

                <form method="post" action="enrollment_status_update.php" class="inline">
                    <input type="hidden" name="redirect_to" value="admin_dashboard.php">
                    <input type="hidden" name="student_id" id="status-student-id-2" value="">
                    <input type="hidden" name="new_status" value="Pending">
                    <button type="submit" class="px-4 py-2.5 bg-amber-500 text-gray-950 font-extrabold rounded-xl text-xs hover:bg-amber-400 transition-colors flex items-center gap-1.5 shadow-sm">
                        <i data-lucide="clock" class="w-4 h-4"></i> Mark Pending
                    </button>
                </form>

                <form method="post" action="enrollment_status_update.php" class="inline">
                    <input type="hidden" name="redirect_to" value="admin_dashboard.php">
                    <input type="hidden" name="student_id" id="status-student-id-3" value="">
                    <input type="hidden" name="new_status" value="Rejected">
                    <button type="submit" class="px-4 py-2.5 bg-orange-600 text-white font-extrabold rounded-xl text-xs hover:bg-orange-700 transition-colors flex items-center gap-1.5 shadow-sm"
                            onclick="return confirm('Are you sure you want to mark this application as Rejected?');">
                        <i data-lucide="x-circle" class="w-4 h-4"></i> Reject Application
                    </button>
                </form>

                <form method="post" action="delete_item.php" class="inline" onsubmit="return confirm('Are you sure you want to PERMANENTLY DELETE this student record?');">
                    <input type="hidden" name="type" value="student">
                    <input type="hidden" name="id" id="delete-student-id-modal" value="">
                    <input type="hidden" name="redirect_to" value="admin_dashboard.php">
                    <button type="submit" class="px-4 py-2.5 bg-red-700 text-white font-extrabold rounded-xl text-xs hover:bg-red-800 transition-colors flex items-center gap-1.5 shadow-sm">
                        <i data-lucide="trash-2" class="w-4 h-4"></i> Delete Record
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="bg-gray-900 text-white pt-12 pb-8 border-t-4 border-brand-gold mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                <div>
                    <h3 class="text-xl font-bold mb-3 flex items-center gap-2">
                        <img src="thamani-logo.png" class="h-8 w-auto" alt="Logo" onerror="this.src='favicon.svg'">
                        <span>Thamani High School</span>
                    </h3>
                    <p class="text-gray-300 text-xs leading-relaxed">Empowering the next generation of Ugandan leaders through excellence in education, culture, and character building.</p>
                </div>
                <div>
                    <h3 class="text-base font-bold mb-3 text-amber-400">Quick Navigation</h3>
                    <ul class="space-y-2 text-gray-300 text-xs font-medium">
                        <li><button type="button" onclick="switchAdminTab('tab-admin-overview');" class="hover:text-amber-400 transition-colors">Admin Dashboard</button></li>
                        <li><button type="button" onclick="switchAdminTab('tab-admin-teachers');" class="hover:text-amber-400 transition-colors">Teachers Roster</button></li>
                        <li><button type="button" onclick="switchAdminTab('tab-admin-enrollment');" class="hover:text-amber-400 transition-colors">Student Enrollments</button></li>
                        <li><button type="button" onclick="switchAdminTab('tab-admin-alumni');" class="hover:text-amber-400 transition-colors">Alumni Directory</button></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-base font-bold mb-3 text-amber-400">Contact Us</h3>
                    <ul class="space-y-2 text-gray-300 text-xs">
                        <li class="flex items-center gap-2"><i data-lucide="map-pin" class="w-4 h-4 text-amber-400"></i><span>Kakiri Main Campus, Wakiso District</span></li>
                        <li class="flex items-center gap-2"><i data-lucide="phone" class="w-4 h-4 text-amber-400"></i><span>+256 414 123 456</span></li>
                        <li class="flex items-center gap-2"><i data-lucide="mail" class="w-4 h-4 text-amber-400"></i><span>info@thamani.ac.ug</span></li>
                    </ul>
                </div>
            </div>
            <div class="pt-6 border-t border-gray-800 text-center text-gray-400 text-xs flex justify-between items-center">
                <span>© 2026 Thamani High School. All rights reserved.</span>
                <span>Admin Session · <?= htmlspecialchars($admin['admin_id']) ?></span>
            </div>
        </div>
    </footer>

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
                btn.classList.remove('active');
                btn.classList.add('text-gray-700');
            });

            document.getElementById(tabId)?.classList.remove('hidden');
            const btn = document.getElementById('btn-' + tabId);
            if (btn) {
                btn.classList.add('active');
                btn.classList.remove('text-gray-700');
            }
            lucide.createIcons();
            window.scrollTo({ top: 300, behavior: 'smooth' });
        }

        // ---------- Student Search & Status Filter ----------
        let currentStudentStatus = 'ALL';
        function setStudentStatusFilter(status) {
            currentStudentStatus = status;
            ['all', 'pending', 'enrolled', 'rejected'].forEach(s => {
                const btn = document.getElementById('status-btn-' + s);
                if (btn) {
                    if (s.toUpperCase() === status.toUpperCase()) {
                        btn.className = 'px-3 py-1.5 rounded-lg text-xs font-bold bg-white text-gray-900 shadow-sm';
                    } else {
                        btn.className = 'px-3 py-1.5 rounded-lg text-xs font-bold text-gray-600 hover:bg-white/50';
                    }
                }
            });
            filterStudents();
        }

        function filterStudents() {
            const query = (document.getElementById('search-students')?.value || '').toLowerCase();
            document.querySelectorAll('.student-row').forEach(row => {
                const rowStatus = row.getAttribute('data-status') || '';
                const matchStatus = (currentStudentStatus === 'ALL') || (rowStatus.toUpperCase() === currentStudentStatus.toUpperCase());
                
                let textContent = '';
                row.querySelectorAll('.search-target').forEach(el => textContent += ' ' + el.textContent.toLowerCase());
                const matchQuery = !query || textContent.includes(query);

                if (matchStatus && matchQuery) {
                    row.classList.remove('hidden');
                } else {
                    row.classList.add('hidden');
                }
            });
        }

        // ---------- Teacher Search Filter ----------
        function filterTeachers() {
            const query = (document.getElementById('search-teachers')?.value || '').toLowerCase();
            document.querySelectorAll('.teacher-row').forEach(row => {
                let textContent = '';
                row.querySelectorAll('.search-target').forEach(el => textContent += ' ' + el.textContent.toLowerCase());
                if (!query || textContent.includes(query)) {
                    row.classList.remove('hidden');
                } else {
                    row.classList.add('hidden');
                }
            });
        }

        // ---------- Alumni Search Filter ----------
        function filterAlumni() {
            const query = (document.getElementById('search-alumni')?.value || '').toLowerCase();
            document.querySelectorAll('.alumni-row').forEach(row => {
                let textContent = '';
                row.querySelectorAll('.search-target').forEach(el => textContent += ' ' + el.textContent.toLowerCase());
                if (!query || textContent.includes(query)) {
                    row.classList.remove('hidden');
                } else {
                    row.classList.add('hidden');
                }
            });
        }

        // ---------- Alumni POST Handler ----------
        function handleAdminAddAlumni(event) {
            event.preventDefault();
            const payload = {
                name: document.getElementById('alumni_name').value,
                year: document.getElementById('alumni_year').value,
                profession: document.getElementById('alumni_profession').value,
                phone: document.getElementById('alumni_phone').value,
                email: document.getElementById('alumni_email').value
            };

            fetch('register_alumni.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Alumni registered successfully!');
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to register alumni');
                }
            })
            .catch(err => alert('Error connecting to server'));
        }

        // ---------- Student Profile Modal ----------
        function esc(v) {
            return (v === null || v === undefined || v === '') ? '—' : String(v);
        }

        function showStudentModal(s) {
            document.getElementById('modal-student-name').textContent = s.full_name || 'Student Detail';
            document.getElementById('modal-student-sub').textContent  = (s.class_level || '') + ' · ' + (s.stream || '');

            document.getElementById('status-student-id').value   = s.id;
            document.getElementById('status-student-id-2').value = s.id;
            document.getElementById('status-student-id-3').value = s.id;
            const delEl = document.getElementById('delete-student-id-modal');
            if (delEl) delEl.value = s.id;

            const statusClass =
                s.status === 'Enrolled' ? 'bg-green-100 text-green-800' :
                s.status === 'Rejected' ? 'bg-red-100 text-red-800' :
                                          'bg-yellow-100 text-yellow-800';

            const row = (label, value) => `
                <div class="flex justify-between gap-4 py-2 border-b border-gray-100 text-sm">
                    <span class="text-gray-500 font-medium">${label}</span>
                    <span class="text-gray-900 font-semibold text-right">${esc(value)}</span>
                </div>`;

            document.getElementById('modal-student-body').innerHTML = `
                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <div class="text-xs text-gray-500 font-medium">
                        Student Record ID: <strong class="text-amber-700 font-mono">#${esc(s.id)}</strong>
                    </div>
                    <span class="text-xs font-bold px-3 py-1 rounded-full uppercase ${statusClass}">${esc(s.status || 'Pending')}</span>
                </div>

                <div>
                    <h4 class="text-xs font-extrabold text-amber-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <i data-lucide="user" class="w-4 h-4"></i> Personal Information
                    </h4>
                    <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100">
                        ${row('Full Name', s.full_name)}
                        ${row('Date of Birth', s.date_of_birth)}
                        ${row('Gender', s.gender)}
                        ${row('Nationality', s.nationality)}
                        ${row('LIN / UNEB Index', s.lin_number)}
                        ${row('Previous School', s.previous_school)}
                    </div>
                </div>

                <div>
                    <h4 class="text-xs font-extrabold text-amber-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <i data-lucide="graduation-cap" class="w-4 h-4"></i> Placement Info
                    </h4>
                    <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100">
                        ${row('Class Level', s.class_level)}
                        ${row('Assigned Stream', s.stream)}
                    </div>
                </div>

                <div>
                    <h4 class="text-xs font-extrabold text-amber-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <i data-lucide="users" class="w-4 h-4"></i> Parent / Guardian Details
                    </h4>
                    <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100">
                        ${row('Guardian Name', s.guardian_name)}
                        ${row('Relationship', s.guardian_relationship)}
                        ${row('Phone Number', s.guardian_phone)}
                        ${row('Email Address', s.guardian_email)}
                        ${row('Physical Address', s.guardian_address)}
                        ${row('Occupation', s.guardian_occupation)}
                    </div>
                </div>

                <div>
                    <h4 class="text-xs font-extrabold text-amber-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <i data-lucide="activity" class="w-4 h-4"></i> Emergency & Medical
                    </h4>
                    <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100">
                        ${row('Emergency Contact', s.emergency_name)}
                        ${row('Emergency Phone', s.emergency_phone)}
                        <div class="pt-2">
                            <div class="text-gray-500 font-medium text-xs mb-1">Medical Notes</div>
                            <div class="text-gray-900 text-sm whitespace-pre-wrap">${esc(s.medical_notes)}</div>
                        </div>
                    </div>
                </div>
            `;
            openModal('modal-student');
            lucide.createIcons();
        }
    </script>
</body>
</html>