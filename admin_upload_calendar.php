<?php
/**
 * Thamani High School - Admin Upload: Calendar / Fees Document
 * ---------------------------------------------------------
 * - Admin only
 * - Validates + stores document in /calendar_docs/
 * - Inserts a row into calendar_documents
 * - Redirects back to the page that sent the form (redirect_to)
 */

require_once 'auth_admin.php';
require_admin_login();

require_once 'conn.php';

$adminId = (int)$_SESSION['admin_id'];

// ---------- Redirect target (validated whitelist) ----------
$allowedTargets = ['admin_dashboard.php', 'calendar.php'];
$redirectTo = $_POST['redirect_to'] ?? 'admin_dashboard.php';
if (!in_array($redirectTo, $allowedTargets, true)) {
    $redirectTo = 'admin_dashboard.php';
}

// Flash key depends on where the form was submitted from
$flashKey = ($redirectTo === 'calendar.php') ? 'calendar_flash' : 'admin_flash';

// ---------- Only POST ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectTo);
    exit;
}

// ---------- Config ----------
$uploadDir = __DIR__ . '/calendar_docs/';
$urlPrefix = 'calendar_docs/';
$maxBytes  = 20 * 1024 * 1024;

$allowedExt = ['pdf','doc','docx','xls','xlsx'];
$allowedMimes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/octet-stream',
];

// ---------- Collect + validate ----------
$title       = trim($_POST['title']       ?? '');
$docType     = trim($_POST['doc_type']    ?? 'calendar');
$description = trim($_POST['description'] ?? '');

$errors = [];

if ($title === '')             $errors[] = 'Title is required.';
if (mb_strlen($title) > 255)   $errors[] = 'Title is too long.';
if (!in_array($docType, ['calendar','fees','timetable','other'], true)) $errors[] = 'Invalid document type.';
if (mb_strlen($description) > 500) $errors[] = 'Description is too long.';

$file = $_FILES['doc_file'] ?? null;

if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
    $errors[] = 'Please choose a file.';
} elseif ($file['error'] !== UPLOAD_ERR_OK) {
    switch ($file['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errors[] = 'File is too large (max 20 MB).';
            break;
        case UPLOAD_ERR_PARTIAL:
            $errors[] = 'File upload was interrupted.';
            break;
        default:
            $errors[] = 'File upload failed (code ' . $file['error'] . ').';
    }
} else {
    if ($file['size'] > $maxBytes) $errors[] = 'File is too large (max 20 MB).';

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        $errors[] = 'File type not allowed. Allowed: ' . implode(', ', array_map('strtoupper', $allowedExt));
    }

    $mime = 'application/octet-stream';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) {
            $mime = finfo_file($fi, $file['tmp_name']) ?: $mime;
            finfo_close($fi);
        }
    }
    if (!in_array($mime, $allowedMimes, true)) {
        $errors[] = 'File content does not match an allowed document type.';
    }

    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    if (!is_writable($uploadDir)) $errors[] = 'Storage folder not writable. Contact ICT.';
}

// ---------- Bounce on error ----------
if (!empty($errors)) {
    $_SESSION[$flashKey] = ['type' => 'error', 'message' => implode(' ', $errors)];
    header('Location: ' . $redirectTo);
    exit;
}

require_once 'cloudinary_helper.php';

// ---------- Move / Upload file ----------
$cRes = cloudinary_upload($file['tmp_name'], $file['name'], 'raw');

if ($cRes['success']) {
    $relPath    = $cRes['secure_url'];
    $randomName = $cRes['public_id'] ?? ('cal_' . date('Ymd_His') . '.' . $ext);
    $destPath   = null;
} else {
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    $randomName = 'cal_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destPath   = $uploadDir . $randomName;
    $relPath    = $urlPrefix . $randomName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        error_log('[Admin Calendar Upload Move] Failed → ' . $destPath);
        $_SESSION[$flashKey] = ['type' => 'error', 'message' => 'Failed to store uploaded file.'];
        header('Location: ' . $redirectTo);
        exit;
    }
}

// ---------- Insert into DB ----------
$sql = "INSERT INTO calendar_documents
        (title, doc_type, description, file_name, stored_name, file_path, file_size, mime_type, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt === false) {
    error_log('[Admin Calendar Prepare] ' . mysqli_error($conn));
    @unlink($destPath);
    $_SESSION[$flashKey] = ['type' => 'error', 'message' => 'Database error while saving.'];
    header('Location: ' . $redirectTo);
    exit;
}

$descVal = $description !== '' ? $description : null;

mysqli_stmt_bind_param(
    $stmt,
    "ssssssisi",
    $title, $docType, $descVal,
    $file['name'], $randomName, $relPath,
    $file['size'], $mime, $adminId
);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    $_SESSION[$flashKey] = ['type' => 'success', 'message' => 'Document "' . $title . '" uploaded successfully.'];
} else {
    error_log('[Admin Calendar Execute] ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    @unlink($destPath);
    $_SESSION[$flashKey] = ['type' => 'error', 'message' => 'Database error while saving the document.'];
}

header('Location: ' . $redirectTo);
exit;