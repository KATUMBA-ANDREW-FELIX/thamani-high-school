<?php
/**
 * Thamani High School - Database Seed & Mock Data Generator
 * --------------------------------------------------------
 * Run this script to populate realistic test data for:
 * 1. Admins & Teachers with different role levels and enrollment access permissions.
 * 2. Enrolling applicants (Pending), Admitted students (Enrolled), and Rejected applicants.
 * 3. Daily Attendance records.
 * 4. Subject Academic Marks.
 */

require_once __DIR__ . '/conn.php';

echo "Populating Thamani High School mock database...\n";

$validHash = '$2y$10$NmyZfb876NINiIdxUOgROOSHCRe5SmBF5nt1Ja1DTXjr7/zVj8J6O'; // Password: Admin@2026

// ----------------------------------------------------
// 1. TEACHERS SEED DATA
// ----------------------------------------------------
$teachers = [
    [
        'staff_id' => 'TSC-2026-001',
        'full_name' => 'Mr. Denis Mukasa',
        'email' => 'teacher@thamani.ac.ug',
        'department' => 'Mathematics',
        'is_class_teacher' => 1,
        'class_teacher_of' => 'Senior 1',
        'class_teacher_stream' => 'Stream A',
        'classes_taught' => 'Senior 1, Senior 3',
        'can_view_enrollments' => 1,
        'can_manage_duty_roster' => 1,
        'password_hash' => $validHash
    ],
    [
        'staff_id' => 'TSC-2026-002',
        'full_name' => 'Mrs. Grace Atuhaire',
        'email' => 'grace.atuhaire@thamani.ac.ug',
        'department' => 'Physics',
        'is_class_teacher' => 0,
        'class_teacher_of' => '',
        'class_teacher_stream' => 'Stream A',
        'classes_taught' => 'Senior 1, Senior 2',
        'can_view_enrollments' => 0,
        'can_manage_duty_roster' => 0,
        'password_hash' => $validHash
    ],
    [
        'staff_id' => 'TSC-2026-003',
        'full_name' => 'Mr. Emmanuel Okello',
        'email' => 'emmanuel.okello@thamani.ac.ug',
        'department' => 'Chemistry',
        'is_class_teacher' => 1,
        'class_teacher_of' => 'Senior 2',
        'class_teacher_stream' => 'Stream B',
        'classes_taught' => 'Senior 2, Senior 4',
        'can_view_enrollments' => 0,
        'can_manage_duty_roster' => 0,
        'password_hash' => $validHash
    ]
];

foreach ($teachers as $t) {
    $stmt = thamani_db_prepare($conn, "SELECT id FROM teachers WHERE staff_id = ? OR email = ? LIMIT 1");
    thamani_db_stmt_bind_param($stmt, "ss", $t['staff_id'], $t['email']);
    thamani_db_stmt_execute($stmt);
    $res = thamani_db_stmt_get_result($stmt);
    $exists = $res ? thamani_db_fetch_assoc($res) : null;
    thamani_db_stmt_close($stmt);

    if ($exists) {
        $upd = thamani_db_prepare($conn, "UPDATE teachers SET full_name = ?, email = ?, department = ?, is_class_teacher = ?, class_teacher_of = ?, class_teacher_stream = ?, classes_taught = ?, can_view_enrollments = ?, can_manage_duty_roster = ?, password_hash = ? WHERE id = ?");
        thamani_db_stmt_bind_param($upd, "sssissisiisi", $t['full_name'], $t['email'], $t['department'], $t['is_class_teacher'], $t['class_teacher_of'], $t['class_teacher_stream'], $t['classes_taught'], $t['can_view_enrollments'], $t['can_manage_duty_roster'], $t['password_hash'], $exists['id']);
        thamani_db_stmt_execute($upd);
        thamani_db_stmt_close($upd);
    } else {
        $ins = thamani_db_prepare($conn, "INSERT INTO teachers (staff_id, full_name, email, department, is_class_teacher, class_teacher_of, class_teacher_stream, classes_taught, can_view_enrollments, can_manage_duty_roster, password_hash, must_change_password, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)");
        thamani_db_stmt_bind_param($ins, "ssssisssiss", $t['staff_id'], $t['full_name'], $t['email'], $t['department'], $t['is_class_teacher'], $t['class_teacher_of'], $t['class_teacher_stream'], $t['classes_taught'], $t['can_view_enrollments'], $t['can_manage_duty_roster'], $t['password_hash']);
        thamani_db_stmt_execute($ins);
        thamani_db_stmt_close($ins);
    }
}
echo "✓ Teachers seed data populated.\n";

