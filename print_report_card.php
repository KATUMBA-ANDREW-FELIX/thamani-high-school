<?php
/**
 * Thamani High School - Authentic Ugandan Academic Report Card Generator
 * ----------------------------------------------------------------------
 * Styled after official dotShule / national Ugandan secondary school report cards.
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
    $wStmt = thamani_db_prepare($conn, "SELECT id, title, academic_year, term, assessment_type, is_open, is_published, show_positions, show_points, disabled_points_levels FROM reporting_windows WHERE id = ? LIMIT 1");
    if ($wStmt) {
        thamani_db_stmt_bind_param($wStmt, "i", $windowId);
        thamani_db_stmt_execute($wStmt);
        $wRes = thamani_db_stmt_get_result($wStmt);
        if ($wRes) $window = thamani_db_fetch_assoc($wRes);
        thamani_db_stmt_close($wStmt);
    }
}

if (!$window) {
    $wRes = thamani_db_query($conn, "SELECT id, title, academic_year, term, assessment_type, is_open, is_published, show_positions, show_points, disabled_points_levels FROM reporting_windows WHERE is_published = 1 ORDER BY id DESC LIMIT 1");
    if ($wRes) $window = thamani_db_fetch_assoc($wRes);
}

if (!$window) {
    die("No published reporting window found for this report card.");
}

if (!$currentAdmin && !$currentTeacher && empty($window['is_published'])) {
    die("Academic results for this term have not been published by Administration yet.");
}

$windowId  = (int)$window['id'];
$class     = $student['class_level'];
$stream    = $student['stream'];
$edLevel   = (strpos($class, 'Senior 5') !== false || strpos($class, 'Senior 6') !== false) ? 'A-Level' : 'O-Level';
$showPoints = isPointsEnabledForLevel($window, $class);

// Formatted Short Class & Stream e.g. S.1/STREAM A
$shortClass = preg_replace('/Senior\s*/i', 'S.', $class);
$classStreamFormatted = strtoupper($shortClass . '/' . str_replace('Stream ', '', $stream));

// Fetch Marks & Teacher Initials
$marks = [];
$mSql = "SELECT sm.subject, sm.score, sm.max_score, sm.comments, t.full_name as teacher_name
         FROM student_marks sm
         LEFT JOIN teacher_subject_assignments tsa 
           ON (tsa.class_level = sm.class_level 
               AND (tsa.stream = sm.stream OR tsa.stream = 'All Streams' OR tsa.stream IS NULL) 
               AND strcasecmp(tsa.subject, sm.subject) = 0)
         LEFT JOIN teachers t ON tsa.teacher_id = t.id
         WHERE sm.student_id = ? AND (sm.window_id = ? OR (sm.class_level = ? AND sm.term = ?))
         ORDER BY sm.subject ASC";

$mStmt = thamani_db_prepare($conn, $mSql);
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

// Function to extract initials
function get_initials($name) {
    if (empty($name)) return 'THS';
    $words = array_filter(explode(' ', trim($name)));
    $initials = '';
    foreach ($words as $w) {
        $clean = preg_replace('/[^A-Za-z]/', '', $w);
        if (!empty($clean)) {
            $initials .= strtoupper($clean[0]);
        }
    }
    return $initials ?: 'THS';
}

// Calculate Class & Stream Positions
$classPosText = 'N/A';
$streamPosText = 'N/A';

// 1. Class Position
$classTotals = [];
$cStmt = thamani_db_prepare($conn, "SELECT student_id, SUM(score) as total_score FROM student_marks WHERE class_level = ? AND (window_id = ? OR term = ?) GROUP BY student_id");
if ($cStmt) {
    thamani_db_stmt_bind_param($cStmt, "sis", $class, $windowId, $window['term']);
    thamani_db_stmt_execute($cStmt);
    $cRes = thamani_db_stmt_get_result($cStmt);
    if ($cRes) {
        while ($cr = thamani_db_fetch_assoc($cRes)) {
            $classTotals[(int)$cr['student_id']] = floatval($cr['total_score']);
        }
    }
    thamani_db_stmt_close($cStmt);
}
if (!empty($classTotals)) {
    arsort($classTotals);
    $cRank = 1;
    $totalInClass = count($classTotals);
    foreach ($classTotals as $sId => $score) {
        if ($sId === $studentId) {
            $classPosText = "{$cRank} out of {$totalInClass}";
            break;
        }
        $cRank++;
    }
}

