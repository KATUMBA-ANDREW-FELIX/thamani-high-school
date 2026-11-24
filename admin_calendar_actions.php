<?php
require_once 'auth_admin.php';
require_admin_login();
require_once 'conn.php';

$redirectTo = 'admin_dashboard.php?tab=tab-admin-calendar#tab-admin-calendar';

$flashAndRedirect = function (string $type, string $message) use ($redirectTo): void {
    $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
    header('Location: ' . $redirectTo);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectTo);
    exit;
}

$expectedToken = (string)($_SESSION['calendar_events_csrf_token'] ?? '');
$postedToken = $_POST['csrf_token'] ?? '';
if ($expectedToken === '' || !is_string($postedToken) || !hash_equals($expectedToken, $postedToken)) {
    $flashAndRedirect('error', 'Your session expired. Please try again.');
}

$readPostString = function (string $key): string {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
};

$action = $readPostString('action');

if ($action === 'delete_event') {
    $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$eventId || $eventId < 1) {
        $flashAndRedirect('error', 'Please select a valid event to delete.');
    }

    $check = thamani_db_prepare($conn, 'SELECT id FROM calendar_events WHERE id = ? LIMIT 1');
    if (!$check) {
        error_log('[Calendar Event Delete Prepare] ' . thamani_db_error($conn));
        $flashAndRedirect('error', 'Could not delete the event right now.');
    }
    thamani_db_stmt_bind_param($check, 'i', $eventId);
    $checkExecuted = thamani_db_stmt_execute($check);
    $checkResult = $checkExecuted ? thamani_db_stmt_get_result($check) : false;
    $eventExists = $checkResult ? thamani_db_fetch_assoc($checkResult) : null;
    thamani_db_stmt_close($check);

    if (!$checkExecuted) {
        error_log('[Calendar Event Delete Check] ' . thamani_db_error($conn));
        $flashAndRedirect('error', 'Could not delete the event right now.');
    }
    if (!$eventExists) {
        $flashAndRedirect('error', 'That event no longer exists.');
    }

    $stmt = thamani_db_prepare($conn, 'DELETE FROM calendar_events WHERE id = ?');
    if (!$stmt) {
        error_log('[Calendar Event Delete Prepare] ' . thamani_db_error($conn));
        $flashAndRedirect('error', 'Could not delete the event right now.');
    }

    thamani_db_stmt_bind_param($stmt, 'i', $eventId);
    $deleted = thamani_db_stmt_execute($stmt);
    if (!$deleted) {
        error_log('[Calendar Event Delete Execute] ' . thamani_db_stmt_error($stmt));
    }
    thamani_db_stmt_close($stmt);

    $flashAndRedirect(
        $deleted ? 'success' : 'error',
        $deleted ? 'Calendar event deleted.' : 'Could not delete the event right now.'
    );
}

if ($action !== 'save_event') {
    $flashAndRedirect('error', 'Invalid calendar event action.');
}

$rawEventId = $_POST['event_id'] ?? '';
if (!is_string($rawEventId)) {
    $eventId = -1;
} elseif ($rawEventId === '') {
    $eventId = 0;
} else {
    $validatedEventId = filter_var($rawEventId, FILTER_VALIDATE_INT);
    $eventId = $validatedEventId === false ? -1 : (int)$validatedEventId;
}
$title = $readPostString('title');
$description = $readPostString('description');
$eventDate = $readPostString('event_date');
$eventTime = $readPostString('event_time');
$location = $readPostString('location');
$category = $readPostString('category');
$visibility = $readPostString('visibility');
$textLength = function (string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
};

$allowedCategories = ['Election', 'Assembly', 'Campaign', 'Meeting', 'Other'];
$errors = [];

