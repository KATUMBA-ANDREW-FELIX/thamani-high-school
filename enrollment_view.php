<?php
/**
 * Thamani High School - Enrollment Registry View
 * -------------------------------------------
 * - Admin + Teacher accessible
 * - Lists all enrolled students with filters + search
 * - Full student detail in a modal
 * - Admins can change status (Pending / Enrolled / Rejected)
 */

require_once 'auth_admin.php'; // provides session start + helpers

// Allow either teacher OR admin
$isTeacher = !empty($_SESSION['teacher_id']);
$isAdmin   = !empty($_SESSION['admin_id']);

if (!$isTeacher && !$isAdmin) {
    header('Location: teacher-login.php');
    exit;
}

// Which user is signed in?
$viewerName = $isAdmin
    ? ($_SESSION['admin_name'] ?? 'Admin')
    : ($_SESSION['teacher_name'] ?? 'Teacher');
$viewerRole = $isAdmin ? 'admin' : 'teacher';
$viewerId   = $isAdmin ? 'ADM' : ($_SESSION['teacher_staff_id'] ?? 'TCH');

require_once 'conn.php';

// ---------- Filters ----------
$search = trim($_GET['q'] ?? '');
$class  = trim($_GET['class'] ?? '');
$status = trim($_GET['status'] ?? '');

$allowedClasses  = ['Senior 1','Senior 2','Senior 3','Senior 4','Senior 5','Senior 6'];
$allowedStatuses = ['Pending','Enrolled','Rejected'];

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = "(full_name LIKE ? OR lin_number LIKE ? OR guardian_name LIKE ? OR guardian_phone LIKE ?)";
    $like     = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'ssss';
}
if ($class !== '' && in_array($class, $allowedClasses, true)) {
    $where[]  = "class_level = ?";
    $params[] = $class;
    $types   .= 's';
}
if ($status !== '' && in_array($status, $allowedStatuses, true)) {
    $where[]  = "status = ?";
    $params[] = $status;
    $types   .= 's';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ---------- Fetch students ----------
$students = [];
$dbError  = '';

$sql = "SELECT id, full_name, date_of_birth, gender, nationality,
               lin_number, previous_school, class_level, stream,
               guardian_name, guardian_relationship, guardian_phone,
               guardian_email, guardian_address, guardian_occupation,
               emergency_name, emergency_phone, medical_notes,
               status, registered_at
        FROM students
        $whereSql
        ORDER BY registered_at DESC";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt === false) {
    error_log('[Enrollment View] ' . mysqli_error($conn));
    $dbError = 'Could not load enrollment records.';
} else {
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);

    if (function_exists('mysqli_stmt_get_result')) {
        $res = mysqli_stmt_get_result($stmt);
        if ($res) while ($row = mysqli_fetch_assoc($res)) $students[] = $row;
    } else {
        // fallback (no mysqlnd)
        mysqli_stmt_bind_result($stmt,
            $id, $full_name, $dob, $gender, $nat, $lin, $prev, $cls, $stream,
            $gname, $grel, $gphone, $gmail, $gaddr, $gocc,
            $ename, $ephone, $med, $status, $reg);
        while (mysqli_stmt_fetch($stmt)) {
            $students[] = compact('id','full_name','dob','gender','nat','lin','prev','cls','stream',
                'gname','grel','gphone','gmail','gaddr','gocc','ename','ephone','med','status','reg');
        }
    }
    mysqli_stmt_close($stmt);
}

// ---------- Stats ----------
$countAll      = 0;
$countPending  = 0;
$countEnrolled = 0;
$countRejected = 0;

$stat = mysqli_query($conn, "SELECT status, COUNT(*) AS c FROM students GROUP BY status");
if ($stat) {
    while ($r = mysqli_fetch_assoc($stat)) {
        $countAll += (int)$r['c'];
        if ($r['status'] === 'Pending')  $countPending  = (int)$r['c'];
        if ($r['status'] === 'Enrolled') $countEnrolled = (int)$r['c'];
        if ($r['status'] === 'Rejected') $countRejected = (int)$r['c'];
    }
}