// 2. Stream Position
$streamTotals = [];
$sStmt = thamani_db_prepare($conn, "SELECT student_id, SUM(score) as total_score FROM student_marks WHERE class_level = ? AND (stream = ? OR ? = 'All Streams') AND (window_id = ? OR term = ?) GROUP BY student_id");
if ($sStmt) {
    thamani_db_stmt_bind_param($sStmt, "sssis", $class, $stream, $stream, $windowId, $window['term']);
    thamani_db_stmt_execute($sStmt);
    $sRes = thamani_db_stmt_get_result($sStmt);
    if ($sRes) {
        while ($sr = thamani_db_fetch_assoc($sRes)) {
            $streamTotals[(int)$sr['student_id']] = floatval($sr['total_score']);
        }
    }
    thamani_db_stmt_close($sStmt);
}
if (!empty($streamTotals)) {
    arsort($streamTotals);
    $sRank = 1;
    $totalInStream = count($streamTotals);
    foreach ($streamTotals as $sId => $score) {
        if ($sId === $studentId) {
            $streamPosText = "{$sRank} out of {$totalInStream}";
            break;
        }
        $sRank++;
    }
}

// Fetch Comments
$reportComment = ['class_teacher_comment' => '', 'head_teacher_comment' => ''];
$commStmt = thamani_db_prepare($conn, "SELECT class_teacher_comment, head_teacher_comment FROM report_comments WHERE student_id = ? AND window_id = ? LIMIT 1");
if ($commStmt) {
    thamani_db_stmt_bind_param($commStmt, "ii", $studentId, $windowId);
    thamani_db_stmt_execute($commStmt);
    $commRes = thamani_db_stmt_get_result($commStmt);
    if ($commRes && $cr = thamani_db_fetch_assoc($commRes)) {
        $reportComment = $cr;
    }
    thamani_db_stmt_close($commStmt);
}

// Totals & Averages Calculation
$totalScore = 0;
$totalMax   = 0;
$allPoints  = [];

foreach ($marks as $m) {
    $sc = floatval($m['score']);
    $mx = floatval($m['max_score'] ?: 100);
    $gi = calculate_grade_info($sc, $mx, $edLevel);
    $totalScore += $sc;
    $totalMax   += $mx;
    $allPoints[] = $gi['points'];
}

$avgScore = ($totalMax > 0) ? round(($totalScore / $totalMax) * 100, 2) : 0;

$showPoints = !isset($window['show_points']) || (int)$window['show_points'] === 1;

// O-Level Aggregates (best 8 subjects) & Division Calculation
$totalAggregatesText = 'N/A';
$divisionText = 'N/A';
if (!$showPoints) {
    $totalAggregatesText = 'Disabled';
} elseif ($edLevel === 'O-Level' && count($allPoints) >= 8) {
    sort($allPoints);
    $best8 = array_slice($allPoints, 0, 8);
    $sumAgg = array_sum($best8);
    $totalAggregatesText = (string)$sumAgg;
    if ($sumAgg <= 32) $divisionText = 'DIV I';
    elseif ($sumAgg <= 45) $divisionText = 'DIV II';
    elseif ($sumAgg <= 58) $divisionText = 'DIV III';
    elseif ($sumAgg <= 72) $divisionText = 'DIV IV';
    else $divisionText = 'DIV U';
}

// Verification Code
$vcode = date('Y') . sprintf("%05d", $studentId) . sprintf("%03d", $windowId);
$scholarId = $student['lin_number'] ?: ('1000' . sprintf("%05d", $studentId));
$reportDate = strtoupper(date('d-M-Y'));

