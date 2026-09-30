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
        $upd = thamani_db_prepare($conn, "UPDATE teachers SET full_name = ?, department = ?, is_class_teacher = ?, class_teacher_of = ?, class_teacher_stream = ?, classes_taught = ?, can_view_enrollments = ?, can_manage_duty_roster = ?, password_hash = ? WHERE id = ?");
        thamani_db_stmt_bind_param($upd, "ssisssiisi", $t['full_name'], $t['department'], $t['is_class_teacher'], $t['class_teacher_of'], $t['class_teacher_stream'], $t['classes_taught'], $t['can_view_enrollments'], $t['can_manage_duty_roster'], $t['password_hash'], $exists['id']);
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
        'status' => 'Pending'
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
        'status' => 'Pending'
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
        $upd = thamani_db_prepare($conn, "UPDATE students SET full_name = ?, class_level = ?, stream = ?, status = ?, academic_doc_path = ?, recommendation_doc_path = ?, medical_doc_path = ? WHERE id = ?");
        thamani_db_stmt_bind_param($upd, "sssssssi", $s['full_name'], $s['class'], $s['stream'], $s['status'], $s['acad_doc'], $s['rec_doc'], $s['med_doc'], $exists['id']);
        thamani_db_stmt_execute($upd);
        thamani_db_stmt_close($upd);
    } else {
        $ins = thamani_db_prepare($conn, "INSERT INTO students (
            full_name, date_of_birth, gender, nationality, lin_number, previous_school,
            class_level, stream, guardian_name, guardian_relationship, guardian_phone,
            guardian_email, guardian_address, guardian_occupation, emergency_name,
            emergency_phone, medical_notes, academic_doc_path, recommendation_doc_path,
            medical_doc_path, status, registered_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

        thamani_db_stmt_bind_param($ins, "sssssssssssssssssssss",
            $s['full_name'], $s['dob'], $s['gender'], $s['nationality'], $s['lin'], $s['prev_school'],
            $s['class'], $s['stream'], $s['gname'], $s['grel'], $s['gphone'],
            $s['gmail'], $s['gaddr'], $s['gocc'], $s['ename'],
            $s['ephone'], $s['med'], $s['acad_doc'], $s['rec_doc'],
            $s['med_doc'], $s['status']
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
// 4. SUBJECT MARKS SEED DATA
// ----------------------------------------------------
$term = 'Term III 2026';
$marksData = [
    ['name' => 'Kizito Joseph', 'subject' => 'Mathematics', 'score' => 88.5, 'max' => 100, 'comments' => 'Excellent problem solving'],
    ['name' => 'Kizito Joseph', 'subject' => 'Physics', 'score' => 79.0, 'max' => 100, 'comments' => 'Good understanding of mechanics'],
    ['name' => 'Babirye Sarah', 'subject' => 'Mathematics', 'score' => 92.0, 'max' => 100, 'comments' => 'Top student in class'],
    ['name' => 'Babirye Sarah', 'subject' => 'Physics', 'score' => 84.5, 'max' => 100, 'comments' => 'Strong practical work'],
    ['name' => 'Mwesigwa Daniel', 'subject' => 'Chemistry', 'score' => 76.0, 'max' => 100, 'comments' => 'Good stoichiometry lab work'],
    ['name' => 'Akello Peace', 'subject' => 'Chemistry', 'score' => 85.0, 'max' => 100, 'comments' => 'Very attentive in class']
];

foreach ($marksData as $mk) {
    if (isset($studentMap[$mk['name']])) {
        $st = $studentMap[$mk['name']];
        $sid = (int)$st['id'];
        $cLevel = $st['class_level'];

        $check = thamani_db_prepare($conn, "SELECT id FROM student_marks WHERE student_id = ? AND subject = ? AND term = ? LIMIT 1");
        thamani_db_stmt_bind_param($check, "iss", $sid, $mk['subject'], $term);
        thamani_db_stmt_execute($check);
        $mRes = thamani_db_stmt_get_result($check);
        $mkExists = $mRes ? thamani_db_fetch_assoc($mRes) : null;
        thamani_db_stmt_close($check);

        if (!$mkExists) {
            $insMk = thamani_db_prepare($conn, "INSERT INTO student_marks (student_id, class_level, subject, term, score, max_score, comments, recorded_by_teacher_id) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            thamani_db_stmt_bind_param($insMk, "isssdds", $sid, $cLevel, $mk['subject'], $term, $mk['score'], $mk['max'], $mk['comments']);
            thamani_db_stmt_execute($insMk);
            thamani_db_stmt_close($insMk);
        }
    }
}
echo "✓ Subject academic marks mock records created.\n";

echo "\n======================================================\n";
echo "SUCCESS: All mock data seeded successfully!\n";
echo "======================================================\n";
