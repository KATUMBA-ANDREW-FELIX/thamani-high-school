<?php
/**
 * Thamani High School - Academic Reporting Actions Controller
 * -------------------------------------------------------------
 * Admin endpoint for managing exam reporting windows, subject teacher assignments,
 * and customizable grading scales.
 */

require_once 'auth_admin.php';
require_admin_login();
require_once 'conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_dashboard.php?tab=tab-admin-reporting#tab-admin-reporting');
    exit;
}

$action = trim($_POST['action'] ?? '');
$redirectTab = 'admin_dashboard.php?tab=tab-admin-reporting#tab-admin-reporting';

switch ($action) {
    case 'create_window':
        $title          = trim($_POST['title'] ?? '');
        $year           = trim($_POST['academic_year'] ?? date('Y'));
        $term           = trim($_POST['term'] ?? 'Term I');
        $type           = trim($_POST['assessment_type'] ?? 'EOT');
        $isOpen         = isset($_POST['is_open']) ? 1 : 0;
        $isPublished    = isset($_POST['is_published']) ? 1 : 0;
        $showPositions  = isset($_POST['show_positions']) ? 1 : 0;

        if ($title === '') {
            $title = "{$year} {$term} - " . strtoupper($type);
        }

        $stmt = thamani_db_prepare($conn, "INSERT INTO reporting_windows (title, academic_year, term, assessment_type, is_open, is_published, show_positions) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            thamani_db_stmt_bind_param($stmt, "ssssiii", $title, $year, $term, $type, $isOpen, $isPublished, $showPositions);
            thamani_db_stmt_execute($stmt);
            thamani_db_stmt_close($stmt);
            $_SESSION['admin_flash'] = ['type' => 'success', 'message' => "Reporting Window '{$title}' created successfully."];
        } else {
            $_SESSION['admin_flash'] = ['type' => 'error', 'message' => "Failed to create reporting window."];
        }
        break;

    case 'toggle_window_open':
        $id     = (int)($_POST['window_id'] ?? 0);
        $status = (int)($_POST['status'] ?? 0);
        $stmt   = thamani_db_prepare($conn, "UPDATE reporting_windows SET is_open = ? WHERE id = ?");
        if ($stmt) {
            thamani_db_stmt_bind_param($stmt, "ii", $status, $id);
            thamani_db_stmt_execute($stmt);
            thamani_db_stmt_close($stmt);
            $msg = $status === 1 ? "Reporting Window opened for teacher marks entry." : "Reporting Window closed and locked.";
            $_SESSION['admin_flash'] = ['type' => 'success', 'message' => $msg];
        }
        break;

    case 'toggle_window_published':
        $id     = (int)($_POST['window_id'] ?? 0);
        $status = (int)($_POST['status'] ?? 0);
        $stmt   = thamani_db_prepare($conn, "UPDATE reporting_windows SET is_published = ? WHERE id = ?");
        if ($stmt) {
            thamani_db_stmt_bind_param($stmt, "ii", $status, $id);
            thamani_db_stmt_execute($stmt);
            thamani_db_stmt_close($stmt);
            $msg = $status === 1 ? "Results published to Student & Parent portals." : "Results unpublished from student view.";
            $_SESSION['admin_flash'] = ['type' => 'success', 'message' => $msg];
        }
        break;

    case 'toggle_show_positions':
        $id     = (int)($_POST['window_id'] ?? 0);
        $status = (int)($_POST['status'] ?? 0);
        $stmt   = thamani_db_prepare($conn, "UPDATE reporting_windows SET show_positions = ? WHERE id = ?");
        if ($stmt) {
            thamani_db_stmt_bind_param($stmt, "ii", $status, $id);
            thamani_db_stmt_execute($stmt);
            thamani_db_stmt_close($stmt);
            $msg = $status === 1 ? "Stream ranking/positions set to SHOW on report cards." : "Stream ranking/positions HIDE from report cards.";
            $_SESSION['admin_flash'] = ['type' => 'success', 'message' => $msg];
        }
        break;

    case 'delete_window':
        $id = (int)($_POST['window_id'] ?? 0);
        if ($id > 0) {
            $stmt = thamani_db_prepare($conn, "DELETE FROM reporting_windows WHERE id = ?");
            if ($stmt) {
                thamani_db_stmt_bind_param($stmt, "i", $id);
                thamani_db_stmt_execute($stmt);
                thamani_db_stmt_close($stmt);
                $_SESSION['admin_flash'] = ['type' => 'success', 'message' => "Reporting Window deleted."];
            }
        }
        break;

    case 'assign_subject_teacher':
        $teacherId  = (int)($_POST['teacher_id'] ?? 0);
        $subject    = trim($_POST['subject'] ?? '');
        $classLevel = trim($_POST['class_level'] ?? '');
        $stream     = trim($_POST['stream'] ?? 'All Streams');

        if ($teacherId <= 0 || $subject === '' || $classLevel === '') {
            $_SESSION['admin_flash'] = ['type' => 'error', 'message' => "All fields are required to assign a subject teacher."];
            break;
        }

        $ins = thamani_db_prepare($conn, "INSERT INTO teacher_subject_assignments (teacher_id, subject, class_level, stream) VALUES (?, ?, ?, ?)");
        if ($ins) {
            thamani_db_stmt_bind_param($ins, "isss", $teacherId, $subject, $classLevel, $stream);
            thamani_db_stmt_execute($ins);
            thamani_db_stmt_close($ins);
            $_SESSION['admin_flash'] = ['type' => 'success', 'message' => "Teacher assigned to {$subject} ({$classLevel} - {$stream}) successfully."];
        }
        break;

    case 'delete_subject_assignment':
        $id = (int)($_POST['assignment_id'] ?? 0);
        if ($id > 0) {
            $stmt = thamani_db_prepare($conn, "DELETE FROM teacher_subject_assignments WHERE id = ?");
            if ($stmt) {
                thamani_db_stmt_bind_param($stmt, "i", $id);
                thamani_db_stmt_execute($stmt);
                thamani_db_stmt_close($stmt);
                $_SESSION['admin_flash'] = ['type' => 'success', 'message' => "Subject assignment removed."];
            }
        }
        break;

    case 'save_grading_scale':
        $scaleName  = trim($_POST['scale_name'] ?? 'O-Level Standard');
        $minScore   = floatval($_POST['min_score'] ?? 0);
        $maxScore   = floatval($_POST['max_score'] ?? 100);
        $grade      = trim($_POST['grade'] ?? 'D1');
        $points     = (int)($_POST['points'] ?? 1);
        $remark     = trim($_POST['remark'] ?? '');
        $edLevel    = trim($_POST['education_level'] ?? 'O-Level');

        $stmt = thamani_db_prepare($conn, "INSERT INTO grading_scales (scale_name, min_score, max_score, grade, points, remark, education_level) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            thamani_db_stmt_bind_param($stmt, "sddsiss", $scaleName, $minScore, $maxScore, $grade, $points, $remark, $edLevel);
            thamani_db_stmt_execute($stmt);
            thamani_db_stmt_close($stmt);
            $_SESSION['admin_flash'] = ['type' => 'success', 'message' => "Grade boundary rule '{$grade}' added to {$edLevel} scale."];
        }
        break;

    case 'delete_grading_scale':
        $id = (int)($_POST['scale_id'] ?? 0);
        if ($id > 0) {
            $stmt = thamani_db_prepare($conn, "DELETE FROM grading_scales WHERE id = ?");
            if ($stmt) {
                thamani_db_stmt_bind_param($stmt, "i", $id);
                thamani_db_stmt_execute($stmt);
                thamani_db_stmt_close($stmt);
                $_SESSION['admin_flash'] = ['type' => 'success', 'message' => "Grade rule removed."];
            }
        }
        break;
}

header('Location: ' . $redirectTab);
exit;
