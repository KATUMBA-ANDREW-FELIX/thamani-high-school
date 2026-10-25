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

require_once 'grading_helper.php';

// ---------- Fetch Academic Reports visible to this student ----------
$studentId = (int)$student['id'];
$publishedWindows = [];

// 1) Windows the admin has explicitly published
$pwRes = thamani_db_query(
    $db,
    "SELECT id, title, academic_year, term, assessment_type, show_positions, created_at
     FROM reporting_windows
     WHERE is_published = 1
     ORDER BY id DESC"
);
if ($pwRes) {
    while ($r = thamani_db_fetch_assoc($pwRes)) {
        $publishedWindows[] = $r;
    }
}

// 2) Fallback: if nothing is published yet, still surface any window that has
//    marks for THIS student — either linked by window_id, or by
//    (term, academic_year, assessment_type) when window_id was saved as 0.
if (empty($publishedWindows)) {
    $fbSql = "SELECT DISTINCT rw.id, rw.title, rw.academic_year, rw.term,
                     rw.assessment_type, rw.show_positions, rw.created_at
              FROM reporting_windows rw
              WHERE EXISTS (
                  SELECT 1 FROM student_marks sm
                  WHERE sm.student_id = ?
                    AND (
                        sm.window_id = rw.id
                        OR (
                            (sm.window_id IS NULL OR sm.window_id = 0)
                            AND sm.term = rw.term
                            AND sm.academic_year = rw.academic_year
                            AND sm.assessment_type = rw.assessment_type
                        )
                    )
              )
              ORDER BY rw.id DESC";
    $fbStmt = thamani_db_prepare($db, $fbSql);
    if ($fbStmt) {
        thamani_db_stmt_bind_param($fbStmt, "i", $studentId);
        if (thamani_db_stmt_execute($fbStmt)) {
            $fbRes = thamani_db_stmt_get_result($fbStmt);
            if ($fbRes) {
                while ($r = thamani_db_fetch_assoc($fbRes)) {
                    $publishedWindows[] = $r;
                }
            }
        }
        thamani_db_stmt_close($fbStmt);
    }
}

$selectedWindowId = (int)($_GET['window_id'] ?? ($publishedWindows[0]['id'] ?? 0));
$selectedWindow = null;
foreach ($publishedWindows as $pw) {
    if ((int)$pw['id'] === $selectedWindowId) {
        $selectedWindow = $pw;
        break;
    }
}
if (!$selectedWindow && !empty($publishedWindows)) {
    $selectedWindow = $publishedWindows[0];
    $selectedWindowId = (int)$selectedWindow['id'];
}

$studentMarks = [];
$totalScore = 0;
$totalMax   = 0;
$studentComment = ['class_teacher_comment' => '', 'head_teacher_comment' => ''];
$streamRankText = 'N/A';

