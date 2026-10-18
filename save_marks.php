<?php
/**
 * Thamani High School - Academic Marks & Report Comments Processor
 * ------------------------------------------------------------------
 * Supports batch marks submission per subject/class/stream and class teacher comments.
 * Enforces open reporting window checks & teacher subject assignments.
 *
 * Response contract (both AJAX and non-AJAX):
 *   - AJAX  → JSON { success: bool, message: string }
 *   - Plain → session flash + 302 redirect to $redirect_to
 *
 * Mode is auto-detected from the form's `action` hidden field:
 *   action=save_teacher_comments → class_comments
 *   action=save_subject_marks    → batch
 *   mode=...                     → explicit override
 */

require_once 'auth_teacher.php';
require_teacher_login();
require_password_changed();
require_once 'conn.php';

// Detect AJAX very early so every failure path below can respond in JSON.
$isAjax = !empty($_POST['ajax'])
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

/**
 * Terminate the request safely:
 *   - AJAX  → emits JSON and exits
 *   - Plain → sets a flash message and returns (caller then redirects)
 */
if (!function_exists('thamani_marks_emit_error')) {
    function thamani_marks_emit_error($isAjax, string $message, int $httpCode = 200): void {
        if ($isAjax) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code($httpCode);
            }
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }
        $_SESSION['teacher_flash'] = ['type' => 'error', 'message' => $message];
    }
}

// =========================================================
// Request gate
// =========================================================
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    if ($isAjax) {
        thamani_marks_emit_error(true, 'Invalid request method (expected POST).', 405);
    }
    header('Location: teacher_dashboard.php');
    exit;
}

if (!$conn || !($conn instanceof ThamaniPolyfillConn) || empty($conn->pdo)) {
    thamani_marks_emit_error($isAjax, 'Database connection is unavailable. Please reload the page and try again.', 500);
    header('Location: teacher_dashboard.php');
    exit;
}

// =========================================================
// Resolve request shape
// =========================================================
$teacher   = current_teacher();
$isAdmin   = !empty($_SESSION['admin_id']);
$teacherId = (int)($teacher['id'] ?? 0);

$postedAction = trim($_POST['action'] ?? '');
$mode         = trim($_POST['mode'] ?? '');

if ($mode === '') {
    if ($postedAction === 'save_teacher_comments') {
        $mode = 'class_comments';
    } elseif ($postedAction === 'save_subject_marks') {
        $mode = 'batch';
    } else {
        $mode = 'batch';
    }
}

$windowId   = (int)($_POST['window_id'] ?? 0);
$classLevel = trim($_POST['class_level'] ?? '');
$stream     = trim($_POST['stream'] ?? 'Stream A');
$subject    = trim($_POST['subject'] ?? '');

$redirectUrl = $_POST['redirect_to']
    ?? 'teacher_dashboard.php?tab=tab-teacher-marks#tab-teacher-marks';

// =========================================================
// Load the reporting window (if one was requested or discoverable)
// =========================================================
$window = null;

if ($windowId > 0) {
    $wStmt = thamani_db_prepare($conn, "SELECT id, title, academic_year, term, assessment_type, is_open, is_published FROM reporting_windows WHERE id = ? LIMIT 1");
    if ($wStmt) {
        thamani_db_stmt_bind_param($wStmt, "i", $windowId);
        if (thamani_db_stmt_execute($wStmt)) {
            $wRes = thamani_db_stmt_get_result($wStmt);
            if ($wRes) $window = thamani_db_fetch_assoc($wRes);
        }
        thamani_db_stmt_close($wStmt);
    }
}

// Only auto-discover a window when the caller supplied no id at all.
// If an id WAS supplied but the row is missing, we keep the posted id so
// admin saves against a since-deleted window don't silently drift to a
// different one.
if (!$window && $windowId <= 0) {
    $wRes = thamani_db_query($conn, "SELECT id, title, academic_year, term, assessment_type, is_open, is_published FROM reporting_windows WHERE is_open = 1 ORDER BY id DESC LIMIT 1");
    if ($wRes) {
        $window = thamani_db_fetch_assoc($wRes);
        if ($window) $windowId = (int)$window['id'];
    }
}

