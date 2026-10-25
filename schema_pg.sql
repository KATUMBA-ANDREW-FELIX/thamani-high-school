-- PostgreSQL Database Schema for Thamani High School (Optimized with Indexes)
-- Database Name: thamani_postgress

-- Enable UUID extension if needed in future
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- 1. Students Table
CREATE TABLE IF NOT EXISTS students (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    date_of_birth VARCHAR(50),
    gender VARCHAR(20),
    nationality VARCHAR(100),
    lin_number VARCHAR(100) UNIQUE,
    previous_school VARCHAR(255),
    class_level VARCHAR(50),
    stream VARCHAR(50),
    guardian_name VARCHAR(255),
    guardian_relationship VARCHAR(100),
    guardian_phone VARCHAR(50),
    guardian_email VARCHAR(255),
    guardian_address TEXT,
    guardian_occupation VARCHAR(100),
    emergency_name VARCHAR(255),
    emergency_phone VARCHAR(50),
    medical_notes TEXT,
    status VARCHAR(50) DEFAULT 'Pending',
    password_hash VARCHAR(255),
    must_change_password INT DEFAULT 1,
    account_active INT DEFAULT 1,
    last_login TIMESTAMP WITH TIME ZONE,
    registered_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 2. Teachers Table
CREATE TABLE IF NOT EXISTS teachers (
    id SERIAL PRIMARY KEY,
    staff_id VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    department VARCHAR(100),
    is_class_teacher INT DEFAULT 0,
    class_teacher_of VARCHAR(50),
    class_teacher_stream VARCHAR(50) DEFAULT 'Stream A',
    classes_taught VARCHAR(255),
    phone VARCHAR(30),
    can_send_sms INT DEFAULT 0,
    password_hash VARCHAR(255) NOT NULL,
    must_change_password INT DEFAULT 0,
    is_active INT DEFAULT 1,
    last_login TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 3. Admins Table
CREATE TABLE IF NOT EXISTS admins (
    id SERIAL PRIMARY KEY,
    admin_id VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    must_change_password INT DEFAULT 0,
    is_active INT DEFAULT 1,
    last_login TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 4. Alumni Table
CREATE TABLE IF NOT EXISTS alumni (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    year VARCHAR(20),
    profession VARCHAR(255),
    phone VARCHAR(50),
    email VARCHAR(255),
    editor_password_hash VARCHAR(255),
    is_page_editor INT DEFAULT 0,
    editor_active INT DEFAULT 0,
    editor_last_login TIMESTAMP WITH TIME ZONE
);
ALTER TABLE alumni ADD COLUMN IF NOT EXISTS editor_password_hash VARCHAR(255);
ALTER TABLE alumni ADD COLUMN IF NOT EXISTS is_page_editor INT DEFAULT 0;
ALTER TABLE alumni ADD COLUMN IF NOT EXISTS editor_active INT DEFAULT 0;
ALTER TABLE alumni ADD COLUMN IF NOT EXISTS editor_last_login TIMESTAMP WITH TIME ZONE;

CREATE TABLE IF NOT EXISTS alumni_page_content (
    id INT PRIMARY KEY,
    content_json TEXT NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 8. Class Timetables Table
CREATE TABLE IF NOT EXISTS class_timetables (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    class_level VARCHAR(50) NOT NULL,
    stream VARCHAR(50) DEFAULT 'All Streams',
    schedule_json TEXT,
    file_name VARCHAR(255),
    file_path TEXT,
    file_size INT,
    uploaded_by INT,
    is_active INT DEFAULT 1,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 5. Academic Calendar & Fee Structure Documents Table
CREATE TABLE IF NOT EXISTS calendar_documents (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    doc_type VARCHAR(50) NOT NULL DEFAULT 'calendar',
    description TEXT,
    file_name VARCHAR(255),
    stored_name VARCHAR(255),
    file_path TEXT NOT NULL,
    file_size BIGINT,
    mime_type VARCHAR(100),
    uploaded_by INT,
    is_active INT DEFAULT 1,
    uploaded_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 6. Photo Gallery Table
CREATE TABLE IF NOT EXISTS gallery_photos (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    caption TEXT,
    category VARCHAR(100),
    file_name VARCHAR(255),
    stored_name VARCHAR(255),
    file_path TEXT NOT NULL,
    file_size BIGINT,
    mime_type VARCHAR(100),
    uploaded_by INT,
    is_active INT DEFAULT 1,
    uploaded_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 7. E-Library Resources Table
CREATE TABLE IF NOT EXISTS library_resources (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255),
    subject VARCHAR(100),
    category VARCHAR(100),
    class_level VARCHAR(50),
    description TEXT,
    file_name VARCHAR(255),
    stored_name VARCHAR(255),
    file_path TEXT NOT NULL,
    file_size BIGINT,
    mime_type VARCHAR(100),
    uploaded_by INT,
    is_active INT DEFAULT 1,
    uploaded_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 8. Class & Teacher Announcements Table
CREATE TABLE IF NOT EXISTS class_announcements (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    class_level VARCHAR(50) NOT NULL,
    posted_by_teacher_id INT,
    posted_by_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS school_settings (
    setting_key VARCHAR(255) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sms_contacts (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    category VARCHAR(30) DEFAULT 'Other',
    class_level VARCHAR(50) DEFAULT '',
    stream VARCHAR(50) DEFAULT '',
    notes TEXT DEFAULT '',
    is_active INT DEFAULT 1,
    created_by INT DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sms_logs (
    id SERIAL PRIMARY KEY,
    batch_id VARCHAR(32),
    sender_id INT DEFAULT 0,
    sender_role VARCHAR(20) DEFAULT 'admin',
    recipient_count INT DEFAULT 0,
    category_filter VARCHAR(50) DEFAULT 'All',
    message TEXT,
    status VARCHAR(20) DEFAULT 'SUCCESS',
    api_response TEXT DEFAULT '',
    sent_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Safe migrations for existing installations
ALTER TABLE teachers ADD COLUMN IF NOT EXISTS is_class_teacher INT DEFAULT 0;
ALTER TABLE teachers ADD COLUMN IF NOT EXISTS class_teacher_of VARCHAR(50);
ALTER TABLE teachers ADD COLUMN IF NOT EXISTS class_teacher_stream VARCHAR(50) DEFAULT 'Stream A';
ALTER TABLE teachers ADD COLUMN IF NOT EXISTS classes_taught VARCHAR(255);
ALTER TABLE teachers ADD COLUMN IF NOT EXISTS phone VARCHAR(30);
ALTER TABLE teachers ADD COLUMN IF NOT EXISTS can_send_sms INT DEFAULT 0;
ALTER TABLE students ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255);
ALTER TABLE students ADD COLUMN IF NOT EXISTS must_change_password INT DEFAULT 1;
ALTER TABLE students ADD COLUMN IF NOT EXISTS account_active INT DEFAULT 1;
ALTER TABLE students ADD COLUMN IF NOT EXISTS last_login TIMESTAMP WITH TIME ZONE;

-- Database Performance Indexes
CREATE INDEX IF NOT EXISTS idx_students_status ON students(status);
CREATE INDEX IF NOT EXISTS idx_students_class ON students(class_level);
CREATE INDEX IF NOT EXISTS idx_teachers_active ON teachers(is_active);
CREATE INDEX IF NOT EXISTS idx_calendar_active ON calendar_documents(is_active, uploaded_at DESC);
CREATE INDEX IF NOT EXISTS idx_gallery_active ON gallery_photos(is_active, uploaded_at DESC);
CREATE INDEX IF NOT EXISTS idx_library_active ON library_resources(is_active, uploaded_at DESC);
CREATE INDEX IF NOT EXISTS idx_announcements_class ON class_announcements(class_level, created_at DESC);

-- Initial Default System Data
-- Default Admin Account (password: Admin@2026)
INSERT INTO admins (admin_id, full_name, email, password_hash, must_change_password, is_active)
VALUES ('ADM-2026-001', 'System Administrator', 'admin@thamani.ac.ug', '$2y$10$NmyZfb876NINiIdxUOgROOSHCRe5SmBF5nt1Ja1DTXjr7/zVj8J6O', 0, 1)
ON CONFLICT (email) DO UPDATE SET password_hash = '$2y$10$NmyZfb876NINiIdxUOgROOSHCRe5SmBF5nt1Ja1DTXjr7/zVj8J6O';

-- Default Teacher Account (password: Admin@2026)
INSERT INTO teachers (staff_id, full_name, email, department, is_class_teacher, class_teacher_of, classes_taught, password_hash, must_change_password, is_active)
VALUES ('TSC-2026-001', 'Mr. Denis Mukasa', 'teacher@thamani.ac.ug', 'Science & Technology', 1, 'Form 1', 'Form 1, Form 2, Form 3', '$2y$10$NmyZfb876NINiIdxUOgROOSHCRe5SmBF5nt1Ja1DTXjr7/zVj8J6O', 0, 1)
ON CONFLICT (email) DO UPDATE SET password_hash = '$2y$10$NmyZfb876NINiIdxUOgROOSHCRe5SmBF5nt1Ja1DTXjr7/zVj8J6O', is_class_teacher = 1, class_teacher_of = 'Form 1', classes_taught = 'Form 1, Form 2, Form 3';




-- Student Marks (used by save_marks.php / teacher & student dashboards)
CREATE TABLE IF NOT EXISTS student_marks (
    id SERIAL PRIMARY KEY,
    student_id INT NOT NULL,
    class_level VARCHAR(50) NOT NULL,
    stream VARCHAR(50) DEFAULT 'Stream A',
    subject VARCHAR(100) NOT NULL,
    term VARCHAR(20) NOT NULL,
    score REAL NOT NULL,
    max_score REAL DEFAULT 100,
    comments TEXT,
    recorded_by_teacher_id INT,
    window_id INT DEFAULT 0,
    assessment_type VARCHAR(20) DEFAULT 'EOT',
    academic_year VARCHAR(10) DEFAULT '2026',
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_marks_student ON student_marks(student_id);
CREATE INDEX IF NOT EXISTS idx_marks_window  ON student_marks(window_id);
CREATE INDEX IF NOT EXISTS idx_marks_student_window ON student_marks(student_id, window_id);

-- Reporting Windows (drives the marks entry selector + student report visibility)
CREATE TABLE IF NOT EXISTS reporting_windows (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    academic_year VARCHAR(10) NOT NULL,
    term VARCHAR(20) NOT NULL,
    assessment_type VARCHAR(20) DEFAULT 'EOT',
    is_open INT DEFAULT 1,
    is_published INT DEFAULT 0,
    show_positions INT DEFAULT 1,
    show_points INT DEFAULT 1,
    disabled_points_levels TEXT DEFAULT '',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Report Comments
CREATE TABLE IF NOT EXISTS report_comments (
    id SERIAL PRIMARY KEY,
    student_id INT NOT NULL,
    window_id INT NOT NULL,
    class_teacher_comment TEXT,
    head_teacher_comment TEXT,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_report_comments UNIQUE(student_id, window_id)
);

-- Stream Attendance
CREATE TABLE IF NOT EXISTS student_attendance (
    id SERIAL PRIMARY KEY,
    student_id INT NOT NULL,
    class_level VARCHAR(50) NOT NULL,
    attendance_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL,
    recorded_by_teacher_id INT,
    remarks TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Subject Assignments
CREATE TABLE IF NOT EXISTS teacher_subject_assignments (
    id SERIAL PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject VARCHAR(100) NOT NULL,
    class_level VARCHAR(50) NOT NULL,
    stream VARCHAR(50) DEFAULT 'All Streams',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Duty Rosters
CREATE TABLE IF NOT EXISTS teacher_duty_rosters (
    id SERIAL PRIMARY KEY,
    week_title VARCHAR(255),
    senior_duty_teacher VARCHAR(255),
    assistant_duty_teacher VARCHAR(255),
    primary_focus_area VARCHAR(255),
    notes TEXT,
    created_by INT,
    created_by_role VARCHAR(20),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Grading Scales
CREATE TABLE IF NOT EXISTS grading_scales (
    id SERIAL PRIMARY KEY,
    scale_name VARCHAR(100) DEFAULT 'O-Level Standard',
    min_score REAL NOT NULL,
    max_score REAL NOT NULL,
    grade VARCHAR(10) NOT NULL,
    points INT DEFAULT 1,
    remark VARCHAR(100),
    education_level VARCHAR(20) DEFAULT 'O-Level'
);

-- Streams
CREATE TABLE IF NOT EXISTS school_streams (
    id SERIAL PRIMARY KEY,
    stream_name VARCHAR(50) UNIQUE NOT NULL,
    stream_code VARCHAR(20),
    description TEXT,
    is_active INT DEFAULT 1,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Fees
CREATE TABLE IF NOT EXISTS class_fee_rates (
    id SERIAL PRIMARY KEY,
    class_level VARCHAR(50) UNIQUE NOT NULL,
    fee_amount REAL NOT NULL DEFAULT 850000,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS student_fees (
    id SERIAL PRIMARY KEY,
    student_id INT UNIQUE NOT NULL,
    class_level VARCHAR(50),
    term VARCHAR(30) DEFAULT 'Term III 2026',
    total_fee REAL DEFAULT 0,
    paid_amount REAL DEFAULT 0,
    balance REAL DEFAULT 0,
    status VARCHAR(20) DEFAULT 'UNPAID',
    pay_code VARCHAR(100),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bank_integrations (
    id SERIAL PRIMARY KEY,
    bank_code VARCHAR(30) UNIQUE NOT NULL,
    bank_name VARCHAR(255) NOT NULL,
    account_number VARCHAR(100),
    api_endpoint TEXT,
    api_key TEXT,
    secret_key TEXT,
    webhook_url TEXT,
    webhook_secret TEXT,
    environment VARCHAR(20) DEFAULT 'sandbox',
    is_active INT DEFAULT 1,
    last_sync_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bank_transactions (
    id SERIAL PRIMARY KEY,
    transaction_ref VARCHAR(100) UNIQUE NOT NULL,
    student_id INT,
    lin_number VARCHAR(100),
    student_name VARCHAR(255),
    bank_code VARCHAR(30) NOT NULL,
    payment_channel VARCHAR(50),
    amount REAL NOT NULL,
    payment_date TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    receipt_number VARCHAR(100),
    raw_payload TEXT,
    status VARCHAR(20) DEFAULT 'SUCCESS',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Add pay_code column to students (used by ensure_student_pay_codes())
ALTER TABLE students ADD COLUMN IF NOT EXISTS pay_code VARCHAR(100);
CREATE INDEX IF NOT EXISTS idx_marks_student_window ON student_marks(student_id, window_id);
CREATE INDEX IF NOT EXISTS idx_windows_published ON reporting_windows(is_published);
CREATE INDEX IF NOT EXISTS idx_windows_open ON reporting_windows(is_open);