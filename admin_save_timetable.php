<?php
/**
 * Thamani High School - Admin Save Class & Stream Timetable Handler
 * Supports manual schedule grids (Mon-Sun), global programs, and file attachments (PDF/DOCX/XLSX).
 */

require_once 'auth_admin.php';
require_admin_login();
require_once 'conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_dashboard.php');
    exit;
}

$title        = trim($_POST['title'] ?? '');
$classLevel   = trim($_POST['class_level'] ?? '');
$stream       = trim($_POST['stream'] ?? 'All Streams');
$scheduleRaw  = $_POST['schedule_json'] ?? '';
$adminId      = $_SESSION['admin_id'] ?? 0;

$errors = [];
if ($title === '') {
    $errors[] = 'Timetable title is required.';
}
if ($classLevel === '') {
    $errors[] = 'Class level is required.';
}

// Process schedule JSON if provided as array or JSON string
$scheduleJson = null;
if (is_array($scheduleRaw)) {
    $scheduleJson = json_encode(array_values($scheduleRaw));
} elseif (is_string($scheduleRaw) && !empty($scheduleRaw)) {
    $scheduleJson = $scheduleRaw;
} else {
    // Collect individual period inputs from form if passed separately
    $times = $_POST['slot_time'] ?? [];
    $isGlobals = $_POST['slot_is_global'] ?? [];
    $programs = $_POST['slot_program'] ?? [];
    $mons = $_POST['slot_mon'] ?? [];
    $tues = $_POST['slot_tue'] ?? [];
    $weds = $_POST['slot_wed'] ?? [];
    $thus = $_POST['slot_thu'] ?? [];
    $fris = $_POST['slot_fri'] ?? [];
    $sats = $_POST['slot_sat'] ?? [];
    $suns = $_POST['slot_sun'] ?? [];

    $slots = [];
    if (is_array($times)) {
        for ($i = 0; $i < count($times); $i++) {
            $t = trim($times[$i] ?? '');
            if ($t === '') continue;
            $isG = !empty($isGlobals[$i]) ? 1 : 0;
            $slots[] = [
                'time' => $t,
                'is_global_program' => $isG,
                'program_title' => trim($programs[$i] ?? ''),
                'mon' => trim($mons[$i] ?? ''),
                'tue' => trim($tues[$i] ?? ''),
                'wed' => trim($weds[$i] ?? ''),
                'thu' => trim($thus[$i] ?? ''),
                'fri' => trim($fris[$i] ?? ''),
                'sat' => trim($sats[$i] ?? ''),
                'sun' => trim($suns[$i] ?? ''),
            ];
        }
    }
    if (!empty($slots)) {
        $scheduleJson = json_encode($slots);
    }
}

// Optional File Attachment Upload (PDF, DOCX, XLSX)
$fileName = null;
$filePath = null;
$fileSize = null;

if (isset($_FILES['timetable_file']) && $_FILES['timetable_file']['error'] === UPLOAD_ERR_OK) {
    $fileTmp  = $_FILES['timetable_file']['tmp_name'];
    $fileName = $_FILES['timetable_file']['name'];
    $fileSize = (int)$_FILES['timetable_file']['size'];
    $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $allowedExts = ['pdf', 'docx', 'doc', 'xlsx', 'xls', 'csv'];
    if (!in_array($ext, $allowedExts)) {
        $errors[] = 'Only PDF, Word (.docx), or Excel (.xlsx) files can be attached.';
    } else {
        // Try Cloudinary upload first
        $cloudinaryUrl = null;
        if (file_exists('cloudinary_helper.php')) {
            require_once 'cloudinary_helper.php';
            $cRes = cloudinary_upload_file($fileTmp, 'timetables', 'raw');
            if ($cRes['success']) {
                $cloudinaryUrl = $cRes['url'];
            }
        }

        if ($cloudinaryUrl) {
            $filePath = $cloudinaryUrl;
        } else {
            // Local storage fallback
            $uploadDir = __DIR__ . '/uploads/timetables/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $uniqueName = 'timetable_' . time() . '_' . uniqid() . '.' . $ext;
            $destPath   = $uploadDir . $uniqueName;
            if (move_uploaded_file($fileTmp, $destPath)) {
                $filePath = 'uploads/timetables/' . $uniqueName;
            } else {
                $errors[] = 'Failed to save uploaded timetable document on server.';
            }
        }
    }
}

if (!empty($errors)) {
    $_SESSION['admin_flash'] = ['type' => 'error', 'message' => implode(' ', $errors)];
    header('Location: admin_dashboard.php');
    exit;
}

// Insert timetable into database
$sql = "INSERT INTO class_timetables (title, class_level, stream, schedule_json, file_name, file_path, file_size, uploaded_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = thamani_db_prepare($conn, $sql);

if ($stmt) {
    thamani_db_stmt_bind_param($stmt, "ssssssii", $title, $classLevel, $stream, $scheduleJson, $fileName, $filePath, $fileSize, $adminId);
    $res = thamani_db_stmt_execute($stmt);
    thamani_db_stmt_close($stmt);

    if ($res) {
        $_SESSION['admin_flash'] = [
            'type' => 'success',
            'message' => "Timetable \"{$title}\" for {$classLevel} ({$stream}) published successfully!"
        ];
    } else {
        $_SESSION['admin_flash'] = [
            'type' => 'error',
            'message' => "Failed to save timetable: " . thamani_db_error($conn)
        ];
    }
} else {
    $_SESSION['admin_flash'] = [
        'type' => 'error',
        'message' => "Database prepare error: " . thamani_db_error($conn)
    ];
}

header('Location: admin_dashboard.php');
exit;