// Seed default personal schedules for Mr. Denis Mukasa (TSC-2026-001)
$stmtM = thamani_db_query($conn, "SELECT id FROM teachers WHERE staff_id = 'TSC-2026-001' LIMIT 1");
if ($stmtM && $tr = thamani_db_fetch_assoc($stmtM)) {
    $tId = (int)$tr['id'];
    $cntRes = thamani_db_query($conn, "SELECT COUNT(*) as cnt FROM teacher_personal_schedules WHERE teacher_id = {$tId}");
    $cntRow = $cntRes ? thamani_db_fetch_assoc($cntRes) : null;
    if (empty($cntRow['cnt'])) {
        $defaultSchedules = [
            ['day' => 'Monday', 'start' => '08:00 AM', 'end' => '09:20 AM', 'subject' => 'Mathematics', 'class' => 'Senior 1', 'stream' => 'Stream A', 'room' => 'Room 101', 'notes' => 'Algebra & Linear Equations intro'],
            ['day' => 'Monday', 'start' => '11:00 AM', 'end' => '12:20 PM', 'subject' => 'Physics', 'class' => 'Senior 3', 'stream' => 'Stream B', 'room' => 'Physics Lab', 'notes' => 'Mechanics & Newton Laws Practical'],
            ['day' => 'Tuesday', 'start' => '09:20 AM', 'end' => '10:40 AM', 'subject' => 'Mathematics', 'class' => 'Senior 1', 'stream' => 'Stream A', 'room' => 'Room 101', 'notes' => 'Quadratic Expressions'],
            ['day' => 'Wednesday', 'start' => '08:00 AM', 'end' => '09:20 AM', 'subject' => 'Physics', 'class' => 'Senior 3', 'stream' => 'Stream B', 'room' => 'Science Lab 2', 'notes' => 'Optical Instruments & Lenses'],
            ['day' => 'Thursday', 'start' => '02:00 PM', 'end' => '03:30 PM', 'subject' => 'Mathematics', 'class' => 'Senior 1', 'stream' => 'Stream A', 'room' => 'Room 101', 'notes' => 'Weekly Quiz & Revision Session'],
            ['day' => 'Friday', 'start' => '10:40 AM', 'end' => '12:00 PM', 'subject' => 'Physics', 'class' => 'Senior 3', 'stream' => 'Stream B', 'room' => 'Main Hall', 'notes' => 'Past Paper Seminar']
        ];
        foreach ($defaultSchedules as $sch) {
            $insS = thamani_db_prepare($conn, "INSERT INTO teacher_personal_schedules (teacher_id, day_of_week, start_time, end_time, subject, class_level, stream, room_no, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            thamani_db_stmt_bind_param($insS, "issssssss", $tId, $sch['day'], $sch['start'], $sch['end'], $sch['subject'], $sch['class'], $sch['stream'], $sch['room'], $sch['notes']);
            thamani_db_stmt_execute($insS);
            thamani_db_stmt_close($insS);
        }
        echo "✓ Sample teaching schedule seeded for teacher TSC-2026-001.\n";
    }
}

// ----------------------------------------------------
// 2. STUDENTS SEED DATA
// ----------------------------------------------------
$dummyDocPath = 'uploads/enrollment_docs/sample_academic_doc.pdf';
if (!is_dir(__DIR__ . '/uploads/enrollment_docs')) {
    @mkdir(__DIR__ . '/uploads/enrollment_docs', 0755, true);
}
@file_put_contents(__DIR__ . '/' . $dummyDocPath, "THAMANI HIGH SCHOOL SAMPLE DOCUMENT");

$students = [
    [
        'full_name' => 'Kato Ivan',
        'dob' => '2010-04-12',
        'gender' => 'Male',
        'nationality' => 'Ugandan',
        'lin' => 'LIN-2026-001',
        'prev_school' => 'Kampala Parents School',
        'class' => 'Senior 1',
        'stream' => 'Stream A',
        'gname' => 'Kato Moses',
        'grel' => 'Father',
        'gphone' => '+256 772 100 200',
        'gmail' => 'moses.kato@gmail.com',
        'gaddr' => 'Kakiri Town, Wakiso',
        'gocc' => 'Civil Engineer',
        'ename' => 'Kato Moses',
        'ephone' => '+256 772 100 200',
        'med' => 'No known allergies. Fit for sports.',
        'acad_doc' => $dummyDocPath,
        'rec_doc' => $dummyDocPath,
        'med_doc' => $dummyDocPath,
        'status' => 'Enrolled'
    ],
    [
        'full_name' => 'Nakalema Brenda',
        'dob' => '2009-08-25',
        'gender' => 'Female',
        'nationality' => 'Ugandan',
        'lin' => 'LIN-2026-002',
        'prev_school' => 'Gayaza Junior School',
        'class' => 'Senior 2',
        'stream' => 'Stream B',
        'gname' => 'Nakalema Sarah',
        'grel' => 'Mother',
        'gphone' => '+256 701 300 400',
        'gmail' => 'sarah.nakalema@gmail.com',
        'gaddr' => 'Gayaza Road, Wakiso',
        'gocc' => 'Accountant',
        'ename' => 'Nakalema Sarah',
        'ephone' => '+256 701 300 400',
        'med' => 'Mild asthmatic. Inhaler kept at sickbay.',
        'acad_doc' => $dummyDocPath,
        'rec_doc' => null,
        'med_doc' => $dummyDocPath,
        'status' => 'Enrolled'
    ],
    [
        'full_name' => 'Kizito Joseph',
        'dob' => '2010-01-15',
        'gender' => 'Male',
        'nationality' => 'Ugandan',
        'lin' => 'LIN-2026-003',
        'prev_school' => 'St. Joseph Primary School',
        'class' => 'Senior 1',
        'stream' => 'Stream A',
        'gname' => 'Kizito Charles',
        'grel' => 'Father',
        'gphone' => '+256 774 555 666',
        'gmail' => 'charles.kizito@yahoo.com',
        'gaddr' => 'Nansana, Wakiso',
        'gocc' => 'Merchant',
        'ename' => 'Kizito Charles',
        'ephone' => '+256 774 555 666',
        'med' => 'None.',
        'acad_doc' => $dummyDocPath,
        'rec_doc' => $dummyDocPath,
        'med_doc' => null,
        'status' => 'Enrolled'
    ],
    [
        'full_name' => 'Babirye Sarah',
        'dob' => '2010-06-30',
        'gender' => 'Female',
        'nationality' => 'Ugandan',
        'lin' => 'LIN-2026-004',
        'prev_school' => 'City Parents School',
        'class' => 'Senior 1',
        'stream' => 'Stream B',
        'gname' => 'Kintu Patrick',
        'grel' => 'Uncle',
        'gphone' => '+256 782 999 111',
        'gmail' => 'pkintu@corporate.co.ug',
        'gaddr' => 'Bweyogerere, Wakiso',
        'gocc' => 'IT Consultant',
        'ename' => 'Kintu Patrick',
        'ephone' => '+256 782 999 111',
        'med' => 'None.',
        'acad_doc' => $dummyDocPath,
        'rec_doc' => null,
        'med_doc' => null,
        'status' => 'Enrolled'
    ],
    [
        'full_name' => 'Mwesigwa Daniel',
        'dob' => '2009-03-10',
        'gender' => 'Male',
        'nationality' => 'Ugandan',
        'lin' => 'LIN-2026-005',
        'prev_school' => 'Greenhill Academy',
        'class' => 'Senior 2',
        'stream' => 'Stream A',
        'gname' => 'Mwesigwa Robert',
        'grel' => 'Father',
        'gphone' => '+256 772 444 888',
        'gmail' => 'robert.mwesigwa@outlook.com',
        'gaddr' => 'Entebbe Road, Kampala',
        'gocc' => 'Lawyer',
        'ename' => 'Mwesigwa Robert',
        'ephone' => '+256 772 444 888',
        'med' => 'Wears prescription glasses.',
        'acad_doc' => $dummyDocPath,
        'rec_doc' => $dummyDocPath,
        'med_doc' => null,
        'status' => 'Enrolled'
    ],
    [
        'full_name' => 'Akello Peace',
        'dob' => '2009-11-05',
        'gender' => 'Female',
        'nationality' => 'Ugandan',
        'lin' => 'LIN-2026-006',
        'prev_school' => 'Lira Army Primary',
        'class' => 'Senior 2',
        'stream' => 'Stream B',
        'gname' => 'Akello Mary',
        'grel' => 'Aunt',
        'gphone' => '+256 703 222 333',
        'gmail' => 'mary.akello@gmail.com',
        'gaddr' => 'Kakiri, Wakiso',
        'gocc' => 'Nurse',
        'ename' => 'Akello Mary',
        'ephone' => '+256 703 222 333',
        'med' => 'None.',
        'acad_doc' => $dummyDocPath,
        'rec_doc' => null,
        'med_doc' => null,
        'status' => 'Enrolled'
    ],
    [
        'full_name' => 'Tusiime Brian',
        'dob' => '2008-07-19',
        'gender' => 'Male',
        'nationality' => 'Ugandan',
        'lin' => 'LIN-2026-007',
        'prev_school' => 'Mbarara Junior School',
        'class' => 'Senior 3',
        'stream' => 'Stream A',
        'gname' => 'Tusiime David',
        'grel' => 'Father',
        'gphone' => '+256 775 888 999',
        'gmail' => 'dtusiime@gmail.com',
        'gaddr' => 'Kira, Wakiso',
        'gocc' => 'Business Executive',
        'ename' => 'Tusiime David',
        'ephone' => '+256 775 888 999',
        'med' => 'None.',
        'acad_doc' => $dummyDocPath,
        'rec_doc' => $dummyDocPath,
        'med_doc' => null,
        'status' => 'Enrolled'
    ],
    [
        'full_name' => 'Wasswa Patrick',
        'dob' => '2007-09-14',
        'gender' => 'Male',
        'nationality' => 'Ugandan',
        'lin' => 'LIN-2026-008',
        'prev_school' => 'Mengo Senior School',
        'class' => 'Senior 4',
        'stream' => 'Stream A',
        'gname' => 'Nsubuga Henry',
        'grel' => 'Guardian',
        'gphone' => '+256 702 777 666',
        'gmail' => 'hnsubuga@gmail.com',
        'gaddr' => 'Rubaga, Kampala',
        'gocc' => 'Teacher',
        'ename' => 'Nsubuga Henry',
        'ephone' => '+256 702 777 666',
        'med' => 'None.',
        'acad_doc' => $dummyDocPath,
        'rec_doc' => null,
        'med_doc' => null,
        'status' => 'Enrolled'
    ],
    [
        'full_name' => 'Mukasa Ronald',
        'dob' => '2010-12-01',
        'gender' => 'Male',
        'nationality' => 'Ugandan',
        'lin' => 'LIN-2026-009',
        'prev_school' => 'Nakasero Primary School',
        'class' => 'Senior 1',
        'stream' => 'Stream A',
        'gname' => 'Mukasa Simon',
        'grel' => 'Father',
        'gphone' => '+256 771 000 999',
        'gmail' => 'simon.mukasa@gmail.com',
        'gaddr' => 'Kampala',
        'gocc' => 'Trader',
        'ename' => 'Mukasa Simon',
        'ephone' => '+256 771 000 999',
        'med' => 'None.',
        'acad_doc' => null,
        'rec_doc' => null,
        'med_doc' => null,
        'status' => 'Rejected'
    ]
];

foreach ($students as $s) {
    $stmt = thamani_db_prepare($conn, "SELECT id FROM students WHERE lin_number = ? LIMIT 1");
    thamani_db_stmt_bind_param($stmt, "s", $s['lin']);
    thamani_db_stmt_execute($stmt);
    $res = thamani_db_stmt_get_result($stmt);
    $exists = $res ? thamani_db_fetch_assoc($res) : null;
    thamani_db_stmt_close($stmt);

    if ($exists) {
        $upd = thamani_db_prepare($conn, "UPDATE students SET full_name = ?, class_level = ?, stream = ?, status = ?, academic_doc_path = ?, recommendation_doc_path = ?, medical_doc_path = ?, password_hash = ?, account_active = 1 WHERE id = ?");
        thamani_db_stmt_bind_param($upd, "ssssssssi", $s['full_name'], $s['class'], $s['stream'], $s['status'], $s['acad_doc'], $s['rec_doc'], $s['med_doc'], $validHash, $exists['id']);
        thamani_db_stmt_execute($upd);
        thamani_db_stmt_close($upd);
    } else {
        $ins = thamani_db_prepare($conn, "INSERT INTO students (
            full_name, date_of_birth, gender, nationality, lin_number, previous_school,
            class_level, stream, guardian_name, guardian_relationship, guardian_phone,
            guardian_email, guardian_address, guardian_occupation, emergency_name,
            emergency_phone, medical_notes, academic_doc_path, recommendation_doc_path,
            medical_doc_path, status, password_hash, account_active, registered_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");

        thamani_db_stmt_bind_param($ins, "ssssssssssssssssssssss",
            $s['full_name'], $s['dob'], $s['gender'], $s['nationality'], $s['lin'], $s['prev_school'],
            $s['class'], $s['stream'], $s['gname'], $s['grel'], $s['gphone'],
            $s['gmail'], $s['gaddr'], $s['gocc'], $s['ename'],
            $s['ephone'], $s['med'], $s['acad_doc'], $s['rec_doc'],
            $s['med_doc'], $s['status'], $validHash
        );
        thamani_db_stmt_execute($ins);
        thamani_db_stmt_close($ins);
    }
}
echo "✓ Student registry seed data populated.\n";

// ----------------------------------------------------
// 3. ATTENDANCE SEED DATA
// ----------------------------------------------------
$today = date('Y-m-d');
$studentMap = [];
$res = thamani_db_query($conn, "SELECT id, full_name, class_level FROM students WHERE status = 'Enrolled'");
if ($res) {
    while ($r = thamani_db_fetch_assoc($res)) {
        $studentMap[$r['full_name']] = $r;
    }
}

$attendanceRecords = [
    ['name' => 'Kizito Joseph', 'status' => 'Present', 'remarks' => 'On time'],
    ['name' => 'Babirye Sarah', 'status' => 'Late', 'remarks' => 'Arrived at 8:15 AM'],
    ['name' => 'Mwesigwa Daniel', 'status' => 'Present', 'remarks' => 'On time'],
    ['name' => 'Akello Peace', 'status' => 'Excused', 'remarks' => 'Medical appointment'],
    ['name' => 'Tusiime Brian', 'status' => 'Present', 'remarks' => 'On time'],
    ['name' => 'Wasswa Patrick', 'status' => 'Absent', 'remarks' => 'Unexcused']
];

foreach ($attendanceRecords as $att) {
    if (isset($studentMap[$att['name']])) {
        $st = $studentMap[$att['name']];
        $sid = (int)$st['id'];
        $cLevel = $st['class_level'];

        $check = thamani_db_prepare($conn, "SELECT id FROM student_attendance WHERE student_id = ? AND attendance_date = ? LIMIT 1");
        thamani_db_stmt_bind_param($check, "is", $sid, $today);
        thamani_db_stmt_execute($check);
        $cRes = thamani_db_stmt_get_result($check);
        $attExists = $cRes ? thamani_db_fetch_assoc($cRes) : null;
        thamani_db_stmt_close($check);

        if (!$attExists) {
            $insAtt = thamani_db_prepare($conn, "INSERT INTO student_attendance (student_id, class_level, attendance_date, status, recorded_by_teacher_id, remarks) VALUES (?, ?, ?, ?, 1, ?)");
            thamani_db_stmt_bind_param($insAtt, "issss", $sid, $cLevel, $today, $att['status'], $att['remarks']);
            thamani_db_stmt_execute($insAtt);
            thamani_db_stmt_close($insAtt);
        }
    }
}
echo "✓ Student attendance mock records created.\n";

// ----------------------------------------------------
// 5. REPORTING SYSTEM SEED DATA (WINDOWS, ASSIGNMENTS, SCALES, MARKS, COMMENTS)
// ----------------------------------------------------

// 5.1 Reporting Windows
$rwRows = [
    [
        'title' => 'Term III 2026 End of Term Exams (EOT)',
        'academic_year' => '2026',
        'term' => '3',
        'assessment_type' => 'EOT',
        'is_open' => 1,
        'is_published' => 1,
        'show_positions' => 1
    ],
    [
        'title' => 'Term III 2026 Mid-Term Examinations (MOT)',
        'academic_year' => '2026',
        'term' => '3',
        'assessment_type' => 'MOT',
        'is_open' => 0,
        'is_published' => 1,
        'show_positions' => 1
    ],
    [
        'title' => 'Term II 2026 End of Term Exams (EOT)',
        'academic_year' => '2026',
        'term' => '2',
        'assessment_type' => 'EOT',
        'is_open' => 0,
        'is_published' => 1,
        'show_positions' => 1
    ]
];

$winIds = [];
foreach ($rwRows as $rw) {
    $cStmt = thamani_db_prepare($conn, "SELECT id FROM reporting_windows WHERE title = ? LIMIT 1");
    thamani_db_stmt_bind_param($cStmt, "s", $rw['title']);
    thamani_db_stmt_execute($cStmt);
    $cRes = thamani_db_stmt_get_result($cStmt);
    $rwExists = $cRes ? thamani_db_fetch_assoc($cRes) : null;
    thamani_db_stmt_close($cStmt);

    if ($rwExists) {
        $winIds[$rw['assessment_type'] . '_' . $rw['term']] = (int)$rwExists['id'];
    } else {
        $iStmt = thamani_db_prepare($conn, "INSERT INTO reporting_windows (title, academic_year, term, assessment_type, is_open, is_published, show_positions) VALUES (?, ?, ?, ?, ?, ?, ?)");
        thamani_db_stmt_bind_param($iStmt, "ssssiii", $rw['title'], $rw['academic_year'], $rw['term'], $rw['assessment_type'], $rw['is_open'], $rw['is_published'], $rw['show_positions']);
        thamani_db_stmt_execute($iStmt);
        $newId = thamani_db_insert_id($conn);
        thamani_db_stmt_close($iStmt);
        $winIds[$rw['assessment_type'] . '_' . $rw['term']] = (int)$newId;
    }
}
echo "✓ Reporting windows seeded.\n";

$primaryWinId = $winIds['EOT_3'] ?? 1;

// 5.2 Teacher Subject Allocations
$teacherAllocations = [
    ['staff_id' => 'TSC-2026-001', 'subject' => 'Mathematics', 'class' => 'Senior 1', 'stream' => 'Stream A'],
    ['staff_id' => 'TSC-2026-001', 'subject' => 'English Language', 'class' => 'Senior 1', 'stream' => 'Stream A'],
    ['staff_id' => 'TSC-2026-001', 'subject' => 'Physics', 'class' => 'Senior 3', 'stream' => 'Stream B'],
    ['staff_id' => 'TSC-2026-002', 'subject' => 'Physics', 'class' => 'Senior 1', 'stream' => 'Stream A'],
    ['staff_id' => 'TSC-2026-002', 'subject' => 'Chemistry', 'class' => 'Senior 1', 'stream' => 'Stream A'],
    ['staff_id' => 'TSC-2026-003', 'subject' => 'Biology', 'class' => 'Senior 1', 'stream' => 'Stream A'],
    ['staff_id' => 'TSC-2026-003', 'subject' => 'Chemistry', 'class' => 'Senior 2', 'stream' => 'Stream B']
];

foreach ($teacherAllocations as $alloc) {
    $tRes = thamani_db_query($conn, "SELECT id FROM teachers WHERE staff_id = '{$alloc['staff_id']}' LIMIT 1");
    if ($tRes && $tr = thamani_db_fetch_assoc($tRes)) {
        $tId = (int)$tr['id'];
        $chkA = thamani_db_prepare($conn, "SELECT id FROM teacher_subject_assignments WHERE teacher_id = ? AND subject = ? AND class_level = ? AND stream = ? LIMIT 1");
        thamani_db_stmt_bind_param($chkA, "isss", $tId, $alloc['subject'], $alloc['class'], $alloc['stream']);
        thamani_db_stmt_execute($chkA);
        $resA = thamani_db_stmt_get_result($chkA);
        $aExists = $resA ? thamani_db_fetch_assoc($resA) : null;
        thamani_db_stmt_close($chkA);

        if (!$aExists) {
            $insA = thamani_db_prepare($conn, "INSERT INTO teacher_subject_assignments (teacher_id, subject, class_level, stream) VALUES (?, ?, ?, ?)");
            thamani_db_stmt_bind_param($insA, "isss", $tId, $alloc['subject'], $alloc['class'], $alloc['stream']);
            thamani_db_stmt_execute($insA);
            thamani_db_stmt_close($insA);
        }
    }
}
echo "✓ Teacher subject allocations seeded.\n";

// 5.3 UNEB O-Level & A-Level Grading Scales
$gradingRules = [
    ['O-Level Standard', 85, 100, 'D1', 1, 'Distinction 1', 'O-Level'],
    ['O-Level Standard', 75, 84.99, 'D2', 2, 'Distinction 2', 'O-Level'],
    ['O-Level Standard', 65, 74.99, 'C3', 3, 'Credit 3', 'O-Level'],
    ['O-Level Standard', 60, 64.99, 'C4', 4, 'Credit 4', 'O-Level'],
    ['O-Level Standard', 55, 59.99, 'C5', 5, 'Credit 5', 'O-Level'],
    ['O-Level Standard', 50, 54.99, 'C6', 6, 'Credit 6', 'O-Level'],
    ['O-Level Standard', 45, 49.99, 'P7', 7, 'Pass 7', 'O-Level'],
    ['O-Level Standard', 40, 44.99, 'P8', 8, 'Pass 8', 'O-Level'],
    ['O-Level Standard', 0, 39.99, 'F9', 9, 'Fail 9', 'O-Level']
];

foreach ($gradingRules as $rule) {
    $chkG = thamani_db_prepare($conn, "SELECT id FROM grading_scales WHERE scale_name = ? AND grade = ? AND education_level = ? LIMIT 1");
    thamani_db_stmt_bind_param($chkG, "sss", $rule[0], $rule[3], $rule[6]);
    thamani_db_stmt_execute($chkG);
    $resG = thamani_db_stmt_get_result($chkG);
    $gExists = $resG ? thamani_db_fetch_assoc($resG) : null;
    thamani_db_stmt_close($chkG);

    if (!$gExists) {
        $insG = thamani_db_prepare($conn, "INSERT INTO grading_scales (scale_name, min_score, max_score, grade, points, remark, education_level) VALUES (?, ?, ?, ?, ?, ?, ?)");
        thamani_db_stmt_bind_param($insG, "sddsiss", $rule[0], $rule[1], $rule[2], $rule[3], $rule[4], $rule[5], $rule[6]);
        thamani_db_stmt_execute($insG);
        thamani_db_stmt_close($insG);
    }
}
echo "✓ UNEB grading scales seeded.\n";

// 5.4 Seed Marks across subjects for Enrolled Senior 1 Students
$s1StudentsRes = thamani_db_query($conn, "SELECT id, full_name, class_level, stream FROM students WHERE class_level = 'Senior 1' AND status = 'Enrolled'");
if ($s1StudentsRes) {
    $subjectsList = ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Geography', 'History', 'Commerce'];
    while ($st = thamani_db_fetch_assoc($s1StudentsRes)) {
        $stId = (int)$st['id'];
        $cLevel = $st['class_level'];
        $stream = $st['stream'] ?: 'Stream A';

        // Deterministic realistic scores for each student
        $baseSeed = $stId * 13;
        foreach ($subjectsList as $subIdx => $sub) {
            $score = 65 + (($baseSeed + $subIdx * 7) % 32);
            if ($score > 98) $score = 98;
            $remark = $score >= 80 ? 'Outstanding performance' : ($score >= 65 ? 'Good effort' : 'Requires steady practice');

            // Insert into student_marks with window_id, stream, assessment_type
            $chkM = thamani_db_query($conn, "SELECT id FROM student_marks WHERE student_id = {$stId} AND window_id = {$primaryWinId} AND subject = '{$sub}' LIMIT 1");
            if ($chkM && !thamani_db_fetch_assoc($chkM)) {
                $insM = thamani_db_prepare($conn, "INSERT INTO student_marks (student_id, class_level, stream, subject, term, window_id, assessment_type, academic_year, score, max_score, comments, recorded_by_teacher_id) VALUES (?, ?, ?, ?, 'Term III 2026', ?, 'EOT', '2026', ?, 100.00, ?, 1)");
                thamani_db_stmt_bind_param($insM, "isssids", $stId, $cLevel, $stream, $sub, $primaryWinId, $score, $remark);
                thamani_db_stmt_execute($insM);
                thamani_db_stmt_close($insM);
            }
        }

        // Seed Class Teacher comment
        $chkC = thamani_db_query($conn, "SELECT id FROM report_comments WHERE student_id = {$stId} AND window_id = {$primaryWinId} LIMIT 1");
        if ($chkC && !thamani_db_fetch_assoc($chkC)) {
            $ctComm = "An attentive and disciplined student with great academic potential. Recommended to maintain this consistency.";
            $hmComm = "Promising results. Keep aiming for academic excellence.";
            $insC = thamani_db_prepare($conn, "INSERT INTO report_comments (student_id, window_id, class_teacher_comment, head_teacher_comment) VALUES (?, ?, ?, ?)");
            thamani_db_stmt_bind_param($insC, "iiss", $stId, $primaryWinId, $ctComm, $hmComm);
            thamani_db_stmt_execute($insC);
            thamani_db_stmt_close($insC);
        }
    }
}
echo "✓ Comprehensive student marks & report comments seeded.\n";

echo "\n======================================================\n";
echo "SUCCESS: All mock data seeded successfully!\n";
echo "======================================================\n";

