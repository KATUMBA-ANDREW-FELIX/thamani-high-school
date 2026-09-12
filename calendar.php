<?php
/**
 * Thamani High School - Academic Calendar & Fees (Public View)
 * --------------------------------------------------------
 * - Displays all uploaded calendar, fees, timetable documents
 * - Admin gets an upload button + modal on this page
 * - Teachers can view only
 */

session_start();
require_once 'conn.php';

$isAdmin   = !empty($_SESSION['admin_id']);
$isTeacher = !empty($_SESSION['teacher_id']);

if ($isAdmin) {
    $backLink   = 'admin_dashboard.php';
    $logoutLink = 'admin_logout.php';
    $viewerName = $_SESSION['admin_name'] ?? 'Admin';
} elseif ($isTeacher) {
    $backLink   = 'teacher_dashboard.php';
    $logoutLink = 'teacher_logout.php';
    $viewerName = $_SESSION['teacher_name'] ?? 'Teacher';
} else {
    $backLink   = 'index.php';
    $logoutLink = null;
    $viewerName = null;
}

// ---------- Fetch documents ----------
$documents = [];
$dbError   = '';

$sql = "SELECT id, title, doc_type, description, file_name, file_path, file_size, mime_type, uploaded_at, uploaded_by
        FROM calendar_documents
        WHERE is_active = 1
        ORDER BY uploaded_at DESC";

$res = mysqli_query($conn, $sql);
if ($res === false) {
    error_log('[Calendar Fetch] ' . mysqli_error($conn));
    $dbError = 'Could not load calendar documents right now.';
} else {
    while ($row = mysqli_fetch_assoc($res)) $documents[] = $row;
}

