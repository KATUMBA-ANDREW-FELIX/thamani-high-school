<?php
/**
 * Thamani High School - Digital Library (public view)
 * ------------------------------------------------
 * - Fetches all active library resources from the database
 * - Passes them as JSON to library.html for rendering
 * - Shows an "Upload" button + modal only if a teacher is logged in
 */

session_start();

// Teacher session check (lightweight — do NOT force login on this page)
$isAdmin       = !empty($_SESSION['admin_id']);
$isTeacher     = !empty($_SESSION['teacher_id']);
$currentUserId = (int)($_SESSION['teacher_id'] ?? $_SESSION['admin_id'] ?? 0);

require_once 'conn.php';

// ---------- Helpers ----------
function formatFileSize(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1)    . ' KB';
    return $bytes . ' B';
}

// ---------- Fetch resources ----------
$books = [];
$subjects = [];
$dbError = '';

$sql = "SELECT id, title, author, subject, category, class_level,
               file_name, file_path, file_size, uploaded_at, uploaded_by
        FROM library_resources
        WHERE is_active = 1
        ORDER BY uploaded_at DESC";

$res = mysqli_query($conn, $sql);

if ($res === false) {
    error_log('[Library Fetch] ' . mysqli_error($conn));
    $dbError = 'Could not load library resources right now.';
} else {
    while ($row = mysqli_fetch_assoc($res)) {
        // Map DB row → the JSON shape the JS expects
        $ext = strtoupper(pathinfo($row['file_name'], PATHINFO_EXTENSION));

        // Pick icon based on category
        $icon = 'file-text';
        if ($row['category'] === 'textbook')     $icon = 'book-open';
        if ($row['category'] === 'revision')     $icon = 'pencil-ruler';
        if ($row['category'] === 'other')        $icon = 'file';

        $books[] = [
            'id'          => (int)$row['id'],
            'kind'        => $row['category'],
            'title'       => $row['title'],
            'author'      => $row['author'] ?: '',
            'subject'     => $row['subject'],
            'level'       => $row['class_level'] ?: 'All Levels',
            'format'      => $ext ?: 'FILE',
            'size'        => formatFileSize((int)$row['file_size']),
            'downloadUrl' => $row['file_path'],
            'icon'        => $icon,
            'uploadedBy'  => (int)($row['uploaded_by'] ?? 0),
        ];
    }

    // Distinct subject list for the filter pills
    $subsRes = mysqli_query($conn, "SELECT DISTINCT subject FROM library_resources WHERE is_active = 1 ORDER BY subject ASC");
    if ($subsRes) {
        while ($s = mysqli_fetch_assoc($subsRes)) {
            if ($s['subject'] !== '') $subjects[] = $s['subject'];
        }
    }
}

// ---------- Encode for JS ----------
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

// ---------- Inject data as a global JS var ----------
$dataPayload = json_encode([
    'books'         => $books,
    'subjects'      => $subjects,
    'isTeacher'     => $isTeacher,
    'isAdmin'       => $isAdmin,
    'currentUserId' => $currentUserId,
], $jsonFlags);

$dataScript = '<script>window.__LIBRARY_DATA = ' . $dataPayload . ';</script>';

// ---------- Flash message (from upload_book.php redirect) ----------
$flashHtml = '';
if (!empty($_SESSION['library_flash'])) {
    $flash = $_SESSION['library_flash'];
    unset($_SESSION['library_flash']);

    $colorMap = [
        'success' => ['bg-green-50',  'border-green-200',  'text-green-800'],
        'error'   => ['bg-red-50',    'border-red-200',    'text-red-800'],
        'info'    => ['bg-blue-50',   'border-blue-200',   'text-blue-800'],
    ];
    [$bg, $border, $text] = $colorMap[$flash['type']] ?? $colorMap['info'];

    $safeMsg = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
    $flashHtml = <<<HTML
        <div class="mb-6 {$bg} border {$border} {$text} px-5 py-4 rounded-xl text-sm font-semibold flex items-start gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
            <span>{$safeMsg}</span>
        </div>
    HTML;
}

// If DB error, show it as a flash too
if ($dbError !== '') {
    $flashHtml .= '<div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-5 py-4 rounded-xl text-sm font-semibold">'
                . htmlspecialchars($dbError, ENT_QUOTES)
                . '</div>';
}

// ---------- Render template ----------
ob_start();
require './library.html';
$html = ob_get_clean();

$html = str_replace('<!-- LIBRARY_FLASH -->', $flashHtml, $html);
$html = str_replace('<!-- LIBRARY_DATA_SCRIPT -->', $dataScript, $html);

echo $html;