if ($selectedWindow) {
    // Fetch marks (matches both window-linked rows and legacy window_id = 0 rows)
    $mStmt = thamani_db_prepare(
        $db,
        "SELECT subject, score, max_score, comments
         FROM student_marks
         WHERE student_id = ?
           AND (
               window_id = ?
               OR (
                   (window_id IS NULL OR window_id = 0)
                   AND class_level = ?
                   AND term = ?
                   AND academic_year = ?
                   AND assessment_type = ?
               )
           )
         ORDER BY subject ASC"
    );
    if ($mStmt) {
        thamani_db_stmt_bind_param(
            $mStmt,
            "iissss",
            $studentId,
            $selectedWindowId,
            $studentClass,
            $selectedWindow['term'],
            $selectedWindow['academic_year'],
            $selectedWindow['assessment_type']
        );
        if (thamani_db_stmt_execute($mStmt)) {
            $mRes = thamani_db_stmt_get_result($mStmt);
            if ($mRes) {
                while ($r = thamani_db_fetch_assoc($mRes)) {
                    $studentMarks[] = $r;
                    $totalScore += floatval($r['score']);
                    $totalMax   += floatval($r['max_score'] ?: 100);
                }
            }
        } else {
            error_log('[Student Dashboard Marks Fetch] ' . thamani_db_stmt_error($mStmt));
        }
        thamani_db_stmt_close($mStmt);
    } else {
        error_log('[Student Dashboard Marks Prepare] ' . thamani_db_error($db));
    }

    // Fetch comments
    $cStmt = thamani_db_prepare($db, "SELECT class_teacher_comment, head_teacher_comment FROM report_comments WHERE student_id = ? AND window_id = ? LIMIT 1");
    if ($cStmt) {
        thamani_db_stmt_bind_param($cStmt, "ii", $studentId, $selectedWindowId);
        thamani_db_stmt_execute($cStmt);
        $cRes = thamani_db_stmt_get_result($cStmt);
        if ($cRes && $cr = thamani_db_fetch_assoc($cRes)) {
            $studentComment = $cr;
        }
        thamani_db_stmt_close($cStmt);
    }

    // Compute rank if enabled
    if (!empty($selectedWindow['show_positions'])) {
        $streamTotals = [];
        $allStreamStmt = thamani_db_prepare($db, "SELECT student_id, SUM(score) as total_score FROM student_marks WHERE class_level = ? AND (stream = ? OR ? = 'All Streams') AND (window_id = ? OR ((window_id IS NULL OR window_id = 0) AND term = ? AND academic_year = ? AND assessment_type = ?)) GROUP BY student_id");
        if ($allStreamStmt) {
            thamani_db_stmt_bind_param($allStreamStmt, "sssisss", $studentClass, $studentStream, $studentStream, $selectedWindowId, $selectedWindow['term'], $selectedWindow['academic_year'], $selectedWindow['assessment_type']);
            thamani_db_stmt_execute($allStreamStmt);
            $asRes = thamani_db_stmt_get_result($allStreamStmt);
            if ($asRes) {
                while ($ar = thamani_db_fetch_assoc($asRes)) {
                    $streamTotals[(int)$ar['student_id']] = floatval($ar['total_score']);
                }
            }
            thamani_db_stmt_close($allStreamStmt);
        }
        if (!empty($streamTotals)) {
            $ranksMap = calculate_stream_ranks($streamTotals);
            $streamRankText = $ranksMap[$studentId] ?? 'N/A';
        }
    }
}

$edLevel = (strpos($studentClass, 'Senior 5') !== false || strpos($studentClass, 'Senior 6') !== false) ? 'A-Level' : 'O-Level';
$averagePercent = ($totalMax > 0) ? round(($totalScore / $totalMax) * 100, 1) : 0;
$overallGradeInfo = calculate_grade_info($totalScore, $totalMax ?: 100, $edLevel);

$performanceChartIsDemo = false;
$appEnvironment = strtolower(trim((string)(getenv('APP_ENV') ?: '')));
$isDevelopmentEnvironment = in_array($appEnvironment, ['development', 'dev', 'local'], true);
if ($appEnvironment === '' && $db instanceof ThamaniPolyfillConn && $db->driver === 'sqlite') {
    $localHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $localHost = preg_replace('/:\d+$/', '', $localHost);
    $isDevelopmentEnvironment = in_array($localHost, ['localhost', '127.0.0.1', '::1'], true);
}

$termWindows = [];
foreach ($publishedWindows as $window) {
    if (!preg_match('/\bterm\s*(iii|ii|i|[1-3])\b/i', (string)$window['term'], $termMatch)) {
        continue;
    }

    $termNumber = is_numeric($termMatch[1])
        ? (int)$termMatch[1]
        : ['i' => 1, 'ii' => 2, 'iii' => 3][strtolower($termMatch[1])];
    $termKey = (string)$window['academic_year'] . '-' . $termNumber;
    $isEotWindow = strtoupper(trim((string)$window['assessment_type'])) === 'EOT';
    $existingIsEotWindow = isset($termWindows[$termKey])
        && strtoupper(trim((string)$termWindows[$termKey]['assessment_type'])) === 'EOT';

    if (!isset($termWindows[$termKey]) || ($isEotWindow && !$existingIsEotWindow)) {
        $termWindows[$termKey] = $window + ['term_number' => $termNumber];
    }
}

