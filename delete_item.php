<?php
/**
 * Thamani High School - Universal Item Deletion Handler
 * ----------------------------------------------------
 * Allows Admins to delete any record/file.
 * Allows Teachers to delete items they uploaded/created.
 */

session_start();

$isAdmin   = !empty($_SESSION['admin_id']);
$isTeacher = !empty($_SESSION['teacher_id']);

if (!$isAdmin && !$isTeacher) {
    header('Location: teacher-login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_dashboard.php');
    exit;
}

require_once 'conn.php';
require_once 'cloudinary_helper.php';

$type       = trim($_POST['type']        ?? '');
$id         = (int)($_POST['id']         ?? 0);
$redirectTo = trim($_POST['redirect_to'] ?? ($isAdmin ? 'admin_dashboard.php' : 'teacher_dashboard.php'));

$allowedTargets = ['admin_dashboard.php', 'teacher_dashboard.php', 'enrollment_view.php', 'alumni_view.php', 'library.php', 'gallery.php', 'calendar.php'];
if (!in_array($redirectTo, $allowedTargets, true)) {
    $redirectTo = $isAdmin ? 'admin_dashboard.php' : 'teacher_dashboard.php';
}

$setFlash = function($flashType, $msg) use ($redirectTo) {
    $payload = ['type' => $flashType, 'message' => $msg];
    $_SESSION['admin_flash']   = $payload;
    $_SESSION['library_flash'] = $payload;
    $_SESSION['calendar_flash'] = $payload;
    $_SESSION['gallery_flash']  = $payload;
    $_SESSION['enroll_flash']   = $payload;
    header('Location: ' . $redirectTo);
    exit;
};

if ($id <= 0) {
    $setFlash('error', 'Invalid item selected for deletion.');
}

$currentTeacherId = (int)($_SESSION['teacher_id'] ?? 0);

