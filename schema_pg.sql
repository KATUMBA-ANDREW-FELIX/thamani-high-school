-- PostgreSQL Database Schema for Thamani High School
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
    registered_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 2. Teachers Table
CREATE TABLE IF NOT EXISTS teachers (
    id SERIAL PRIMARY KEY,
    staff_id VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    department VARCHAR(100),
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
    email VARCHAR(255)
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

-- Initial Default System Data
-- Default Admin Account (password: Admin@2026)
INSERT INTO admins (admin_id, full_name, email, password_hash, must_change_password, is_active)
VALUES ('ADM-2026-001', 'System Administrator', 'admin@thamani.ac.ug', '$2y$10$NmyZfb876NINiIdxUOgROOSHCRe5SmBF5nt1Ja1DTXjr7/zVj8J6O', 0, 1)
ON CONFLICT (email) DO UPDATE SET password_hash = '$2y$10$NmyZfb876NINiIdxUOgROOSHCRe5SmBF5nt1Ja1DTXjr7/zVj8J6O';

-- Default Teacher Account (password: Admin@2026)
INSERT INTO teachers (staff_id, full_name, email, department, password_hash, must_change_password, is_active)
VALUES ('TSC-2026-001', 'Mr. Denis Mukasa', 'teacher@thamani.ac.ug', 'Science & Technology', '$2y$10$NmyZfb876NINiIdxUOgROOSHCRe5SmBF5nt1Ja1DTXjr7/zVj8J6O', 0, 1)
ON CONFLICT (email) DO UPDATE SET password_hash = '$2y$10$NmyZfb876NINiIdxUOgROOSHCRe5SmBF5nt1Ja1DTXjr7/zVj8J6O';