if ($title === '' || $textLength($title) > 255) {
    $errors[] = 'Enter an event title up to 255 characters.';
}
if ($textLength($description) > 5000) {
    $errors[] = 'The event description must be 5,000 characters or fewer.';
}
$dateParts = explode('-', $eventDate);
if (
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)
    || count($dateParts) !== 3
    || !checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])
) {
    $errors[] = 'Enter a valid event date.';
}
if ($eventTime !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $eventTime)) {
    $errors[] = 'Enter a valid event time.';
}
if ($textLength($location) > 255) {
    $errors[] = 'The event location must be 255 characters or fewer.';
}
if (!in_array($category, $allowedCategories, true)) {
    $errors[] = 'Choose a valid event category.';
}
if (!in_array($visibility, ['public', 'private'], true)) {
    $errors[] = 'Choose whether the event is public or private.';
}
if ($eventId < 0) {
    $errors[] = 'Invalid event selected for editing.';
}

if ($errors) {
    $flashAndRedirect('error', implode(' ', $errors));
}

$eventTime = $eventTime !== '' ? $eventTime . ':00' : null;
$description = $description !== '' ? $description : null;
$location = $location !== '' ? $location : null;

if ($eventId > 0) {
    $check = thamani_db_prepare($conn, 'SELECT id FROM calendar_events WHERE id = ? LIMIT 1');
    if (!$check) {
        error_log('[Calendar Event Update Check Prepare] ' . thamani_db_error($conn));
        $flashAndRedirect('error', 'Could not save the event right now.');
    }
    thamani_db_stmt_bind_param($check, 'i', $eventId);
    $checkExecuted = thamani_db_stmt_execute($check);
    $checkResult = $checkExecuted ? thamani_db_stmt_get_result($check) : false;
    $eventExists = $checkResult ? thamani_db_fetch_assoc($checkResult) : null;
    thamani_db_stmt_close($check);
    if (!$checkExecuted) {
        error_log('[Calendar Event Update Check] ' . thamani_db_error($conn));
        $flashAndRedirect('error', 'Could not save the event right now.');
    }
    if (!$eventExists) {
        $flashAndRedirect('error', 'That event no longer exists.');
    }

    $stmt = thamani_db_prepare(
        $conn,
        'UPDATE calendar_events
         SET title = ?, description = ?, event_date = ?, event_time = ?, location = ?,
             category = ?, visibility = ?, updated_at = CURRENT_TIMESTAMP
         WHERE id = ?'
    );
    if (!$stmt) {
        error_log('[Calendar Event Update Prepare] ' . thamani_db_error($conn));
        $flashAndRedirect('error', 'Could not save the event right now.');
    }
    thamani_db_stmt_bind_param(
        $stmt,
        'sssssssi',
        $title,
        $description,
        $eventDate,
        $eventTime,
        $location,
        $category,
        $visibility,
        $eventId
    );
    $saved = thamani_db_stmt_execute($stmt);
    if (!$saved) {
        error_log('[Calendar Event Update Execute] ' . thamani_db_stmt_error($stmt));
    }
    thamani_db_stmt_close($stmt);
    $flashAndRedirect(
        $saved ? 'success' : 'error',
        $saved ? 'Calendar event updated.' : 'Could not save the event right now.'
    );
}

$stmt = thamani_db_prepare(
    $conn,
    'INSERT INTO calendar_events
        (title, description, event_date, event_time, location, category, visibility)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
if (!$stmt) {
    error_log('[Calendar Event Insert Prepare] ' . thamani_db_error($conn));
    $flashAndRedirect('error', 'Could not create the event right now.');
}

thamani_db_stmt_bind_param(
    $stmt,
    'sssssss',
    $title,
    $description,
    $eventDate,
    $eventTime,
    $location,
    $category,
    $visibility
);
$saved = thamani_db_stmt_execute($stmt);
if (!$saved) {
    error_log('[Calendar Event Insert Execute] ' . thamani_db_stmt_error($stmt));
}
thamani_db_stmt_close($stmt);

$flashAndRedirect(
    $saved ? 'success' : 'error',
    $saved ? 'Calendar event created.' : 'Could not create the event right now.'
);
