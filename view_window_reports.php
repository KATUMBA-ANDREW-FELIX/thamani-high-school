<?php
/**
 * Thamani High School - Reporting Window Class & Stream Reports Explorer
 * ----------------------------------------------------------------------
 * Organizes reports strictly by Class Level (Senior 1..6) and Streams (Stream A, B, C, Falcon, etc.)
 */

require_once 'conn.php';
require_once 'grading_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdmin = !empty($_SESSION['admin_id']);
$isTeacher = !empty($_SESSION['teacher_id']);

if (!$isAdmin && !$isTeacher) {
    header('Location: teacher-login.php');
    exit;
}

$windowId = (int)($_GET['window_id'] ?? 0);
$filterClass = trim($_GET['class'] ?? 'ALL');
$filterStream = trim($_GET['stream'] ?? 'ALL');

// 1. Fetch Window details
$window = null;
if ($windowId > 0) {
    $wStmt = thamani_db_prepare($conn, "SELECT id, title, academic_year, term, assessment_type, is_open, is_published, show_positions, show_points, disabled_points_levels, created_at FROM reporting_windows WHERE id = ? LIMIT 1");
    if ($wStmt) {
        thamani_db_stmt_bind_param($wStmt, "i", $windowId);
        thamani_db_stmt_execute($wStmt);
        $wRes = thamani_db_stmt_get_result($wStmt);
        if ($wRes) $window = thamani_db_fetch_assoc($wRes);
        thamani_db_stmt_close($wStmt);
    }
}

if (!$window) {
    $wRes = thamani_db_query($conn, "SELECT id, title, academic_year, term, assessment_type, is_open, is_published, show_positions, show_points, disabled_points_levels, created_at FROM reporting_windows ORDER BY id DESC LIMIT 1");
    if ($wRes) $window = thamani_db_fetch_assoc($wRes);
}

if (!$window) {
    die("No reporting window found in system.");
}

$windowId = (int)$window['id'];

// 2. Fetch all Reporting Windows for quick switching dropdown
$allWindows = [];
$rwRes = thamani_db_query($conn, "SELECT id, title, academic_year, term, assessment_type, is_open, is_published FROM reporting_windows ORDER BY id DESC");
if ($rwRes) {
    while ($r = thamani_db_fetch_assoc($rwRes)) {
        $allWindows[] = $r;
    }
}

// 3. Fetch all system active streams
$systemStreams = get_system_streams($conn);

// 4. Fetch all students
$studentsSql = "SELECT id, full_name, lin_number, gender, class_level, stream, previous_school 
                FROM students 
                WHERE status = 'Enrolled' OR status IS NULL OR status = 'Pending'
                ORDER BY class_level ASC, stream ASC, full_name ASC";
$sRes = thamani_db_query($conn, $studentsSql);
$allStudents = [];
if ($sRes) {
    while ($st = thamani_db_fetch_assoc($sRes)) {
        $allStudents[] = $st;
    }
}

// 5. Fetch all student marks for this reporting window
$marksSql = "SELECT student_id, subject, score, max_score 
             FROM student_marks 
             WHERE window_id = ? OR term = ?";
$marksStmt = thamani_db_prepare($conn, $marksSql);
$studentMarksMap = [];
if ($marksStmt) {
    thamani_db_stmt_bind_param($marksStmt, "is", $windowId, $window['term']);
    thamani_db_stmt_execute($marksStmt);
    $mRes = thamani_db_stmt_get_result($marksStmt);
    if ($mRes) {
        while ($mr = thamani_db_fetch_assoc($mRes)) {
            $sId = (int)$mr['student_id'];
            if (!isset($studentMarksMap[$sId])) {
                $studentMarksMap[$sId] = [];
            }
            $studentMarksMap[$sId][] = $mr;
        }
    }
    thamani_db_stmt_close($marksStmt);
}

// 6. Fetch report card comments
$commentsMap = [];
$commRes = thamani_db_query($conn, "SELECT student_id, class_teacher_comment, head_teacher_comment FROM report_comments WHERE window_id = " . (int)$windowId);
if ($commRes) {
    while ($cm = thamani_db_fetch_assoc($commRes)) {
        $commentsMap[(int)$cm['student_id']] = $cm;
    }
}

