<?php
/**
 * Thamani Academy - Admin Upload: Gallery Photo
 * ----------------------------------------------
 * - Admin only
 * - Validates + stores image in /gallery_images/
 * - Inserts a row into gallery_photos
 * - Redirects back to the page that sent the form (redirect_to)
 */

require_once 'auth_admin.php';
require_admin_login();

require_once 'conn.php';

$adminId = (int)$_SESSION['admin_id'];

// ---------- Redirect target ----------
$allowedTargets = ['admin_dashboard.php', 'gallery.php'];
$redirectTo = $_POST['redirect_to'] ?? 'admin_dashboard.php';
if (!in_array($redirectTo, $allowedTargets, true)) {
    $redirectTo = 'admin_dashboard.php';
}

$flashKey = ($redirectTo === 'gallery.php') ? 'gallery_flash' : 'admin_flash';

// ---------- Only POST ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectTo);
    exit;
}

// ---------- Config ----------
$uploadDir = __DIR__ . '/gallery_images/';
$urlPrefix = 'gallery_images/';
$maxBytes  = 8 * 1024 * 1024;

$allowedExt = ['jpg','jpeg','png','webp','gif'];
$allowedMimes = ['image/jpeg','image/png','image/webp','image/gif'];

// ---------- Collect + validate ----------
$title    = trim($_POST['title']    ?? '');
$category = trim($_POST['category'] ?? 'campus');
$caption  = trim($_POST['caption']  ?? '');

$errors = [];

if ($title === '')            $errors[] = 'Title is required.';
if (mb_strlen($title) > 255)  $errors[] = 'Title is too long.';
if (!in_array($category, ['cultural','sports','campus','academic','other'], true)) $errors[] = 'Invalid category.';
if (mb_strlen($caption) > 500) $errors[] = 'Caption is too long.';

$file = $_FILES['photo_file'] ?? null;

if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
    $errors[] = 'Please choose an image.';
} elseif ($file['error'] !== UPLOAD_ERR_OK) {
    switch ($file['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errors[] = 'Image is too large (max 8 MB).';
            break;
        case UPLOAD_ERR_PARTIAL:
            $errors[] = 'Upload was interrupted.';
            break;
        default:
            $errors[] = 'Upload failed (code ' . $file['error'] . ').';
    }
} else {
    if ($file['size'] > $maxBytes) $errors[] = 'Image is too large (max 8 MB).';

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        $errors[] = 'Image type not allowed. Allowed: ' . implode(', ', array_map('strtoupper', $allowedExt));
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
        $errors[] = 'File is not a valid image.';
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

// ---------- Move file ----------
$randomName = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$destPath   = $uploadDir . $randomName;
$relPath    = $urlPrefix . $randomName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    error_log('[Admin Gallery Upload Move] Failed → ' . $destPath);
    $_SESSION[$flashKey] = ['type' => 'error', 'message' => 'Failed to store the image.'];
    header('Location: ' . $redirectTo);
    exit;
}

// ---------- Insert into DB ----------
$sql = "INSERT INTO gallery_photos
        (title, caption, category, file_name, stored_name, file_path, file_size, mime_type, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt === false) {
    error_log('[Admin Gallery Prepare] ' . mysqli_error($conn));
    @unlink($destPath);
    $_SESSION[$flashKey] = ['type' => 'error', 'message' => 'Database error while saving.'];
    header('Location: ' . $redirectTo);
    exit;
}

$capVal = $caption !== '' ? $caption : null;

mysqli_stmt_bind_param(
    $stmt,
    "ssssssisi",
    $title, $capVal, $category,
    $file['name'], $randomName, $relPath,
    $file['size'], $mime, $adminId
);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    $_SESSION[$flashKey] = ['type' => 'success', 'message' => 'Photo "' . $title . '" uploaded successfully.'];
} else {
    error_log('[Admin Gallery Execute] ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    @unlink($destPath);
    $_SESSION[$flashKey] = ['type' => 'error', 'message' => 'Database error while saving the photo.'];
}

header('Location: ' . $redirectTo);
exit;