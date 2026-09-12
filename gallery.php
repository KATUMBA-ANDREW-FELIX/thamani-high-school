<?php
/**
 * Thamani High School - Gallery (Public View)
 * ----------------------------------------
 * - Displays all uploaded photos
 * - Admin can upload new photos from this page
 * - Category filter + lightbox preview
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

// Fetch photos
$photos = [];
$dbError = '';

$sql = "SELECT id, title, caption, category, file_path, uploaded_at
        FROM gallery_photos
        WHERE is_active = 1
        ORDER BY uploaded_at DESC";

$res = mysqli_query($conn, $sql);
if ($res === false) {
    error_log('[Gallery Fetch] ' . mysqli_error($conn));
    $dbError = 'Could not load gallery photos.';
} else {
    while ($row = mysqli_fetch_assoc($res)) $photos[] = $row;
}

$flashHtml = '';
if (!empty($_SESSION['gallery_flash'])) {
    $flash = $_SESSION['gallery_flash'];
    unset($_SESSION['gallery_flash']);
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

$photosPayload = json_encode(array_map(function($p) {
    return [
        'id'       => (int)$p['id'],
        'title'    => $p['title'],
        'caption'  => $p['caption'] ?? '',
        'category' => $p['category'],
        'url'      => $p['file_path'],
        'uploaded' => date('d M Y', strtotime($p['uploaded_at'])),
    ];
}, $photos), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery - THAMANI HIGH SCHOOL - Kakiri</title>
    <link rel="icon" type="image/ico" href="favicon.ico" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { brand: {
                green: '#1A472A', maroon: '#800000', gold: '#D4AF37',
                lightGreen: '#E8F5E9', darkGreen: '#0F2D1A', lightMaroon: '#FDF2F2'
            }}}}
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .nav-link-active { background-color: #1A472A; color: #ffffff !important; }
        #page-loader {
            position: fixed; inset: 0; background: #1A472A;
            display: flex; align-items: center; justify-content: center;
            z-index: 9999; transition: opacity .5s ease, visibility .5s ease;
        }
        #page-loader.hidden { opacity: 0; visibility: hidden; pointer-events: none; display: none !important; }
        .filter-pill.active { background-color: #1A472A !important; color: #fff !important; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
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
                    <a href="gallery.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium transition-colors text-white bg-brand-green">Gallery</a>
                    <a href="calendar.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-brand-green">Calendar & Fees</a>
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
            <a href="gallery.php" class="block px-3 py-2 rounded-md text-base font-medium text-white bg-brand-green">Gallery</a>
            <a href="calendar.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Calendar & Fees</a>
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

    <main class="flex-grow">
        <section class="py-12 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <?= $flashHtml ?>

                <div class="text-center mb-12">
                    <span class="inline-block px-4 py-1.5 rounded-full bg-brand-lightGreen text-brand-green font-bold text-xs uppercase tracking-wider mb-4">Vibrant Campus Life</span>
                    <h1 class="text-4xl font-bold text-brand-green mb-4">School Gallery</h1>
                    <p class="text-gray-600 max-w-2xl mx-auto">A visual journey through the cultural heritage, sporting excellence, and daily life at Thamani High School.</p>

                    <?php if ($isAdmin): ?>
                        <button onclick="openModal('modal-upload-gallery');" class="mt-6 inline-flex items-center gap-2 px-6 py-3 bg-brand-maroon text-white font-bold rounded-lg hover:bg-red-900 transition-all shadow-md hover:shadow-lg">
                            <i data-lucide="image-plus" class="w-5 h-5"></i> Upload Photo
                        </button>
                    <?php endif; ?>
                </div>

                <div id="category-filters" class="flex overflow-x-auto pb-3 gap-2 no-scrollbar mb-10 border-b border-gray-200 justify-center flex-wrap"></div>

                <div class="mb-6 flex items-center justify-between">
                    <p id="photo-count" class="text-sm text-gray-500 font-medium"></p>
                    <span class="bg-brand-lightGreen text-brand-green font-bold px-4 py-1.5 rounded-full text-xs">2026 Collection</span>
                </div>

                <div id="gallery-grid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"></div>

                <div id="gallery-empty" class="hidden text-center py-20">
                    <i data-lucide="image-off" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-700 mb-2">No photos yet</h3>
                    <p class="text-gray-500 text-sm">Check back soon for updates from campus life.</p>
                </div>

            </div>
        </section>
    </main>

    <!-- ADMIN UPLOAD MODAL -->
    <?php if ($isAdmin): ?>
    <div id="modal-upload-gallery" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-xl font-bold text-brand-maroon flex items-center gap-2">
                    <i data-lucide="image-plus" class="w-6 h-6"></i> Upload Gallery Photo
                </h3>
                <button onclick="closeModal('modal-upload-gallery');" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <form action="admin_upload_gallery.php" method="post" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Photo Title <span class="text-brand-maroon">*</span></label>
                    <input type="text" name="title" required maxlength="255" placeholder="e.g. Cultural Gala 2026" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Category <span class="text-brand-maroon">*</span></label>
                    <select name="category" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:outline-none">
                        <option value="cultural">Cultural</option>
                        <option value="sports">Sports</option>
                        <option value="campus">Campus Life</option>
                        <option value="academic">Academic</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Caption <span class="text-gray-400 font-normal normal-case">(optional)</span></label>
                    <textarea name="caption" rows="2" maxlength="500" placeholder="Short caption..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-maroon focus:outline-none resize-none"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Image File <span class="text-brand-maroon">*</span></label>
                    <input type="file" name="photo_file" required accept="image/jpeg,image/png,image/webp,image/gif" class="w-full text-xs text-gray-600 border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-brand-maroon">
                    <p class="text-[11px] text-gray-500 mt-1">Allowed: JPG, PNG, WEBP, GIF. Max: 8 MB.</p>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 py-3 bg-brand-maroon text-white font-bold rounded-lg hover:bg-red-900 text-sm flex items-center justify-center gap-2">
                        <i data-lucide="upload" class="w-4 h-4"></i> Upload Photo
                    </button>
                    <button type="button" onclick="closeModal('modal-upload-gallery');" class="w-32 py-3 border-2 border-gray-200 text-gray-600 font-bold rounded-lg hover:bg-gray-50 text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- LIGHTBOX -->
    <div id="lightbox" class="hidden fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4">
        <button onclick="closeLightbox()" class="absolute top-4 right-4 p-3 rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>
        <div class="max-w-5xl w-full max-h-full flex flex-col items-center gap-4">
            <img id="lightbox-img" src="" alt="Preview" class="max-h-[80vh] w-auto rounded-xl shadow-2xl object-contain">
            <div class="text-center">
                <h3 id="lightbox-title" class="text-white text-xl font-bold"></h3>
                <p id="lightbox-caption" class="text-gray-300 text-sm mt-1 max-w-xl mx-auto"></p>
            </div>
        </div>
    </div>

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
                        <i data-lucide="facebook" class="w-6 h-6 cursor-pointer hover:text-white transition-colors"></i>
                        <i data-lucide="twitter" class="w-6 h-6 cursor-pointer hover:text-white transition-colors"></i>
                        <i data-lucide="instagram" class="w-6 h-6 cursor-pointer hover:text-white transition-colors"></i>
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
        const PHOTOS = <?= $photosPayload ?: '[]' ?>;

        const CATEGORY_LABELS = {
            all:      { label: 'All Photos', icon: 'layout-grid' },
            cultural: { label: 'Cultural',   icon: 'music' },
            sports:   { label: 'Sports',     icon: 'trophy' },
            campus:   { label: 'Campus Life', icon: 'building' },
            academic: { label: 'Academic',   icon: 'book-open' },
            other:    { label: 'Other',      icon: 'image' }
        };

        let activeCategory = 'all';

        function renderFilters() {
            const wrap = document.getElementById('category-filters');
            if (!wrap) return;
            const present = new Set(PHOTOS.map(p => p.category));

            wrap.innerHTML = Object.entries(CATEGORY_LABELS)
                .filter(([k]) => k === 'all' || present.has(k))
                .map(([key, cfg]) => {
                    const isActive = activeCategory === key;
                    return `
                        <button onclick="setCategory('${key}')"
                                class="filter-pill px-5 py-2 rounded-full font-medium whitespace-nowrap transition-colors flex items-center gap-2 ${isActive ? 'active text-white' : 'bg-white text-gray-600 hover:bg-gray-100'}">
                            <i data-lucide="${cfg.icon}" class="w-4 h-4"></i> ${cfg.label}
                        </button>`;
                }).join('');
            lucide.createIcons();
        }

        function renderPhotos() {
            const grid  = document.getElementById('gallery-grid');
            const empty = document.getElementById('gallery-empty');
            const count = document.getElementById('photo-count');

            const list = PHOTOS.filter(p => activeCategory === 'all' || p.category === activeCategory);
            count.textContent = `Showing ${list.length} photo${list.length === 1 ? '' : 's'}`;

            if (list.length === 0) {
                grid.innerHTML = '';
                empty.classList.remove('hidden');
                return;
            }
            empty.classList.add('hidden');

            grid.innerHTML = list.map(p => `
                <div onclick='openLightbox(${JSON.stringify(p).replace(/'/g, "&#39;")})'
                     class="group cursor-pointer bg-white rounded-xl overflow-hidden border border-gray-100 hover:shadow-lg transition-all">
                    <div class="relative aspect-square overflow-hidden bg-gray-100">
                        <img src="${p.url}" alt="${p.title.replace(/"/g, '&quot;')}"
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" loading="lazy">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-4">
                            <div class="text-white">
                                <div class="font-bold text-sm">${p.title}</div>
                                <div class="text-xs text-gray-200 uppercase">${p.category}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
            lucide.createIcons();
        }

        function setCategory(c) { activeCategory = c; renderFilters(); renderPhotos(); }

        function openModal(id)  { document.getElementById(id)?.classList.remove('hidden'); }
        function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }

        function openLightbox(photo) {
            document.getElementById('lightbox-img').src = photo.url;
            document.getElementById('lightbox-title').textContent = photo.title;
            document.getElementById('lightbox-caption').textContent = photo.caption || '';
            document.getElementById('lightbox').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            document.getElementById('lightbox').classList.add('hidden');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeLightbox();
        });
        document.getElementById('lightbox')?.addEventListener('click', e => {
            if (e.target.id === 'lightbox') closeLightbox();
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
        renderPhotos();
    </script>
</body>
</html>