// 7. Organize Students into Classes -> Streams
$classOrder = ['Senior 1', 'Senior 2', 'Senior 3', 'Senior 4', 'Senior 5', 'Senior 6'];
$matrix = [];
foreach ($classOrder as $cName) {
    $matrix[$cName] = [];
    foreach ($systemStreams as $sName) {
        $matrix[$cName][$sName] = [];
    }
}

// Populate students into matrix
foreach ($allStudents as $st) {
    $cLevel = trim($st['class_level'] ?? 'Senior 1');
    $sLevel = trim($st['stream'] ?? 'Stream A');
    
    if (!isset($matrix[$cLevel])) {
        $matrix[$cLevel] = [];
    }
    if (!isset($matrix[$cLevel][$sLevel])) {
        $matrix[$cLevel][$sLevel] = [];
    }
    
    $stId = (int)$st['id'];
    $stMarks = $studentMarksMap[$stId] ?? [];
    $totScore = 0;
    $totMax = 0;
    $pointsList = [];
    
    $edLevel = (strpos($cLevel, 'Senior 5') !== false || strpos($cLevel, 'Senior 6') !== false) ? 'A-Level' : 'O-Level';
    
    foreach ($stMarks as $m) {
        $sc = floatval($m['score']);
        $mx = floatval($m['max_score'] ?: 100);
        $gi = calculate_grade_info($sc, $mx, $edLevel);
        $totScore += $sc;
        $totMax   += $mx;
        $pointsList[] = $gi['points'];
    }
    
    $avgScore = ($totMax > 0) ? round(($totScore / $totMax) * 100, 1) : 0;
    
    $divisionText = '—';
    $aggText = '—';
    if ($edLevel === 'O-Level' && count($pointsList) >= 8) {
        sort($pointsList);
        $best8 = array_slice($pointsList, 0, 8);
        $sumAgg = array_sum($best8);
        $aggText = (string)$sumAgg . ' Agg';
        if ($sumAgg <= 32) $divisionText = 'DIV I';
        elseif ($sumAgg <= 45) $divisionText = 'DIV II';
        elseif ($sumAgg <= 58) $divisionText = 'DIV III';
        elseif ($sumAgg <= 72) $divisionText = 'DIV IV';
        else $divisionText = 'DIV U';
    } elseif ($edLevel === 'A-Level' && !empty($pointsList)) {
        $totPts = array_sum($pointsList);
        $aggText = (string)$totPts . ' Pts';
        $divisionText = $totPts >= 12 ? 'PASS' : 'SUB-PASS';
    }
    
    $st['subject_count'] = count($stMarks);
    $st['total_score']   = $totScore;
    $st['average_score'] = $avgScore;
    $st['division']      = $divisionText;
    $st['aggregates']    = $aggText;
    $st['has_comment']   = !empty($commentsMap[$stId]['class_teacher_comment']);
    
    $matrix[$cLevel][$sLevel][] = $st;
}

