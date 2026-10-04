<?php
/**
 * Thamani High School - Academic Marks & Report Comments Processor
 * ------------------------------------------------------------------
 * Supports batch marks submission per subject/class/stream and class teacher comments.
 * Enforces open reporting window checks & teacher subject assignments.
 */

require_once 'auth_teacher.php';
require_teacher_login();
require_password_changed();

require_once 'conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: teacher_dashboard.php');
    exit;
}

$teacher = current_teacher();
$isAdmin = !empty($_SESSION['admin_id']);
$teacherId = (int)($teacher['id'] ?? 0);

$mode       = trim($_POST['mode'] ?? 'batch'); // 'batch', 'single', or 'class_comments'
$windowId   = (int)($_POST['window_id'] ?? 0);
$classLevel = trim($_POST['class_level'] ?? '');
$stream     = trim($_POST['stream'] ?? 'Stream A');
$subject    = trim($_POST['subject'] ?? '');

$redirectUrl = $_POST['redirect_to'] ?? ('teacher_dashboard.php?tab=tab-teacher-marks#tab-teacher-marks');

// Fetch reporting window details
$window = null;
if ($windowId > 0) {
    $wStmt = thamani_db_prepare($conn, "SELECT id, title, academic_year, term, assessment_type, is_open, is_published FROM reporting_windows WHERE id = ? LIMIT 1");
    if ($wStmt) {
        thamani_db_stmt_bind_param($wStmt, "i", $windowId);
        thamani_db_stmt_execute($wStmt);
        $wRes = thamani_db_stmt_get_result($wStmt);
        if ($wRes) $window = thamani_db_fetch_assoc($wRes);
        thamani_db_stmt_close($wStmt);
    }
}

// Fallback to active open window if not explicitly provided
if (!$window) {
    $wRes = thamani_db_query($conn, "SELECT id, title, academic_year, term, assessment_type, is_open, is_published FROM reporting_windows WHERE is_open = 1 ORDER BY id DESC LIMIT 1");
    if ($wRes) $window = thamani_db_fetch_assoc($wRes);
}

// Security Check 1: Window Open Check for non-admin
$isAjax = !empty($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if (!$isAdmin) {
    if (!$window || empty($window['is_open'])) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Permission Denied: Academic reporting window is currently CLOSED by Administration.']);
            exit;
        }
        $_SESSION['teacher_flash'] = [
            'type' => 'error',
            'message' => 'Permission Denied: Academic reporting window is currently CLOSED by Administration.'
        ];
        header('Location: ' . $redirectUrl);
        exit;
    }
}

$windowId  = (int)($window['id'] ?? 0);
$term      = $window['term'] ?? 'Term III';
$year      = $window['academic_year'] ?? '2026';
$assType   = $window['assessment_type'] ?? 'EOT';

// Mode 1: Class Teacher Report Comments
if ($mode === 'class_comments') {
    $commentsArr = $_POST['class_teacher_comments'] ?? [];
    $headCommentsArr = $_POST['head_teacher_comments'] ?? [];
    $savedCount = 0;

    foreach ($commentsArr as $sId => $ctComment) {
        $studentId = (int)$sId;
        $ctComment = trim($ctComment);
        $htComment = trim($headCommentsArr[$studentId] ?? '');

        if ($studentId <= 0 || $windowId <= 0) continue;

        // Check if comment row exists
        $check = thamani_db_prepare($conn, "SELECT id FROM report_comments WHERE student_id = ? AND window_id = ? LIMIT 1");
        $existId = 0;
        if ($check) {
            thamani_db_stmt_bind_param($check, "ii", $studentId, $windowId);
            thamani_db_stmt_execute($check);
            $rRes = thamani_db_stmt_get_result($check);
            if ($rRes && $r = thamani_db_fetch_assoc($rRes)) $existId = (int)$r['id'];
            thamani_db_stmt_close($check);
        }

        if ($existId > 0) {
            $up = thamani_db_prepare($conn, "UPDATE report_comments SET class_teacher_comment = ?, head_teacher_comment = ?, updated_at = NOW() WHERE id = ?");
            if ($up) {
                thamani_db_stmt_bind_param($up, "ssi", $ctComment, $htComment, $existId);
                thamani_db_stmt_execute($up);
                thamani_db_stmt_close($up);
                $savedCount++;
            }
        } else {
            $ins = thamani_db_prepare($conn, "INSERT INTO report_comments (student_id, window_id, class_teacher_comment, head_teacher_comment) VALUES (?, ?, ?, ?)");
            if ($ins) {
                thamani_db_stmt_bind_param($ins, "iiss", $studentId, $windowId, $ctComment, $htComment);
                thamani_db_stmt_execute($ins);
                thamani_db_stmt_close($ins);
                $savedCount++;
            }
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => "Class teacher report comments updated for {$savedCount} student(s)."]);
        exit;
    }

    $_SESSION['teacher_flash'] = [
        'type' => 'success',
        'message' => "Class teacher report comments updated for {$savedCount} student(s)."
    ];
    header('Location: ' . $redirectUrl);
    exit;
}

