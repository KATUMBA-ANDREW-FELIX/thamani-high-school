<?php
require_once 'conn.php';
require_once 'auth_student.php';
require_student_login();
require_student_password_changed();

$db = $GLOBALS['conn'];
$student = current_student();
$hour    = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

// ---------- Fetch library resources (filtered by student class) ----------
$libraryResources = [];
$studentClass = $student['class'] ?? '';
$studentStream = $student['stream'] ?? '';

if ($studentClass) {
    $sql = "SELECT id, title, author, subject, category, class_level, file_path, file_name, file_size
            FROM library_resources
            WHERE is_active = 1
              AND (class_level = ? OR class_level = 'All Levels' OR class_level IS NULL OR class_level = '')
            ORDER BY uploaded_at DESC
            LIMIT 50";
    $stmt = thamani_db_prepare($db, $sql);
    if ($stmt) {
        thamani_db_stmt_bind_param($stmt, "s", $studentClass);
        if (thamani_db_stmt_execute($stmt)) {
            $res = thamani_db_stmt_get_result($stmt);
            if ($res) while ($r = thamani_db_fetch_assoc($res)) $libraryResources[] = $r;
        }
        thamani_db_stmt_close($stmt);
    }
}

// ---------- Fetch class timetable (by class + stream) ----------
$timetable = null;
$studentClassNorm = $studentClass;
if ($studentClassNorm) {
    $sql2 = "SELECT id, title, class_level, stream, schedule_json, file_name, file_path
             FROM class_timetables
             WHERE is_active = 1
               AND (class_level = ? OR class_level = ?)
               AND (stream = ? OR stream = 'All Streams')
             ORDER BY created_at DESC
             LIMIT 1";
    $classVariant = 'Form ' . preg_replace('/[^0-9]/', '', $studentClassNorm);
    $stmt2 = thamani_db_prepare($db, $sql2);
    if ($stmt2) {
        thamani_db_stmt_bind_param($stmt2, "sss", $studentClassNorm, $classVariant, $studentStream);
        if (thamani_db_stmt_execute($stmt2)) {
            $res2 = thamani_db_stmt_get_result($stmt2);
            if ($res2 && $res2->num_rows > 0) $timetable = $res2->fetch_assoc();
        }
        thamani_db_stmt_close($stmt2);
    }
}

$timetableSlots = $timetable ? (json_decode($timetable['schedule_json'] ?: '[]', true) ?: []) : [];

