<?php
/**
 * Thamani High School - Printable Official Academic Report Card
 * -------------------------------------------------------------
 * Clean, printable A4 layout with school crest, subject grades,
 * performance charts, stream rank position, attendance stats & comments.
 */

require_once 'conn.php';
require_once 'auth_student.php';
require_once 'grading_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$studentId = (int)($_GET['student_id'] ?? 0);
$windowId  = (int)($_GET['window_id'] ?? 0);

$currentStudent = !empty($_SESSION['student_id']) ? (int)$_SESSION['student_id'] : 0;
$currentAdmin   = !empty($_SESSION['admin_id']);
$currentTeacher = !empty($_SESSION['teacher_id']);

if (!$currentAdmin && !$currentTeacher && $currentStudent !== $studentId) {
    if ($currentStudent > 0) {
        $studentId = $currentStudent;
    } else {
        header('Location: student-login.php');
        exit;
    }
}

if ($studentId <= 0) {
    die("Invalid student record requested.");
}

// Fetch Student details
$student = null;
$sStmt = thamani_db_prepare($conn, "SELECT id, full_name, lin_number, gender, class_level, stream, previous_school, guardian_name, guardian_phone FROM students WHERE id = ? LIMIT 1");
if ($sStmt) {
    thamani_db_stmt_bind_param($sStmt, "i", $studentId);
    thamani_db_stmt_execute($sStmt);
    $sRes = thamani_db_stmt_get_result($sStmt);
    if ($sRes) $student = thamani_db_fetch_assoc($sRes);
    thamani_db_stmt_close($sStmt);
}

if (!$student) {
    die("Student record not found.");
}

// Fetch Window details
$window = null;
if ($windowId > 0) {
    $wStmt = thamani_db_prepare($conn, "SELECT id, title, academic_year, term, assessment_type, is_open, is_published, show_positions FROM reporting_windows WHERE id = ? LIMIT 1");
    if ($wStmt) {
        thamani_db_stmt_bind_param($wStmt, "i", $windowId);
        thamani_db_stmt_execute($wStmt);
        $wRes = thamani_db_stmt_get_result($wStmt);
        if ($wRes) $window = thamani_db_fetch_assoc($wRes);
        thamani_db_stmt_close($wStmt);
    }
}

if (!$window) {
    $wRes = thamani_db_query($conn, "SELECT id, title, academic_year, term, assessment_type, is_open, is_published, show_positions FROM reporting_windows WHERE is_published = 1 ORDER BY id DESC LIMIT 1");
    if ($wRes) $window = thamani_db_fetch_assoc($wRes);
}

if (!$window) {
    die("No published reporting window found for this report card.");
}

// Non-admin & non-teacher access check: Must be published
if (!$currentAdmin && !$currentTeacher && empty($window['is_published'])) {
    die("Academic results for this term have not been published by the Administration yet.");
}

$windowId  = (int)$window['id'];
$class     = $student['class_level'];
$stream    = $student['stream'];
$edLevel   = (strpos($class, 'Senior 5') !== false || strpos($class, 'Senior 6') !== false) ? 'A-Level' : 'O-Level';

// Fetch Marks for this student in this window
$marks = [];
$mStmt = thamani_db_prepare($conn, "SELECT subject, score, max_score, comments, updated_at FROM student_marks WHERE student_id = ? AND (window_id = ? OR (class_level = ? AND term = ?)) ORDER BY subject ASC");
if ($mStmt) {
    thamani_db_stmt_bind_param($mStmt, "iiss", $studentId, $windowId, $class, $window['term']);
    thamani_db_stmt_execute($mStmt);
    $mRes = thamani_db_stmt_get_result($mStmt);
    if ($mRes) {
        while ($r = thamani_db_fetch_assoc($mRes)) {
            $marks[] = $r;
        }
    }
    thamani_db_stmt_close($mStmt);
}