// Non-admins cannot write into a closed/missing window.
if (!$isAdmin) {
    if (!$window || empty($window['is_open'])) {
        thamani_marks_emit_error($isAjax, 'Permission Denied: Academic reporting window is currently CLOSED by Administration.');
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// Derive term/year/assessment_type from the window, or fall back to defaults
// so admin saves don't lose the metadata if the window row is missing.
$term    = $window['term']            ?? 'Term III';
$year    = $window['academic_year']   ?? '2026';
$assType = $window['assessment_type'] ?? 'EOT';

// =========================================================
// Mode 1: Class-teacher report comments
// =========================================================
if ($mode === 'class_comments') {
    // Accept BOTH array shapes so the dashboard's `comments[]` form and any
    // legacy callers using `class_teacher_comments[]` both work.
    $commentsArr     = $_POST['class_teacher_comments'] ?? $_POST['comments'] ?? [];
    $headCommentsArr = $_POST['head_teacher_comments']  ?? [];

    if (empty($commentsArr)) {
        thamani_marks_emit_error($isAjax, 'No comments were submitted.');
        header('Location: ' . $redirectUrl);
        exit;
    }

    $savedCount  = 0;
    $failedCount = 0;

    foreach ($commentsArr as $sId => $ctComment) {
        $studentId = (int)$sId;
        $ctComment = trim((string)$ctComment);
        $htComment = trim((string)($headCommentsArr[$studentId] ?? ''));

        if ($studentId <= 0 || $windowId <= 0) continue;

        // Look up the existing row (if any).
        $check   = thamani_db_prepare($conn, "SELECT id FROM report_comments WHERE student_id = ? AND window_id = ? LIMIT 1");
        $existId = 0;
        if ($check) {
            thamani_db_stmt_bind_param($check, "ii", $studentId, $windowId);
            if (thamani_db_stmt_execute($check)) {
                $rRes = thamani_db_stmt_get_result($check);
                if ($rRes && $r = thamani_db_fetch_assoc($rRes)) $existId = (int)$r['id'];
            }
            thamani_db_stmt_close($check);
        }

        if ($existId > 0) {
            $up = thamani_db_prepare($conn, "UPDATE report_comments SET class_teacher_comment = ?, head_teacher_comment = ?, updated_at = NOW() WHERE id = ?");
            if (!$up) {
                error_log('[Save Comments Update Prepare] ' . thamani_db_error($conn));
                $failedCount++;
                continue;
            }
            thamani_db_stmt_bind_param($up, "ssi", $ctComment, $htComment, $existId);
            if (thamani_db_stmt_execute($up)) {
                $savedCount++;
            } else {
                error_log('[Save Comments Update Execute] ' . thamani_db_stmt_error($up));
                $failedCount++;
            }
            thamani_db_stmt_close($up);
        } else {
            $ins = thamani_db_prepare($conn, "INSERT INTO report_comments (student_id, window_id, class_teacher_comment, head_teacher_comment) VALUES (?, ?, ?, ?)");
            if (!$ins) {
                error_log('[Save Comments Insert Prepare] ' . thamani_db_error($conn));
                $failedCount++;
                continue;
            }
            thamani_db_stmt_bind_param($ins, "iiss", $studentId, $windowId, $ctComment, $htComment);
            if (thamani_db_stmt_execute($ins)) {
                $savedCount++;
            } else {
                error_log('[Save Comments Insert Execute] ' . thamani_db_stmt_error($ins));
                $failedCount++;
            }
            thamani_db_stmt_close($ins);
        }
    }

    $message = "Class teacher comments saved for {$savedCount} student(s).";
    if ($failedCount > 0) {
        $message .= " {$failedCount} record(s) failed — check the server log for details.";
    }

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $failedCount === 0, 'message' => $message]);
        exit;
    }

    $_SESSION['teacher_flash'] = [
        'type'    => $failedCount === 0 ? 'success' : 'error',
        'message' => $message,
    ];
    header('Location: ' . $redirectUrl);
    exit;
}