// Security Check 2: Subject Teacher Assignment Check for non-admin
if (!$isAdmin) {
    $isClassTeacherOfThisStream = !empty($teacher['is_class_teacher']) &&
                                  trim($teacher['class_teacher_of']) === $classLevel &&
                                  (trim($teacher['class_teacher_stream']) === $stream || trim($teacher['class_teacher_stream']) === 'All Streams');

    // Check specific teacher_subject_assignments table
    $assigned = false;
    $aStmt = thamani_db_prepare($conn, "SELECT id FROM teacher_subject_assignments WHERE teacher_id = ? AND subject = ? AND class_level = ? AND (stream = ? OR stream = 'All Streams') LIMIT 1");
    if ($aStmt) {
        thamani_db_stmt_bind_param($aStmt, "isss", $teacherId, $subject, $classLevel, $stream);
        thamani_db_stmt_execute($aStmt);
        $aRes = thamani_db_stmt_get_result($aStmt);
        if ($aRes && $aRes->num_rows > 0) $assigned = true;
        thamani_db_stmt_close($aStmt);
    }

    // Fallback: check legacy classes_taught string & department match
    if (!$assigned && !$isClassTeacherOfThisStream) {
        $classesTaughtArr = array_filter(array_map('trim', explode(',', $teacher['classes_taught'] ?? '')));
        $dept = strtolower(trim($teacher['department'] ?? ''));
        $sub  = strtolower($subject);
        $subjectMatchesDept = ($dept !== '' && (strpos($sub, $dept) !== false || strpos($dept, $sub) !== false || $dept === 'academic'));

        if (!in_array($classLevel, $classesTaughtArr, true) || !$subjectMatchesDept) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => "Permission Denied: You are not assigned to teach {$subject} in {$classLevel} ({$stream})."]);
                exit;
            }
            $_SESSION['teacher_flash'] = [
                'type' => 'error',
                'message' => "Permission Denied: You are not assigned to teach {$subject} in {$classLevel} ({$stream})."
            ];
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}

// Mode 2: Single Student Submission
if ($mode === 'single') {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $score     = floatval($_POST['score'] ?? 0);
    $maxScore  = floatval($_POST['max_score'] ?? 100);
    $comments  = trim($_POST['comments'] ?? '');

    if ($studentId <= 0 || $subject === '') {
        $_SESSION['teacher_flash'] = ['type' => 'error', 'message' => 'Invalid student or subject data.'];
        header('Location: ' . $redirectUrl);
        exit;
    }

    $scoresArr   = [$studentId => $score];
    $maxScoresArr= [$studentId => $maxScore];
    $commentsArr = [$studentId => $comments];
} else {
    // Mode 3: Batch Stream Submission
    $scoresArr   = $_POST['scores'] ?? [];
    $maxScoresArr= $_POST['max_scores'] ?? [];
    $commentsArr = $_POST['comments'] ?? [];
}

$processedCount = 0;

foreach ($scoresArr as $sId => $rawScore) {
    $studentId = (int)$sId;
    if ($studentId <= 0) continue;

    $score    = floatval($rawScore);
    $maxScore = floatval($maxScoresArr[$studentId] ?? 100);
    $comments = trim($commentsArr[$studentId] ?? '');

    if ($score < 0) $score = 0;
    if ($maxScore <= 0) $maxScore = 100;
    if ($score > $maxScore) $score = $maxScore;

    // Check existing record
    $checkSql = "SELECT id FROM student_marks WHERE student_id = ? AND subject = ? AND (window_id = ? OR (class_level = ? AND term = ?)) LIMIT 1";
    $checkStmt = thamani_db_prepare($conn, $checkSql);
    $existingId = 0;

    if ($checkStmt) {
        thamani_db_stmt_bind_param($checkStmt, "isiss", $studentId, $subject, $windowId, $classLevel, $term);
        thamani_db_stmt_execute($checkStmt);
        $res = thamani_db_stmt_get_result($checkStmt);
        if ($res && $r = thamani_db_fetch_assoc($res)) {
            $existingId = (int)$r['id'];
        }
        thamani_db_stmt_close($checkStmt);
    }

    if ($existingId > 0) {
        $upSql = "UPDATE student_marks SET score = ?, max_score = ?, comments = ?, recorded_by_teacher_id = ?, stream = ?, window_id = ?, assessment_type = ?, academic_year = ?, updated_at = NOW() WHERE id = ?";
        $upStmt = thamani_db_prepare($conn, $upSql);
        if ($upStmt) {
            thamani_db_stmt_bind_param($upStmt, "ddsisissi", $score, $maxScore, $comments, $teacherId, $stream, $windowId, $assType, $year, $existingId);
            thamani_db_stmt_execute($upStmt);
            thamani_db_stmt_close($upStmt);
            $processedCount++;
        }
    } else {
        $insSql = "INSERT INTO student_marks (student_id, class_level, stream, subject, term, score, max_score, comments, recorded_by_teacher_id, window_id, assessment_type, academic_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insStmt = thamani_db_prepare($conn, $insSql);
        if ($insStmt) {
            thamani_db_stmt_bind_param($insStmt, "issssddsiiss", $studentId, $classLevel, $stream, $subject, $term, $score, $maxScore, $comments, $teacherId, $windowId, $assType, $year);
            thamani_db_stmt_execute($insStmt);
            thamani_db_stmt_close($insStmt);
            $processedCount++;
        }
    }
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => "Academic marks for {$subject} ({$classLevel} - {$stream}) saved successfully for {$processedCount} student(s)."
    ]);
    exit;
}

$_SESSION['teacher_flash'] = [
    'type' => 'success',
    'message' => "Academic marks for {$subject} ({$classLevel} - {$stream}) saved successfully for {$processedCount} student(s)."
];

header('Location: ' . $redirectUrl);
exit;