function formatSize($bytes) {
    $bytes = (int)$bytes;
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - THAMANI ACADEMY - Kakiri</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { brand: {
                green: '#1A472A', maroon: '#800000', gold: '#D4AF37',
                lightGreen: '#E8F5E9', darkGreen: '#0F2D1A'
            }}}}
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .feature-card.active {
            background-color: #1A472A !important;
            color: #ffffff !important;
            border-color: #1A472A !important;
        }
        .feature-card.active .card-title { color: #ffffff !important; }
        .feature-card.active .card-desc  { color: #D1D5DB !important; }
        .feature-card.active .card-icon-wrap {
            background-color: rgba(212, 175, 55, 0.2) !important;
        }
        .feature-card.active .card-icon-wrap i { color: #D4AF37 !important; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans min-h-screen flex flex-col">

    <div class="bg-brand-maroon text-white text-xs py-2 px-4 text-center font-medium">
        <div class="max-w-7xl mx-auto w-full flex justify-between items-center">
            <span>📍 THAMANI ACADEMY - Kakiri Main Campus, Wakiso District, Uganda</span>
            <span class="bg-brand-gold text-brand-green px-2.5 py-0.5 rounded font-bold uppercase tracking-wider text-[10px]">Student Portal</span>
        </div>
    </div>

    <nav class="sticky top-0 z-50 bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <a href="student-dashboard.php" class="flex items-center gap-3">
                    <img class="h-12 w-auto" src="thamani-logo.png" alt="Logo" onerror="this.src='favicon.svg'">
                    <div class="flex flex-col">
                        <span class="text-2xl font-bold tracking-tight text-brand-green">Thamani Academy</span>
                        <span class="text-[10px] font-semibold text-brand-maroon tracking-widest uppercase">Student Portal</span>
                    </div>
                </a>
                <div class="hidden md:flex items-center space-x-3">
                    <span class="text-sm text-gray-600 hidden lg:inline">
                        Signed in as <strong class="text-brand-green"><?= htmlspecialchars($student['name']) ?></strong>
                    </span>
                    <a href="student-change-password.php"
                       class="px-4 py-2 rounded-md text-sm font-bold text-brand-green bg-brand-lightGreen hover:bg-brand-green hover:text-white transition-colors flex items-center gap-1.5">
                        <i data-lucide="key-round" class="w-4 h-4"></i> Password
                    </a>
                    <a href="student_logout.php"
                       class="px-4 py-2 rounded-md text-sm font-bold text-white bg-brand-maroon hover:bg-red-900 transition-colors flex items-center gap-1.5">
                        <i data-lucide="log-out" class="w-4 h-4"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">

        <!-- Welcome Banner -->
        <div class="bg-brand-green text-white p-8 rounded-2xl shadow-lg mb-8">
            <span class="bg-brand-gold text-brand-green px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider">Student Dashboard</span>
            <h1 class="text-3xl font-bold mt-3"><?= $greeting ?>, <?= htmlspecialchars($student['name']) ?></h1>
            <p class="text-sm text-gray-200 mt-2">
                Class: <strong><?= htmlspecialchars($student['class']) ?> <?= htmlspecialchars($student['stream']) ?></strong>
                · Last login: <?= date('d M Y, g:ia', $_SESSION['student_logged_in_at']) ?>
            </p>
        </div>

        <!-- Feature Cards (toggle buttons) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8" id="feature-cards">
            <button type="button" onclick="switchStudentView('overview');" data-view="overview"
                    class="feature-card active bg-white p-6 rounded-2xl shadow-sm border-2 border-gray-100 hover:shadow-md hover:border-brand-green/30 transition-all text-left group flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-brand-green/10 text-brand-green flex-shrink-0 card-icon-wrap transition-colors">
                    <i data-lucide="layout-dashboard" class="w-6 h-6 card-icon"></i>
                </div>
                <div class="min-w-0">
                    <div class="font-bold text-brand-green text-lg card-title">Overview</div>
                    <div class="text-xs text-gray-500 mt-0.5 card-desc">Your dashboard home</div>
                </div>
            </button>

            <button type="button" onclick="switchStudentView('reports');" data-view="reports"
                    class="feature-card bg-white p-6 rounded-2xl shadow-sm border-2 border-gray-100 hover:shadow-md hover:border-brand-green/30 transition-all text-left group flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-brand-maroon/10 text-brand-maroon flex-shrink-0 card-icon-wrap transition-colors">
                    <i data-lucide="file-bar-chart-2" class="w-6 h-6 card-icon"></i>
                </div>
                <div class="min-w-0">
                    <div class="font-bold text-brand-green text-lg card-title">Academic Reports</div>
                    <div class="text-xs text-gray-500 mt-0.5 card-desc">Results &amp; performance</div>
                </div>
            </button>

            <button type="button" onclick="switchStudentView('library');" data-view="library"
                    class="feature-card bg-white p-6 rounded-2xl shadow-sm border-2 border-gray-100 hover:shadow-md hover:border-brand-green/30 transition-all text-left group flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-brand-gold/20 text-brand-green flex-shrink-0 card-icon-wrap transition-colors">
                    <i data-lucide="book-open" class="w-6 h-6 card-icon"></i>
                </div>
                <div class="min-w-0">
                    <div class="font-bold text-brand-green text-lg card-title">Digital Library</div>
                    <div class="text-xs text-gray-500 mt-0.5 card-desc">
                        <?= count($libraryResources) ?> resource<?= count($libraryResources) === 1 ? '' : 's' ?> for your class
                    </div>
                </div>
            </button>

            <button type="button" onclick="switchStudentView('timetable');" data-view="timetable"
                    class="feature-card bg-white p-6 rounded-2xl shadow-sm border-2 border-gray-100 hover:shadow-md hover:border-brand-green/30 transition-all text-left group flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-blue-50 text-blue-700 flex-shrink-0 card-icon-wrap transition-colors">
                    <i data-lucide="calendar-days" class="w-6 h-6 card-icon"></i>
                </div>
                <div class="min-w-0">
                    <div class="font-bold text-brand-green text-lg card-title">Class Timetable</div>
                    <div class="text-xs text-gray-500 mt-0.5 card-desc">
                        <?= $timetable ? 'Available' : 'Not yet published' ?>
                    </div>
                </div>
            </button>
        </div>

        <!-- ============================================================ -->
        <!-- VIEW: OVERVIEW                                                -->
        <!-- ============================================================ -->
        <div id="view-overview" class="student-view space-y-6">
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 text-center">
                <i data-lucide="construction" class="w-12 h-12 text-brand-gold mx-auto mb-3"></i>
                <h2 class="text-xl font-bold text-brand-green mb-2">Welcome to your Student Portal</h2>
                <p class="text-sm text-gray-500 max-w-xl mx-auto">
                    Use the cards above to access your academic reports, browse library resources, and view your class timetable.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-brand-maroon/10 text-brand-maroon flex items-center justify-center">
                            <i data-lucide="file-bar-chart-2" class="w-5 h-5"></i>
                        </div>
                        <span class="text-[10px] font-bold text-gray-400 uppercase">Reports</span>
                    </div>
                    <div class="text-2xl font-bold text-brand-green">—</div>
                    <div class="text-xs text-gray-500 mt-1">Coming in Phase 3</div>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-brand-gold/20 text-brand-green flex items-center justify-center">
                            <i data-lucide="book-open" class="w-5 h-5"></i>
                        </div>
                        <span class="text-[10px] font-bold text-gray-400 uppercase">Library</span>
                    </div>
                    <div class="text-2xl font-bold text-brand-green"><?= count($libraryResources) ?></div>
                    <div class="text-xs text-gray-500 mt-1">Resources for your class</div>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center">
                            <i data-lucide="calendar-days" class="w-5 h-5"></i>
                        </div>
                        <span class="text-[10px] font-bold text-gray-400 uppercase">Timetable</span>
                    </div>
                    <div class="text-2xl font-bold text-brand-green"><?= $timetable ? count($timetableSlots) : '—' ?></div>
                    <div class="text-xs text-gray-500 mt-1"><?= $timetable ? 'Time slots published' : 'Not yet published' ?></div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- VIEW: REPORTS                                                 -->
        <!-- ============================================================ -->
        <div id="view-reports" class="student-view hidden space-y-6">
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 text-center">
                <div class="w-16 h-16 mx-auto rounded-full bg-brand-maroon/10 text-brand-maroon flex items-center justify-center mb-4">
                    <i data-lucide="file-bar-chart-2" class="w-8 h-8"></i>
                </div>
                <span class="bg-brand-maroon text-white px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest">Coming Soon</span>
                <h2 class="text-2xl font-bold text-brand-green mt-3 mb-2">Academic Reports</h2>
                <p class="text-sm text-gray-500 max-w-xl mx-auto">
                    Your term results, exam scores, subject grades, averages, and teacher comments will appear here once the academic results module is published by the administration.
                </p>
                <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-3xl mx-auto text-left">
                    <div class="p-4 rounded-xl border border-gray-100 bg-gray-50">
                        <i data-lucide="file-text" class="w-6 h-6 text-brand-maroon mb-2"></i>
                        <div class="font-bold text-sm text-gray-800">Term Reports</div>
                        <div class="text-xs text-gray-500 mt-1">Printable report cards by term</div>
                    </div>
                    <div class="p-4 rounded-xl border border-gray-100 bg-gray-50">
                        <i data-lucide="trending-up" class="w-6 h-6 text-brand-green mb-2"></i>
                        <div class="font-bold text-sm text-gray-800">Subject Grades</div>
                        <div class="text-xs text-gray-500 mt-1">Per-subject performance</div>
                    </div>
                    <div class="p-4 rounded-xl border border-gray-100 bg-gray-50">
                        <i data-lucide="message-square" class="w-6 h-6 text-brand-gold mb-2"></i>
                        <div class="font-bold text-sm text-gray-800">Teacher Comments</div>
                        <div class="text-xs text-gray-500 mt-1">Remarks from your teachers</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- VIEW: LIBRARY                                                 -->
        <!-- ============================================================ -->
        <div id="view-library" class="student-view hidden space-y-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-4">
                    <div>
                        <h3 class="text-xl font-bold text-brand-green flex items-center gap-2">
                            <i data-lucide="book-open" class="w-5 h-5"></i> Digital Library
                        </h3>
                        <p class="text-xs text-gray-500 mt-1">
                            Resources available for <strong><?= htmlspecialchars($student['class']) ?></strong>
                        </p>
                    </div>
                    <span class="text-xs font-bold px-3 py-1 rounded-full bg-brand-lightGreen text-brand-green">
                        <?= count($libraryResources) ?> item<?= count($libraryResources) === 1 ? '' : 's' ?>
                    </span>
                </div>

                <?php if (empty($libraryResources)): ?>
                    <div class="py-16 text-center">
                        <i data-lucide="book-x" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
                        <p class="text-gray-500 font-semibold">No library resources available for your class yet.</p>
                        <p class="text-xs text-gray-400 mt-1">Check back soon — teachers upload new materials regularly.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($libraryResources as $r): ?>
                            <div class="bg-white p-4 rounded-xl border border-gray-100 hover:shadow-md transition-shadow flex flex-col">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="w-10 h-10 rounded-lg bg-brand-green/10 text-brand-green flex items-center justify-center flex-shrink-0">
                                        <i data-lucide="file-text" class="w-5 h-5"></i>
                                    </div>
                                    <span class="text-[10px] font-bold px-2 py-1 rounded bg-gray-100 text-gray-600 uppercase">
                                        <?= htmlspecialchars(strtoupper(pathinfo($r['file_name'], PATHINFO_EXTENSION))) ?>
                                    </span>
                                </div>
                                <h4 class="font-bold text-sm text-gray-900 leading-snug mb-1 line-clamp-2"><?= htmlspecialchars($r['title']) ?></h4>
                                <?php if (!empty($r['author'])): ?>
                                    <p class="text-xs text-gray-500 italic mb-2">by <?= htmlspecialchars($r['author']) ?></p>
                                <?php endif; ?>
                                <div class="flex items-center gap-2 text-xs text-gray-500 mb-3">
                                    <span class="px-2 py-0.5 rounded bg-brand-lightGreen text-brand-green font-semibold"><?= htmlspecialchars($r['subject']) ?></span>
                                    <span><?= formatSize($r['file_size']) ?></span>
                                </div>
                                <a href="<?= htmlspecialchars($r['file_path']) ?>" target="_blank" download
                                   class="mt-auto w-full flex items-center justify-center gap-1.5 py-2 rounded-lg font-bold text-white bg-brand-green hover:bg-brand-darkGreen transition-colors text-xs">
                                    <i data-lucide="download" class="w-3.5 h-3.5"></i> Download
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- VIEW: TIMETABLE                                               -->
        <!-- ============================================================ -->
        <div id="view-timetable" class="student-view hidden space-y-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-4">
                    <div>
                        <h3 class="text-xl font-bold text-brand-green flex items-center gap-2">
                            <i data-lucide="calendar-days" class="w-5 h-5"></i> Class Timetable
                        </h3>
                        <p class="text-xs text-gray-500 mt-1">
                            <?= htmlspecialchars($student['class']) ?> · Stream <?= htmlspecialchars($student['stream']) ?>
                        </p>
                    </div>
                    <?php if ($timetable && !empty($timetable['file_path'])): ?>
                        <a href="<?= htmlspecialchars($timetable['file_path']) ?>" target="_blank" download
                           class="px-3 py-1.5 bg-brand-green text-white font-bold rounded-lg text-xs hover:bg-brand-darkGreen flex items-center gap-1.5">
                            <i data-lucide="download" class="w-3.5 h-3.5"></i> Download
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (!$timetable): ?>
                    <div class="py-16 text-center">
                        <i data-lucide="calendar-x" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
                        <p class="text-gray-500 font-semibold">No timetable published yet for your class.</p>
                        <p class="text-xs text-gray-400 mt-1">The administration will publish it soon.</p>
                    </div>
                <?php elseif (empty($timetableSlots)): ?>
                    <div class="py-10 text-center">
                        <i data-lucide="file-text" class="w-12 h-12 text-brand-gold mx-auto mb-3"></i>
                        <p class="text-gray-500 font-semibold mb-3">Timetable is available as a document only.</p>
                        <?php if (!empty($timetable['file_path'])): ?>
                            <a href="<?= htmlspecialchars($timetable['file_path']) ?>" target="_blank" download
                               class="inline-flex items-center gap-2 px-5 py-2.5 bg-brand-green text-white font-bold rounded-lg text-sm hover:bg-brand-darkGreen">
                                <i data-lucide="download" class="w-4 h-4"></i> Download Timetable File
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-brand-green text-white font-bold">
                                    <th class="p-2.5">Time Slot</th>
                                    <th class="p-2.5">Monday</th>
                                    <th class="p-2.5">Tuesday</th>
                                    <th class="p-2.5">Wednesday</th>
                                    <th class="p-2.5">Thursday</th>
                                    <th class="p-2.5">Friday</th>
                                    <th class="p-2.5">Saturday</th>
                                    <th class="p-2.5">Sunday</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($timetableSlots as $s):
                                    $isGlobal = !empty($s['is_global_program']);
                                    if ($isGlobal): ?>
                                        <tr class="bg-brand-gold/10">
                                            <td class="p-2.5 font-mono font-bold text-xs text-brand-green"><?= htmlspecialchars($s['time'] ?? '') ?></td>
                                            <td colspan="7" class="p-2.5 text-center font-bold text-brand-green uppercase tracking-wider">
                                                ⭐ <?= htmlspecialchars($s['program_title'] ?? 'Global School Program') ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="p-2.5 font-mono font-bold text-xs text-gray-700 bg-gray-50"><?= htmlspecialchars($s['time'] ?? '') ?></td>
                                            <td class="p-2.5 font-semibold text-gray-800"><?= htmlspecialchars($s['mon'] ?? '') ?></td>
                                            <td class="p-2.5 font-semibold text-gray-800"><?= htmlspecialchars($s['tue'] ?? '') ?></td>
                                            <td class="p-2.5 font-semibold text-gray-800"><?= htmlspecialchars($s['wed'] ?? '') ?></td>
                                            <td class="p-2.5 font-semibold text-gray-800"><?= htmlspecialchars($s['thu'] ?? '') ?></td>
                                            <td class="p-2.5 font-semibold text-gray-800"><?= htmlspecialchars($s['fri'] ?? '') ?></td>
                                            <td class="p-2.5 font-semibold text-gray-800"><?= htmlspecialchars($s['sat'] ?? '') ?></td>
                                            <td class="p-2.5 font-semibold text-gray-800"><?= htmlspecialchars($s['sun'] ?? '') ?></td>
                                        </tr>
                                    <?php endif;
                                endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <footer class="bg-brand-green text-white pt-10 pb-8 mt-auto border-t-4 border-brand-gold">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-300 text-sm">
            © 2026 Thamani Academy. All rights reserved. — Signed in as <?= htmlspecialchars($student['name']) ?>
        </div>
    </footer>

    <script>
        lucide.createIcons();

        function switchStudentView(viewName) {
            // Hide all views
            document.querySelectorAll('.student-view').forEach(el => el.classList.add('hidden'));
            // Show selected
            document.getElementById('view-' + viewName)?.classList.remove('hidden');

            // Toggle active state on cards
            document.querySelectorAll('.feature-card').forEach(card => {
                if (card.dataset.view === viewName) {
                    card.classList.add('active');
                } else {
                    card.classList.remove('active');
                }
            });

            // Scroll a bit so the tab is visible
            document.getElementById('feature-cards')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            lucide.createIcons();
        }
    </script>
</body>
</html>