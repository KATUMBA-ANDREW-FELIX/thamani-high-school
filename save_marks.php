<?php
/**
 * Thamani High School - Subject Marks Processor
 * ----------------------------------------------
 * - Class Teachers can enter/view marks across subjects for their assigned class.
 * - Subject Teachers can ONLY enter/edit marks for their specific taught subject(s).
 */

require_once 'auth_teacher.php';
require_teacher_login();
require_password_changed();

require_once 'conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: students_view.php');
    exit;
}

$teacher = current_teacher();
$isAdmin = !empty($_SESSION['admin_id']);

$studentId  = (int)($_POST['student_id'] ?? 0);
$classLevel = trim($_POST['class_level'] ?? '');
$subject    = trim($_POST['subject'] ?? '');
$term       = trim($_POST['term'] ?? 'Term III 2026');
$score      = floatval($_POST['score'] ?? 0);
$maxScore   = floatval($_POST['max_score'] ?? 100);
$comments   = trim($_POST['comments'] ?? '');

$errors = [];

if ($studentId <= 0) {
    $errors[] = 'Invalid student record.';
}
if ($subject === '') {
    $errors[] = 'Subject name is required.';
}
if ($score < 0 || $score > $maxScore) {
    $errors[] = "Score must be between 0 and {$maxScore}.";
}

// Security Check: Subject Teacher & Class Teacher Scoping
if (!$isAdmin) {
    $isClassTeacherOfThisClass = !empty($teacher['is_class_teacher']) && trim($teacher['class_teacher_of']) === $classLevel;
    
    // Parse classes taught by this teacher
    $classesTaughtArr = array_filter(array_map('trim', explode(',', $teacher['classes_taught'] ?? '')));
    $teachesThisClass  = in_array($classLevel, $classesTaughtArr, true) || $isClassTeacherOfThisClass;

    if (!$teachesThisClass) {
        $errors[] = "Permission Denied: You are not assigned to teach or manage {$classLevel}.";
    } elseif (!$isClassTeacherOfThisClass) {
        // If not Class Teacher, verify that the subject matches the teacher's department/taught subject
        $dept = strtolower(trim($teacher['department'] ?? ''));
        $sub  = strtolower($subject);
        
        // Match if department is contained in subject name or vice versa, or if department is general
        if ($dept !== '' && strpos($sub, $dept) === false && strpos($dept, $sub) === false && $dept !== 'academic') {
            $errors[] = "Permission Denied: You can only add or edit marks for your assigned subject ({$teacher['department']}).";
        }
    }
}

if (!empty($errors)) {
    $_SESSION['teacher_flash'] = ['type' => 'error', 'message' => implode(' ', $errors)];
    header('Location: students_view.php?class=' . urlencode($classLevel));
    exit;
}

$teacherId = (int)($teacher['id'] ?? 0);

// Check if a mark entry already exists for this student, class, subject & term
$checkSql = "SELECT id FROM student_marks WHERE student_id = ? AND class_level = ? AND subject = ? AND term = ? LIMIT 1";
$checkStmt = thamani_db_prepare($conn, $checkSql);
$existingId = 0;

if ($checkStmt) {
    thamani_db_stmt_bind_param($checkStmt, "isss", $studentId, $classLevel, $subject, $term);
    thamani_db_stmt_execute($checkStmt);
    $res = thamani_db_stmt_get_result($checkStmt);
    if ($res && $r = thamani_db_fetch_assoc($res)) {
        $existingId = (int)$r['id'];
    }
    thamani_db_stmt_close($checkStmt);
}

if ($existingId > 0) {
    $upSql = "UPDATE student_marks SET score = ?, max_score = ?, comments = ?, recorded_by_teacher_id = ?, updated_at = NOW() WHERE id = ?";
    $upStmt = thamani_db_prepare($conn, $upSql);
    if ($upStmt) {
        thamani_db_stmt_bind_param($upStmt, "ddsii", $score, $maxScore, $comments, $teacherId, $existingId);
        thamani_db_stmt_execute($upStmt);
        thamani_db_stmt_close($upStmt);
    }
} else {
    $insSql = "INSERT INTO student_marks (student_id, class_level, subject, term, score, max_score, comments, recorded_by_teacher_id)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $insStmt = thamani_db_prepare($conn, $insSql);
    if ($insStmt) {
        thamani_db_stmt_bind_param($insStmt, "isssddsi", $studentId, $classLevel, $subject, $term, $score, $maxScore, $comments, $teacherId);
        thamani_db_stmt_execute($insStmt);
        thamani_db_stmt_close($insStmt);
    }
}

$_SESSION['teacher_flash'] = [
    'type' => 'success',
    'message' => "Academic marks for {$subject} ({$term}) saved successfully: {$score}/{$maxScore}."
];

header('Location: students_view.php?class=' . urlencode($classLevel));
exit;