$termPerformanceData = [];
foreach ($termWindows as $window) {
    $termMarks = [];
    $termTotalScore = 0;
    $termTotalMax = 0;
    $termMarksStmt = thamani_db_prepare(
        $db,
        "SELECT subject, score, max_score
         FROM student_marks
         WHERE student_id = ?
           AND (
               window_id = ?
               OR (
                   (window_id IS NULL OR window_id = 0)
                   AND class_level = ?
                   AND term = ?
                   AND academic_year = ?
                   AND assessment_type = ?
               )
           )
         ORDER BY subject ASC"
    );
    if (!$termMarksStmt) {
        error_log('[Student Dashboard Term Marks Prepare] ' . thamani_db_error($db));
        continue;
    }

    thamani_db_stmt_bind_param(
        $termMarksStmt,
        "iissss",
        $studentId,
        $window['id'],
        $studentClass,
        $window['term'],
        $window['academic_year'],
        $window['assessment_type']
    );
    if (!thamani_db_stmt_execute($termMarksStmt)) {
        error_log('[Student Dashboard Term Marks Fetch] ' . thamani_db_stmt_error($termMarksStmt));
        thamani_db_stmt_close($termMarksStmt);
        continue;
    }

    $termMarksResult = thamani_db_stmt_get_result($termMarksStmt);
    if ($termMarksResult) {
        while ($mark = thamani_db_fetch_assoc($termMarksResult)) {
            $mark['score'] = (float)$mark['score'];
            $mark['max_score'] = (float)($mark['max_score'] ?: 100);
            $termMarks[] = $mark;
            $termTotalScore += $mark['score'];
            $termTotalMax += $mark['max_score'];
        }
    }
    thamani_db_stmt_close($termMarksStmt);

    if ($termTotalMax > 0) {
        $year = (string)$window['academic_year'];
        $termNumber = (int)$window['term_number'];
        $termPerformanceData[$year][$termNumber] = [
            'year' => $year,
            'term_number' => $termNumber,
            'label' => 'Term ' . $termNumber,
            'average' => round(($termTotalScore / $termTotalMax) * 100, 1),
            'marks' => $termMarks,
        ];
    }
}

