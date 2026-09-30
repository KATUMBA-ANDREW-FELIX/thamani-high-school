<?php
/**
 * Thamani High School - Save Teacher On Duty (TOD) Roster Handler
 * ---------------------------------------------------------------
 * Allows Admins or Teachers explicitly granted permission (can_manage_duty_roster = 1)
 * to create or update duty roster entries.
 */

session_start();

$isAdmin   = !empty($_SESSION['admin_id']);
$isTeacher = !empty($_SESSION['teacher_id']);

if (!$isAdmin && !$isTeacher) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ($isAdmin ? 'admin_dashboard.php' : 'teacher_dashboard.php'));
    exit;
}

require_once 'conn.php';

$currentTeacherId = (int)($_SESSION['teacher_id'] ?? 0);
$currentAdminId   = (int)($_SESSION['admin_id'] ?? 0);

$canManage = false;
if ($isAdmin) {
    $canManage = true;
} else if ($isTeacher) {
    $tStmt = thamani_db_prepare($conn, "SELECT can_manage_duty_roster FROM teachers WHERE id = ? LIMIT 1");
    if ($tStmt) {
        thamani_db_stmt_bind_param($tStmt, "i", $currentTeacherId);
        thamani_db_stmt_execute($tStmt);
        $res = thamani_db_stmt_get_result($tStmt);
        $tData = $res ? thamani_db_fetch_assoc($res) : null;
        thamani_db_stmt_close($tStmt);
        if (!empty($tData['can_manage_duty_roster'])) {
            $canManage = true;
        }
    }
}

$defaultRedirect = $isAdmin ? 'admin_dashboard.php?tab=tab-admin-roster#tab-admin-roster' : 'teacher_dashboard.php?tab=tab-teacher-roster#tab-teacher-roster';
$redirectTo = trim($_POST['redirect_to'] ?? $defaultRedirect);

if (!$canManage) {
    $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'You do not have permission to upload or manage the Teacher On Duty Roster.'];
    $_SESSION['teacher_flash'] = ['type' => 'error', 'message' => 'You do not have permission to upload or manage the Teacher On Duty Roster.'];
    header('Location: ' . $redirectTo);
    exit;
}

$id                   = (int)($_POST['id'] ?? 0);
$weekTitle            = trim($_POST['week_title'] ?? '');
$seniorDutyTeacher    = trim($_POST['senior_duty_teacher'] ?? '');
$assistantDutyTeacher = trim($_POST['assistant_duty_teacher'] ?? '');
$primaryFocusArea     = trim($_POST['primary_focus_area'] ?? '');
$notes                = trim($_POST['notes'] ?? '');

$errors = [];
if ($weekTitle === '') {
    $errors[] = 'Week Title / Date Range is required (e.g., "Week 4 (Oct 6 - Oct 12)").';
}
if ($seniorDutyTeacher === '') {
    $errors[] = 'Senior Duty Teacher name is required.';
}
if ($primaryFocusArea === '') {
    $errors[] = 'Primary Focus Area is required.';
}

if (!empty($errors)) {
    $msg = implode(' ', $errors);
    $_SESSION['admin_flash'] = ['type' => 'error', 'message' => $msg];
    $_SESSION['teacher_flash'] = ['type' => 'error', 'message' => $msg];
    header('Location: ' . $redirectTo);
    exit;
}

if ($id > 0) {
    // Update existing roster entry
    $upSql = "UPDATE teacher_duty_rosters 
              SET week_title = ?, senior_duty_teacher = ?, assistant_duty_teacher = ?, primary_focus_area = ?, notes = ?
              WHERE id = ?";
    $upStmt = thamani_db_prepare($conn, $upSql);
    if ($upStmt) {
        thamani_db_stmt_bind_param($upStmt, "sssssi", $weekTitle, $seniorDutyTeacher, $assistantDutyTeacher, $primaryFocusArea, $notes, $id);
        if (thamani_db_stmt_execute($upStmt)) {
            thamani_db_stmt_close($upStmt);
            $_SESSION['admin_flash'] = ['type' => 'success', 'message' => 'Duty roster entry updated successfully.'];
            $_SESSION['teacher_flash'] = ['type' => 'success', 'message' => 'Duty roster entry updated successfully.'];
        } else {
            thamani_db_stmt_close($upStmt);
            $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Failed to update duty roster entry.'];
            $_SESSION['teacher_flash'] = ['type' => 'error', 'message' => 'Failed to update duty roster entry.'];
        }
    }
} else {
    // Insert new roster entry
    $createdByRole = $isAdmin ? 'admin' : 'teacher';
    $creatorId = $isAdmin ? $currentAdminId : $currentTeacherId;
    $insSql = "INSERT INTO teacher_duty_rosters (week_title, senior_duty_teacher, assistant_duty_teacher, primary_focus_area, notes, created_by, created_by_role)
               VALUES (?, ?, ?, ?, ?, ?, ?)";
    $insStmt = thamani_db_prepare($conn, $insSql);
    if ($insStmt) {
        thamani_db_stmt_bind_param($insStmt, "sssssis", $weekTitle, $seniorDutyTeacher, $assistantDutyTeacher, $primaryFocusArea, $notes, $creatorId, $createdByRole);
        if (thamani_db_stmt_execute($insStmt)) {
            thamani_db_stmt_close($insStmt);
            $_SESSION['admin_flash'] = ['type' => 'success', 'message' => 'New Duty Roster entry published successfully!'];
            $_SESSION['teacher_flash'] = ['type' => 'success', 'message' => 'New Duty Roster entry published successfully!'];
        } else {
            thamani_db_stmt_close($insStmt);
            $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Failed to save duty roster entry.'];
            $_SESSION['teacher_flash'] = ['type' => 'error', 'message' => 'Failed to save duty roster entry.'];
        }
    }
}

header('Location: ' . $redirectTo);
exit;
