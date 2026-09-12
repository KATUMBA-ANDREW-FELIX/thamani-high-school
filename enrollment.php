<?php
/**
 * Thamani High School - Student Enrollment Handler (mysqli)
 * -----------------------------------------------------
 * - Validates all registration fields
 * - Detects duplicate LIN / UNEB index numbers
 * - Inserts student record via mysqli prepared statements
 * - Injects success/error alert HTML into enrollment.html
 *
 * Requires: conn.php exposing a mysqli connection named $conn
 */

session_start();

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---------- 1. Collect + trim inputs ----------
    $full_name             = trim($_POST['full_name']             ?? '');
    $date_of_birth         = trim($_POST['date_of_birth']         ?? '');
    $gender                = trim($_POST['gender']                ?? '');
    $nationality           = trim($_POST['nationality']           ?? '');
    $lin_number            = trim($_POST['lin_number']            ?? '');
    $previous_school       = trim($_POST['previous_school']       ?? '');
    $class_level           = trim($_POST['class_level']           ?? '');
    $stream                = trim($_POST['stream']                ?? '');
    $guardian_name         = trim($_POST['guardian_name']         ?? '');
    $guardian_relationship = trim($_POST['guardian_relationship'] ?? '');
    $guardian_phone        = trim($_POST['guardian_phone']        ?? '');
    $guardian_email        = trim($_POST['guardian_email']        ?? '');
    $guardian_address      = trim($_POST['guardian_address']      ?? '');
    $guardian_occupation   = trim($_POST['guardian_occupation']   ?? '');
    $emergency_name        = trim($_POST['emergency_name']        ?? '');
    $emergency_phone       = trim($_POST['emergency_phone']       ?? '');
    $medical_notes         = trim($_POST['medical_notes']         ?? '');
    $declaration           = isset($_POST['declaration']) ? 1 : 0;

    // ---------- 2. Allowed values (server-side whitelist) ----------
    $allowedClasses = ['Senior 1','Senior 2','Senior 3','Senior 4','Senior 5','Senior 6'];
    $allowedStreams = ['North','South','East','West'];
    $allowedGenders = ['Male','Female'];
    $allowedRels    = ['Father','Mother','Uncle','Aunt','Sibling','Guardian','Other'];

    // ---------- 3. Validate ----------

    // Student name
    if ($full_name === '') {
        $errors[] = 'Full student name is required.';
    } elseif (!preg_match("/^[\p{L}\s'\-\.]{2,150}$/u", $full_name)) {
        $errors[] = 'Full student name contains invalid characters.';
    }

    // Date of birth
    if ($date_of_birth === '') {
        $errors[] = 'Date of birth is required.';
    } else {
        $d = DateTime::createFromFormat('Y-m-d', $date_of_birth);
        if (!$d || $d->format('Y-m-d') !== $date_of_birth) {
            $errors[] = 'Date of birth is not a valid date.';
        } else {
            $today     = new DateTime();
            $age       = $d->diff($today)->y;
            $maxAge    = 30;
            if ($d > $today) {
                $errors[] = 'Date of birth cannot be in the future.';
            } elseif ($age < 9 || $age > $maxAge) {
                $errors[] = "Student age must be between 9 and {$maxAge} years.";
            }
        }
    }

    // Gender
    if ($gender === '') {
        $errors[] = 'Gender is required.';
    } elseif (!in_array($gender, $allowedGenders, true)) {
        $errors[] = 'Invalid gender selection.';
    }

    // Nationality
    if ($nationality === '') {
        $errors[] = 'Nationality is required.';
    } elseif (mb_strlen($nationality) > 60) {
        $errors[] = 'Nationality must be 60 characters or fewer.';
    }

    // LIN / UNEB index
    if ($lin_number === '') {
        $errors[] = 'LIN / UNEB index number is required.';
    } elseif (!preg_match("/^[A-Za-z0-9\/\-]{4,30}$/", $lin_number)) {
        $errors[] = 'LIN / UNEB index must be 4–30 characters (letters, numbers, / and - only).';
    }

    // Previous school
    if ($previous_school === '') {
        $errors[] = 'Previous / current school is required.';
    } elseif (mb_strlen($previous_school) > 150) {
        $errors[] = 'Previous school name must be 150 characters or fewer.';
    }

    // Class
    if ($class_level === '') {
        $errors[] = 'Class level is required.';
    } elseif (!in_array($class_level, $allowedClasses, true)) {
        $errors[] = 'Invalid class level selected.';
    }

    // Stream
    if ($stream === '') {
        $errors[] = 'Stream is required.';
    } elseif (!in_array($stream, $allowedStreams, true)) {
        $errors[] = 'Invalid stream selected.';
    }

    // Guardian name
    if ($guardian_name === '') {
        $errors[] = 'Parent / guardian name is required.';
    } elseif (!preg_match("/^[\p{L}\s'\-\.]{2,150}$/u", $guardian_name)) {
        $errors[] = 'Parent / guardian name contains invalid characters.';
    }

    // Relationship
    if ($guardian_relationship === '') {
        $errors[] = 'Relationship to student is required.';
    } elseif (!in_array($guardian_relationship, $allowedRels, true)) {
        $errors[] = 'Invalid relationship selection.';
    }

    // Guardian phone
    if ($guardian_phone === '') {
        $errors[] = 'Parent / guardian phone number is required.';
    } elseif (!preg_match("/^[\+]?[0-9\s\-\(\)]{7,20}$/", $guardian_phone)) {
        $errors[] = 'Parent / guardian phone number is not valid.';
    }

    // Guardian email (optional)
    if ($guardian_email !== '' && !filter_var($guardian_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Parent / guardian email address is not valid.';
    }
    if (mb_strlen($guardian_email) > 150) {
        $errors[] = 'Parent / guardian email must be 150 characters or fewer.';
    }

    // Guardian address (optional)
    if (mb_strlen($guardian_address) > 255) {
        $errors[] = 'Physical address must be 255 characters or fewer.';
    }

    // Guardian occupation (optional)
    if (mb_strlen($guardian_occupation) > 100) {
        $errors[] = 'Occupation must be 100 characters or fewer.';
    }

    // Emergency contact (optional, but if one is set, validate)
    if ($emergency_name !== '' && mb_strlen($emergency_name) > 150) {
        $errors[] = 'Emergency contact name must be 150 characters or fewer.';
    }
    if ($emergency_phone !== '' && !preg_match("/^[\+]?[0-9\s\-\(\)]{7,20}$/", $emergency_phone)) {
        $errors[] = 'Emergency contact phone number is not valid.';
    }

    // Medical notes (optional)
    if (mb_strlen($medical_notes) > 500) {
        $errors[] = 'Medical notes must be 500 characters or fewer.';
    }

    // Declaration
    if ($declaration !== 1) {
        $errors[] = 'You must accept the declaration to submit the application.';
    }

    // ---------- 4. Database insert ----------
    if (empty($errors)) {
        require_once 'conn.php';

        // 4a. Duplicate LIN check
        $checkSql  = "SELECT id FROM students WHERE lin_number = ? LIMIT 1";
        $checkStmt = mysqli_prepare($conn, $checkSql);

        if ($checkStmt === false) {
            error_log('[Enrollment Check Prepare] ' . mysqli_error($conn));
            $errors[] = 'A system error occurred. Please try again later.';
        } else {
            mysqli_stmt_bind_param($checkStmt, "s", $lin_number);
            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);

            if (mysqli_stmt_num_rows($checkStmt) > 0) {
                $errors[] = 'A student with this LIN / UNEB index number is already registered.';
            }
            mysqli_stmt_close($checkStmt);
        }

        // 4b. Insert
        if (empty($errors)) {
            $insertSql = "INSERT INTO students (
                            full_name, date_of_birth, gender, nationality,
                            lin_number, previous_school, class_level, stream,
                            guardian_name, guardian_relationship, guardian_phone,
                            guardian_email, guardian_address, guardian_occupation,
                            emergency_name, emergency_phone, medical_notes,
                            status, registered_at
                          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())";

            $stmt = mysqli_prepare($conn, $insertSql);

            if ($stmt === false) {
                error_log('[Enrollment Insert Prepare] ' . mysqli_error($conn));
                $errors[] = 'A system error occurred while saving your application. Please try again later.';
            } else {
                // Convert empty optional fields to NULL
                $guardianEmail      = $guardian_email      !== '' ? $guardian_email      : null;
                $guardianAddress    = $guardian_address    !== '' ? $guardian_address    : null;
                $guardianOccupation = $guardian_occupation !== '' ? $guardian_occupation : null;
                $emergencyName      = $emergency_name      !== '' ? $emergency_name      : null;
                $emergencyPhone     = $emergency_phone     !== '' ? $emergency_phone     : null;
                $medicalNotes       = $medical_notes       !== '' ? $medical_notes       : null;

                // Type string: 17 placeholders
                // s=string, i=integer. All 17 are strings except none — everything is s here.
                $types = "sssssssssssssssss";

                mysqli_stmt_bind_param(
                    $stmt,
                    $types,
                    $full_name,
                    $date_of_birth,
                    $gender,
                    $nationality,
                    $lin_number,
                    $previous_school,
                    $class_level,
                    $stream,
                    $guardian_name,
                    $guardian_relationship,
                    $guardian_phone,
                    $guardianEmail,
                    $guardianAddress,
                    $guardianOccupation,
                    $emergencyName,
                    $emergencyPhone,
                    $medicalNotes
                );

                if (mysqli_stmt_execute($stmt)) {
                    $success = true;
                    $_POST   = []; // clear form so it renders empty after success
                } else {
                    error_log('[Enrollment Insert Execute] ' . mysqli_stmt_error($stmt));
                    $errors[] = 'A system error occurred while saving your application. Please try again later.';
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// ---------- 5. Build flash messages ----------
$flashHtml = '';

if (!empty($errors)) {
    $items = '';
    foreach ($errors as $err) {
        $safe   = htmlspecialchars($err, ENT_QUOTES, 'UTF-8');
        $items .= "<li>{$safe}</li>";
    }
    $flashHtml = <<<HTML
        <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-5 py-4 rounded-xl flex items-start gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div>
                <p class="font-bold mb-1">Please fix the following:</p>
                <ul class="list-disc list-inside text-sm space-y-0.5">{$items}</ul>
            </div>
        </div>
    HTML;
}

if ($success) {
    $flashHtml = <<<HTML
        <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-5 py-4 rounded-xl flex items-start gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <div>
                <p class="font-bold">Application submitted successfully!</p>
                <p class="text-sm">Your enrollment application has been received and is pending review. The Admissions Office will contact you shortly.</p>
            </div>
        </div>
    HTML;
}

// ---------- 6. Render template with flash injected ----------
ob_start();
require './enrollment.html';
$html = ob_get_clean();

$html = str_replace('<!-- ENROLLMENT_FLASH_MESSAGES -->', $flashHtml, $html);

echo $html;