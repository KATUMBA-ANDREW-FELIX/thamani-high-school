<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function require_alumni_editor_login($connection): array
{
    $editorId = (int)($_SESSION['alumni_editor_id'] ?? 0);
    if ($editorId <= 0) {
        header('Location: login.php');
        exit;
    }

    $stmt = thamani_db_prepare(
        $connection,
        'SELECT id, name, email FROM alumni WHERE id = ? AND is_page_editor = 1 AND editor_active = 1 LIMIT 1'
    );
    if (!$stmt) {
        error_log('[Alumni Editor Session Prepare] ' . thamani_db_error($connection));
        http_response_code(500);
        exit('Unable to verify the alumni editor account.');
    }

    thamani_db_stmt_bind_param($stmt, 'i', $editorId);
    if (!thamani_db_stmt_execute($stmt)) {
        error_log('[Alumni Editor Session Fetch] ' . thamani_db_stmt_error($stmt));
        thamani_db_stmt_close($stmt);
        http_response_code(500);
        exit('Unable to verify the alumni editor account.');
    }

    $result = thamani_db_stmt_get_result($stmt);
    $editor = $result ? thamani_db_fetch_assoc($result) : null;
    thamani_db_stmt_close($stmt);
    if (!$editor) {
        unset($_SESSION['alumni_editor_id'], $_SESSION['alumni_editor_name'], $_SESSION['alumni_editor_email']);
        header('Location: login.php');
        exit;
    }

    return $editor;
}
