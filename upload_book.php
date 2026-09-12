<?php
/**
 * Thamani High School - Library Resource Upload Handler
 * --------------------------------------------------
 * - Requires teacher login
 * - Validates file type, size, and metadata
 * - Stores file in /library/ with a hashed filename
 * - Inserts a row into library_resources
 */

session_start();

// Teacher or Admin
if (empty($_SESSION['teacher_id']) && empty($_SESSION['admin_id'])) {
    header('Location: teacher-login.php');
    exit;
}

require_once 'conn.php';

$teacherId = (int)($_SESSION['teacher_id'] ?? $_SESSION['admin_id']);

// ---------- Config ----------
$uploadDir       = __DIR__ . '/library/';
$uploadUrlPrefix = 'library/';
$maxBytes        = 25 * 1024 * 1024; // 25 MB

$allowedExtensions = ['pdf','doc','docx','xls','xlsx','ppt','pptx','epub','zip'];
$allowedMimes      = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/epub+zip',
    'application/zip',
    'application/x-zip-compressed',
    'application/octet-stream', // fallback for some ZIP variants
];

// ---------- Only POST ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: library.php');
    exit;
}

// ---------- Collect + validate ----------
$title       = trim($_POST['title']       ?? '');
$author      = trim($_POST['author']      ?? '');
$subject     = trim($_POST['subject']     ?? '');
$category    = trim($_POST['category']    ?? '');
$classLevel  = trim($_POST['class_level'] ?? '');
$description = trim($_POST['description'] ?? '');

$errors = [];

if ($title === '')            $errors[] = 'Title is required.';
if (mb_strlen($title) > 255)  $errors[] = 'Title is too long.';
if (mb_strlen($author) > 150) $errors[] = 'Author name is too long.';

if ($subject === '')          $errors[] = 'Subject is required.';
if (mb_strlen($subject) > 100)$errors[] = 'Subject is too long.';

$validCategories = ['past-paper','textbook','revision','other'];
if (!in_array($category, $validCategories, true)) $errors[] = 'Invalid category.';

if (mb_strlen($classLevel) > 50) $errors[] = 'Class level is too long.';
if (mb_strlen($description) > 500) $errors[] = 'Description is too long.';

// ---------- File checks ----------
$file = $_FILES['book_file'] ?? null;

if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
    $errors[] = 'Please choose a file to upload.';
} elseif ($file['error'] !== UPLOAD_ERR_OK) {
    switch ($file['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errors[] = 'File is too large. Maximum allowed: 25 MB.';
            break;
        case UPLOAD_ERR_PARTIAL:
            $errors[] = 'File upload was interrupted. Please try again.';
            break;
        default:
            $errors[] = 'File upload failed (error code ' . $file['error'] . ').';
    }
} else {
    if ($file['size'] > $maxBytes) {
        $errors[] = 'File is too large. Maximum allowed: 25 MB.';
    }

    $origName = $file['name'];
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExtensions, true)) {
        $errors[] = 'File type not allowed. Allowed: ' . implode(', ', array_map('strtoupper', $allowedExtensions));
    }

    // MIME check (best-effort; may fall back to octet-stream)
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

    // Ensure upload folder exists and is writable
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0755, true)) {
            $errors[] = 'Server could not create the library storage folder.';
        }
    }
    if (is_dir($uploadDir) && !is_writable($uploadDir)) {
        $errors[] = 'Library storage folder is not writable. Contact ICT.';
    }
}

// ---------- On errors: bounce back with flash ----------
if (!empty($errors)) {
    $_SESSION['library_flash'] = [
        'type'    => 'error',
        'message' => implode(' ', $errors),
    ];
    header('Location: library.php');
    exit;
}

require_once 'cloudinary_helper.php';

// ---------- Move / Upload file ----------
$cRes = cloudinary_upload($file['tmp_name'], $file['name'], 'raw');

if ($cRes['success']) {
    $relPath    = $cRes['secure_url'];
    $randomName = $cRes['public_id'] ?? ('lib_' . date('Ymd_His') . '.' . $ext);
    $destPath   = null;
} else {
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    $randomName = 'lib_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destPath   = $uploadDir . $randomName;
    $relPath    = $uploadUrlPrefix . $randomName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        error_log('[Library Upload Move] Failed to move to ' . $destPath);
        $_SESSION['library_flash'] = [
            'type'    => 'error',
            'message' => 'Failed to store the uploaded file. Please try again.',
        ];
        header('Location: library.php');
        exit;
    }
}

// ---------- Insert into DB ----------
$sql = "INSERT INTO library_resources
        (title, author, subject, category, class_level, description,
         file_name, stored_name, file_path, file_size, mime_type, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt === false) {
    error_log('[Library Insert Prepare] ' . mysqli_error($conn));
    @unlink($destPath);
    $_SESSION['library_flash'] = [
        'type'    => 'error',
        'message' => 'Database error while saving the resource.',
    ];
    header('Location: library.php');
    exit;
}

$authorVal  = $author      !== '' ? $author      : null;
$classVal   = $classLevel  !== '' ? $classLevel  : null;
$descVal    = $description !== '' ? $description : null;

mysqli_stmt_bind_param(
    $stmt,
    "sssssssssisi",
    $title, $authorVal, $subject, $category, $classVal, $descVal,
    $file['name'], $randomName, $relPath,
    $file['size'], $mime, $teacherId
);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    $_SESSION['library_flash'] = [
        'type'    => 'success',
        'message' => 'Resource "' . $title . '" uploaded successfully.',
    ];
    header('Location: library.php');
    exit;
} else {
    error_log('[Library Insert Execute] ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    @unlink($destPath);
    $_SESSION['library_flash'] = [
        'type'    => 'error',
        'message' => 'Database error while saving the resource.',
    ];
    header('Location: library.php');
    exit;
}