// Compute Stream Ranking if enabled
$streamRankText = 'N/A';
if (!empty($window['show_positions'])) {
    $streamTotals = [];
    $allStreamStmt = thamani_db_prepare($conn, "SELECT student_id, SUM(score) as total_score FROM student_marks WHERE class_level = ? AND (stream = ? OR ? = 'All Streams') AND (window_id = ? OR term = ?) GROUP BY student_id");
    if ($allStreamStmt) {
        thamani_db_stmt_bind_param($allStreamStmt, "sssis", $class, $stream, $stream, $windowId, $window['term']);
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

// Fetch Comments
$reportComment = ['class_teacher_comment' => '', 'head_teacher_comment' => ''];
$cStmt = thamani_db_prepare($conn, "SELECT class_teacher_comment, head_teacher_comment FROM report_comments WHERE student_id = ? AND window_id = ? LIMIT 1");
if ($cStmt) {
    thamani_db_stmt_bind_param($cStmt, "ii", $studentId, $windowId);
    thamani_db_stmt_execute($cStmt);
    $cRes = thamani_db_stmt_get_result($cStmt);
    if ($cRes && $cr = thamani_db_fetch_assoc($cRes)) {
        $reportComment = $cr;
    }
    thamani_db_stmt_close($cStmt);
}

// Fetch Attendance Stats
$attStats = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Excused' => 0];
$attRes = thamani_db_query($conn, "SELECT status, COUNT(*) as cnt FROM student_attendance WHERE student_id = {$studentId} GROUP BY status");
if ($attRes) {
    while ($ar = thamani_db_fetch_assoc($attRes)) {
        $attStats[$ar['status']] = (int)$ar['cnt'];
    }
}

// Calculate totals and averages
$totalScore = 0;
$totalMax   = 0;
$subjectCount = count($marks);

foreach ($marks as $m) {
    $totalScore += floatval($m['score']);
    $totalMax   += floatval($m['max_score'] ?: 100);
}

$averagePercent = ($totalMax > 0) ? round(($totalScore / $totalMax) * 100, 1) : 0;
$overallGradeInfo = calculate_grade_info($totalScore, $totalMax ?: 100, $edLevel);
$gradingLegend = get_grading_scales_from_db($edLevel);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Report Card - <?= htmlspecialchars($student['full_name']) ?> (<?= htmlspecialchars($window['title']) ?>)</title>
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
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; font-size: 11px; }
            .report-card { border: none !important; shadow: none !important; width: 100% !important; margin: 0 !important; }
            @page { size: A4 portrait; margin: 12mm; }
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-900 font-sans p-4 sm:p-8 min-h-screen">

    <!-- Action Toolbar (No Print) -->
    <div class="no-print max-w-4xl mx-auto mb-6 flex justify-between items-center bg-white p-4 rounded-2xl shadow-md border border-gray-200">
        <div class="flex items-center gap-3">
            <a href="javascript:history.back()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl text-xs flex items-center gap-1.5">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
            </a>
            <span class="text-xs text-gray-500 font-medium">Official Academic Report Card Preview</span>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.print();" class="px-6 py-2.5 bg-brand-green hover:bg-brand-darkGreen text-white font-extrabold rounded-xl text-xs flex items-center gap-2 shadow-lg active:scale-95 transition-transform">
                <i data-lucide="printer" class="w-4 h-4"></i> Print / Download PDF
            </button>
        </div>
    </div>

    <!-- Official Report Card Box -->
    <div class="report-card max-w-4xl mx-auto bg-white p-8 sm:p-12 rounded-3xl shadow-xl border border-gray-200 text-gray-900 relative">

        <!-- Header Crest & School Title -->
        <div class="border-b-4 border-brand-gold pb-6 mb-6">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-center sm:text-left">
                <div class="flex items-center gap-4">
                    <img src="thamani-logo.png" alt="Thamani Crest" class="h-20 w-auto object-contain" onerror="this.src='favicon.svg'">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-black text-brand-green uppercase tracking-tight">THAMANI HIGH SCHOOL</h1>
                        <p class="text-xs font-bold text-brand-maroon uppercase tracking-widest">Knowledge · Integrity · Excellence</p>
                        <p class="text-[11px] text-gray-500 mt-1">Kakiri Main Campus, Wakiso District, Uganda | UNEB Center No: U3421</p>
                        <p class="text-[11px] text-gray-500">Tel: +256 414 123 456 | Email: info@thamani.ac.ug</p>
                    </div>
                </div>
                <div class="bg-brand-lightGreen p-4 rounded-2xl border border-brand-green/30 text-center flex-shrink-0 min-w-[170px]">
                    <span class="text-[10px] font-black uppercase text-brand-maroon tracking-wider block">Official Report</span>
                    <span class="text-sm font-black text-brand-green block mt-0.5"><?= htmlspecialchars($window['title']) ?></span>
                    <span class="text-[11px] font-bold text-gray-600 block"><?= htmlspecialchars($window['term']) ?> · <?= htmlspecialchars($window['academic_year']) ?></span>
                </div>
            </div>
        </div>

        <!-- Student Biodata Sheet -->
        <div class="bg-gray-50 p-5 rounded-2xl border border-gray-200 mb-6 text-xs grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <span class="text-gray-400 font-bold uppercase text-[10px] block">Student Name</span>
                <span class="font-black text-gray-900 text-sm"><?= htmlspecialchars($student['full_name']) ?></span>
            </div>
            <div>
                <span class="text-gray-400 font-bold uppercase text-[10px] block">LIN Number</span>
                <span class="font-mono font-bold text-brand-green"><?= htmlspecialchars($student['lin_number'] ?: 'N/A') ?></span>
            </div>
            <div>
                <span class="text-gray-400 font-bold uppercase text-[10px] block">Class Level & Stream</span>
                <span class="font-bold text-gray-900"><?= htmlspecialchars($student['class_level']) ?> (<?= htmlspecialchars($student['stream']) ?>)</span>
            </div>
            <div>
                <span class="text-gray-400 font-bold uppercase text-[10px] block">Stream Rank Position</span>
                <span class="font-black text-brand-maroon text-sm"><?= htmlspecialchars($streamRankText) ?></span>
            </div>
        </div>

        <!-- Academic Marks Table -->
        <div class="mb-6 overflow-hidden rounded-2xl border border-gray-200 shadow-sm">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-brand-green text-white font-bold uppercase tracking-wider text-[11px]">
                        <th class="p-3">Subject Name</th>
                        <th class="p-3 text-center">Score</th>
                        <th class="p-3 text-center">Max</th>
                        <th class="p-3 text-center">% Score</th>
                        <th class="p-3 text-center">Grade</th>
                        <th class="p-3 text-center">Points</th>
                        <th class="p-3">Teacher Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <?php if (!empty($marks)): ?>
                        <?php foreach ($marks as $m): 
                            $sc = floatval($m['score']);
                            $mx = floatval($m['max_score'] ?: 100);
                            $gi = calculate_grade_info($sc, $mx, $edLevel);
                        ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="p-3 font-bold text-gray-900"><?= htmlspecialchars($m['subject']) ?></td>
                                <td class="p-3 text-center font-mono font-bold text-gray-900"><?= number_format($sc, 1) ?></td>
                                <td class="p-3 text-center font-mono text-gray-500"><?= number_format($mx, 0) ?></td>
                                <td class="p-3 text-center font-mono font-bold text-brand-green"><?= $gi['percent'] ?>%</td>
                                <td class="p-3 text-center">
                                    <span class="px-2 py-0.5 rounded font-black text-xs bg-brand-lightGreen text-brand-green border border-brand-green/30">
                                        <?= htmlspecialchars($gi['grade']) ?>
                                    </span>
                                </td>
                                <td class="p-3 text-center font-bold text-gray-700"><?= $gi['points'] ?></td>
                                <td class="p-3 text-gray-600 text-[11px] italic"><?= htmlspecialchars($m['comments'] ?: $gi['remark']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-gray-500">
                                No subject marks submitted for this student in this term yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Performance Summary & Visual Chart -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">

            <!-- Stat Summary Card -->
            <div class="bg-brand-lightGreen p-5 rounded-2xl border border-brand-green/30 flex flex-col justify-between">
                <h3 class="text-xs font-black uppercase text-brand-green tracking-wider mb-3 flex items-center gap-1.5">
                    <i data-lucide="award" class="w-4 h-4"></i> Overall Performance Summary
                </h3>
                <div class="grid grid-cols-2 gap-3 text-xs mb-3">
                    <div class="bg-white p-3 rounded-xl border border-brand-green/20">
                        <span class="text-gray-400 font-bold uppercase text-[9px] block">Total Marks</span>
                        <span class="text-lg font-black text-brand-green"><?= number_format($totalScore, 1) ?> / <?= number_format($totalMax, 0) ?></span>
                    </div>
                    <div class="bg-white p-3 rounded-xl border border-brand-green/20">
                        <span class="text-gray-400 font-bold uppercase text-[9px] block">Overall Average</span>
                        <span class="text-lg font-black text-brand-maroon"><?= $averagePercent ?>%</span>
                    </div>
                </div>
                <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-brand-green/20 text-xs">
                    <span class="font-bold text-gray-700">Overall Grade & Award:</span>
                    <span class="px-3 py-1 bg-brand-gold text-brand-green font-black rounded-lg uppercase tracking-wider text-xs">
                        <?= htmlspecialchars($overallGradeInfo['grade']) ?> (<?= htmlspecialchars($overallGradeInfo['remark']) ?>)
                    </span>
                </div>
            </div>

            <!-- Visual Subject Performance Chart (SVG/CSS Bars) -->
            <div class="bg-gray-50 p-5 rounded-2xl border border-gray-200">
                <h3 class="text-xs font-black uppercase text-gray-700 tracking-wider mb-3 flex items-center gap-1.5">
                    <i data-lucide="bar-chart-2" class="w-4 h-4 text-brand-green"></i> Subject Performance Chart
                </h3>
                <div class="space-y-2 max-h-40 overflow-y-auto pr-1">
                    <?php foreach ($marks as $m): 
                        $sc = floatval($m['score']);
                        $mx = floatval($m['max_score'] ?: 100);
                        $pct = ($mx > 0) ? min(100, max(0, ($sc / $mx) * 100)) : 0;
                    ?>
                        <div>
                            <div class="flex justify-between text-[10px] font-bold text-gray-700 mb-0.5">
                                <span class="truncate max-w-[140px]"><?= htmlspecialchars($m['subject']) ?></span>
                                <span><?= round($pct, 1) ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 h-2 rounded-full overflow-hidden">
                                <div class="bg-brand-green h-full rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Attendance Stats & Comments -->
        <div class="space-y-4 mb-6 text-xs">
            
            <!-- Attendance Row -->
            <div class="bg-white p-4 rounded-2xl border border-gray-200 flex items-center justify-between">
                <span class="font-bold text-gray-800 uppercase tracking-wider text-[10px] flex items-center gap-1.5">
                    <i data-lucide="check-circle-2" class="w-4 h-4 text-brand-green"></i> Term Attendance Record:
                </span>
                <div class="flex gap-4 font-semibold text-gray-700">
                    <span>Days Present: <strong class="text-brand-green"><?= $attStats['Present'] ?></strong></span>
                    <span>Absent: <strong class="text-red-600"><?= $attStats['Absent'] ?></strong></span>
                    <span>Late: <strong class="text-amber-600"><?= $attStats['Late'] ?></strong></span>
                </div>
            </div>

            <!-- Class Teacher Comment -->
            <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30">
                <span class="font-bold text-amber-900 uppercase text-[10px] block mb-1">Class Teacher Remarks:</span>
                <p class="text-gray-800 italic"><?= htmlspecialchars($reportComment['class_teacher_comment'] ?: 'A dedicated and disciplined student with consistent effort across subjects.') ?></p>
            </div>

            <!-- Head Teacher Comment -->
            <div class="p-4 rounded-2xl bg-brand-lightGreen border border-brand-green/30">
                <span class="font-bold text-brand-green uppercase text-[10px] block mb-1">Head Teacher Remarks & Recommendation:</span>
                <p class="text-gray-800 italic"><?= htmlspecialchars($reportComment['head_teacher_comment'] ?: 'Promoted to the next class. Keep up the high standard of academic discipline.') ?></p>
            </div>
        </div>

        <!-- Grading Legend Footer -->
        <div class="bg-gray-50 p-4 rounded-2xl border border-gray-200 text-[10px] mb-8">
            <span class="font-extrabold text-gray-700 uppercase tracking-wider block mb-1.5">Key to Grading System (<?= htmlspecialchars($edLevel) ?>):</span>
            <div class="flex flex-wrap gap-3 text-gray-600">
                <?php foreach ($gradingLegend as $gl): ?>
                    <span class="bg-white px-2 py-0.5 rounded border border-gray-200 font-semibold">
                        <strong class="text-brand-green"><?= htmlspecialchars($gl['grade']) ?></strong>: <?= $gl['min'] ?>%-<?= $gl['max'] ?>% (<?= htmlspecialchars($gl['remark']) ?>)
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Official Signatures -->
        <div class="pt-8 border-t border-gray-200 grid grid-cols-2 gap-8 text-center text-xs">
            <div>
                <div class="h-12 flex items-end justify-center pb-1">
                    <span class="font-serif italic text-gray-400">Class Teacher Signature</span>
                </div>
                <div class="border-t border-gray-400 pt-1 font-bold text-gray-800">Class Teacher</div>
            </div>
            <div>
                <div class="h-12 flex items-end justify-center pb-1">
                    <span class="font-serif italic text-brand-green font-bold text-sm">Mr. Mukasa Denis (HM)</span>
                </div>
                <div class="border-t border-gray-400 pt-1 font-bold text-gray-800">Head Teacher & School Stamp</div>
            </div>
        </div>

    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
