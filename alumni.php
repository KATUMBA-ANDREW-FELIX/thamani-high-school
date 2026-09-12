<?php
/**
 * Thamani High School - Alumni Registration Handler (mysqli)
 * ------------------------------------------------------
 * - Validates form input
 * - Inserts into `alumni` table using mysqli prepared statements
 * - Prevents duplicate emails
 * - Injects success/error alert HTML into alumni.html via output buffering
 *
 * Requires: conn.php exposing a mysqli connection named $conn
 */

session_start();

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

    // ---------- 1. Grab + trim inputs ----------
    $name       = trim($_POST['name']       ?? '');
    $year       = trim($_POST['year']       ?? '');
    $profession = trim($_POST['profession'] ?? '');
    $phone      = trim($_POST['phone']      ?? '');
    $email      = trim($_POST['email']      ?? '');

    // ---------- 2. Validate ----------
    if ($name === '') {
        $errors[] = 'Full name is required.';
    } elseif (!preg_match("/^[\p{L}\s'\-\.]{2,100}$/u", $name)) {
        $errors[] = 'Full name contains invalid characters.';
    }

    $yearInt = 0;
    if ($year === '') {
        $errors[] = 'Year of completion is required.';
    } elseif (!ctype_digit($year)) {
        $errors[] = 'Year must be a valid number.';
    } else {
        $yearInt   = (int)$year;
        $currentYr = (int)date('Y');
        if ($yearInt < 2000 || $yearInt > $currentYr) {
            $errors[] = "Year must be between 2000 and {$currentYr}.";
        }
    }

    if ($profession === '') {
        $errors[] = 'Profession / Institution is required.';
    } elseif (mb_strlen($profession) > 150) {
        $errors[] = 'Profession must be 150 characters or fewer.';
    }

    if ($email === '') {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (mb_strlen($email) > 150) {
        $errors[] = 'Email must be 150 characters or fewer.';
    }

    // ---------- 3. Insert if no validation errors ----------
    if (empty($errors)) {
        require_once 'conn.php'; // provides $conn (mysqli connection)

        // -- 3a. Check for duplicate email using prepared statement --
        $checkSql  = "SELECT id FROM alumni WHERE email = ? LIMIT 1";
        $checkStmt = mysqli_prepare($conn, $checkSql);

        if ($checkStmt === false) {
            error_log('[Alumni Check Prepare Error] ' . mysqli_error($conn));
            $errors[] = 'A system error occurred. Please try again later.';
        } else {
            mysqli_stmt_bind_param($checkStmt, "s", $email);
            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);

            if (mysqli_stmt_num_rows($checkStmt) > 0) {
                $errors[] = 'This email address is already registered.';
            }
            mysqli_stmt_close($checkStmt);
        }

        // -- 3b. Insert the record if still no errors --
        if (empty($errors)) {
            $insertSql  = "INSERT INTO alumni (name, year, profession, phone, email)
                           VALUES (?, ?, ?, ?, ?)";
            $insertStmt = mysqli_prepare($conn, $insertSql);

            if ($insertStmt === false) {
                error_log('[Alumni Insert Prepare Error] ' . mysqli_error($conn));
                $errors[] = 'A system error occurred while saving your registration. Please try again later.';
            } else {
                $phoneVal = $phone !== '' ? $phone : null;
                mysqli_stmt_bind_param(
                    $insertStmt,
                    "sisss",
                    $name,
                    $yearInt,
                    $profession,
                    $phoneVal,
                    $email
                );

                if (mysqli_stmt_execute($insertStmt)) {
                    $success = true;
                    $_POST   = []; // clear so fields render empty
                } else {
                    error_log('[Alumni Insert Execute Error] ' . mysqli_stmt_error($insertStmt));
                    $errors[] = 'A system error occurred while saving your registration. Please try again later.';
                }

                mysqli_stmt_close($insertStmt);
            }
        }
    }
}

// ---------- 4. Build flash message HTML ----------
$flashHtml = '';

if (!empty($errors)) {
    $items = '';
    foreach ($errors as $err) {
        $safe   = htmlspecialchars($err, ENT_QUOTES, 'UTF-8');
        $items .= "<li>{$safe}</li>";
    }
    $flashHtml = <<<HTML
        <div class="max-w-3xl mx-auto mb-6 bg-red-50 border border-red-200 text-red-800 px-5 py-4 rounded-xl flex items-start gap-3">
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
        <div class="max-w-3xl mx-auto mb-6 bg-green-50 border border-green-200 text-green-800 px-5 py-4 rounded-xl flex items-start gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <div>
                <p class="font-bold">Registration successful!</p>
                <p class="text-sm">Thank you for joining the Thamani High School Alumni Network. We'll be in touch soon.</p>
            </div>
        </div>
    HTML;
}

// ---------- 5. Render the template with flash injected ----------
ob_start();
require './alumni.html';
$html = ob_get_clean();

// Inject flash messages at the placeholder
$html = str_replace('<!-- ALUMNI_FLASH_MESSAGES -->', $flashHtml, $html);

echo $html;