// =========================================================
// Subject-teacher assignment check (non-admins only)
// =========================================================
if (!$isAdmin) {
    $isClassTeacherOfThisStream = !empty($teacher['is_class_teacher'])
        && trim($teacher['class_teacher_of'] ?? '') === $classLevel
        && (trim($teacher['class_teacher_stream'] ?? '') === $stream
            || trim($teacher['class_teacher_stream'] ?? '') === 'All Streams');

    $assigned = false;
    $aStmt = thamani_db_prepare($conn, "SELECT id FROM teacher_subject_assignments WHERE teacher_id = ? AND subject = ? AND class_level = ? AND (stream = ? OR stream = 'All Streams') LIMIT 1");
    if ($aStmt) {
        thamani_db_stmt_bind_param($aStmt, "isss", $teacherId, $subject, $classLevel, $stream);
        if (thamani_db_stmt_execute($aStmt)) {
            $aRes = thamani_db_stmt_get_result($aStmt);
            if ($aRes && $aRes->num_rows > 0) $assigned = true;
        }
        thamani_db_stmt_close($aStmt);
    }

    if (!$assigned && !$isClassTeacherOfThisStream) {
        $classesTaughtArr = array_filter(array_map('trim', explode(',', $teacher['classes_taught'] ?? '')));
        $dept = strtolower(trim($teacher['department'] ?? ''));
        $sub  = strtolower($subject);
        $subjectMatchesDept = ($dept !== '' && (strpos($sub, $dept) !== false || strpos($dept, $sub) !== false || $dept === 'academic'));

        if (!in_array($classLevel, $classesTaughtArr, true) || !$subjectMatchesDept) {
            thamani_marks_emit_error($isAjax, "Permission Denied: You are not assigned to teach {$subject} in {$classLevel} ({$stream}).");
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}

// =========================================================
// Modes 2 & 3: mark submission
// =========================================================
if ($subject === '') {
    thamani_marks_emit_error($isAjax, 'Subject is required for mark submission.');
    header('Location: ' . $redirectUrl);
    exit;
}

if ($mode === 'single') {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $score     = floatval($_POST['score'] ?? 0);
    $maxScore  = floatval($_POST['max_score'] ?? 100);
    $comments  = trim($_POST['comments'] ?? '');

    if ($studentId <= 0) {
        thamani_marks_emit_error($isAjax, 'Invalid student or subject data.');
        header('Location: ' . $redirectUrl);
        exit;
    }

    $scoresArr    = [$studentId => $score];
    $maxScoresArr = [$studentId => $maxScore];
    $commentsArr  = [$studentId => $comments];
} else {
    $scoresArr    = $_POST['scores']     ?? $_POST['marks']   ?? [];
    $maxScoresArr = $_POST['max_scores'] ?? [];
    $commentsArr  = $_POST['comments']   ?? $_POST['remarks'] ?? [];
}

if (empty($scoresArr)) {
    thamani_marks_emit_error($isAjax, "No marks were submitted for {$subject} ({$classLevel} - {$stream}). Enter at least one score and try again.");
    header('Location: ' . $redirectUrl);
    exit;
}

$processedCount = 0;
$failedCount    = 0;

foreach ($scoresArr as $sId => $rawScore) {
    $studentId = (int)$sId;
    if ($studentId <= 0) continue;
    if (trim((string)$rawScore) === '') continue;

    $score    = floatval($rawScore);
    $maxScore = floatval($maxScoresArr[$studentId] ?? 100);
    $comments = trim($commentsArr[$studentId] ?? '');

    if ($score < 0)          $score = 0;
    if ($maxScore <= 0)      $maxScore = 100;
    if ($score > $maxScore)  $score = $maxScore;

    // Find an existing row for this (student, subject, window) OR legacy
    // (window_id = 0, term/year/assessment_type) combination.
    $checkSql = "SELECT id FROM student_marks
                 WHERE student_id = ? AND subject = ?
                   AND (
                       window_id = ?
                       OR (
                           (window_id IS NULL OR window_id = 0)
                           AND class_level = ? AND term = ? AND academic_year = ? AND assessment_type = ?
                       )
                   )
                 LIMIT 1";
    $checkStmt = thamani_db_prepare($conn, $checkSql);
    if (!$checkStmt) {
        error_log('[Save Marks Check Prepare] ' . thamani_db_error($conn));
        $failedCount++;
        continue;
    }

    $existingId = 0;
    thamani_db_stmt_bind_param($checkStmt, "isissss", $studentId, $subject, $windowId, $classLevel, $term, $year, $assType);
    if (thamani_db_stmt_execute($checkStmt)) {
        $res = thamani_db_stmt_get_result($checkStmt);
        if ($res && $r = thamani_db_fetch_assoc($res)) {
            $existingId = (int)$r['id'];
        }
    } else {
        error_log('[Save Marks Check Execute] ' . thamani_db_stmt_error($checkStmt));
        thamani_db_stmt_close($checkStmt);
        $failedCount++;
        continue;
    }
    thamani_db_stmt_close($checkStmt);

    if ($existingId > 0) {
        $upSql = "UPDATE student_marks
                  SET score = ?, max_score = ?, comments = ?, recorded_by_teacher_id = ?,
                      stream = ?, window_id = ?, assessment_type = ?, academic_year = ?,
                      updated_at = NOW()
                  WHERE id = ?";
        $upStmt = thamani_db_prepare($conn, $upSql);
        if (!$upStmt) {
            error_log('[Save Marks Update Prepare] ' . thamani_db_error($conn));
            $failedCount++;
            continue;
        }
        thamani_db_stmt_bind_param($upStmt, "ddsisissi", $score, $maxScore, $comments, $teacherId, $stream, $windowId, $assType, $year, $existingId);
        if (thamani_db_stmt_execute($upStmt)) {
            $processedCount++;
        } else {
            error_log('[Save Marks Update Execute] ' . thamani_db_stmt_error($upStmt));
            $failedCount++;
        }
        thamani_db_stmt_close($upStmt);
    } else {
        $insSql = "INSERT INTO student_marks
                   (student_id, class_level, stream, subject, term, score, max_score, comments,
                    recorded_by_teacher_id, window_id, assessment_type, academic_year)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insStmt = thamani_db_prepare($conn, $insSql);
        if (!$insStmt) {
            error_log('[Save Marks Insert Prepare] ' . thamani_db_error($conn));
            $failedCount++;
            continue;
        }
        thamani_db_stmt_bind_param($insStmt, "issssddsiiss", $studentId, $classLevel, $stream, $subject, $term, $score, $maxScore, $comments, $teacherId, $windowId, $assType, $year);
        if (thamani_db_stmt_execute($insStmt)) {
            $processedCount++;
        } else {
            error_log('[Save Marks Insert Execute] ' . thamani_db_stmt_error($insStmt));
            $failedCount++;
        }
        thamani_db_stmt_close($insStmt);
    }
}

// =========================================================
// Final response
// =========================================================
$saveSucceeded = ($failedCount === 0 && $processedCount > 0);
$saveMessage   = $processedCount > 0
    ? "Academic marks for {$subject} ({$classLevel} - {$stream}): {$processedCount} student(s) saved."
    : "No marks were submitted for {$subject} ({$classLevel} - {$stream}). Enter at least one score and try again.";
if ($failedCount > 0) {
    $saveMessage .= " {$failedCount} student record(s) failed to save; check the server log for details.";
}

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $saveSucceeded,
        'message' => $saveMessage,
    ]);
    exit;
}

$_SESSION['teacher_flash'] = [
    'type'    => $saveSucceeded ? 'success' : 'error',
    'message' => $saveMessage,
];
header('Location: ' . $redirectUrl);
exit;