// ---------- Flash ----------
$flashHtml = '';
if (!empty($_SESSION['enroll_flash'])) {
    $flash = $_SESSION['enroll_flash'];
    unset($_SESSION['enroll_flash']);
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

// Which dashboard do we link back to?
$backLink = $isAdmin ? 'admin_dashboard.php' : 'teacher_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Registry - THAMANI HIGH SCHOOL - Kakiri</title>
    <link rel="icon" type="image/ico" href="favicon.ico" />
    <link rel="stylesheet" href="css/tailwind.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        #page-loader {
            position: fixed; inset: 0; background: #1F2937;
            display: flex; align-items: center; justify-content: center;
            z-index: 9999; transition: opacity .5s ease, visibility .5s ease;
        }
        #page-loader.hidden { opacity: 0; visibility: hidden; pointer-events: none; display: none !important; }
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

    <!-- Top Bar -->
    <div class="bg-brand-maroon text-white text-xs py-2 px-4 text-center font-medium">
        <div class="max-w-7xl mx-auto w-full flex justify-between items-center">
            <span>📍 THAMANI HIGH SCHOOL - Kakiri Main Campus, Wakiso District, Uganda</span>
            <span class="hidden sm:inline">📞 Enquiries: +256 414 123 456 | ✉️ info@thamani.ac.ug</span>
            <span class="bg-brand-gold text-brand-green px-2.5 py-0.5 rounded font-bold uppercase tracking-wider text-[10px]"><?= $isAdmin ? 'Admin' : 'Teacher' ?> Session</span>
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
                        <span class="text-[10px] font-semibold text-brand-maroon tracking-widest uppercase"><?= $isAdmin ? 'Admin Control Panel' : 'Teacher Portal' ?></span>
                    </div>
                </a>
                <div class="hidden lg:flex items-center space-x-2">
                    <a href="<?= $backLink ?>" class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-brand-green transition-colors">Dashboard</a>
                    <a href="enrollment_view.php" class="px-3 py-2 rounded-md text-sm font-medium text-white bg-brand-green">Enrollment Registry</a>
                    <?php if ($isAdmin): ?>
                        <a href="alumni_view.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-brand-green transition-colors">Alumni</a>
                    <?php else: ?>
                        <a href="alumni_view.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-brand-green transition-colors">Alumni</a>
                    <?php endif; ?>
                </div>
                <div class="hidden md:flex items-center space-x-3">
                    <span class="text-sm text-gray-600 hidden lg:inline">
                        Signed in as <strong class="<?= $isAdmin ? 'text-brand-maroon' : 'text-brand-green' ?>"><?= htmlspecialchars($viewerName) ?></strong>
                    </span>
                    <a href="<?= $isAdmin ? 'admin_logout.php' : 'teacher_logout.php' ?>"
                       class="px-4 py-2 rounded-md text-sm font-bold text-white bg-brand-maroon hover:bg-red-900 transition-colors flex items-center gap-1.5">
                        <i data-lucide="log-out" class="w-4 h-4"></i> Logout
                    </a>
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
            <a href="<?= $backLink ?>" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Dashboard</a>
            <a href="enrollment_view.php" class="block px-3 py-2 rounded-md text-base font-medium text-white bg-brand-green">Enrollment Registry</a>
            <a href="alumni_view.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Alumni Registry</a>
            <div class="pt-2 border-t border-gray-100">
                <a href="<?= $isAdmin ? 'admin_logout.php' : 'teacher_logout.php' ?>" class="w-full py-2.5 rounded-md font-bold text-white bg-brand-maroon text-center block">Logout</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">

        <!-- Header -->
        <div class="mb-8 flex flex-wrap justify-between items-end gap-4">
            <div>
                <span class="bg-brand-maroon text-white px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest">Student Records</span>
                <h1 class="text-4xl font-bold text-brand-green mt-3">Enrollment Registry</h1>
                <p class="text-gray-600 mt-1">All students who have applied to Thamani High School, with full admission details.</p>
            </div>
        </div>

        <?= $flashHtml ?>

        <!-- Stat cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                <div class="text-xs font-bold text-gray-400 uppercase mb-1">Total</div>
                <div class="text-3xl font-bold text-brand-green"><?= $countAll ?></div>
                <div class="text-xs text-gray-500 mt-1">All applications</div>
            </div>
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                <div class="text-xs font-bold text-gray-400 uppercase mb-1">Pending</div>
                <div class="text-3xl font-bold text-yellow-600"><?= $countPending ?></div>
                <div class="text-xs text-gray-500 mt-1">Awaiting review</div>
            </div>
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                <div class="text-xs font-bold text-gray-400 uppercase mb-1">Enrolled</div>
                <div class="text-3xl font-bold text-green-700"><?= $countEnrolled ?></div>
                <div class="text-xs text-gray-500 mt-1">Admitted students</div>
            </div>
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                <div class="text-xs font-bold text-gray-400 uppercase mb-1">Rejected</div>
                <div class="text-3xl font-bold text-brand-maroon"><?= $countRejected ?></div>
                <div class="text-xs text-gray-500 mt-1">Not admitted</div>
            </div>
        </div>

        <!-- Filters -->
        <form method="get" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Search</label>
                    <div class="relative">
                        <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                        <input type="text" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>"
                               placeholder="Name, LIN, guardian name or phone..."
                               class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-green focus:outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Class</label>
                    <select name="class" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-green focus:outline-none">
                        <option value="">All Classes</option>
                        <?php foreach ($allowedClasses as $c): ?>
                            <option value="<?= $c ?>" <?= $class === $c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-green focus:outline-none">
                        <option value="">All Statuses</option>
                        <?php foreach ($allowedStatuses as $s): ?>
                            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex gap-2 mt-4">
                <button type="submit" class="px-5 py-2.5 bg-brand-green text-white font-bold rounded-lg text-sm hover:bg-brand-darkGreen transition-colors shadow">
                    Apply Filters
                </button>
                <?php if ($search !== '' || $class !== '' || $status !== ''): ?>
                    <a href="enrollment_view.php" class="px-4 py-2.5 border-2 border-gray-200 text-gray-600 font-bold rounded-lg text-sm hover:bg-gray-50 transition-colors">
                        Clear
                    </a>
                <?php endif; ?>
                <div class="ml-auto flex items-center text-xs text-gray-500">
                    Showing <strong class="text-brand-green mx-1"><?= count($students) ?></strong> result<?= count($students) === 1 ? '' : 's' ?>
                </div>
            </div>
        </form>

        <?php if ($dbError): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-5 py-4 rounded-xl mb-6">
                <?= htmlspecialchars($dbError, ENT_QUOTES) ?>
            </div>
        <?php endif; ?>

        <!-- Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-brand-green flex items-center gap-2">
                    <i data-lucide="list" class="w-5 h-5"></i> Student Applications
                </h3>
                <span class="text-xs font-bold text-gray-400 uppercase"><?= count($students) ?> record<?= count($students) === 1 ? '' : 's' ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-gray-700 font-bold border-b border-gray-200">
                            <th class="p-4">#</th>
                            <th class="p-4">Student Name</th>
                            <th class="p-4">Class & Stream</th>
                            <th class="p-4">LIN / Index</th>
                            <th class="p-4">Guardian</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="7" class="p-12 text-center">
                                    <i data-lucide="user-x" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
                                    <p class="text-gray-500 font-semibold mb-1">No student records found</p>
                                    <p class="text-gray-400 text-xs">Try adjusting your filters or search.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $i => $s):
                                $statusClass = $s['status'] === 'Enrolled'
                                    ? 'bg-green-100 text-green-800'
                                    : ($s['status'] === 'Rejected'
                                        ? 'bg-red-100 text-red-800'
                                        : 'bg-yellow-100 text-yellow-800');
                            ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4 text-gray-400 font-mono text-xs"><?= $i + 1 ?></td>
                                    <td class="p-4">
                                        <div class="font-bold text-brand-green"><?= htmlspecialchars($s['full_name']) ?></div>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($s['gender'] ?? '') ?> · <?= htmlspecialchars($s['nationality'] ?? '') ?></div>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-1 rounded bg-brand-lightGreen text-brand-green font-bold text-xs">
                                            <?= htmlspecialchars($s['class_level']) ?> · <?= htmlspecialchars($s['stream']) ?>
                                        </span>
                                    </td>
                                    <td class="p-4 font-mono text-xs text-gray-700"><?= htmlspecialchars($s['lin_number']) ?></td>
                                    <td class="p-4">
                                        <div class="text-sm text-gray-800"><?= htmlspecialchars($s['guardian_name']) ?></div>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($s['guardian_phone']) ?></div>
                                    </td>
                                    <td class="p-4">
                                        <span class="text-xs font-bold px-2.5 py-1 rounded uppercase <?= $statusClass ?>">
                                            <?= htmlspecialchars($s['status']) ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-right whitespace-nowrap">
                                        <button onclick='showStudent(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) ?>)'
                                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-brand-green text-white font-bold rounded text-xs hover:bg-brand-darkGreen transition-colors">
                                            <i data-lucide="eye" class="w-3 h-3"></i> View
                                        </button>
                                        <?php if ($isAdmin): ?>
                                            <form action="delete_item.php" method="post" class="inline ml-1" onsubmit="return confirm('Are you sure you want to delete student record for <?= htmlspecialchars(addslashes($s['full_name'])) ?>?');">
                                                <input type="hidden" name="type" value="student">
                                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                                <input type="hidden" name="redirect_to" value="enrollment_view.php">
                                                <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-600 text-white font-bold rounded text-xs hover:bg-red-700 transition-colors shadow-sm">
                                                    <i data-lucide="trash-2" class="w-3 h-3"></i> Delete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- DETAIL MODAL -->
    <div id="modal-student" class="hidden fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-3xl w-full shadow-2xl max-h-[92vh] overflow-y-auto">

            <!-- Modal Header -->
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-100 flex justify-between items-center z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-brand-green/10 text-brand-green flex items-center justify-center">
                        <i data-lucide="user" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 id="modal-student-name" class="text-lg font-bold text-brand-green">Student Detail</h3>
                        <p id="modal-student-sub" class="text-xs text-gray-500">Full application record</p>
                    </div>
                </div>
                <button onclick="closeStudentModal()" class="p-2 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div id="modal-student-body" class="p-6 space-y-6"></div>

            <!-- Modal Footer: status buttons (admin only) -->
            <?php if ($isAdmin): ?>
            <div class="sticky bottom-0 bg-white border-t border-gray-100 px-6 py-4 flex flex-wrap gap-2 justify-end">
                <form method="post" action="enrollment_status_update.php" class="inline">
                    <input type="hidden" name="student_id" id="status-student-id" value="">
                    <input type="hidden" name="new_status" value="Enrolled">
                    <button type="submit" class="px-4 py-2.5 bg-green-600 text-white font-bold rounded-lg text-xs hover:bg-green-700 transition-colors flex items-center gap-1.5">
                        <i data-lucide="check-circle" class="w-4 h-4"></i> Mark Enrolled
                    </button>
                </form>
                <form method="post" action="enrollment_status_update.php" class="inline">
                    <input type="hidden" name="student_id" id="status-student-id-2" value="">
                    <input type="hidden" name="new_status" value="Pending">
                    <button type="submit" class="px-4 py-2.5 bg-yellow-500 text-white font-bold rounded-lg text-xs hover:bg-yellow-600 transition-colors flex items-center gap-1.5">
                        <i data-lucide="clock" class="w-4 h-4"></i> Mark Pending
                    </button>
                </form>
                <form method="post" action="enrollment_status_update.php" class="inline">
                    <input type="hidden" name="student_id" id="status-student-id-3" value="">
                    <input type="hidden" name="new_status" value="Rejected">
                    <button type="submit" class="px-4 py-2.5 bg-brand-maroon text-white font-bold rounded-lg text-xs hover:bg-red-900 transition-colors flex items-center gap-1.5"
                            onclick="return confirm('Mark this student as Rejected?');">
                        <i data-lucide="x-circle" class="w-4 h-4"></i> Reject
                    </button>
                </form>
                <form method="post" action="delete_item.php" class="inline" onsubmit="return confirm('Are you sure you want to PERMANENTLY DELETE this student record?');">
                    <input type="hidden" name="type" value="student">
                    <input type="hidden" name="id" id="delete-student-id-modal" value="">
                    <input type="hidden" name="redirect_to" value="enrollment_view.php">
                    <button type="submit" class="px-4 py-2.5 bg-red-700 text-white font-bold rounded-lg text-xs hover:bg-red-800 transition-colors flex items-center gap-1.5 shadow-sm">
                        <i data-lucide="trash-2" class="w-4 h-4"></i> Delete Record
                    </button>
                </form>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- FOOTER -->
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
                        <li><a href="<?= $backLink ?>" class="hover:text-brand-gold transition-colors">Dashboard</a></li>
                        <li><a href="enrollment_view.php" class="hover:text-brand-gold transition-colors">Enrollment Registry</a></li>
                        <li><a href="alumni_view.php" class="hover:text-brand-gold transition-colors">Alumni Registry</a></li>
                        <?php if ($isAdmin): ?>
                            <li><a href="admin_dashboard.php" class="hover:text-brand-gold transition-colors">Admin Dashboard</a></li>
                        <?php endif; ?>
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
                <span><?= $isAdmin ? 'Admin' : 'Teacher' ?> · <?= htmlspecialchars($viewerId) ?></span>
            </div>
        </div>
    </footer>

    <script src="app.js"></script>
    <script>
        lucide.createIcons();
        const hideLoader = () => document.getElementById('page-loader')?.classList.add('hidden');
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', hideLoader);
        } else {
            hideLoader();
        }
        setTimeout(hideLoader, 100);

        // ============================================================
        // STUDENT DETAIL MODAL
        // ============================================================
        function esc(v) {
            return (v === null || v === undefined || v === '') ? '—' : String(v);
        }

        function showStudent(s) {
            const modal = document.getElementById('modal-student');
            const body  = document.getElementById('modal-student-body');

            document.getElementById('modal-student-name').textContent = s.full_name || 'Student';
            document.getElementById('modal-student-sub').textContent  = (s.class_level || '') + ' · ' + (s.stream || '');

            // Status badges
            const statusClass =
                s.status === 'Enrolled' ? 'bg-green-100 text-green-800' :
                s.status === 'Rejected' ? 'bg-red-100 text-red-800' :
                                          'bg-yellow-100 text-yellow-800';

            const row = (label, value) => `
                <div class="flex justify-between gap-4 py-2 border-b border-gray-100 text-sm">
                    <span class="text-gray-500 font-medium">${label}</span>
                    <span class="text-gray-900 font-semibold text-right">${esc(value)}</span>
                </div>`;

            body.innerHTML = `
                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <div class="text-xs text-gray-500">
                        Application ID: <strong class="text-brand-green">#${esc(s.id)}</strong>
                    </div>
                    <span class="text-xs font-bold px-3 py-1 rounded uppercase ${statusClass}">${esc(s.status)}</span>
                </div>

                <div>
                    <h4 class="text-sm font-bold text-brand-green mb-2 flex items-center gap-2">
                        <i data-lucide="user" class="w-4 h-4"></i> Student Information
                    </h4>
                    <div class="bg-gray-50 rounded-xl p-4">
                        ${row('Full Name', s.full_name)}
                        ${row('Date of Birth', s.date_of_birth)}
                        ${row('Gender', s.gender)}
                        ${row('Nationality', s.nationality)}
                        ${row('LIN / UNEB Index', s.lin_number)}
                        ${row('Previous School', s.previous_school)}
                    </div>
                </div>

                <div>
                    <h4 class="text-sm font-bold text-brand-green mb-2 flex items-center gap-2">
                        <i data-lucide="graduation-cap" class="w-4 h-4"></i> Class Placement
                    </h4>
                    <div class="bg-gray-50 rounded-xl p-4">
                        ${row('Class', s.class_level)}
                        ${row('Stream', s.stream)}
                    </div>
                </div>

                <div>
                    <h4 class="text-sm font-bold text-brand-green mb-2 flex items-center gap-2">
                        <i data-lucide="users" class="w-4 h-4"></i> Parent / Guardian
                    </h4>
                    <div class="bg-gray-50 rounded-xl p-4">
                        ${row('Name', s.guardian_name)}
                        ${row('Relationship', s.guardian_relationship)}
                        ${row('Phone', s.guardian_phone)}
                        ${row('Email', s.guardian_email)}
                        ${row('Physical Address', s.guardian_address)}
                        ${row('Occupation', s.guardian_occupation)}
                    </div>
                </div>

                <div>
                    <h4 class="text-sm font-bold text-brand-green mb-2 flex items-center gap-2">
                        <i data-lucide="heart-pulse" class="w-4 h-4"></i> Emergency & Medical
                    </h4>
                    <div class="bg-gray-50 rounded-xl p-4">
                        ${row('Emergency Contact', s.emergency_name)}
                        ${row('Emergency Phone', s.emergency_phone)}
                        <div class="pt-2">
                            <div class="text-gray-500 font-medium text-sm mb-1">Medical Notes</div>
                            <div class="text-gray-900 text-sm whitespace-pre-wrap">${esc(s.medical_notes)}</div>
                        </div>
                    </div>
                </div>
            `;

            // Populate hidden status form fields
            ['status-student-id','status-student-id-2','status-student-id-3','delete-student-id-modal'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = s.id;
            });

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            lucide.createIcons();
        }

        function closeStudentModal() {
            document.getElementById('modal-student')?.classList.add('hidden');
            document.body.style.overflow = '';
        }

        // Close on ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeStudentModal();
        });

        // Close on backdrop click
        document.getElementById('modal-student')?.addEventListener('click', (e) => {
            if (e.target.id === 'modal-student') closeStudentModal();
        });
    </script>
</body>
</html>