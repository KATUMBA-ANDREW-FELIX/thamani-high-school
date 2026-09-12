<?php
/**
 * Thamani Academy - Alumni Registry (Teacher View)
 * -------------------------------------------------
 * - Teacher-only (requires session)
 * - Lists all registered alumni from the database
 * - Search filter by name, email, profession, or year
 * - Read-only (registration happens on alumni.php)
 */

require_once 'auth_teacher.php';
require_teacher_login();
require_password_changed();

$teacher = current_teacher();

// ---------- Search ----------
$search = trim($_GET['q'] ?? '');
$year   = trim($_GET['year'] ?? '');

// ---------- Fetch alumni from DB ----------
require_once 'conn.php';

$alumni     = [];
$totalCount = 0;
$dbError    = '';

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = "(name LIKE ? OR email LIKE ? OR profession LIKE ?)";
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}

if ($year !== '' && ctype_digit($year)) {
    $where[]  = "year = ?";
    $params[] = (int)$year;
    $types   .= 'i';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT id, name, year, profession, email
        FROM alumni
        $whereSql
        ORDER BY id DESC";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt === false) {
    error_log('[Alumni View Prepare] ' . mysqli_error($conn));
    $dbError = 'Could not load alumni records.';
} else {
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);

    if (function_exists('mysqli_stmt_get_result')) {
        $res = mysqli_stmt_get_result($stmt);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $alumni[] = $row;
            }
        }
    } else {
        // Fallback for servers without mysqlnd
        mysqli_stmt_bind_result($stmt, $id, $name, $yoc, $prof, $em);
        while (mysqli_stmt_fetch($stmt)) {
            $alumni[] = [
                'id'         => $id,
                'name'       => $name,
                'year'       => $yoc,
                'profession' => $prof,
                'email'      => $em,
            ];
        }
    }

    $totalCount = count($alumni);
    mysqli_stmt_close($stmt);
}

