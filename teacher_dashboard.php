<?php
require_once 'auth_teacher.php';
require_teacher_login();
require_password_changed();
require_once 'conn.php';

$teacher  = current_teacher();
$hour     = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

$classAnnouncements = [];
$caRes = mysqli_query($conn, "SELECT id, title, content, class_level, posted_by_name, created_at FROM class_announcements ORDER BY created_at DESC LIMIT 30");
if ($caRes) {
    while ($r = mysqli_fetch_assoc($caRes)) {
        $classAnnouncements[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - THAMANI HIGH SCHOOL - Kakiri</title>
    <link rel="icon" type="image/ico" href="favicon.ico" />
    <link rel="stylesheet" href="css/tailwind.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .tab-btn.active { background-color: #1F2937; color: #D4AF37; }
        .nav-link-active { background-color: #1F2937; color: #ffffff !important; }
        #page-loader {
            position: fixed; inset: 0; background: #1F2937;
            display: flex; align-items: center; justify-content: center;
            z-index: 9999; transition: opacity .5s ease, visibility .5s ease;
        }
        #page-loader.hidden { opacity: 0; visibility: hidden; pointer-events: none; display: none !important; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans flex flex-col min-h-screen">

    <!-- Page Loader -->
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
                            <span class="text-[10px] font-semibold text-brand-maroon tracking-widest uppercase">Teacher Portal</span>
                        </div>
                    </a>
                </div>

                <!-- Desktop Nav: Student Roster, Alumni & Timetables -->
                <div class="hidden lg:flex items-center space-x-2">
                    <a href="students_view.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-brand-green">Student Roster</a>
                    <a href="alumni_view.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-brand-green">Alumni</a>
                    <a href="timetables.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-brand-green">Class Timetables</a>
                </div>

                <div class="hidden md:flex items-center space-x-3">
                    <span class="text-sm text-gray-600 hidden lg:inline">
                        Signed in as <strong class="text-brand-green"><?= htmlspecialchars($teacher['name']) ?></strong>
                    </span>
                    <a href="teacher-change-password.php?voluntary=1"
                       class="px-4 py-2 rounded-md text-sm font-bold text-brand-green bg-brand-lightGreen hover:bg-brand-green hover:text-white transition-colors flex items-center gap-1.5">
                        <i data-lucide="key-round" class="w-4 h-4"></i> Password
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

        <!-- Mobile Menu: Student Roster + Alumni + Timetables + Password + Logout -->
        <div id="mobile-menu" class="hidden lg:hidden bg-white border-t border-gray-200 px-4 pt-2 pb-4 space-y-2">
            <a href="students_view.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Student Roster</a>
            <a href="alumni_view.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Alumni Registry</a>
            <a href="timetables.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Class Timetables</a>
            <div class="pt-2 border-t border-gray-100 flex flex-col gap-2">
                <a href="teacher-change-password.php?voluntary=1" class="w-full py-2.5 rounded-md font-bold text-brand-green bg-brand-lightGreen text-center">Change Password</a>
                <a href="teacher_logout.php" class="w-full py-2.5 rounded-md font-bold text-white bg-brand-maroon text-center">Logout</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow">
        <section id="page-teacher" class="py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Welcome Strip -->
            <div class="mb-6 text-sm text-gray-600">
                <?= $greeting ?>, <strong class="text-brand-green"><?= htmlspecialchars($teacher['name']) ?></strong>
                · Staff ID: <strong class="text-brand-green"><?= htmlspecialchars($teacher['staff_id']) ?></strong>
                <?php if (!empty($teacher['department'])): ?>
                    · Department: <strong class="text-brand-green"><?= htmlspecialchars($teacher['department']) ?></strong>
                <?php endif; ?>
                <?php if (!empty($teacher['classes_taught'])): ?>
                    · Classes Taught: <strong class="text-brand-green"><?= htmlspecialchars($teacher['classes_taught']) ?></strong>
                <?php endif; ?>
                · Last login: <?= date('d M Y, g:ia', $_SESSION['teacher_logged_in_at']) ?>
            </div>

            <?php if (!empty($_SESSION['teacher_flash'])): 
                $flash = $_SESSION['teacher_flash'];
                unset($_SESSION['teacher_flash']);
                $bgColor = $flash['type'] === 'success' ? 'bg-green-50 text-green-800 border-green-200' : 'bg-red-50 text-red-800 border-red-200';
            ?>
                <div class="mb-6 p-4 rounded-xl border <?= $bgColor ?> text-xs font-bold flex items-center gap-2">
                    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-triangle' ?>" class="w-4 h-4"></i>
                    <span><?= htmlspecialchars($flash['message']) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($teacher['is_class_teacher'])): ?>
                <div class="mb-6 p-6 rounded-2xl bg-amber-500 text-gray-950 shadow-md border-2 border-amber-400 flex flex-wrap justify-between items-center gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full bg-gray-950 text-amber-400 font-black text-[11px] uppercase tracking-wider">⭐ Elevated Role</span>
                            <h2 class="text-xl font-black">Class Teacher: <?= htmlspecialchars($teacher['class_teacher_of']) ?></h2>
                        </div>
                        <p class="text-xs font-medium text-amber-950 mt-1">You are assigned as Class Teacher for <strong><?= htmlspecialchars($teacher['class_teacher_of']) ?></strong>. You can publish class timetables and announcements to fellow teachers & students.</p>
                    </div>
                    <button onclick="openModal('modal-post-class-announcement');" class="px-5 py-3 bg-gray-950 text-amber-400 font-extrabold rounded-xl text-xs hover:bg-gray-800 shadow flex items-center gap-2 transition-transform hover:-translate-y-0.5 active:scale-95">
                        <i data-lucide="megaphone" class="w-4 h-4 text-amber-400"></i> Post Class Announcement
                    </button>
                </div>
            <?php endif; ?>

            <!-- Quick Access: Student Roster & Alumni Registry -->
            <div class="mb-8 flex flex-wrap gap-4">
                <a href="students_view.php"
                   class="min-w-[260px] max-w-sm bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md hover:border-brand-green/30 transition-all group flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-brand-green/10 text-brand-green group-hover:bg-brand-green group-hover:text-white transition-colors">
                        <i data-lucide="user-check" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <div class="font-bold text-brand-green group-hover:text-brand-maroon transition-colors">Enrolled Students</div>
                        <div class="text-xs text-gray-500 mt-0.5">View live backend student list</div>
                    </div>
                </a>
                <a href="alumni_view.php"
                   class="min-w-[260px] max-w-sm bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md hover:border-brand-green/30 transition-all group flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-brand-maroon/10 text-brand-maroon group-hover:bg-brand-maroon group-hover:text-white transition-colors">
                        <i data-lucide="users" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <div class="font-bold text-brand-green group-hover:text-brand-maroon transition-colors">Alumni Registry</div>
                        <div class="text-xs text-gray-500 mt-0.5">View all registered alumni</div>
                    </div>
                </a>
            </div>

            <!-- Header Banner -->
            <div class="flex flex-wrap justify-between items-center mb-8 gap-4 bg-brand-green text-white p-8 rounded-2xl shadow-lg">
                <div>
                    <span class="bg-brand-gold text-brand-green px-3.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider">Teacher Operations</span>
                    <h1 class="text-3xl font-bold mt-2">Teacher Operations & UNEB Roster</h1>
                    <p class="text-sm text-gray-200 mt-1">Manage teaching timetables, duty rosters, discipline logs, and class/stream UNEB syllabus coverage.</p>
                </div>
                <div class="flex gap-3">
                    <button onclick="openModal('modal-add-discipline');" class="px-5 py-2.5 bg-brand-maroon hover:bg-red-900 font-bold rounded-lg text-xs flex items-center gap-2 shadow">
                        <i data-lucide="alert-circle" class="w-4 h-4"></i> Log Discipline Case
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-3 mb-8">
                <button onclick="switchTeacherTab('tab-teacher-syllabus');" id="btn-tab-teacher-syllabus" class="tab-btn active px-5 py-2.5 rounded-lg text-sm font-bold flex items-center gap-2 border border-gray-200"><i data-lucide="book-open-check" class="w-4 h-4"></i> UNEB Syllabus Coverage</button>
                <button onclick="switchTeacherTab('tab-teacher-announcements');" id="btn-tab-teacher-announcements" class="tab-btn px-5 py-2.5 rounded-lg text-sm font-bold flex items-center gap-2 border border-gray-200 text-gray-700"><i data-lucide="megaphone" class="w-4 h-4"></i> Class Announcements & Timetables</button>
                <button onclick="switchTeacherTab('tab-teacher-schedule');" id="btn-tab-teacher-schedule" class="tab-btn px-5 py-2.5 rounded-lg text-sm font-bold flex items-center gap-2 border border-gray-200 text-gray-700"><i data-lucide="clock" class="w-4 h-4"></i> Personal Teaching Schedule</button>
                <button onclick="switchTeacherTab('tab-teacher-roster');" id="btn-tab-teacher-roster" class="tab-btn px-5 py-2.5 rounded-lg text-sm font-bold flex items-center gap-2 border border-gray-200 text-gray-700"><i data-lucide="calendar-check" class="w-4 h-4"></i> Teacher On Duty Roster</button>
                <button onclick="switchTeacherTab('tab-teacher-discipline');" id="btn-tab-teacher-discipline" class="tab-btn px-5 py-2.5 rounded-lg text-sm font-bold flex items-center gap-2 border border-gray-200 text-gray-700"><i data-lucide="shield-alert" class="w-4 h-4"></i> Discipline & Behavioral Log</button>
            </div>

            <!-- TAB 1: UNEB SYLLABUS COVERAGE TRACKER -->
            <div id="tab-teacher-syllabus" class="teacher-tab-content space-y-6">
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex flex-wrap justify-between items-center mb-8 gap-4">
                        <div>
                            <h3 class="text-2xl font-bold text-brand-green">UNEB Syllabus Coverage Tracker</h3>
                            <p class="text-xs text-gray-500 mt-1">Filter syllabus by Class and Stream. Department heads can upload official subject syllabus files.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <div>
                                <label class="text-xs font-bold text-gray-600 block uppercase">Class</label>
                                <select id="syllabus-filter-class" onchange="renderSyllabusTracker();" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-green">
                                    <option value="Senior 1">Senior 1</option><option value="Senior 2">Senior 2</option>
                                    <option value="Senior 3">Senior 3</option><option value="Senior 4">Senior 4</option>
                                    <option value="Senior 5">Senior 5</option><option value="Senior 6">Senior 6</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-600 block uppercase">Stream</label>
                                <select id="syllabus-filter-stream" onchange="renderSyllabusTracker();" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-green">
                                    <option value="North">Stream North</option><option value="South">Stream South</option>
                                    <option value="East">Stream East</option><option value="West">Stream West</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-600 block uppercase">Subject</label>
                                <select id="syllabus-filter-subject" onchange="renderSyllabusTracker();" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-green">
                                    <option value="Physics">Physics</option><option value="Mathematics">Mathematics</option>
                                    <option value="Chemistry">Chemistry</option><option value="Biology">Biology</option>
                                    <option value="English Language">English Language</option>
                                </select>
                            </div>
                            <div class="pt-4">
                              <a href="library.php" class="px-4 py-2.5 bg-brand-gold text-brand-green font-bold rounded-lg text-xs shadow hover:bg-yellow-400 flex items-center gap-1.5">
    <i data-lucide="upload-cloud" class="w-4 h-4"></i> Upload Library Book
</a>

</div>
                        </div>
                    </div>
                    <div id="syllabus-summary-box" class="bg-brand-lightGreen p-6 rounded-2xl mb-8 flex justify-between items-center border border-brand-green/20"></div>
                    <div id="syllabus-topics-checklist" class="space-y-4"></div>
                </div>
            </div>

            <!-- TAB 2: PERSONAL TEACHING SCHEDULE -->
            <div id="tab-teacher-schedule" class="teacher-tab-content hidden space-y-6">
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-bold text-brand-green">My Personal Teaching Schedule</h3>
                        <span class="text-xs font-bold text-gray-500">Term III - 2026 Timetable</span>
                    </div>
                    <div id="teacher-schedule-container" class="overflow-x-auto"></div>
                </div>
            </div>

            <!-- TAB 3: TEACHER ON DUTY ROSTER -->
            <div id="tab-teacher-roster" class="teacher-tab-content hidden space-y-6">
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-2xl font-bold text-brand-green mb-6">Weekly Teacher On Duty (TOD) Roster</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="bg-brand-green text-white font-bold">
                                    <th class="p-3.5">Week / Dates</th><th class="p-3.5">Senior Duty Teacher</th>
                                    <th class="p-3.5">Assistant Duty Teacher</th><th class="p-3.5">Primary Focus Area</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr class="hover:bg-gray-50"><td class="p-3.5 font-bold text-brand-maroon">Week 1 (Sept 15 - Sept 21)</td><td class="p-3.5">Mr. Mukasa Denis (Physics Dept)</td><td class="p-3.5">Ms. Namatovu Sarah (English Dept)</td><td class="p-3.5 text-xs bg-yellow-50 text-yellow-800 rounded font-semibold">Dining Hall & Evening Prep Supervision</td></tr>
                                <tr class="hover:bg-gray-50"><td class="p-3.5 font-bold text-brand-green">Week 2 (Sept 22 - Sept 28)</td><td class="p-3.5">Mr. Okello Patrick (Math Dept)</td><td class="p-3.5">Mrs. Akello Grace (Chemistry Dept)</td><td class="p-3.5 text-xs bg-green-50 text-green-800 rounded font-semibold">Campus Cleanliness & Assembly Rollcall</td></tr>
                                <tr class="hover:bg-gray-50"><td class="p-3.5 font-bold text-brand-green">Week 3 (Sept 29 - Oct 5)</td><td class="p-3.5">Dr. Kiggundu John (Biology Dept)</td><td class="p-3.5">Ms. Atuhaire Brenda (History Dept)</td><td class="p-3.5 text-xs bg-blue-50 text-blue-800 rounded font-semibold">Library Silence & Dormitory Lights Out</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 4: DISCIPLINE LOG -->
            <div id="tab-teacher-discipline" class="teacher-tab-content hidden space-y-6">
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-bold text-brand-green">Class Discipline & Behavioral Log</h3>
                        <button onclick="openModal('modal-add-discipline');" class="px-4 py-2.5 bg-brand-maroon text-white font-bold rounded-lg text-xs shadow hover:bg-red-900 flex items-center gap-1.5">
                            <i data-lucide="plus" class="w-4 h-4"></i> Add Behavioral Record
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-100 text-gray-700 font-bold border-b border-gray-200">
                                    <th class="p-3.5">Date</th><th class="p-3.5">Student Name</th>
                                    <th class="p-3.5">Class & Stream</th><th class="p-3.5">Category</th>
                                    <th class="p-3.5">Incident Note</th><th class="p-3.5">Action Taken</th>
                                </tr>
                            </thead>
                            <tbody id="discipline-table-body" class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 5: CLASS ANNOUNCEMENTS & TIMETABLES -->
            <div id="tab-teacher-announcements" class="teacher-tab-content hidden space-y-6">
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex flex-wrap justify-between items-center mb-6 gap-4 border-b border-gray-100 pb-4">
                        <div>
                            <h3 class="text-2xl font-bold text-brand-green flex items-center gap-2">
                                <i data-lucide="megaphone" class="w-6 h-6 text-amber-600"></i> Class Announcements & Timetables
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">Official announcements, timetables, and class updates published by Class Teachers.</p>
                        </div>
                        <?php if (!empty($teacher['is_class_teacher'])): ?>
                            <button onclick="openModal('modal-post-class-announcement');" class="px-4 py-2.5 bg-amber-600 text-white font-bold rounded-xl text-xs hover:bg-amber-700 shadow flex items-center gap-2">
                                <i data-lucide="plus-circle" class="w-4 h-4"></i> Post Announcement for <?= htmlspecialchars($teacher['class_teacher_of']) ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-4">
                        <?php if (!empty($classAnnouncements)): ?>
                            <?php foreach ($classAnnouncements as $ann): ?>
                                <div class="p-6 rounded-2xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:shadow-md transition-all">
                                    <div class="flex flex-wrap justify-between items-start gap-2 mb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="px-3 py-1 bg-amber-100 text-amber-900 font-extrabold text-xs rounded-full border border-amber-200">
                                                <?= htmlspecialchars($ann['class_level']) ?>
                                            </span>
                                            <h4 class="text-lg font-bold text-gray-900"><?= htmlspecialchars($ann['title']) ?></h4>
                                        </div>
                                        <span class="text-xs font-mono text-gray-500"><?= date('d M Y, g:ia', strtotime($ann['created_at'])) ?></span>
                                    </div>
                                    <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed mb-3"><?= htmlspecialchars($ann['content']) ?></p>
                                    <div class="text-xs text-gray-500 font-medium flex items-center gap-1">
                                        <i data-lucide="user-check" class="w-3.5 h-3.5 text-amber-600"></i> Posted by <strong class="text-gray-800"><?= htmlspecialchars($ann['posted_by_name']) ?></strong>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-12 text-center text-gray-500 text-sm bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                                <i data-lucide="info" class="w-8 h-8 text-gray-400 mx-auto mb-2"></i>
                                No class announcements or timetables posted yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- MODAL: UPLOAD SYLLABUS -->
    <div id="modal-upload-syllabus" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-brand-green">Upload Subject Syllabus</h3>
                <button onclick="closeModal('modal-upload-syllabus');" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <form onsubmit="handleUploadSyllabus(event);" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Department Head / Subject</label>
                    <input type="text" id="syl-subject" required placeholder="e.g. Physics Department - UNEB Syllabus" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Class</label>
                        <select id="syl-class" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="Senior 1">Senior 1</option><option value="Senior 2">Senior 2</option>
                            <option value="Senior 3">Senior 3</option><option value="Senior 4">Senior 4</option>
                            <option value="Senior 5">Senior 5</option><option value="Senior 6">Senior 6</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Stream</label>
                        <select id="syl-stream" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="North">North</option><option value="South">South</option>
                            <option value="East">East</option><option value="West">West</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Syllabus File (.pdf, .docx, .xlsx)</label>
                    <input type="file" id="syl-file" accept=".pdf,.docx,.xlsx" class="w-full text-xs text-gray-600 border border-gray-300 rounded-lg p-2">
                </div>
                <button type="submit" class="w-full py-3 bg-brand-gold text-brand-green font-bold rounded-lg hover:bg-yellow-400 text-sm">Attach Syllabus Document</button>
            </form>
        </div>
    </div>

    <!-- MODAL: ADD DISCIPLINE CASE -->
    <div id="modal-add-discipline" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-brand-maroon">Log Behavioral Record</h3>
                <button onclick="closeModal('modal-add-discipline');" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <form onsubmit="handleAddDiscipline(event);" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Student Name</label>
                    <input type="text" id="disc-name" required placeholder="e.g. Mukasa Brian" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Class</label>
                        <select id="disc-class" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="Senior 1">Senior 1</option><option value="Senior 2">Senior 2</option>
                            <option value="Senior 3">Senior 3</option><option value="Senior 4">Senior 4</option>
                            <option value="Senior 5">Senior 5</option><option value="Senior 6">Senior 6</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Stream</label>
                        <select id="disc-stream" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="North">North</option><option value="South">South</option>
                            <option value="East">East</option><option value="West">West</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Category</label>
                    <select id="disc-category" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="Lateness">Lateness</option>
                        <option value="Dress Code">Uniform Violation</option>
                        <option value="Disruption">Classroom Disruption</option>
                        <option value="Commendation">Academic Commendation</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Incident Note</label>
                    <textarea id="disc-note" rows="2" required placeholder="Describe what occurred..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Action Taken</label>
                    <input type="text" id="disc-action" required placeholder="e.g. Counseling & Parent notified" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <button type="submit" class="w-full py-3 bg-brand-maroon text-white font-bold rounded-lg hover:bg-red-900 text-sm">Submit Behavioral Record</button>
            </form>
        </div>
    </div>

    <!-- MODAL: POST CLASS ANNOUNCEMENT / TIMETABLE -->
    <div id="modal-post-class-announcement" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-gray-100 pb-3">
                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <i data-lucide="megaphone" class="w-5 h-5 text-amber-600"></i> Post Class Announcement
                </h3>
                <button onclick="closeModal('modal-post-class-announcement');" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <form action="post_class_announcement.php" method="post" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Target Class / Form</label>
                    <input type="text" name="class_level" value="<?= htmlspecialchars($teacher['class_teacher_of'] ?: 'Form 1') ?>" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-gray-100 font-bold text-gray-800 cursor-not-allowed">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Announcement / Timetable Title <span class="text-amber-600">*</span></label>
                    <input type="text" name="title" required placeholder="e.g. Form 4 Weekly Test & Revision Timetable" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Content & Details <span class="text-amber-600">*</span></label>
                    <textarea name="content" rows="5" required placeholder="Write announcement details, timetable schedules, or instructions for teachers and students..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500"></textarea>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 py-3 bg-gray-900 text-amber-400 font-extrabold rounded-xl hover:bg-gray-800 text-xs flex items-center justify-center gap-2 shadow">
                        <i data-lucide="send" class="w-4 h-4"></i> Publish Announcement
                    </button>
                    <button type="button" onclick="closeModal('modal-post-class-announcement');" class="w-28 py-3 border-2 border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 text-xs">Cancel</button>
                </div>
            </form>
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
                        <li class="flex items-center gap-3"><i data-lucide="mail" class="w-5 h-5 text-brand-gold"></i><span>info@thamani.ac.ug</span></li>
                    </ul>
                </div>
            </div>
            <div class="pt-8 border-t border-brand-darkGreen text-center text-gray-400 text-sm flex justify-between items-center">
                <span>© 2026 Thamani High School. All rights reserved.</span>
                <span>Signed in as <?= htmlspecialchars($teacher['staff_id']) ?></span>
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
    </script>
</body>
</html>