if (!empty($termPerformanceData)) {
    $latestAcademicYear = max(array_keys($termPerformanceData));
    $termPerformanceData = $termPerformanceData[$latestAcademicYear];
    ksort($termPerformanceData);
} elseif ($isDevelopmentEnvironment) {
    $sampleMarks = [
        ['subject' => 'Mathematics', 'score' => 78, 'max_score' => 100],
        ['subject' => 'English', 'score' => 84, 'max_score' => 100],
        ['subject' => 'Biology', 'score' => 71, 'max_score' => 100],
        ['subject' => 'Chemistry', 'score' => 66, 'max_score' => 100],
        ['subject' => 'Physics', 'score' => 73, 'max_score' => 100],
        ['subject' => 'Geography', 'score' => 89, 'max_score' => 100],
    ];
    $sampleAverage = array_sum(array_column($sampleMarks, 'score')) / count($sampleMarks);
    $termPerformanceData = [
        1 => [
            'year' => date('Y'),
            'term_number' => 1,
            'label' => 'Term 1',
            'average' => round($sampleAverage, 1),
            'marks' => $sampleMarks,
        ],
    ];
    $performanceChartIsDemo = true;
}

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

    <div class="bg-brand-maroon text-white text-xs py-2 px-3 sm:px-4 text-center font-medium">
        <div class="max-w-7xl mx-auto w-full flex justify-between items-center gap-2">
            <span class="truncate max-w-[210px] sm:max-w-none text-[11px] sm:text-xs">📍 Kakiri Main Campus, Wakiso</span>
            <span class="bg-brand-gold text-brand-green px-2 py-0.5 rounded font-extrabold uppercase tracking-wider text-[10px] shrink-0 whitespace-nowrap">Student Portal</span>
        </div>
    </div>

    <nav class="sticky top-0 z-50 bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 sm:h-20 items-center">
                <a href="student-dashboard.php" class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1 mr-2">
                    <img class="h-9 sm:h-12 w-auto shrink-0" src="thamani-logo.png" alt="Logo" onerror="this.src='favicon.svg'">
                    <div class="flex flex-col min-w-0">
                        <span class="text-base sm:text-xl lg:text-2xl font-bold tracking-tight text-brand-green truncate leading-tight">Thamani High School</span>
                        <span class="text-[9px] sm:text-[10px] font-semibold text-brand-maroon tracking-widest uppercase truncate">Student Portal</span>
                    </div>
                </a>
                <div class="hidden md:flex items-center space-x-3">
                    <span class="text-sm text-gray-600 hidden lg:inline">
                        Signed in as <strong class="text-brand-green"><?= htmlspecialchars($student['name'] ?? '') ?></strong>
                    </span>
                    <a href="home.php"
                       class="px-4 py-2 rounded-md text-sm font-bold text-gray-700 bg-gray-100 hover:bg-brand-green hover:text-white transition-colors flex items-center gap-1.5 shadow-sm">
                        <i data-lucide="globe" class="w-4 h-4"></i> Main Website
                    </a>
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
            <h1 class="text-3xl font-bold mt-3"><?= $greeting ?>, <?= htmlspecialchars($student['name'] ?? '') ?></h1>
            <p class="text-sm text-gray-200 mt-2">
                Class: <strong><?= htmlspecialchars($student['class'] ?? '') ?> <?= htmlspecialchars($student['stream'] ?? '') ?></strong>
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
                    <?php if (!empty($studentMarks)): ?>
                        <div class="text-2xl font-bold text-brand-green"><?= number_format($averagePercent, 1) ?>%</div>
                        <div class="text-xs text-gray-500 mt-1">Average across <?= count($studentMarks) ?> subjects</div>
                    <?php elseif ($performanceChartIsDemo): ?>
                        <div class="text-2xl font-bold text-brand-green">Sample</div>
                        <div class="text-xs text-gray-500 mt-1">Development preview only</div>
                    <?php else: ?>
                        <div class="text-2xl font-bold text-brand-green">—</div>
                        <div class="text-xs text-gray-500 mt-1">Term marks not published</div>
                    <?php endif; ?>
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

            <section class="bg-white p-5 sm:p-7 rounded-2xl shadow-sm border border-gray-100" aria-labelledby="performance-chart-title">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
                    <div>
                        <div class="flex items-center gap-2 text-brand-green">
                            <i data-lucide="chart-no-axes-column-increasing" class="w-5 h-5"></i>
                            <h2 id="performance-chart-title" class="text-lg font-bold">Term Average Trend</h2>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            <?= $performanceChartIsDemo
                                ? 'Example term average to preview the chart. This is not your result.'
                                : (!empty($termPerformanceData)
                                    ? 'Your average mark for each term in ' . htmlspecialchars((string)reset($termPerformanceData)['year'], ENT_QUOTES, 'UTF-8') . '. Select a dot to see your subject marks.'
                                    : 'Term averages will appear here when a term report is published.') ?>
                        </p>
                    </div>
                    <?php if ($performanceChartIsDemo): ?>
                        <span class="px-3 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-extrabold uppercase tracking-wider">Development sample</span>
                    <?php elseif (!empty($termPerformanceData)): ?>
                        <span class="px-3 py-1 rounded-full bg-brand-lightGreen text-brand-green text-[10px] font-bold"><?= htmlspecialchars((string)reset($termPerformanceData)['year'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($termPerformanceData)): ?>
                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <h3 class="text-xs font-extrabold uppercase tracking-wider text-gray-500">Average mark by term</h3>
                            <span class="text-[10px] text-gray-400">Click a point to view subject marks</span>
                        </div>
                        <div class="overflow-x-auto">
                            <svg class="w-full min-w-[360px] h-auto" viewBox="0 0 600 280" role="img" aria-labelledby="term-trend-title term-trend-description">
                                <title id="term-trend-title">Average mark by term</title>
                                <desc id="term-trend-description">A line graph showing the student's average percentage in Terms 1, 2, and 3. Each point can be selected to view subject marks.</desc>
                                <?php foreach ([0, 25, 50, 75, 100] as $tick):
                                    $gridY = 230 - (1.8 * $tick);
                                ?>
                                    <line x1="52" y1="<?= number_format($gridY, 1, '.', '') ?>" x2="550" y2="<?= number_format($gridY, 1, '.', '') ?>" stroke="#E5E7EB" stroke-width="1" />
                                    <text x="42" y="<?= number_format($gridY + 3, 1, '.', '') ?>" text-anchor="end" fill="#9CA3AF" font-size="10"><?= $tick ?>%</text>
                                <?php endforeach; ?>
                                <?php
                                    $termTrendPoints = [];
                                    foreach ($termPerformanceData as $termNumber => $termData) {
                                        $termTrendPoints[] = [
                                            'term_number' => (int)$termNumber,
                                            'x' => 90 + (210 * ((int)$termNumber - 1)),
                                            'y' => 230 - (1.8 * $termData['average']),
                                            'average' => $termData['average'],
                                        ];
                                    }
                                ?>
                                <?php if (count($termTrendPoints) > 1): ?>
                                    <polyline
                                        points="<?= implode(' ', array_map(static function ($point) { return number_format($point['x'], 1, '.', '') . ',' . number_format($point['y'], 1, '.', ''); }, $termTrendPoints)) ?>"
                                        fill="none"
                                        stroke="#1A472A"
                                        stroke-width="3"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                <?php endif; ?>
                                <?php foreach ($termTrendPoints as $point):
                                    $termData = $termPerformanceData[$point['term_number']];
                                    $templateId = 'term-marks-' . $point['term_number'];
                                ?>
                                    <g
                                        role="button"
                                        tabindex="0"
                                        aria-label="<?= htmlspecialchars($termData['label'] . ': ' . number_format($point['average'], 1) . ' percent average. View subject marks.', ENT_QUOTES, 'UTF-8') ?>"
                                        onclick="showTermMarks('<?= $templateId ?>')"
                                        onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); showTermMarks('<?= $templateId ?>'); }"
                                        style="cursor: pointer"
                                    >
                                        <circle cx="<?= number_format($point['x'], 1, '.', '') ?>" cy="<?= number_format($point['y'], 1, '.', '') ?>" r="13" fill="transparent" />
                                        <circle cx="<?= number_format($point['x'], 1, '.', '') ?>" cy="<?= number_format($point['y'], 1, '.', '') ?>" r="7" fill="#1A472A" stroke="white" stroke-width="3" />
                                        <text x="<?= number_format($point['x'], 1, '.', '') ?>" y="<?= number_format($point['y'] - 14, 1, '.', '') ?>" text-anchor="middle" fill="#1A472A" font-size="12" font-weight="700"><?= number_format($point['average'], 1) ?>%</text>
                                    </g>
                                <?php endforeach; ?>
                                <?php foreach ([1, 2, 3] as $termNumber): ?>
                                    <text x="<?= 90 + (210 * ($termNumber - 1)) ?>" y="260" text-anchor="middle" fill="#6B7280" font-size="12" font-weight="600">Term <?= $termNumber ?></text>
                                <?php endforeach; ?>
                            </svg>
                        </div>
                    </div>

                    <?php foreach ($termPerformanceData as $termData): ?>
                        <?php $templateId = 'term-marks-' . $termData['term_number']; ?>
                        <template id="<?= $templateId ?>" data-term-label="<?= htmlspecialchars($termData['label'] . ' ' . $termData['year'], ENT_QUOTES, 'UTF-8') ?>" data-term-average="<?= number_format($termData['average'], 1) ?>">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-200 text-xs uppercase text-gray-500">
                                            <th class="px-3 py-2">Subject</th>
                                            <th class="px-3 py-2 text-right">Mark</th>
                                            <th class="px-3 py-2 text-right">Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php foreach ($termData['marks'] as $mark):
                                            $markMax = (float)($mark['max_score'] ?: 100);
                                            $markPercent = $markMax > 0 ? ($mark['score'] / $markMax) * 100 : 0;
                                        ?>
                                            <tr>
                                                <td class="px-3 py-3 font-semibold text-gray-800"><?= htmlspecialchars((string)$mark['subject'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="px-3 py-3 text-right font-mono"><?= number_format((float)$mark['score'], 1) ?> / <?= number_format($markMax, 0) ?></td>
                                                <td class="px-3 py-3 text-right font-bold text-brand-green"><?= number_format($markPercent, 1) ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    <?php endforeach; ?>

                    <dialog id="term-marks-dialog" class="w-[min(42rem,calc(100%-2rem))] max-h-[85vh] rounded-2xl p-0 shadow-2xl backdrop:bg-black/50">
                        <div class="flex items-start justify-between gap-4 border-b border-gray-100 p-5">
                            <div>
                                <h3 id="term-marks-title" class="text-lg font-bold text-brand-green">Term marks</h3>
                                <p id="term-marks-average" class="mt-1 text-sm text-gray-500"></p>
                            </div>
                            <form method="dialog">
                                <button type="submit" class="rounded-lg px-3 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100" aria-label="Close term marks">Close</button>
                            </form>
                        </div>
                        <div id="term-marks-content" class="max-h-[65vh] overflow-y-auto p-4 sm:p-5"></div>
                    </dialog>
                <?php else: ?>
                    <div class="rounded-xl bg-gray-50 border border-dashed border-gray-200 px-5 py-8 text-center">
                        <i data-lucide="chart-no-axes-column-increasing" class="w-8 h-8 text-gray-300 mx-auto mb-2"></i>
                        <p class="text-sm font-semibold text-gray-600">No published term marks are available yet.</p>
                        <p class="text-xs text-gray-400 mt-1">Your term average graph will update when your results are published.</p>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <!-- ============================================================ -->
        <!-- VIEW: REPORTS                                                 -->
        <!-- ============================================================ -->
        <div id="view-reports" class="student-view hidden space-y-6">
            <?php if (empty($publishedWindows)): ?>
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 text-center">
                    <div class="w-16 h-16 mx-auto rounded-full bg-brand-maroon/10 text-brand-maroon flex items-center justify-center mb-4">
                        <i data-lucide="file-bar-chart-2" class="w-8 h-8"></i>
                    </div>
                    <span class="bg-amber-500 text-gray-950 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest">Awaiting Publication</span>
                    <h2 class="text-2xl font-bold text-brand-green mt-3 mb-2">Academic Results Pending</h2>
                    <p class="text-sm text-gray-500 max-w-xl mx-auto">
                        Your term exam scores, subject grades, and teacher report comments are being processed. They will appear here as soon as published by the school administration.
                    </p>
                </div>
            <?php else: ?>
                <!-- Window Selector & Print Header -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-wrap justify-between items-center gap-4">
                    <div>
                        <span class="text-[10px] font-bold uppercase text-brand-maroon tracking-wider">Select Assessment Window</span>
                        <div class="flex items-center gap-3 mt-1">
                            <form method="get" action="student-dashboard.php" class="flex items-center gap-2">
                                <select name="window_id" onchange="this.form.submit();" class="px-4 py-2 border border-gray-300 rounded-xl text-sm font-bold text-brand-green focus:ring-2 focus:ring-brand-green">
                                    <?php foreach ($publishedWindows as $pw): ?>
                                        <option value="<?= $pw['id'] ?>" <?= $pw['id'] == $selectedWindowId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($pw['title']) ?> (<?= htmlspecialchars($pw['term']) ?> <?= htmlspecialchars($pw['academic_year']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </div>
                    <?php if ($selectedWindow): ?>
                        <a href="print_report_card.php?student_id=<?= $studentId ?>&window_id=<?= $selectedWindowId ?>" target="_blank"
                           class="px-6 py-3 bg-brand-green hover:bg-brand-darkGreen text-white font-extrabold rounded-xl text-xs flex items-center gap-2 shadow-md hover:-translate-y-0.5 transition-all">
                            <i data-lucide="printer" class="w-4 h-4"></i> Download / Print Official PDF Report
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Overall Stat Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
                        <span class="text-gray-400 font-bold uppercase text-[10px]">Total Score</span>
                        <div class="text-2xl font-black text-brand-green mt-1"><?= number_format($totalScore, 1) ?> / <?= number_format($totalMax, 0) ?></div>
                        <div class="text-xs text-gray-500 mt-1"><?= count($studentMarks) ?> subjects recorded</div>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
                        <span class="text-gray-400 font-bold uppercase text-[10px]">Overall Average</span>
                        <div class="text-2xl font-black text-brand-maroon mt-1"><?= $averagePercent ?>%</div>
                        <div class="text-xs text-brand-green font-bold mt-1">Grade: <?= htmlspecialchars($overallGradeInfo['grade']) ?> (<?= htmlspecialchars($overallGradeInfo['remark']) ?>)</div>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
                        <span class="text-gray-400 font-bold uppercase text-[10px]">Stream Rank Position</span>
                        <div class="text-2xl font-black text-gray-900 mt-1"><?= htmlspecialchars($streamRankText) ?></div>
                        <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($studentClass) ?> · <?= htmlspecialchars($studentStream) ?></div>
                    </div>
                </div>

                <!-- Subject Performance Bar Graph -->
                <?php if (!empty($studentMarks)): ?>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                        <h3 class="text-sm font-bold text-brand-green uppercase tracking-wider mb-4 flex items-center gap-2">
                            <i data-lucide="bar-chart-2" class="w-4 h-4 text-brand-gold"></i> Subject Score Comparison Graph
                        </h3>
                        <div class="space-y-3">
                            <?php foreach ($studentMarks as $m): 
                                $sc = floatval($m['score']);
                                $mx = floatval($m['max_score'] ?: 100);
                                $pct = ($mx > 0) ? min(100, max(0, ($sc / $mx) * 100)) : 0;
                                $gi = calculate_grade_info($sc, $mx, $edLevel);
                            ?>
                                <div>
                                    <div class="flex justify-between text-xs font-bold text-gray-800 mb-1">
                                        <span><?= htmlspecialchars($m['subject']) ?></span>
                                        <span><?= number_format($sc, 1) ?> / <?= number_format($mx, 0) ?> (<?= $pct ?>%) — <strong class="text-brand-green"><?= htmlspecialchars($gi['grade']) ?></strong></span>
                                    </div>
                                    <div class="w-full bg-gray-100 h-3 rounded-full overflow-hidden border border-gray-200">
                                        <div class="bg-brand-green h-full rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Detailed Subject Marks Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-4 bg-brand-green text-white font-bold text-sm flex justify-between items-center">
                        <span class="flex items-center gap-2"><i data-lucide="file-text" class="w-4 h-4 text-brand-gold"></i> Detailed Subject Breakdown</span>
                        <span class="text-xs text-gray-200"><?= htmlspecialchars($selectedWindow['title'] ?? '') ?></span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200 font-bold text-gray-700 uppercase">
                                    <th class="p-3">Subject</th>
                                    <th class="p-3 text-center">Score</th>
                                    <th class="p-3 text-center">Max Score</th>
                                    <th class="p-3 text-center">% Score</th>
                                    <th class="p-3 text-center">Grade</th>
                                    <th class="p-3 text-center">Points</th>
                                    <th class="p-3">Teacher Remarks</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (!empty($studentMarks)): ?>
                                    <?php foreach ($studentMarks as $m): 
                                        $sc = floatval($m['score']);
                                        $mx = floatval($m['max_score'] ?: 100);
                                        $gi = calculate_grade_info($sc, $mx, $edLevel);
                                    ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="p-3 font-bold text-gray-900"><?= htmlspecialchars($m['subject']) ?></td>
                                            <td class="p-3 text-center font-mono font-bold"><?= number_format($sc, 1) ?></td>
                                            <td class="p-3 text-center font-mono text-gray-500"><?= number_format($mx, 0) ?></td>
                                            <td class="p-3 text-center font-mono font-bold text-brand-green"><?= $gi['percent'] ?>%</td>
                                            <td class="p-3 text-center">
                                                <span class="px-2 py-0.5 rounded font-black text-xs bg-brand-lightGreen text-brand-green">
                                                    <?= htmlspecialchars($gi['grade']) ?>
                                                </span>
                                            </td>
                                            <td class="p-3 text-center font-bold text-gray-700"><?= $gi['points'] ?></td>
                                            <td class="p-3 text-gray-600 italic"><?= htmlspecialchars($m['comments'] ?: $gi['remark']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="p-8 text-center text-gray-500">
                                            No marks records found for this assessment window.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Report Remarks -->
                <?php if (!empty($studentComment['class_teacher_comment']) || !empty($studentComment['head_teacher_comment'])): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php if (!empty($studentComment['class_teacher_comment'])): ?>
                            <div class="bg-amber-50 p-4 rounded-xl border border-amber-200 text-xs">
                                <span class="font-bold text-amber-900 uppercase block mb-1">Class Teacher Remarks:</span>
                                <p class="text-gray-800 italic"><?= htmlspecialchars($studentComment['class_teacher_comment']) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($studentComment['head_teacher_comment'])): ?>
                            <div class="bg-brand-lightGreen p-4 rounded-xl border border-brand-green/30 text-xs">
                                <span class="font-bold text-brand-green uppercase block mb-1">Head Teacher Remarks:</span>
                                <p class="text-gray-800 italic"><?= htmlspecialchars($studentComment['head_teacher_comment']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
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
            © 2026 Thamani Academy. All rights reserved. — Signed in as <?= htmlspecialchars($student['name'] ?? '') ?>
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

        function showTermMarks(templateId) {
            const template = document.getElementById(templateId);
            const dialog = document.getElementById('term-marks-dialog');
            if (!(template instanceof HTMLTemplateElement) || !(dialog instanceof HTMLDialogElement)) return;

            document.getElementById('term-marks-title').textContent = template.dataset.termLabel;
            document.getElementById('term-marks-average').textContent = `Average: ${template.dataset.termAverage}%`;
            document.getElementById('term-marks-content').replaceChildren(template.content.cloneNode(true));
            dialog.showModal();
        }
    </script>
</body>
</html>