function formatFileSize(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

// ---------- Flash ----------
$flashHtml = '';
if (!empty($_SESSION['calendar_flash'])) {
    $flash = $_SESSION['calendar_flash'];
    unset($_SESSION['calendar_flash']);
    $colorMap = [
        'success' => ['bg-green-50', 'border-green-200', 'text-green-800'],
        'error'   => ['bg-red-50',   'border-red-200',   'text-red-800'],
        'info'    => ['bg-blue-50',  'border-blue-200',  'text-blue-800'],
    ];
    [$bg, $border, $text] = $colorMap[$flash['type']] ?? $colorMap['info'];
    $safeMsg = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
    $flashHtml = <<<HTML
        <div class="mb-6 {$bg} border {$border} {$text} px-5 py-4 rounded-xl text-sm font-semibold flex items-start gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <span>{$safeMsg}</span>
        </div>
    HTML;
}

// ---------- JSON payload for JS ----------
$docsPayload = json_encode(array_map(function($d) {
    return [
        'id'          => (int)$d['id'],
        'title'       => $d['title'],
        'doc_type'    => $d['doc_type'],
        'description' => $d['description'] ?? '',
        'fileName'    => $d['file_name'],
        'url'         => $d['file_path'],
        'size'        => formatFileSize((int)$d['file_size']),
        'uploaded'    => date('d M Y', strtotime($d['uploaded_at'])),
        'uploadedBy'  => (int)($d['uploaded_by'] ?? 0),
    ];
}, $documents), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar & Fees - THAMANI HIGH SCHOOL - Kakiri</title>
    <link rel="preconnect" href="https://res.cloudinary.com" crossorigin>
    <link rel="dns-prefetch" href="https://res.cloudinary.com">
    <link rel="preconnect" href="https://unpkg.com" crossorigin>
    <link rel="icon" type="image/ico" href="favicon.ico" />
    <link rel="stylesheet" href="css/tailwind.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .nav-link-active { background-color: #1F2937; color: #ffffff !important; }
        #page-loader {
            position: fixed; inset: 0; background: #1F2937;
            display: flex; align-items: center; justify-content: center;
            z-index: 9999; transition: opacity .5s ease, visibility .5s ease;
        }
        #page-loader.hidden { opacity: 0; visibility: hidden; pointer-events: none; display: none !important; }
        .filter-pill.active { background-color: #1F2937 !important; color: #D4AF37 !important; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans flex flex-col min-h-screen">

    <div id="page-loader">
        <div class="text-center">
            <img src="thamani-logo.png" alt="Thamani High School"
                 class="h-20 w-auto mx-auto mb-4 animate-pulse" onerror="this.style.display='none'">
            <div class="w-12 h-12 border-4 border-brand-gold border-t-transparent rounded-full animate-spin mx-auto"></div>
        </div>
    </div>
    <script>
        setTimeout(function() {
            var l = document.getElementById('page-loader');
            if (l) { l.classList.add('hidden'); l.style.display = 'none'; }
        }, 50);
    </script>

    <div class="bg-brand-maroon text-white text-xs py-2 px-4 text-center font-medium">
        <div class="max-w-7xl mx-auto w-full flex justify-between items-center">
            <span>📍 THAMANI HIGH SCHOOL - Kakiri Main Campus, Wakiso District, Uganda</span>
            <span class="hidden sm:inline">📞 Enquiries: +256 414 123 456 | ✉️ info@thamani.ac.ug</span>
            <span class="bg-brand-gold text-brand-green px-2.5 py-0.5 rounded font-bold uppercase tracking-wider text-[10px]">Term III 2026 Active</span>
        </div>
    </div>

    <!-- Nav -->
    <nav class="sticky top-0 z-50 bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <a href="<?= $backLink ?>" class="flex items-center gap-3">
                    <img class="h-12 w-auto" src="thamani-logo.png" alt="Logo" onerror="this.src='favicon.svg'">
                    <div class="flex flex-col">
                        <span class="text-2xl font-bold tracking-tight text-brand-green">Thamani High School</span>
                        <span class="text-[10px] font-semibold text-brand-maroon tracking-widest uppercase"><?= $isAdmin ? 'Admin Control Panel' : ($isTeacher ? 'Teacher Portal' : 'Kakiri - Uganda') ?></span>
                    </div>
                </a>
                <div class="hidden lg:flex items-center space-x-2">
                    <a href="index.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-brand-green">Home</a>
                    <a href="enrollment.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-brand-green">Enrollment</a>
                    <a href="library.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-brand-green">Library</a>
                    <a href="gallery.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-brand-green">Gallery</a>
                    <a href="calendar.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium transition-colors text-white bg-brand-green">Calendar & Fees</a>
                    <a href="alumni.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-brand-green">Alumni</a>
                    <a href="map.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-brand-green">Campus Map</a>
                </div>
                <div class="hidden md:flex items-center space-x-3">
                    <?php if ($viewerName): ?>
                        <span class="text-sm text-gray-600 hidden lg:inline">
                            Signed in as <strong class="<?= $isAdmin ? 'text-brand-maroon' : 'text-brand-green' ?>"><?= htmlspecialchars($viewerName) ?></strong>
                        </span>
                        <a href="<?= $backLink ?>"
                           class="px-4 py-2 rounded-md text-sm font-bold text-brand-green bg-brand-lightGreen hover:bg-brand-green hover:text-white transition-colors flex items-center gap-1.5">
                            <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                        </a>
                        <a href="<?= $logoutLink ?>"
                           class="px-4 py-2 rounded-md text-sm font-bold text-white bg-brand-maroon hover:bg-red-900 transition-colors flex items-center gap-1.5">
                            <i data-lucide="log-out" class="w-4 h-4"></i> Logout
                        </a>
                    <?php else: ?>
                        <a href="teacher-login.php" class="px-4 py-2 rounded-md text-sm font-bold text-white bg-brand-maroon transition-transform hover:scale-105 shadow flex items-center gap-1.5">
                            <i data-lucide="graduation-cap" class="w-4 h-4"></i> Teacher Portal
                        </a>
                        <a href="teacher-login.php?role=admin" class="px-4 py-2 rounded-md text-sm font-bold text-brand-green bg-brand-gold transition-transform hover:scale-105 shadow flex items-center gap-1.5">
                            <i data-lucide="shield-check" class="w-4 h-4"></i> Admin Panel
                        </a>
                    <?php endif; ?>
                </div>
                <div class="lg:hidden flex items-center">
                    <button onclick="document.getElementById('mobile-menu').classList.toggle('hidden')"
                            class="inline-flex items-center justify-center p-2 rounded-md text-gray-700 hover:text-brand-green hover:bg-gray-100">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                </div>
            </div>
        </div>
        <div id="mobile-menu" class="hidden lg:hidden bg-white border-t border-gray-200 px-4 pt-2 pb-4 space-y-2">
            <a href="index.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Home</a>
            <a href="enrollment.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Enrollment</a>
            <a href="library.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Library</a>
            <a href="gallery.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Gallery</a>
            <a href="calendar.php" class="block px-3 py-2 rounded-md text-base font-medium text-white bg-brand-green">Calendar & Fees</a>
            <a href="alumni.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Alumni</a>
            <a href="map.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Campus Map</a>
            <div class="pt-2 border-t border-gray-100 flex flex-col gap-2">
                <?php if ($viewerName): ?>
                    <a href="<?= $backLink ?>" class="w-full py-2.5 rounded-md font-bold text-brand-green bg-brand-lightGreen text-center">Dashboard</a>
                    <a href="<?= $logoutLink ?>" class="w-full py-2.5 rounded-md font-bold text-white bg-brand-maroon text-center">Logout</a>
                <?php else: ?>
                    <a href="teacher-login.php" class="w-full py-2.5 rounded-md font-bold text-white bg-brand-maroon text-center">Teacher Portal</a>
                    <a href="teacher-login.php?role=admin" class="w-full py-2.5 rounded-md font-bold text-brand-green bg-brand-gold text-center">Admin Panel</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main -->
    <main class="flex-grow">
        <section class="py-12 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <?= $flashHtml ?>

                <!-- Page Title -->
                <div class="text-center mb-12">
                    <span class="inline-block px-4 py-1.5 rounded-full bg-brand-lightGreen text-brand-green font-bold text-xs uppercase tracking-wider mb-4">Official Documents</span>
                    <h1 class="text-4xl font-bold text-brand-green mb-4">Academic Calendar & Fee Structure</h1>
                    <p class="text-gray-600 max-w-2xl mx-auto">Official term dates, visitation days, UNEB examination schedules, and tuition structures for Thamani High School.</p>

                    <?php if ($isAdmin): ?>
                        <button onclick="openModal('modal-upload-calendar');" class="mt-6 inline-flex items-center gap-2 px-6 py-3 bg-brand-maroon text-white font-bold rounded-lg hover:bg-red-900 transition-all shadow-md hover:shadow-lg">
                            <i data-lucide="upload-cloud" class="w-5 h-5"></i> Upload Document
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Filter Tabs -->
                <div id="type-filters" class="flex overflow-x-auto pb-3 gap-2 no-scrollbar mb-10 border-b border-gray-200"></div>

                <!-- Count -->
                <div class="mb-6 flex items-center justify-between">
                    <p id="doc-count" class="text-sm text-gray-500 font-medium"></p>
                    <span class="bg-brand-lightGreen text-brand-green font-bold px-4 py-1.5 rounded-full text-xs">Updated 2026</span>
                </div>

                <!-- Grid -->
                <div id="documents-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"></div>

                <!-- Empty -->
                <div id="documents-empty" class="hidden text-center py-20">
                    <i data-lucide="calendar-x" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-700 mb-2">No documents published yet</h3>
                    <p class="text-gray-500 text-sm">Check back soon — the school administration publishes updates here.</p>
                </div>

            </div>
        </section>
    </main>

    <!-- ADMIN UPLOAD MODAL -->
    <?php if ($isAdmin): ?>
    <div id="modal-upload-calendar" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-xl font-bold text-brand-maroon flex items-center gap-2">
                    <i data-lucide="calendar-plus" class="w-6 h-6"></i> Upload Calendar / Fees
                </h3>
                <button onclick="closeModal('modal-upload-calendar');" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <form action="admin_upload_calendar.php" method="post" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Document Title <span class="text-brand-maroon">*</span></label>
                    <input type="text" name="title" required maxlength="255" placeholder="e.g. Term III 2026 Fee Structure" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Type <span class="text-brand-maroon">*</span></label>
                    <select name="doc_type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:outline-none">
                        <option value="calendar">Academic Calendar</option>
                        <option value="fees">Fee Structure</option>
                        <option value="timetable">Timetable</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Description <span class="text-gray-400 font-normal normal-case">(optional)</span></label>
                    <textarea name="description" rows="2" maxlength="500" placeholder="Brief description..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:outline-none resize-none"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">File <span class="text-brand-maroon">*</span></label>
                    <input type="file" name="doc_file" required accept=".pdf,.doc,.docx,.xls,.xlsx" class="w-full text-xs text-gray-600 border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-brand-maroon">
                    <p class="text-[11px] text-gray-500 mt-1">Allowed: PDF, DOC, DOCX, XLS, XLSX. Max: 20 MB.</p>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 py-3 bg-brand-maroon text-white font-bold rounded-lg hover:bg-red-900 text-sm flex items-center justify-center gap-2">
                        <i data-lucide="upload" class="w-4 h-4"></i> Upload
                    </button>
                    <button type="button" onclick="closeModal('modal-upload-calendar');" class="w-32 py-3 border-2 border-gray-200 text-gray-600 font-bold rounded-lg hover:bg-gray-50 text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- PREVIEW MODAL -->
    <div id="modal-preview" class="hidden fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-2 sm:p-4">
        <div class="bg-white rounded-2xl w-full max-w-5xl shadow-2xl overflow-hidden flex flex-col">
            <div class="flex justify-between items-center px-4 py-2.5 border-b border-gray-200 bg-white flex-shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-brand-green/10 text-brand-green flex-shrink-0">
                        <i data-lucide="file-text" class="w-5 h-5"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 id="preview-title" class="text-base font-bold text-brand-green truncate">Document Preview</h3>
                        <p id="preview-subtitle" class="text-xs text-gray-500 truncate">Loading…</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a id="preview-download" href="#" download
                       class="hidden sm:inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold text-white bg-brand-green hover:bg-brand-darkGreen transition-colors">
                        <i data-lucide="download" class="w-4 h-4"></i> Download
                    </a>
                    <a id="preview-open-new" href="#" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold text-brand-green bg-brand-lightGreen hover:bg-brand-green hover:text-white transition-colors">
                        <i data-lucide="external-link" class="w-4 h-4"></i> <span class="hidden sm:inline">Open</span>
                    </a>
                    <button onclick="closePreview()" class="p-2 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div id="preview-body" class="relative bg-gray-100 w-full" style="height: 78vh; max-height: 800px;">
                <iframe id="preview-frame" src="" class="absolute inset-0 w-full h-full border-0 hidden" title="Document Preview"></iframe>
                <div id="preview-loader" class="absolute inset-0 flex items-center justify-center bg-gray-100">
                    <div class="text-center">
                        <div class="w-12 h-12 border-4 border-brand-green border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                        <p class="text-gray-500 text-sm font-medium">Loading preview…</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-brand-green text-white pt-16 pb-10 mt-auto border-t-4 border-brand-gold">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-10 mb-12">
                <div>
                    <h3 class="text-2xl font-bold mb-4 flex items-center gap-2">
                        <img src="thamani-logo.png" class="h-10 w-auto" alt="Logo" onerror="this.src='favicon.svg'">
                        <span>Thamani High School</span>
                    </h3>
                    <p class="text-gray-300 text-sm leading-relaxed mb-6">Empowering the next generation of Ugandan leaders through excellence in education, culture, and character building.</p>
                    <div class="flex space-x-5 text-brand-gold">
                        <svg class="w-6 h-6 cursor-pointer hover:text-white transition-colors fill-current" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        <svg class="w-6 h-6 cursor-pointer hover:text-white transition-colors fill-current" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        <svg class="w-6 h-6 cursor-pointer hover:text-white transition-colors fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </div>
                </div>
                <div>
                    <h3 class="text-2xl font-bold mb-4">Quick Links</h3>
                    <ul class="space-y-3 text-gray-300 text-sm">
                        <li><a href="enrollment.php" class="hover:text-brand-gold transition-colors">Student Enrollment</a></li>
                        <li><a href="library.php" class="hover:text-brand-gold transition-colors">Digital Library</a></li>
                        <li><a href="calendar.php" class="hover:text-brand-gold transition-colors">Academic Calendar</a></li>
                        <li><a href="alumni.php" class="hover:text-brand-gold transition-colors">Alumni Network</a></li>
                        <li><a href="teacher-login.php" class="hover:text-brand-gold transition-colors">Teacher Portal</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-2xl font-bold mb-4">Contact Us</h3>
                    <ul class="space-y-4 text-gray-300 text-sm">
                        <li class="flex items-center gap-3"><i data-lucide="map-pin" class="w-5 h-5 text-brand-gold"></i><span>Plot 45, Education Road, Kampala, Uganda</span></li>
                        <li class="flex items-center gap-3"><i data-lucide="phone" class="w-5 h-5 text-brand-gold"></i><span>+256 414 123 456</span></li>
                        <li class="flex items-center gap-3"><i data-lucide="mail" class="w-5 h-5 text-brand-gold"></i><span>info@thamani.ac.ug</span></li>
                    </ul>
                </div>
            </div>
            <div class="pt-8 border-t border-brand-darkGreen text-center text-gray-400 text-sm flex justify-between items-center flex-wrap gap-2">
                <span>© 2026 Thamani High School. All rights reserved.</span>
                <span><?= $viewerName ? 'Signed in as ' . htmlspecialchars($viewerName) : 'Public View' ?></span>
            </div>
        </div>
    </footer>

    <script>
        const DOCUMENTS = <?= $docsPayload ?: '[]' ?>;
        const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
        const CURRENT_USER_ID = <?= (int)($_SESSION['teacher_id'] ?? $_SESSION['admin_id'] ?? 0) ?>;

        const TYPE_LABELS = {
            all:       { label: 'All Documents', icon: 'layout-grid' },
            calendar:  { label: 'Academic Calendar', icon: 'calendar-days' },
            fees:      { label: 'Fee Structure', icon: 'receipt' },
            timetable: { label: 'Timetable', icon: 'clock' },
            other:     { label: 'Other', icon: 'file-text' }
        };

        const PREVIEWABLE = ['PDF'];
        let activeType = 'all';

        function renderFilters() {
            const wrap = document.getElementById('type-filters');
            if (!wrap) return;
            const present = new Set(DOCUMENTS.map(d => d.doc_type));

            wrap.innerHTML = Object.entries(TYPE_LABELS)
                .filter(([k]) => k === 'all' || present.has(k))
                .map(([key, cfg]) => {
                    const isActive = activeType === key;
                    return `
                        <button onclick="setType('${key}')"
                                class="filter-pill px-6 py-2 rounded-full font-medium whitespace-nowrap transition-colors flex items-center gap-2 ${isActive ? 'active text-white' : 'bg-white text-gray-600 hover:bg-gray-100'}">
                            <i data-lucide="${cfg.icon}" class="w-4 h-4"></i> ${cfg.label}
                        </button>`;
                }).join('');
            lucide.createIcons();
        }

        function renderDocuments() {
            const grid  = document.getElementById('documents-grid');
            const empty = document.getElementById('documents-empty');
            const count = document.getElementById('doc-count');

            const list = DOCUMENTS.filter(d => activeType === 'all' || d.doc_type === activeType);
            count.textContent = `Showing ${list.length} document${list.length === 1 ? '' : 's'}`;

            if (list.length === 0) {
                grid.innerHTML = '';
                empty.classList.remove('hidden');
                return;
            }
            empty.classList.add('hidden');

            grid.innerHTML = list.map(d => {
                const ext = (d.fileName.split('.').pop() || 'FILE').toUpperCase();
                const iconMap = {
                    calendar: 'calendar-days',
                    fees: 'receipt',
                    timetable: 'clock',
                    other: 'file-text'
                };
                const icon = iconMap[d.doc_type] || 'file-text';
                const extClass = ext === 'PDF' ? 'bg-red-50 text-red-700' :
                                 (ext === 'XLSX' || ext === 'XLS') ? 'bg-green-50 text-green-700' :
                                 (ext === 'DOCX' || ext === 'DOC') ? 'bg-blue-50 text-blue-700' :
                                 'bg-gray-100 text-gray-600';

                const canDelete = IS_ADMIN || (CURRENT_USER_ID > 0 && d.uploadedBy === CURRENT_USER_ID);

                return `
                    <div class="bg-white p-6 rounded-2xl shadow-sm hover:shadow-md transition-all border border-gray-100 group flex flex-col">
                        <div class="flex items-start justify-between mb-4">
                            <div class="p-3 rounded-xl bg-brand-green/10 group-hover:bg-brand-green/20 transition-colors">
                                <i data-lucide="${icon}" class="w-8 h-8 text-brand-green"></i>
                            </div>
                            <span class="text-xs font-bold px-2 py-1 rounded ${extClass} uppercase">${ext}</span>
                        </div>
                        <h3 class="text-lg font-bold mb-2 text-gray-900 group-hover:text-brand-green transition-colors leading-snug">${d.title}</h3>
                        ${d.description ? `<p class="text-xs text-gray-500 mb-3 line-clamp-2">${d.description}</p>` : ''}
                        <div class="flex items-center gap-3 text-xs text-gray-500 mb-4">
                            <span class="uppercase font-bold text-brand-maroon">${d.doc_type}</span>
                            <span>·</span>
                            <span>${d.size}</span>
                            <span>·</span>
                            <span>${d.uploaded}</span>
                        </div>
                        <div class="mt-auto flex gap-2">
                            <button onclick="openPreview('${d.url}', '${d.title.replace(/'/g, "\\'")}', '${ext}')"
                                    class="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl font-bold text-brand-green bg-brand-lightGreen hover:bg-brand-green hover:text-white transition-all active:scale-95">
                                <i data-lucide="eye" class="w-5 h-5"></i> Preview
                            </button>
                            <a href="${d.url}" download
                               class="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl font-bold text-white bg-brand-green hover:bg-brand-darkGreen transition-all active:scale-95">
                                <i data-lucide="download" class="w-5 h-5"></i> Download
                            </a>
                            ${canDelete ? `
                                <form action="delete_item.php" method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete document &quot;${d.title.replace(/'/g, "\\'")}&quot;?');">
                                    <input type="hidden" name="type" value="calendar">
                                    <input type="hidden" name="id" value="${d.id}">
                                    <input type="hidden" name="redirect_to" value="calendar.php">
                                    <button type="submit" class="px-3.5 py-3 rounded-xl font-bold text-white bg-red-600 hover:bg-red-700 transition-all active:scale-95 shadow-sm flex items-center justify-center" title="Delete document">
                                        <i data-lucide="trash-2" class="w-5 h-5"></i>
                                    </button>
                                </form>
                            ` : ''}
                        </div>
                    </div>`;
            }).join('');
            lucide.createIcons();
        }

        function setType(t) { activeType = t; renderFilters(); renderDocuments(); }

        function openModal(id)  { document.getElementById(id)?.classList.remove('hidden'); }
        function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }

        function openPreview(url, title, fmt) {
            const modal = document.getElementById('modal-preview');
            const frame = document.getElementById('preview-frame');
            const loader = document.getElementById('preview-loader');
            if (!modal || !frame) return;

            document.getElementById('preview-title').textContent = title || 'Preview';
            document.getElementById('preview-subtitle').textContent = fmt + ' document';
            document.getElementById('preview-download').href = url;
            document.getElementById('preview-open-new').href = url;

            frame.classList.add('hidden');
            loader.classList.remove('hidden');
            frame.src = '';

            if (PREVIEWABLE.includes(fmt.toUpperCase())) {
                frame.src = url + '#toolbar=1&navpanes=0&view=FitH';
                frame.classList.remove('hidden');
                let done = false;
                const stop = () => { if (!done) { done = true; loader.classList.add('hidden'); } };
                frame.onload = stop;
                setTimeout(stop, 2500);
            } else {
                loader.classList.add('hidden');
                // Just open in new tab for non-previewable
                window.open(url, '_blank');
                modal.classList.add('hidden');
                return;
            }

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            lucide.createIcons();
        }

        function closePreview() {
            document.getElementById('modal-preview')?.classList.add('hidden');
            document.getElementById('preview-frame').src = '';
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closePreview();
        });

        lucide.createIcons();
        const hideLoader = () => document.getElementById('page-loader')?.classList.add('hidden');
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', hideLoader);
        } else {
            hideLoader();
        }
        setTimeout(hideLoader, 100);
        renderFilters();
        renderDocuments();
    </script>
</body>
</html>