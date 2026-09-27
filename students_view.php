<?php
/**
 * Thamani High School - Admitted Students Roster & Academic Management
 * -------------------------------------------------------------------
 * - Teachers can ONLY access students who have been ADMITTED ('Enrolled').
 * - Access is strictly scoped to classes where the teacher is Class Teacher or Subject Teacher.
 * - Class Teachers can record class attendance and view marks.
 * - Subject Teachers can ONLY add/edit marks for their specific taught subject(s).
 */

require_once 'auth_teacher.php';
require_teacher_login();
require_password_changed();

$teacher = current_teacher();
$isAdmin = !empty($_SESSION['admin_id']);
$teacherId = (int)($teacher['id'] ?? 0);

require_once 'conn.php';

// ---------- Class & Subject Scoping ----------
$isClassTeacher   = !empty($teacher['is_class_teacher']);
$classTeacherOf   = trim($teacher['class_teacher_of'] ?? '');
$classesTaughtRaw = $teacher['classes_taught'] ?? '';

$classesTaughtList = array_filter(array_map('trim', explode(',', $classesTaughtRaw)));
$teacherSubject   = trim($teacher['department'] ?? 'General');

// Combine allowed classes for this teacher (Class teacher class + classes taught)
$allowedClassesForTeacher = array_unique(array_filter(array_merge(
    $isClassTeacher && $classTeacherOf !== '' ? [$classTeacherOf] : [],
    $classesTaughtList
)));

// Filters
$search      = trim($_GET['q'] ?? '');
$classFilter = trim($_GET['class'] ?? '');

$students = [];

// Query ONLY admitted ('Enrolled') students
$sql = "SELECT id, full_name, date_of_birth, gender, nationality,
               lin_number, previous_school, class_level, stream,
               guardian_name, guardian_relationship, guardian_phone,
               guardian_email, emergency_name, emergency_phone,
               status, registered_at
        FROM students
        WHERE status = 'Enrolled'
        ORDER BY class_level ASC, full_name ASC";

$res = thamani_db_query($conn, $sql);

if ($res) {
    while ($row = thamani_db_fetch_assoc($res)) {
        $cLevel = $row['class_level'] ?? '';

        // Security check for non-admin teachers
        if (!$isAdmin) {
            // Teacher can ONLY access student if they are class teacher of this class OR teach this class
            if (empty($allowedClassesForTeacher) || !in_array($cLevel, $allowedClassesForTeacher, true)) {
                continue;
            }
        }

        // Apply URL class filter if selected
        if ($classFilter !== '' && $cLevel !== $classFilter) {
            continue;
        }

        // Apply Search filter
        if ($search !== '') {
            $s = strtolower($search);
            if (strpos(strtolower($row['full_name'] ?? ''), $s) === false &&
                strpos(strtolower($row['lin_number'] ?? ''), $s) === false &&
                strpos(strtolower($row['guardian_name'] ?? ''), $s) === false) {
                continue;
            }
        }

        $students[] = $row;
    }
}

// Fetch recent attendance & marks for these students to display badges
$studentIds = array_column($students, 'id');
$attendanceSummary = [];
$marksSummary      = [];

if (!empty($studentIds)) {
    $idsCsv = implode(',', array_map('intval', $studentIds));

    // Attendance
    $attRes = thamani_db_query($conn, "SELECT student_id, status, attendance_date FROM student_attendance WHERE student_id IN ($idsCsv) ORDER BY attendance_date DESC");
    if ($attRes) {
        while ($ar = thamani_db_fetch_assoc($attRes)) {
            $sid = (int)$ar['student_id'];
            if (!isset($attendanceSummary[$sid])) {
                $attendanceSummary[$sid] = $ar;
            }
        }
    }

    // Marks
    $mkRes = thamani_db_query($conn, "SELECT student_id, subject, term, score, max_score FROM student_marks WHERE student_id IN ($idsCsv) ORDER BY updated_at DESC");
    if ($mkRes) {
        while ($mr = thamani_db_fetch_assoc($mkRes)) {
            $sid = (int)$mr['student_id'];
            $marksSummary[$sid][] = $mr;
        }
    }
}