// Total count regardless of filter (for the header stat)
$totalAllRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM alumni");
$totalAllRow = $totalAllRes ? mysqli_fetch_assoc($totalAllRes) : ['c' => 0];
$totalAll    = (int)$totalAllRow['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Registry - THAMANI ACADEMY - Kakiri</title>
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
        #page-loader.hidden { opacity: 0; visibility: hidden; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans flex flex-col min-h-screen">

    <div id="page-loader">
        <div class="text-center">
            <img src="thamani-logo.png" alt="Thamani Academy"
                 class="h-20 w-auto mx-auto mb-4 animate-pulse" onerror="this.style.display='none'">
            <div class="w-12 h-12 border-4 border-brand-gold border-t-transparent rounded-full animate-spin mx-auto"></div>
        </div>
    </div>

    <!-- Top Announcement Bar -->
    <div class="bg-brand-maroon text-white text-xs py-2 px-4 text-center font-medium">
        <div class="max-w-7xl mx-auto w-full flex justify-between items-center">
            <span>📍 THAMANI ACADEMY - Kakiri Main Campus, Wakiso District, Uganda</span>
            <span class="hidden sm:inline">📞 Enquiries: +256 414 123 456 | ✉️ info@thamaniacademy.ac.ug</span>
            <span class="bg-brand-gold text-brand-green px-2.5 py-0.5 rounded font-bold uppercase tracking-wider text-[10px]">Term III 2026 Active</span>
        </div>
    </div>

    <!-- Main Navigation Bar -->
    <nav class="sticky top-0 z-50 bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <div class="flex items-center">
                    <a href="teacher_dashboard.php" class="flex-shrink-0 flex items-center gap-3">
                        <img class="h-12 w-auto" src="thamani-logo.png" alt="Thamani Academy Logo" onerror="this.src='favicon.svg'">
                        <div class="flex flex-col">
                            <span class="text-2xl font-bold tracking-tight text-brand-green">Thamani Academy</span>
                            <span class="text-[10px] font-semibold text-brand-maroon tracking-widest uppercase">Teacher Portal</span>
                        </div>
                    </a>
                </div>

                <!-- Desktop Nav: Alumni only (marked active on this page) -->
                <div class="hidden lg:flex items-center space-x-2">
                    <a href="alumni_view.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium transition-colors text-white bg-brand-green">Alumni</a>
                </div>

                <div class="hidden md:flex items-center space-x-3">
                    <span class="text-sm text-gray-600 hidden lg:inline">
                        Signed in as <strong class="text-brand-green"><?= htmlspecialchars($teacher['name']) ?></strong>
                    </span>
                    <a href="teacher_dashboard.php"
                       class="px-4 py-2 rounded-md text-sm font-bold text-brand-green bg-brand-lightGreen hover:bg-brand-green hover:text-white transition-colors flex items-center gap-1.5">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                    </a>
                    <a href="teacher_logout.php"
                       class="px-4 py-2 rounded-md text-sm font-bold text-white bg-brand-maroon hover:bg-red-900 transition-colors flex items-center gap-1.5">
                        <i data-lucide="log-out" class="w-4 h-4"></i> Logout
                    </a>
                </div>

                <div class="lg:hidden flex items-center">
                    <button onclick="toggleMobileMenu();" class="inline-flex items-center justify-center p-2 rounded-md text-gray-700 hover:text-brand-green hover:bg-gray-100 focus:outline-none">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu: Alumni + Dashboard + Logout -->
        <div id="mobile-menu" class="hidden lg:hidden bg-white border-t border-gray-200 px-4 pt-2 pb-4 space-y-2">
            <a href="alumni_view.php" class="block px-3 py-2 rounded-md text-base font-medium text-white bg-brand-green">Alumni Registry</a>
            <div class="pt-2 border-t border-gray-100 flex flex-col gap-2">
                <a href="teacher_dashboard.php" class="w-full py-2.5 rounded-md font-bold text-brand-green bg-brand-lightGreen text-center">Dashboard</a>
                <a href="teacher_logout.php" class="w-full py-2.5 rounded-md font-bold text-white bg-brand-maroon text-center">Logout</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">

        <!-- Page Header -->
        <div class="mb-8 flex flex-wrap justify-between items-end gap-4">
            <div>
                <span class="bg-brand-maroon text-white px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest">Teacher Access</span>
                <h1 class="text-4xl font-bold text-brand-green mt-3">Alumni Registry</h1>
                <p class="text-gray-600 mt-1">All former students who have registered with the Thamani Academy Alumni Network.</p>
            </div>
            <a href="alumni.php" class="px-5 py-2.5 bg-brand-green text-white font-bold rounded-lg hover:bg-brand-darkGreen transition-colors text-sm shadow flex items-center gap-2">
                <i data-lucide="user-plus" class="w-4 h-4"></i> New Registration
            </a>
        </div>

        <!-- Stat cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-12 h-12 rounded-xl bg-brand-green/10 text-brand-green flex items-center justify-center">
                        <i data-lucide="users" class="w-6 h-6"></i>
                    </div>
                    <span class="text-xs font-bold text-gray-400 uppercase">Total</span>
                </div>
                <div class="text-3xl font-bold text-brand-green"><?= $totalAll ?></div>
                <div class="text-sm text-gray-500 mt-1">Registered alumni</div>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-12 h-12 rounded-xl bg-brand-maroon/10 text-brand-maroon flex items-center justify-center">
                        <i data-lucide="filter" class="w-6 h-6"></i>
                    </div>
                    <span class="text-xs font-bold text-gray-400 uppercase">Filtered</span>
                </div>
                <div class="text-3xl font-bold text-brand-maroon"><?= $totalCount ?></div>
                <div class="text-sm text-gray-500 mt-1">Matching current view</div>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-12 h-12 rounded-xl bg-brand-gold/20 text-brand-green flex items-center justify-center">
                        <i data-lucide="calendar-clock" class="w-6 h-6"></i>
                    </div>
                    <span class="text-xs font-bold text-gray-400 uppercase">Updated</span>
                </div>
                <div class="text-lg font-bold text-brand-green"><?= date('d M Y, g:ia') ?></div>
                <div class="text-sm text-gray-500 mt-1">Live from database</div>
            </div>
        </div>

        <!-- Search form -->
        <form method="get" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Search</label>
                    <div class="relative">
                        <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                        <input type="text" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>"
                               placeholder="Name, email, or profession..."
                               class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-green focus:outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Year of Completion</label>
                    <input type="number" name="year" min="2000" max="<?= date('Y') ?>"
                           value="<?= htmlspecialchars($year, ENT_QUOTES) ?>"
                           placeholder="e.g. 2022"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-green focus:outline-none">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 py-2.5 bg-brand-green text-white font-bold rounded-lg text-sm hover:bg-brand-darkGreen transition-colors shadow">
                        Apply Filter
                    </button>
                    <?php if ($search !== '' || $year !== ''): ?>
                        <a href="alumni_view.php" class="px-4 py-2.5 border-2 border-gray-200 text-gray-600 font-bold rounded-lg text-sm hover:bg-gray-50 transition-colors">
                            Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- Error -->
        <?php if ($dbError): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-5 py-4 rounded-xl mb-6">
                <?= htmlspecialchars($dbError, ENT_QUOTES) ?>
            </div>
        <?php endif; ?>

        <!-- Alumni table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-brand-green flex items-center gap-2">
                    <i data-lucide="list" class="w-5 h-5"></i> Registered Alumni
                </h3>
                <span class="text-xs font-bold text-gray-400 uppercase"><?= $totalCount ?> record<?= $totalCount === 1 ? '' : 's' ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-gray-700 font-bold border-b border-gray-200">
                            <th class="p-4">#</th>
                            <th class="p-4">Full Name</th>
                            <th class="p-4">Year of Completion</th>
                            <th class="p-4">Profession / Institution</th>
                            <th class="p-4">Email</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($alumni)): ?>
                            <tr>
                                <td colspan="5" class="p-10 text-center">
                                    <i data-lucide="users-round" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
                                    <p class="text-gray-500 font-semibold mb-1">No alumni records found</p>
                                    <p class="text-gray-400 text-xs">
                                        <?= ($search !== '' || $year !== '')
                                            ? 'Try adjusting your filter criteria.'
                                            : 'No alumni have registered yet.' ?>
                                    </p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($alumni as $i => $a): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4 text-gray-400 font-mono text-xs"><?= $i + 1 ?></td>
                                    <td class="p-4 font-bold text-brand-green"><?= htmlspecialchars($a['name'], ENT_QUOTES) ?></td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-1 rounded bg-brand-lightGreen text-brand-green font-bold text-xs">
                                            <?= htmlspecialchars((string)$a['year'], ENT_QUOTES) ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-gray-700"><?= htmlspecialchars($a['profession'], ENT_QUOTES) ?></td>
                                    <td class="p-4">
                                        <a href="mailto:<?= htmlspecialchars($a['email'], ENT_QUOTES) ?>"
                                           class="text-brand-maroon hover:underline font-medium">
                                            <?= htmlspecialchars($a['email'], ENT_QUOTES) ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- FOOTER -->
    <footer class="bg-brand-green text-white pt-16 pb-10 mt-auto border-t-4 border-brand-gold">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-10 mb-12">
                <div>
                    <h3 class="text-2xl font-bold mb-4 flex items-center gap-2">
                        <img src="thamani-logo.png" class="h-10 w-auto" alt="Logo" onerror="this.src='favicon.svg'">
                        <span>Thamani Academy</span>
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
                        <li><a href="teacher_dashboard.php" class="hover:text-brand-gold transition-colors">Teacher Dashboard</a></li>
                        <li><a href="alumni_view.php" class="hover:text-brand-gold transition-colors">Alumni Registry</a></li>
                        <li><a href="teacher-change-password.php?voluntary=1" class="hover:text-brand-gold transition-colors">Change Password</a></li>
                        <li><a href="teacher_logout.php" class="hover:text-brand-gold transition-colors">Logout</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-2xl font-bold mb-4">Contact Us</h3>
                    <ul class="space-y-4 text-gray-300 text-sm">
                        <li class="flex items-center gap-3"><i data-lucide="map-pin" class="w-5 h-5 text-brand-gold"></i><span>Plot 45, Education Road, Kampala, Uganda</span></li>
                        <li class="flex items-center gap-3"><i data-lucide="phone" class="w-5 h-5 text-brand-gold"></i><span>+256 414 123 456</span></li>
                        <li class="flex items-center gap-3"><i data-lucide="mail" class="w-5 h-5 text-brand-gold"></i><span>info@thamaniacademy.ac.ug</span></li>
                    </ul>
                </div>
            </div>
            <div class="pt-8 border-t border-brand-darkGreen text-center text-gray-400 text-sm flex justify-between items-center">
                <span>© 2026 Thamani Academy. All rights reserved.</span>
                <span>Signed in as <?= htmlspecialchars($teacher['staff_id']) ?></span>
            </div>
        </div>
    </footer>

    <script src="app.js"></script>
    <script>
        lucide.createIcons();
        window.addEventListener('load', () => {
            setTimeout(() => document.getElementById('page-loader')?.classList.add('hidden'), 400);
        });
    </script>
</body>
</html>