// Assessment Title Label
$assTitleLabel = 'END OF TERM';
if (!empty($window['assessment_type'])) {
    $type = strtoupper($window['assessment_type']);
    if ($type === 'BOT') $assTitleLabel = 'BEGINNING OF TERM';
    elseif ($type === 'MOT') $assTitleLabel = 'MID-TERM EXAMINATION';
    elseif ($type === 'EOT') $assTitleLabel = 'END OF TERM';
    else $assTitleLabel = strtoupper($window['title']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Report - <?= htmlspecialchars($student['full_name']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Courier New', Courier, monospace, Arial, sans-serif;
            background-color: #f4f6f8;
            color: #000;
            padding: 20px;
            font-size: 12px;
            line-height: 1.2;
        }
        .no-print-bar {
            max-width: 900px;
            margin: 0 auto 15px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .btn-print {
            background-color: #1A472A;
            color: #fff;
            padding: 8px 18px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
        }
        .btn-back {
            background-color: #e2e8f0;
            color: #333;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            font-size: 12px;
        }
        .report-page {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border: 2px solid #000;
            padding: 16px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }

        /* Top Header */
        .school-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .logo-box { width: 100px; text-align: center; }
        .logo-img { max-height: 85px; width: auto; }
        .header-text { text-align: center; flex-grow: 1; padding: 0 10px; }
        .school-name { font-size: 26px; font-weight: 900; font-family: 'Times New Roman', Times, serif; letter-spacing: 0.5px; }
        .school-motto { font-size: 13px; font-weight: 800; letter-spacing: 1px; text-decoration: underline; margin-top: 2px; }
        .contact-line { font-size: 10px; font-weight: bold; margin-top: 3px; font-family: monospace; }
        .term-banner { font-size: 13px; font-weight: 900; text-decoration: underline; margin-top: 5px; text-transform: uppercase; }

        /* Biodata & Summary Grid */
        .biodata-section {
            display: flex;
            border: 1px solid #000;
            margin-bottom: 10px;
        }
        .biodata-table {
            width: 80%;
            border-collapse: collapse;
        }
        .biodata-table td, .biodata-table th {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 11px;
        }
        .lbl { font-weight: bold; color: #333; background-color: #f9f9f9; }
        .val { font-weight: bold; color: #000; text-transform: uppercase; }

        .photo-box {
            width: 20%;
            border-left: 1px solid #000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 4px;
            background: #fff;
        }
        .student-photo {
            width: 105px;
            height: 125px;
            object-fit: cover;
            border: 1px solid #000;
        }

        /* Marks Table */
        .marks-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .marks-table th {
            border: 1px solid #000;
            padding: 6px 4px;
            font-size: 11px;
            font-weight: 900;
            text-align: center;
            background-color: #f2f2f2;
            text-transform: UPPERCASE;
        }
        .marks-table td {
            border: 1px solid #000;
            padding: 5px 6px;
            font-size: 11px;
            font-weight: bold;
        }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }

        /* Next Term Bar */
        .next-term-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .next-term-table td {
            border: 1px solid #000;
            padding: 5px 8px;
            font-size: 11px;
            font-weight: bold;
        }

        /* Remarks Section */
        .remarks-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .remarks-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            font-size: 11px;
            vertical-align: top;
        }
        .sig-cell { width: 32%; background-color: #fafafa; font-weight: bold; }
        .sig-box {
            display: inline-block;
            width: 35px;
            height: 18px;
            border: 1px solid #888;
            margin-left: 8px;
            vertical-align: middle;
        }

        /* Footer Grading Legend */
        .grading-legend-bar {
            border: 1px solid #000;
            padding: 4px 8px;
            font-size: 9.5px;
            font-weight: bold;
            background-color: #fff;
            text-transform: uppercase;
        }

        @media print {
            .no-print-bar { display: none !important; }
            body { background-color: #fff; padding: 0; margin: 0; }
            .report-page { border: 2px solid #000; max-width: 100%; width: 100%; box-shadow: none; padding: 12px; }
            @page { size: A4 portrait; margin: 8mm; }
        }
    </style>
</head>
<body>

    <!-- Action Toolbar (No Print) -->
    <div class="no-print-bar">
        <div>
            <a href="javascript:history.back()" class="btn-back">← Back</a>
            <span style="margin-left: 10px; font-weight: bold; color: #555;">Official Uganda Academic Report Card View</span>
        </div>
        <button onclick="window.print();" class="btn-print">🖨️ Print / Download PDF</button>
    </div>

    <!-- Official Report Card Page -->
    <div class="report-page">

        <!-- Top Header -->
        <div class="school-header">
            <div class="logo-box">
                <img src="thamani-logo.png" alt="School Crest" class="logo-img" onerror="this.src='favicon.svg'">
            </div>
            <div class="header-text">
                <div class="school-name">THAMANI HIGH SCHOOL</div>
                <div class="school-motto">EXCELLENCE AND CHARACTER</div>
                <div class="contact-line">SCH.ID: 1000049 TEL: +256 414 123 456 info@thamani.ac.ug</div>
                <div class="contact-line">P.O. BOX 1234 KAKIRI, WAKISO, UGANDA , WWW.THAMANI.AC.UG</div>
                <div class="term-banner"><?= $assTitleLabel ?></div>
            </div>
        </div>

        <!-- Student Biodata & Performance Summary Table Grid -->
        <div class="biodata-section">
            <table class="biodata-table">
                <tr>
                    <td class="lbl" style="width: 15%;">Name</td>
                    <td class="val" style="width: 40%;"><?= htmlspecialchars($student['full_name']) ?></td>
                    <td class="lbl" style="width: 15%;">Date</td>
                    <td class="val" style="width: 30%;"><?= $reportDate ?></td>
                </tr>
                <tr>
                    <td class="lbl">Scholar ID</td>
                    <td class="val" style="font-family: monospace;"><?= htmlspecialchars($scholarId) ?></td>
                    <td class="lbl">Term</td>
                    <td class="val"><?= htmlspecialchars($window['academic_year']) ?> TERM <?= htmlspecialchars($window['term']) ?></td>
                </tr>
                <tr>
                    <td class="lbl">Class/Stream</td>
                    <td class="val"><?= htmlspecialchars($classStreamFormatted) ?></td>
                    <td class="lbl">Vcode</td>
                    <td class="val" style="font-family: monospace;"><?= htmlspecialchars($vcode) ?></td>
                </tr>
                <tr>
                    <td class="lbl">Residence</td>
                    <td class="val">DAY</td>
                    <td class="lbl">Ranked</td>
                    <td class="val">BY AVERAGE</td>
                </tr>
                <!-- Performance Summary Sub-Row -->
                <tr>
                    <td class="lbl">Best</td>
                    <td class="val" style="font-size: 10px;">ALL SUBJECTS</td>
                    <td class="lbl">Average</td>
                    <td class="val" style="font-size: 12px; font-weight: 900;"><?= number_format($avgScore, 2) ?></td>
                </tr>
                <tr>
                    <td class="lbl">Aggregates</td>
                    <td class="val"><?= $totalAggregatesText ?></td>
                    <td class="lbl">Position</td>
                    <td class="val" style="padding: 0;">
                        <table style="width: 100%; border-collapse: collapse; height: 100%;">
                            <tr>
                                <td style="border: none; border-right: 1px solid #000; padding: 2px 4px;" class="lbl">Class</td>
                                <td style="border: none; padding: 2px 4px;" class="val"><?= htmlspecialchars($classPosText) ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="lbl">Division</td>
                    <td class="val" style="font-weight: 900; font-size: 12px; color: #1A472A;"><?= $divisionText ?></td>
                    <td class="lbl" style="border-top: none;"></td>
                    <td class="val" style="padding: 0;">
                        <table style="width: 100%; border-collapse: collapse; height: 100%;">
                            <tr>
                                <td style="border: none; border-right: 1px solid #000; padding: 2px 4px;" class="lbl">Stream</td>
                                <td style="border: none; padding: 2px 4px;" class="val"><?= htmlspecialchars($streamPosText) ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- Passport Photo -->
            <div class="photo-box">
                <img src="student_placeholder.png" alt="Student Passport" class="student-photo" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'105\' height=\'125\' viewBox=\'0 0 105 125\' fill=\'%23f3f4f6\'><rect width=\'105\' height=\'125\' fill=\'%23e5e7eb\'/><circle cx=\'52.5\' cy=\'45\' r=\'25\' fill=\'%239ca3af\'/><path d=\'M15 115 C15 80, 90 80, 90 115 Z\' fill=\'%239ca3af\'/></svg>';">
            </div>
        </div>

        <!-- Subject Marks Breakdown Table -->
        <table class="marks-table">
            <thead>
                <tr>
                    <th style="width: 32%; text-align: left; padding-left: 8px;">SUBJECT</th>
                    <th style="width: 13%;">MARKS (%)</th>
                    <?php if ($showPoints): ?>
                        <th style="width: 18%;">SCORES<br>(AGGREGATES)</th>
                    <?php endif; ?>
                    <th style="width: <?= $showPoints ? '25%' : '43%' ?>;">SUBJECT TEACHER'S COMMENTS</th>
                    <th style="width: 12%;">TEACHER INITIALS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($marks)): ?>
                    <?php foreach ($marks as $m): 
                        $sc = floatval($m['score']);
                        $mx = floatval($m['max_score'] ?: 100);
                        $gi = calculate_grade_info($sc, $mx, $edLevel);
                        $tInitials = get_initials($m['teacher_name'] ?? '');
                    ?>
                        <tr>
                            <td class="text-left" style="padding-left: 8px; font-weight: 800;"><?= strtoupper(htmlspecialchars($m['subject'])) ?></td>
                            <td class="text-center font-mono"><?= number_format($sc, 0) ?></td>
                            <?php if ($showPoints): ?>
                                <td class="text-center font-mono" style="font-weight: 900; font-size: 12px;"><?= htmlspecialchars($gi['grade']) ?></td>
                            <?php endif; ?>
                            <td class="text-left" style="font-weight: bold; color: #222; text-transform: capitalize;"><?= htmlspecialchars($m['comments'] ?: strtolower($gi['remark'])) ?></td>
                            <td class="text-center font-mono"><?= htmlspecialchars($tInitials) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $showPoints ? 5 : 4 ?>" class="text-center" style="padding: 20px; font-weight: normal; color: #666;">
                            No academic marks entered for this student in this term yet.
                        </td>
                    </tr>
                <?php endif; ?>
                <!-- Total Row -->
                <tr style="background-color: #f9f9f9; font-weight: 900;">
                    <td class="text-center" style="font-size: 12px; font-weight: 900;">TOTAL</td>
                    <td class="text-center font-mono" style="font-size: 12px; font-weight: 900;"><?= number_format($totalScore, 0) ?></td>
                    <?php if ($showPoints): ?>
                        <td class="text-center" style="font-size: 12px; font-weight: 900;">STATE</td>
                    <?php endif; ?>
                    <td colspan="2" class="text-left" style="font-size: 11px; font-weight: 900; text-transform: uppercase; color: #1A472A; padding-left: 8px;">
                        <?= $avgScore >= 50 ? 'PASSED / PROMOTED' : 'REQUIRES MORE EFFORT' ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Next Term Dates Row -->
        <table class="next-term-table">
            <tr>
                <td style="width: 25%; background-color: #f2f2f2; text-transform: uppercase; font-weight: 900;">NEXT TERM</td>
                <td style="width: 12%; text-align: right;" class="lbl">Begins</td>
                <td style="width: 25%;" class="val">15-JAN-2027</td>
                <td style="width: 12%; text-align: right;" class="lbl">Ends</td>
                <td style="width: 26%;" class="val">10-APR-2027</td>
            </tr>
        </table>

        <!-- Remarks Section -->
        <table class="remarks-table">
            <tr>
                <td class="sig-cell">
                    Class teacher's remarks<br>
                    <span style="font-size: 10px; font-weight: normal;">Signature</span> <span class="sig-box"></span>
                </td>
                <td style="font-style: italic; font-weight: bold; text-transform: capitalize;">
                    <?= htmlspecialchars($reportComment['class_teacher_comment'] ?: 'An attentive, well-behaved and hardworking student.') ?>
                </td>
            </tr>
            <tr>
                <td class="sig-cell">
                    Headteacher's remarks<br>
                    <span style="font-size: 10px; font-weight: normal;">Signature</span> <span class="sig-box"></span>
                </td>
                <td style="font-style: italic; font-weight: bold; text-transform: capitalize;">
                    <?= htmlspecialchars($reportComment['head_teacher_comment'] ?: 'Promoted to the next class level. High potential for academic excellence.') ?>
                </td>
            </tr>
        </table>

        <!-- Footer Grading Legend -->
        <div class="grading-legend-bar">
            AGGREGATES: D1 100-80; D2 79-70; C3 69-65; C4 64-60; C5 59-55; C6 54-50; P7 49-45; P8 44-35; F9 34-00;
        </div>

    </div>

</body>
</html>