$backUrl = $isAdmin ? 'admin_dashboard.php?tab=tab-admin-reporting#tab-admin-reporting' : 'teacher_dashboard.php?tab=tab-teacher-results#tab-teacher-results';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporting Window Explorer - <?= htmlspecialchars($window['title']) ?> | Thamani High School</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            gold: '#F59E0B',
                            darkGold: '#D97706',
                            green: '#15803D',
                            darkGreen: '#166534',
                            navy: '#0F172A',
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100 font-sans text-gray-900 antialiased min-h-screen flex flex-col">

    <!-- TOP HEADER NAVBAR -->
    <header class="bg-gray-950 text-white border-b-4 border-brand-gold sticky top-0 z-40 shadow-xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-wrap justify-between items-center gap-4">
            <div class="flex items-center gap-3">
                <a href="<?= $backUrl ?>" class="w-10 h-10 rounded-xl bg-gray-800 hover:bg-gray-700 flex items-center justify-center text-amber-400 transition-all shadow border border-gray-700">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded bg-amber-500/20 text-amber-400 border border-amber-500/40 text-[10px] font-black uppercase tracking-wider">Academic Window Reports</span>
                        <?php 
                        $showPoints = !isset($window['show_points']) || (int)$window['show_points'] === 1;
                        if (!empty($window['is_published'])): 
                        ?>
                            <span class="px-2.5 py-0.5 rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 text-[10px] font-black uppercase tracking-wider">📢 Published</span>
                        <?php else: ?>
                            <span class="px-2.5 py-0.5 rounded bg-gray-800 text-gray-400 border border-gray-700 text-[10px] font-black uppercase tracking-wider">Unpublished</span>
                        <?php endif; ?>
                        <?php if (!$showPoints): ?>
                            <span class="px-2.5 py-0.5 rounded bg-red-500/20 text-red-400 border border-red-500/40 text-[10px] font-black uppercase tracking-wider">🚫 Points Column Disabled</span>
                        <?php endif; ?>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight text-white mt-0.5 flex items-center gap-2">
                        <i data-lucide="file-text" class="w-6 h-6 text-amber-400"></i>
                        <span><?= htmlspecialchars($window['title']) ?></span>
                    </h1>
                </div>
            </div>

            <!-- WINDOW SWITCHER DROPDOWN & ACTIONS -->
            <div class="flex items-center gap-3">
                <form method="get" action="view_window_reports.php" class="flex items-center gap-2">
                    <input type="hidden" name="class" value="<?= htmlspecialchars($filterClass) ?>">
                    <input type="hidden" name="stream" value="<?= htmlspecialchars($filterStream) ?>">
                    <select name="window_id" onchange="this.form.submit()" class="px-3.5 py-2.5 bg-gray-900 border border-gray-700 rounded-xl text-xs font-bold text-amber-300 focus:ring-2 focus:ring-amber-500 shadow-sm">
                        <?php foreach ($allWindows as $wOpt): ?>
                            <option value="<?= $wOpt['id'] ?>" <?= $windowId === (int)$wOpt['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($wOpt['title']) ?> (Term <?= $wOpt['term'] ?> <?= $wOpt['academic_year'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <a href="<?= $backUrl ?>" class="px-4 py-2.5 bg-gray-800 hover:bg-gray-700 text-white font-bold rounded-xl text-xs flex items-center gap-2 transition-all border border-gray-700 shadow-sm">
                    <i data-lucide="layout-dashboard" class="w-4 h-4 text-amber-400"></i>
                    <span>Dashboard</span>
                </a>
            </div>
        </div>
    </header>

    <!-- MAIN CONTAINER -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-grow space-y-8">

        <!-- FILTER TABS BAR -->
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-200/80 flex flex-wrap justify-between items-center gap-4">
            <!-- Class Tabs -->
            <div class="flex flex-wrap items-center gap-1.5 overflow-x-auto">
                <a href="view_window_reports.php?window_id=<?= $windowId ?>&class=ALL&stream=<?= urlencode($filterStream) ?>" 
                   class="px-4 py-2 rounded-xl text-xs font-extrabold transition-all border <?= $filterClass === 'ALL' ? 'bg-gray-950 text-amber-400 border-gray-950 shadow' : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100' ?>">
                    All Classes
                </a>
                <?php foreach ($classOrder as $cOption): 
                    $cCount = 0;
                    if (isset($matrix[$cOption])) {
                        foreach ($matrix[$cOption] as $sArr) {
                            $cCount += count($sArr);
                        }
                    }
                ?>
                    <a href="view_window_reports.php?window_id=<?= $windowId ?>&class=<?= urlencode($cOption) ?>&stream=<?= urlencode($filterStream) ?>" 
                       class="px-4 py-2 rounded-xl text-xs font-extrabold transition-all flex items-center gap-1.5 border <?= $filterClass === $cOption ? 'bg-gray-950 text-amber-400 border-gray-950 shadow' : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100' ?>">
                        <span><?= $cOption ?></span>
                        <span class="px-1.5 py-0.5 rounded-md text-[10px] <?= $filterClass === $cOption ? 'bg-amber-400 text-gray-950' : 'bg-gray-200 text-gray-700' ?> font-black"><?= $cCount ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Stream Filter Select -->
            <div class="flex items-center gap-2">
                <label class="text-xs font-black text-gray-600 uppercase">Stream:</label>
                <form method="get" action="view_window_reports.php">
                    <input type="hidden" name="window_id" value="<?= $windowId ?>">
                    <input type="hidden" name="class" value="<?= htmlspecialchars($filterClass) ?>">
                    <select name="stream" onchange="this.form.submit()" class="px-3.5 py-2 border border-gray-300 rounded-xl text-xs font-bold text-gray-800 bg-white focus:ring-2 focus:ring-amber-500 shadow-sm">
                        <option value="ALL" <?= $filterStream === 'ALL' ? 'selected' : '' ?>>All Streams</option>
                        <?php foreach ($systemStreams as $strmOpt): ?>
                            <option value="<?= htmlspecialchars($strmOpt) ?>" <?= $filterStream === $strmOpt ? 'selected' : '' ?>>
                                <?= htmlspecialchars($strmOpt) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <!-- REPORT MATRIX: ORGANIZED BY CLASS -> STREAMS -->
        <?php 
        $renderedAny = false;
        foreach ($classOrder as $cName):
            if ($filterClass !== 'ALL' && $filterClass !== $cName) continue;
            
            $cStreams = $matrix[$cName] ?? [];
            $classTotalStudents = 0;
            foreach ($cStreams as $sName => $sStudents) {
                if ($filterStream === 'ALL' || $filterStream === $sName) {
                    $classTotalStudents += count($sStudents);
                }
            }
            $isClassPointsEnabled = isPointsEnabledForLevel($window, $cName);
        ?>
            <section class="space-y-6 bg-white p-6 sm:p-8 rounded-3xl border border-gray-200/90 shadow-sm">
                
                <!-- CLASS LEVEL BANNER -->
                <div class="flex flex-wrap justify-between items-center gap-4 pb-4 border-b border-gray-200">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 text-gray-950 flex items-center justify-center font-black text-lg shadow-md">
                            <?= preg_replace('/Senior\s*/i', 'S.', $cName) ?>
                        </div>
                        <div>
                            <h2 class="text-xl font-black text-gray-900 tracking-tight flex items-center gap-2">
                                <span><?= htmlspecialchars($cName) ?></span>
                                <span class="text-xs font-extrabold px-2.5 py-1 rounded-full bg-amber-100 text-amber-900 border border-amber-300">
                                    <?= (strpos($cName, 'Senior 5') !== false || strpos($cName, 'Senior 6') !== false) ? 'A-Level Curriculum' : 'O-Level Curriculum (New CBC & UNEB)' ?>
                                </span>
                                <?php if ($isClassPointsEnabled): ?>
                                    <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1">
                                        <i data-lucide="check-circle-2" class="w-3.5 h-3.5 text-emerald-600"></i> Points Enabled
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-rose-100 text-rose-800 border border-rose-300 flex items-center gap-1">
                                        <i data-lucide="slash" class="w-3.5 h-3.5 text-rose-600"></i> Points Disabled
                                    </span>
                                <?php endif; ?>
                            </h2>
                            <p class="text-xs text-gray-500 mt-0.5 font-medium">
                                Organized by Stream divisions · <?= $classTotalStudents ?> Enrolled Students Total
                            </p>
                        </div>
                    </div>
                </div>

                <!-- STREAMS UNDER THIS CLASS -->
                <div class="space-y-6">
                    <?php 
                    $classRenderedStreams = 0;
                    foreach ($cStreams as $sName => $sStudents):
                        if ($filterStream !== 'ALL' && $filterStream !== $sName) continue;
                        $classRenderedStreams++;
                        $renderedAny = true;
                    ?>
                        <div class="border border-gray-200 rounded-2xl overflow-hidden bg-gray-50/50 shadow-sm">
                            
                            <!-- STREAM HEADER -->
                            <div class="bg-gray-900 text-white px-6 py-4 flex flex-wrap justify-between items-center gap-4">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-400 font-black flex items-center justify-center text-xs border border-amber-500/30">
                                        <i data-lucide="layers" class="w-4 h-4"></i>
                                    </span>
                                    <div>
                                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                                            <span><?= htmlspecialchars($cName) ?></span>
                                            <span class="text-amber-400">·</span>
                                            <span class="text-amber-300 font-extrabold"><?= htmlspecialchars($sName) ?></span>
                                        </h3>
                                        <p class="text-[11px] text-gray-400 font-medium">Stream Division · <?= count($sStudents) ?> Students Enrolled</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-gray-400 mr-2"><?= count($sStudents) ?> Reports Prepared</span>
                                </div>
                            </div>

                            <!-- STREAM STUDENTS TABLE -->
                            <div class="overflow-x-auto bg-white">
                                <?php if (!empty($sStudents)): ?>
                                    <table class="w-full text-left text-xs border-collapse">
                                        <thead>
                                            <tr class="bg-gray-100 text-gray-700 font-extrabold uppercase tracking-wider text-[11px] border-b border-gray-200">
                                                <th class="p-3.5 w-12 text-center">#</th>
                                                <th class="p-3.5">LIN / Student ID</th>
                                                <th class="p-3.5">Student Full Name</th>
                                                <th class="p-3.5">Gender</th>
                                                <th class="p-3.5 text-center">Subjects Entered</th>
                                                <th class="p-3.5 text-center">Average Score</th>
                                                <th class="p-3.5 text-center">Grade / Division</th>
                                                <th class="p-3.5 text-right">Report Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 font-medium">
                                            <?php $stIdx = 1; foreach ($sStudents as $st): ?>
                                                <tr class="hover:bg-amber-50/40 transition-colors">
                                                    <td class="p-3.5 text-center font-bold text-gray-500"><?= $stIdx++ ?></td>
                                                    <td class="p-3.5 font-mono text-gray-600 font-bold"><?= htmlspecialchars($st['lin_number'] ?: 'LIN-'.$st['id']) ?></td>
                                                    <td class="p-3.5 font-black text-gray-900 text-sm">
                                                        <?= htmlspecialchars($st['full_name']) ?>
                                                    </td>
                                                    <td class="p-3.5 text-gray-600 font-semibold"><?= htmlspecialchars($st['gender'] ?: '—') ?></td>
                                                    <td class="p-3.5 text-center">
                                                        <?php if ($st['subject_count'] > 0): ?>
                                                            <span class="px-2.5 py-1 rounded-lg bg-green-100 text-green-900 font-bold text-[11px] border border-green-200">
                                                                ✓ <?= $st['subject_count'] ?> Subjects
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="px-2.5 py-1 rounded-lg bg-red-100 text-red-800 font-bold text-[11px] border border-red-200">
                                                                Pending Entry
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="p-3.5 text-center font-mono font-bold text-gray-900">
                                                        <?= $st['average_score'] > 0 ? $st['average_score'] . '%' : '—' ?>
                                                    </td>
                                                    <td class="p-3.5 text-center">
                                                        <?php if ($st['division'] !== '—'): ?>
                                                            <span class="px-2.5 py-1 rounded-lg bg-amber-500 text-gray-950 font-black text-[11px] shadow-sm">
                                                                <?= htmlspecialchars($st['division']) ?> (<?= htmlspecialchars($st['aggregates']) ?>)
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-gray-400 font-bold">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="p-3.5 text-right space-x-1 whitespace-nowrap">
                                                        <a href="print_report_card.php?student_id=<?= (int)$st['id'] ?>&window_id=<?= $windowId ?>" 
                                                           target="_blank" 
                                                           class="px-3.5 py-2 bg-brand-green text-white font-black rounded-xl text-xs hover:bg-green-800 transition-all inline-flex items-center gap-1.5 shadow-sm">
                                                            <i data-lucide="printer" class="w-3.5 h-3.5"></i> View Report Card
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <div class="p-8 text-center text-gray-500 font-medium">
                                        <i data-lucide="info" class="w-6 h-6 text-gray-400 mx-auto mb-2"></i>
                                        No students enrolled in <strong class="text-gray-700"><?= htmlspecialchars($cName) ?> · <?= htmlspecialchars($sName) ?></strong> yet.
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>

                    <?php if ($classRenderedStreams === 0): ?>
                        <div class="p-6 text-center text-gray-500 bg-gray-50 rounded-2xl border border-dashed border-gray-300">
                            No active stream records match the selected filter for <?= htmlspecialchars($cName) ?>.
                        </div>
                    <?php endif; ?>
                </div>

            </section>
        <?php endforeach; ?>

        <?php if (!$renderedAny): ?>
            <div class="bg-white p-12 rounded-3xl border border-gray-200 text-center text-gray-500">
                <i data-lucide="folder-open" class="w-12 h-12 text-amber-500 mx-auto mb-3"></i>
                <h3 class="text-lg font-bold text-gray-900">No Reports Found</h3>
                <p class="text-xs text-gray-500 mt-1">Try selecting a different class level or stream filter above.</p>
            </div>
        <?php endif; ?>

    </main>

    <!-- FOOTER -->
    <footer class="bg-gray-950 text-white pt-8 pb-6 border-t-4 border-brand-gold mt-auto text-xs text-gray-400">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
            <span>© 2026 Thamani High School Academic Reporting System</span>
            <span>Window ID #<?= $windowId ?> · <?= htmlspecialchars($window['title']) ?></span>
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