switch ($type) {
    // ============================================================
    // 1. TEACHER RECORD (Admin only)
    // ============================================================
    case 'teacher':
        if (!$isAdmin) {
            $setFlash('error', 'Only administrators can delete teacher records.');
        }
        $stmt = mysqli_prepare($conn, "DELETE FROM teachers WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $setFlash('success', 'Teacher record deleted successfully.');
            } else {
                mysqli_stmt_close($stmt);
                $setFlash('error', 'Failed to delete teacher record.');
            }
        }
        break;

    // ============================================================
    // 2. STUDENT ENROLLMENT RECORD (Admin only)
    // ============================================================
    case 'student':
        if (!$isAdmin) {
            $setFlash('error', 'Only administrators can delete student enrollment records.');
        }
        $stmt = mysqli_prepare($conn, "DELETE FROM students WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $setFlash('success', 'Student record deleted successfully.');
            } else {
                mysqli_stmt_close($stmt);
                $setFlash('error', 'Failed to delete student record.');
            }
        }
        break;

    // ============================================================
    // 3. ALUMNI RECORD (Admin only)
    // ============================================================
    case 'alumni':
        if (!$isAdmin) {
            $setFlash('error', 'Only administrators can delete alumni records.');
        }
        $stmt = mysqli_prepare($conn, "DELETE FROM alumni WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $setFlash('success', 'Alumni record deleted successfully.');
            } else {
                mysqli_stmt_close($stmt);
                $setFlash('error', 'Failed to delete alumni record.');
            }
        }
        break;

    // ============================================================
    // 4. CALENDAR & FEES DOCUMENT (Admin OR Uploader)
    // ============================================================
    case 'calendar':
        $check = mysqli_prepare($conn, "SELECT id, title, file_path, stored_name, uploaded_by FROM calendar_documents WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($check, "i", $id);
        mysqli_stmt_execute($check);
        $res = mysqli_stmt_get_result($check);
        $item = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($check);

        if (!$item) {
            $setFlash('error', 'Calendar document not found.');
        }

        // Permission check: Admin or Uploader
        if (!$isAdmin && (int)$item['uploaded_by'] !== $currentTeacherId) {
            $setFlash('error', 'You do not have permission to delete this document.');
        }

        // Delete physical file or Cloudinary resource if present
        if (!empty($item['file_path']) && (str_starts_with($item['file_path'], 'http://') || str_starts_with($item['file_path'], 'https://'))) {
            cloudinary_delete($item['stored_name'], 'raw');
        } else {
            if (!empty($item['file_path']) && file_exists(__DIR__ . '/' . $item['file_path'])) {
                @unlink(__DIR__ . '/' . $item['file_path']);
            }
            if (!empty($item['stored_name']) && file_exists(__DIR__ . '/calendar_docs/' . $item['stored_name'])) {
                @unlink(__DIR__ . '/calendar_docs/' . $item['stored_name']);
            }
        }

        $del = mysqli_prepare($conn, "DELETE FROM calendar_documents WHERE id = ?");
        mysqli_stmt_bind_param($del, "i", $id);
        if (mysqli_stmt_execute($del)) {
            mysqli_stmt_close($del);
            $setFlash('success', 'Document "' . $item['title'] . '" deleted successfully.');
        } else {
            mysqli_stmt_close($del);
            $setFlash('error', 'Failed to delete document from database.');
        }
        break;

    // ============================================================
    // 5. GALLERY PHOTO (Admin OR Uploader)
    // ============================================================
    case 'gallery':
        $check = mysqli_prepare($conn, "SELECT id, title, file_path, stored_name, uploaded_by FROM gallery_photos WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($check, "i", $id);
        mysqli_stmt_execute($check);
        $res = mysqli_stmt_get_result($check);
        $item = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($check);

        if (!$item) {
            $setFlash('error', 'Gallery photo not found.');
        }

        // Permission check: Admin or Uploader
        if (!$isAdmin && (int)$item['uploaded_by'] !== $currentTeacherId) {
            $setFlash('error', 'You do not have permission to delete this photo.');
        }

        // Delete physical file or Cloudinary resource if present
        if (!empty($item['file_path']) && (str_starts_with($item['file_path'], 'http://') || str_starts_with($item['file_path'], 'https://'))) {
            cloudinary_delete($item['stored_name'], 'image');
        } else {
            if (!empty($item['file_path']) && file_exists(__DIR__ . '/' . $item['file_path'])) {
                @unlink(__DIR__ . '/' . $item['file_path']);
            }
            if (!empty($item['stored_name']) && file_exists(__DIR__ . '/gallery_images/' . $item['stored_name'])) {
                @unlink(__DIR__ . '/gallery_images/' . $item['stored_name']);
            }
        }

        $del = mysqli_prepare($conn, "DELETE FROM gallery_photos WHERE id = ?");
        mysqli_stmt_bind_param($del, "i", $id);
        if (mysqli_stmt_execute($del)) {
            mysqli_stmt_close($del);
            $setFlash('success', 'Photo "' . $item['title'] . '" deleted successfully.');
        } else {
            mysqli_stmt_close($del);
            $setFlash('error', 'Failed to delete photo from database.');
        }
        break;

    // ============================================================
    // 6. LIBRARY RESOURCE (Admin OR Uploader)
    // ============================================================
    case 'library':
        $check = mysqli_prepare($conn, "SELECT id, title, file_path, stored_name, uploaded_by FROM library_resources WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($check, "i", $id);
        mysqli_stmt_execute($check);
        $res = mysqli_stmt_get_result($check);
        $item = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($check);

        if (!$item) {
            $setFlash('error', 'Library resource not found.');
        }

        // Permission check: Admin or Uploader
        if (!$isAdmin && (int)$item['uploaded_by'] !== $currentTeacherId) {
            $setFlash('error', 'You do not have permission to delete this library resource.');
        }

        // Delete physical file or Cloudinary resource if present
        if (!empty($item['file_path']) && (str_starts_with($item['file_path'], 'http://') || str_starts_with($item['file_path'], 'https://'))) {
            cloudinary_delete($item['stored_name'], 'raw');
        } else {
            if (!empty($item['file_path']) && file_exists(__DIR__ . '/' . $item['file_path'])) {
                @unlink(__DIR__ . '/' . $item['file_path']);
            }
            if (!empty($item['stored_name']) && file_exists(__DIR__ . '/library/' . $item['stored_name'])) {
                @unlink(__DIR__ . '/library/' . $item['stored_name']);
            }
        }

        $del = mysqli_prepare($conn, "DELETE FROM library_resources WHERE id = ?");
        mysqli_stmt_bind_param($del, "i", $id);
        if (mysqli_stmt_execute($del)) {
            mysqli_stmt_close($del);
            $setFlash('success', 'Resource "' . $item['title'] . '" deleted successfully.');
        } else {
            mysqli_stmt_close($del);
            $setFlash('error', 'Failed to delete library resource from database.');
        }
        break;

    default:
        $setFlash('error', 'Invalid deletion request.');
}

$setFlash('info', 'Action processed.');
?>
