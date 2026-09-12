<?php
/**
 * Thamani High School - Enrolled Students Registry (Teacher / Staff View)
 */

require_once 'auth_teacher.php';
require_teacher_login();
require_password_changed();

$teacher = current_teacher();
require_once 'conn.php';

$search = trim($_GET['q'] ?? '');
$classFilter = trim($_GET['class'] ?? '');

$students = [];
$res = mysqli_query($conn, "SELECT * FROM students ORDER BY id DESC");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        if ($search !== '') {
            $s = strtolower($search);
            if (strpos(strtolower($row['full_name'] ?? ''), $s) === false &&
                strpos(strtolower($row['lin_number'] ?? ''), $s) === false &&
                strpos(strtolower($row['guardian_name'] ?? ''), $s) === false) {
                continue;
            }
        }
        if ($classFilter !== '' && ($row['class_level'] ?? '') !== $classFilter) {
            continue;
        }
        $students[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrolled Students - THAMANI HIGH SCHOOL</title>
    <link rel="icon" type="image/ico" href="favicon.ico" />
    <link rel="stylesheet" href="css/tailwind.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gray-50 text-gray-900 font-sans flex flex-col min-h-screen">

    <!-- Top Announcement Bar -->
    <div class="bg-brand-maroon text-white text-xs py-2 px-4 text-center font-medium">
        <div class="max-w-7xl mx-auto w-full flex justify-between items-center">
            <span>📍 THAMANI HIGH SCHOOL - Kakiri Main Campus, Wakiso District, Uganda</span>
            <span class="hidden sm:inline">📞 Enquiries: +256 414 123 456 | ✉️ info@thamani.ac.ug</span>
            <span class="bg-brand-gold text-brand-green px-2.5 py-0.5 rounded font-bold uppercase tracking-wider text-[10px]">Term III 2026 Active</span>
        </div>
    </div>

    <!-- Main Navigation Bar -->
    <nav class="sticky top-0 z-50 bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <div class="flex items-center">
                    <a href="teacher_dashboard.php" class="flex-shrink-0 flex items-center gap-3">
                        <img class="h-12 w-auto" src="thamani-logo.png" alt="Thamani High School Logo" onerror="this.src='favicon.svg'">
                        <div class="flex flex-col">
                            <span class="text-2xl font-bold tracking-tight text-brand-green">Thamani High School</span>
                            <span class="text-[10px] font-semibold text-brand-maroon tracking-widest uppercase">Student Roster</span>
                        </div>
                    </a>
                </div>

                <div class="hidden lg:flex items-center space-x-2">
                    <a href="teacher_dashboard.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-brand-green">Dashboard</a>
                    <a href="students_view.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-white bg-brand-green">Student Roster</a>
                    <a href="alumni_view.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-brand-green">Alumni</a>
                </div>

                <div class="hidden md:flex items-center space-x-3">
                    <span class="text-sm text-gray-600 hidden lg:inline">
                        Signed in as <strong class="text-brand-green"><?= htmlspecialchars($teacher['name']) ?></strong>
                    </span>
                    <a href="teacher_logout.php" class="px-4 py-2 rounded-md text-sm font-bold text-white bg-brand-maroon hover:bg-red-900 transition-colors">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">

        <div class="mb-8 flex flex-wrap justify-between items-end gap-4">
            <div>
                <span class="bg-brand-green text-white px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest">Backend Database Records</span>
                <h1 class="text-4xl font-bold text-brand-green mt-3">Enrolled Student Applications</h1>
                <p class="text-gray-600 mt-1">Live database list of all students enrolled via the enrollment portal.</p>
            </div>
            <a href="enrollment.php" class="px-5 py-2.5 bg-brand-gold text-brand-green font-bold rounded-lg hover:bg-yellow-400 transition-colors text-sm shadow flex items-center gap-2">
                <i data-lucide="user-plus" class="w-4 h-4"></i> Submit New Application
            </a>
        </div>

        <!-- Filter Bar -->
        <form method="get" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Search Student / LIN / Guardian</label>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, LIN number..." class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Class Level</label>
                    <select name="class" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">All Classes</option>
                        <option value="Senior 1" <?= $classFilter==='Senior 1'?'selected':'' ?>>Senior 1</option>
                        <option value="Senior 2" <?= $classFilter==='Senior 2'?'selected':'' ?>>Senior 2</option>
                        <option value="Senior 3" <?= $classFilter==='Senior 3'?'selected':'' ?>>Senior 3</option>
                        <option value="Senior 4" <?= $classFilter==='Senior 4'?'selected':'' ?>>Senior 4</option>
                        <option value="Senior 5" <?= $classFilter==='Senior 5'?'selected':'' ?>>Senior 5</option>
                        <option value="Senior 6" <?= $classFilter==='Senior 6'?'selected':'' ?>>Senior 6</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="w-full py-2 bg-brand-green text-white font-bold rounded-lg text-sm">Filter</button>
                    <?php if ($search || $classFilter): ?>
                        <a href="students_view.php" class="px-4 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- Students Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-brand-green">Student Records (<?= count($students) ?>)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-gray-700 font-bold border-b border-gray-200">
                            <th class="p-4">LIN / UNEB Index</th>
                            <th class="p-4">Student Name</th>
                            <th class="p-4">Class & Stream</th>
                            <th class="p-4">Gender</th>
                            <th class="p-4">Parent / Guardian</th>
                            <th class="p-4">Phone</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Registered At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="8" class="p-8 text-center text-gray-500">No student records found in database.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $s): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="p-4 font-mono font-bold text-brand-maroon"><?= htmlspecialchars($s['lin_number']) ?></td>
                                    <td class="p-4 font-bold text-brand-green"><?= htmlspecialchars($s['full_name']) ?></td>
                                    <td class="p-4"><?= htmlspecialchars($s['class_level']) ?> (<?= htmlspecialchars($s['stream']) ?>)</td>
                                    <td class="p-4"><?= htmlspecialchars($s['gender']) ?></td>
                                    <td class="p-4"><?= htmlspecialchars($s['guardian_name']) ?> (<?= htmlspecialchars($s['guardian_relationship']) ?>)</td>
                                    <td class="p-4 font-mono"><?= htmlspecialchars($s['guardian_phone']) ?></td>
                                    <td class="p-4"><span class="px-2 py-1 rounded bg-yellow-100 text-yellow-800 font-bold text-xs"><?= htmlspecialchars($s['status']) ?></span></td>
                                    <td class="p-4 text-xs text-gray-500"><?= htmlspecialchars($s['registered_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
    <script>lucide.createIcons();</script>
</body>
</html>