// Flash Message
$flashHtml = '';
if (!empty($_SESSION['teacher_flash'])) {
    $flash = $_SESSION['teacher_flash'];
    unset($_SESSION['teacher_flash']);
    $bgColor = $flash['type'] === 'success' ? 'bg-green-50 text-green-800 border-green-200' : 'bg-red-50 text-red-800 border-red-200';
    $icon    = $flash['type'] === 'success' ? 'check-circle' : 'alert-triangle';
    $msg     = htmlspecialchars($flash['message']);
    $flashHtml = <<<HTML
        <div class="mb-6 p-4 rounded-xl border {$bgColor} text-sm font-semibold flex items-center gap-2 shadow-sm">
            <i data-lucide="{$icon}" class="w-5 h-5 flex-shrink-0"></i>
            <span>{$msg}</span>
        </div>
    HTML;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admitted Students & Marks - THAMANI HIGH SCHOOL</title>
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
            <img src="thamani-logo.png" alt="Thamani High School" class="h-20 w-auto mx-auto mb-4 animate-pulse" onerror="this.style.display='none'">
            <div class="w-12 h-12 border-4 border-brand-gold border-t-transparent rounded-full animate-spin mx-auto"></div>
        </div>
    </div>
    <script>
        setTimeout(function() {
            var l = document.getElementById('page-loader');
            if (l) { l.classList.add('hidden'); l.style.display = 'none'; }
        }, 50);
    </script>

    <!-- Top Announcement Bar -->
    <div class="bg-brand-maroon text-white text-xs py-2 px-4 text-center font-medium">
        <div class="max-w-7xl mx-auto w-full flex justify-between items-center">
            <span>📍 THAMANI HIGH SCHOOL - Kakiri Main Campus, Wakiso District, Uganda</span>
            <span class="hidden sm:inline">📞 Enquiries: +256 414 123 456 | ✉️ info@thamani.ac.ug</span>
            <span class="bg-brand-gold text-brand-green px-2.5 py-0.5 rounded font-bold uppercase tracking-wider text-[10px]"><?= $isAdmin ? 'Admin View' : 'Teacher Portal' ?></span>
        </div>
    </div>

    <!-- Main Navigation Bar -->
    <nav class="sticky top-0 z-50 bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <div class="flex items-center">
                    <a href="<?= $isAdmin ? 'admin_dashboard.php' : 'teacher_dashboard.php' ?>" class="flex-shrink-0 flex items-center gap-3">
                        <img class="h-12 w-auto" src="thamani-logo.png" alt="Thamani High School Logo" onerror="this.src='favicon.svg'">
                        <div class="flex flex-col">
                            <span class="text-2xl font-bold tracking-tight text-brand-green">Thamani High School</span>
                            <span class="text-[10px] font-semibold text-brand-maroon tracking-widest uppercase">Student Roster & Marks</span>
                        </div>
                    </a>
                </div>

                <div class="hidden lg:flex items-center space-x-2">
                    <a href="<?= $isAdmin ? 'admin_dashboard.php' : 'teacher_dashboard.php' ?>" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-brand-green">Dashboard</a>
                    <a href="students_view.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-white bg-brand-green">Student Roster</a>
                    <?php if (!empty($teacher['can_view_enrollments']) || $isAdmin): ?>
                        <a href="enrollment_view.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-brand-green">Enrollment Queue</a>
                    <?php endif; ?>
                    <a href="alumni_view.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-brand-green">Alumni</a>
                </div>

                <div class="hidden md:flex items-center space-x-3">
                    <span class="text-sm text-gray-600 hidden lg:inline">
                        Signed in as <strong class="text-brand-green"><?= htmlspecialchars($isAdmin ? 'Admin' : $teacher['name']) ?></strong>
                    </span>
                    <a href="<?= $isAdmin ? 'admin_logout.php' : 'teacher_logout.php' ?>" class="px-4 py-2 rounded-md text-sm font-bold text-white bg-brand-maroon hover:bg-red-900 transition-colors">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">

        <div class="mb-8 flex flex-wrap justify-between items-end gap-4">
            <div>
                <span class="bg-brand-green text-white px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest">Academic Roster & Grading</span>
                <h1 class="text-4xl font-bold text-brand-green mt-2">Admitted Student Roster</h1>
                <p class="text-gray-600 mt-1">Students enrolled at Thamani High School. Only active students in your assigned classes are visible.</p>
            </div>
            
            <!-- Teacher Privilege Summary Pill -->
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm text-xs space-y-1">
                <div class="font-bold text-gray-800 flex items-center gap-1.5">
                    <i data-lucide="shield" class="w-4 h-4 text-brand-green"></i> Teacher Assignment Profile
                </div>
                <div>
                    Department / Subject: <strong class="text-brand-green"><?= htmlspecialchars($teacherSubject) ?></strong>
                </div>
                <?php if ($isClassTeacher): ?>
                    <div class="text-amber-700 font-bold flex items-center gap-1">
                        <i data-lucide="award" class="w-3.5 h-3.5"></i> Class Teacher of: <?= htmlspecialchars($classTeacherOf) ?>
                    </div>
                <?php endif; ?>
                <div>
                    Classes Taught: <span class="font-semibold text-gray-700"><?= htmlspecialchars(implode(', ', $allowedClassesForTeacher) ?: 'None assigned') ?></span>
                </div>
            </div>
        </div>

        <?= $flashHtml ?>

        <?php if (!$isAdmin && empty($allowedClassesForTeacher)): ?>
            <div class="bg-yellow-50 border-2 border-yellow-200 p-8 rounded-2xl text-center mb-8">
                <i data-lucide="info" class="w-12 h-12 text-yellow-600 mx-auto mb-3"></i>
                <h3 class="text-lg font-bold text-yellow-900">No Active Class Assignment</h3>
                <p class="text-xs text-yellow-800 max-w-lg mx-auto mt-1">
                    Your teacher account has not yet been assigned as a Class Teacher or Subject Teacher for any class. Please contact the School Administrator to assign your classes.
                </p>
            </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <form method="get" class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Search Student / LIN</label>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Student name or LIN number..." class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Class Level</label>
                    <select name="class" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">All Accessible Classes</option>
                        <?php foreach (($isAdmin ? ['Senior 1','Senior 2','Senior 3','Senior 4','Senior 5','Senior 6'] : $allowedClassesForTeacher) as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= $classFilter === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="w-full py-2 bg-brand-green text-white font-bold rounded-lg text-sm hover:bg-brand-darkGreen transition-colors">Filter Roster</button>
                    <?php if ($search || $classFilter): ?>
                        <a href="students_view.php" class="px-4 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- Students Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-brand-green flex items-center gap-2">
                    <i data-lucide="users" class="w-5 h-5"></i> Admitted Students (<?= count($students) ?>)
                </h3>
                <span class="text-xs font-bold text-gray-400 uppercase">Status: Admitted / Enrolled</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-gray-700 font-bold border-b border-gray-200">
                            <th class="p-4">LIN / UNEB Index</th>
                            <th class="p-4">Student Name</th>
                            <th class="p-4">Class Level</th>
                            <th class="p-4">Parent / Contact</th>
                            <th class="p-4">Recent Attendance</th>
                            <th class="p-4">Subject Marks</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="7" class="p-12 text-center text-gray-500">
                                    <i data-lucide="user-x" class="w-10 h-10 text-gray-300 mx-auto mb-2"></i>
                                    <p class="font-semibold text-sm">No admitted student records match your query.</p>
                                    <p class="text-xs text-gray-400 mt-1">Teachers can only view students who are officially admitted and assigned to their class.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $s):
                                $sid = (int)$s['id'];
                                $isMyClassTeacherStudent = $isClassTeacher && trim($s['class_level']) === $classTeacherOf;
                                $att = $attendanceSummary[$sid] ?? null;
                                $mks = $marksSummary[$sid] ?? [];
                            ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4 font-mono font-bold text-brand-maroon text-xs"><?= htmlspecialchars($s['lin_number']) ?></td>
                                    <td class="p-4">
                                        <div class="font-bold text-brand-green"><?= htmlspecialchars($s['full_name']) ?></div>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($s['gender'] ?? '') ?> · <?= htmlspecialchars($s['nationality'] ?? '') ?></div>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-1 rounded bg-brand-lightGreen text-brand-green font-bold text-xs">
                                            <?= htmlspecialchars($s['class_level']) ?> (<?= htmlspecialchars($s['stream']) ?>)
                                        </span>
                                    </td>
                                    <td class="p-4 text-xs">
                                        <div class="font-semibold text-gray-800"><?= htmlspecialchars($s['guardian_name']) ?></div>
                                        <div class="text-gray-500 font-mono"><?= htmlspecialchars($s['guardian_phone']) ?></div>
                                    </td>
                                    <td class="p-4 text-xs">
                                        <?php if ($att): ?>
                                            <span class="px-2 py-0.5 rounded font-bold text-[11px] <?= $att['status'] === 'Present' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= htmlspecialchars($att['status']) ?>
                                            </span>
                                            <div class="text-[10px] text-gray-400 mt-0.5"><?= date('d M', strtotime($att['attendance_date'])) ?></div>
                                        <?php else: ?>
                                            <span class="text-gray-400 italic text-[11px]">Not marked today</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-xs">
                                        <?php if (!empty($mks)): ?>
                                            <div class="flex flex-wrap gap-1">
                                                <?php foreach (array_slice($mks, 0, 2) as $mk): ?>
                                                    <span class="px-2 py-0.5 bg-gray-100 text-gray-800 rounded font-semibold text-[10px]">
                                                        <?= htmlspecialchars($mk['subject']) ?>: <strong><?= $mk['score'] ?>/<?= $mk['max_score'] ?></strong>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400 italic text-[11px]">No marks entered yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-right whitespace-nowrap space-x-1">
                                        <!-- Record Attendance Button (Class Teachers or Admin) -->
                                        <?php if ($isMyClassTeacherStudent || $isAdmin): ?>
                                            <button onclick="openAttendanceModal(<?= $sid ?>, '<?= htmlspecialchars(addslashes($s['full_name'])) ?>', '<?= htmlspecialchars($s['class_level']) ?>')"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-amber-600 text-white font-bold rounded text-xs hover:bg-amber-700 transition-colors shadow-sm">
                                                <i data-lucide="check-square" class="w-3.5 h-3.5"></i> Attendance
                                            </button>
                                        <?php endif; ?>

                                        <!-- Add / Edit Marks Button (Subject Teachers and Class Teachers) -->
                                        <button onclick="openMarksModal(<?= $sid ?>, '<?= htmlspecialchars(addslashes($s['full_name'])) ?>', '<?= htmlspecialchars($s['class_level']) ?>')"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-brand-green text-white font-bold rounded text-xs hover:bg-brand-darkGreen transition-colors shadow-sm">
                                            <i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Input Marks
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- MODAL: RECORD CLASS ATTENDANCE -->
    <div id="modal-attendance" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-gray-100 pb-3">
                <h3 class="text-lg font-bold text-brand-green flex items-center gap-2">
                    <i data-lucide="calendar-check" class="w-5 h-5 text-amber-600"></i> Record Class Attendance
                </h3>
                <button onclick="closeModal('modal-attendance');" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <form action="record_attendance.php" method="post" class="space-y-4">
                <input type="hidden" name="student_id" id="att_student_id">
                <input type="hidden" name="class_level" id="att_class_level">

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Student Name</label>
                    <div id="att_student_name" class="text-sm font-bold text-brand-green bg-gray-50 p-2.5 rounded-lg"></div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Attendance Date</label>
                    <input type="date" name="attendance_date" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-green">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-green">
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                        <option value="Late">Late</option>
                        <option value="Excused">Excused (Medical / Leave)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Teacher Remarks (Optional)</label>
                    <input type="text" name="remarks" placeholder="e.g. Arrived 15 minutes late" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 py-2.5 bg-brand-green text-white font-bold rounded-lg text-xs hover:bg-brand-darkGreen transition-colors shadow">
                        Save Attendance Record
                    </button>
                    <button type="button" onclick="closeModal('modal-attendance');" class="px-4 py-2.5 border border-gray-300 text-gray-600 font-bold rounded-lg text-xs">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: INPUT / EDIT SUBJECT MARKS -->
    <div id="modal-marks" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-gray-100 pb-3">
                <h3 class="text-lg font-bold text-brand-green flex items-center gap-2">
                    <i data-lucide="award" class="w-5 h-5 text-brand-green"></i> Input Academic Marks
                </h3>
                <button onclick="closeModal('modal-marks');" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <form action="save_marks.php" method="post" class="space-y-4">
                <input type="hidden" name="student_id" id="mk_student_id">
                <input type="hidden" name="class_level" id="mk_class_level">

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Student</label>
                    <div id="mk_student_name" class="text-sm font-bold text-brand-green bg-gray-50 p-2.5 rounded-lg"></div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Subject Taught <span class="text-red-500">*</span></label>
                    <?php if (!$isAdmin && !$isClassTeacher): ?>
                        <input type="text" name="subject" value="<?= htmlspecialchars($teacherSubject) ?>" readonly class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-sm font-bold text-brand-green cursor-not-allowed">
                        <p class="text-[11px] text-gray-500 mt-0.5">Subject teachers can only edit marks for their assigned department/subject.</p>
                    <?php else: ?>
                        <input type="text" name="subject" required placeholder="e.g. Mathematics, Physics, English" value="<?= htmlspecialchars($teacherSubject !== 'Academic' ? $teacherSubject : '') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-green">
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Term</label>
                        <select name="term" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-green">
                            <option value="Term III 2026" selected>Term III 2026</option>
                            <option value="Term II 2026">Term II 2026</option>
                            <option value="Term I 2026">Term I 2026</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Score / 100 <span class="text-red-500">*</span></label>
                        <input type="number" name="score" step="0.5" min="0" max="100" required placeholder="e.g. 85.5" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono font-bold text-brand-green focus:ring-2 focus:ring-brand-green">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Teacher Remarks (Optional)</label>
                    <input type="text" name="comments" placeholder="e.g. Excellent analytical performance" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 py-2.5 bg-brand-green text-white font-bold rounded-lg text-xs hover:bg-brand-darkGreen transition-colors shadow">
                        Save Subject Marks
                    </button>
                    <button type="button" onclick="closeModal('modal-marks');" class="px-4 py-2.5 border border-gray-300 text-gray-600 font-bold rounded-lg text-xs">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function openModal(id) {
            document.getElementById(id)?.classList.remove('hidden');
        }
        function closeModal(id) {
            document.getElementById(id)?.classList.add('hidden');
        }

        function openAttendanceModal(id, name, classLvl) {
            document.getElementById('att_student_id').value = id;
            document.getElementById('att_class_level').value = classLvl;
            document.getElementById('att_student_name').textContent = name + ' (' + classLvl + ')';
            openModal('modal-attendance');
        }

        function openMarksModal(id, name, classLvl) {
            document.getElementById('mk_student_id').value = id;
            document.getElementById('mk_class_level').value = classLvl;
            document.getElementById('mk_student_name').textContent = name + ' (' + classLvl + ')';
            openModal('modal-marks');
        }
    </script>
</body>
</html>
