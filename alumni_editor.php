<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/alumni_content.php';
require_once __DIR__ . '/auth_alumni.php';

$conn = $GLOBALS['conn'];
$isAdmin = !empty($_SESSION['admin_id']);
$isAlumniEditor = !$isAdmin && !empty($_SESSION['alumni_editor_id']);
$editor = null;

if ($isAlumniEditor) {
    $editor = require_alumni_editor_login($conn);
} elseif (!$isAdmin) {
    header('Location: login.php');
    exit;
}

$csrfKey = 'alumni_editor_csrf';
if (empty($_SESSION[$csrfKey])) {
    $_SESSION[$csrfKey] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[$csrfKey];
$flash = null;
$content = load_alumni_page_content($conn);

$scalarFields = [
    'page_eyebrow' => 'Page eyebrow',
    'page_title' => 'Main page title',
    'hero_intro' => 'Hero introduction',
    'hero_join_label' => 'Hero membership button',
    'hero_leadership_label' => 'Hero council button',
    'president_eyebrow' => 'President section eyebrow',
    'president_message' => 'President message',
    'president_name' => 'President name',
    'president_role' => 'President title / class year',
    'about_heading' => 'Alumni introduction heading',
    'about_paragraph_one' => 'Alumni introduction paragraph 1',
    'about_paragraph_two' => 'Alumni introduction paragraph 2',
    'message_join_label' => 'Join button label',
    'benefits_eyebrow' => 'Benefits eyebrow',
    'benefits_heading' => 'Benefits heading',
    'benefits_intro' => 'Benefits introduction',
    'achievements_eyebrow' => 'Achievements eyebrow',
    'achievements_heading' => 'Achievements heading',
    'achievements_intro' => 'Achievements introduction',
    'leadership_eyebrow' => 'Leadership eyebrow',
    'leadership_heading' => 'Leadership heading',
    'leadership_intro' => 'Leadership introduction',
    'representatives_note' => 'Class representatives note',
    'projects_eyebrow' => 'Projects eyebrow',
    'projects_heading' => 'Projects heading',
    'projects_intro' => 'Projects introduction',
    'workplan_heading' => 'Work-plan heading',
    'workplan_review_note' => 'Work-plan review note',
    'registration_eyebrow' => 'Registration eyebrow',
    'registration_heading' => 'Registration heading',
    'registration_intro' => 'Registration introduction',
    'membership_form_heading' => 'Membership form heading',
    'membership_form_intro' => 'Membership form note',
    'registration_name_label' => 'Name field label',
    'registration_year_label' => 'Completion-year field label',
    'registration_profession_label' => 'Profession field label',
    'registration_email_label' => 'Email field label',
    'registration_phone_label' => 'Phone field label',
    'registration_submit_label' => 'Registration button',
    'registration_privacy_note' => 'Privacy note',
    'footer_mission' => 'Footer school description',
    'footer_links_heading' => 'Footer links heading',
    'footer_office_heading' => 'Footer alumni-office heading',
    'footer_address' => 'Footer address',
    'footer_phone' => 'Footer phone',
    'footer_email' => 'Footer email',
    'footer_copyright' => 'Footer copyright',
    'footer_subtitle' => 'Footer association subtitle',
];

$rowFields = [
    'stats' => ['Alumni statistics', ['value', 'label'], 'value|label', 4],
    'benefits' => ['Membership benefits', ['title', 'description'], 'title|description', 8],
    'achievements' => ['Achievements', ['year', 'title', 'description'], 'year|title|description', 10],
    'leaders' => ['Executive council (up to 6)', ['role', 'name', 'meta', 'description'], 'role|name|meta|description', 6],
    'projects' => ['Projects (progress is 0-100)', ['code', 'status', 'title', 'description', 'progress_label', 'progress'], 'code|status|title|description|progress label|progress %', 10],
    'workplan_phases' => ['Work-plan phases (separate phase items with semicolons)', ['label', 'title', 'items'], 'phase label|phase title|item 1; item 2; item 3', 5],
    'registration_steps' => ['Registration steps', ['title', 'description'], 'title|description', 5],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $postedToken)) {
        http_response_code(400);
        $flash = ['type' => 'error', 'message' => 'This form expired. Reload the page and try again.'];
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'save_content') {
            $updatedContent = $content;
            $validationErrors = [];
            $postedContent = $_POST['content'] ?? [];
            if (!is_array($postedContent)) {
                $postedContent = [];
                $validationErrors[] = 'The submitted page content is invalid. Reload the page and try again.';
            }

            foreach ($scalarFields as $key => $label) {
                $rawValue = $postedContent[$key] ?? '';
                if (!is_scalar($rawValue)) {
                    $validationErrors[] = $label . ' has an invalid value.';
                    continue;
                }
                $value = trim((string)$rawValue);
                if (mb_strlen($value) > 4000) {
                    $validationErrors[] = $label . ' must be 4,000 characters or fewer.';
                } else {
                    $updatedContent[$key] = $value;
                }
            }

            foreach ($rowFields as $key => [$label, $columns, $format, $maxRows]) {
                $rawValue = $postedContent[$key] ?? '';
                if (!is_scalar($rawValue)) {
                    $validationErrors[] = $label . ' has an invalid value.';
                    continue;
                }
                $rawRows = trim((string)$rawValue);
                $parsedRows = [];
                if ($rawRows !== '') {
                    foreach (preg_split('/\r\n|\r|\n/', $rawRows) as $lineNumber => $line) {
                        if (trim($line) === '') {
                            continue;
                        }
                        $parts = array_map('trim', explode('|', $line));
                        if (count($parts) !== count($columns)) {
                            $validationErrors[] = $label . ' line ' . ($lineNumber + 1) . ' must use this format: ' . $format . '.';
                            continue;
                        }
                        foreach ($parts as $part) {
                            if (mb_strlen($part) > 2000) {
                                $validationErrors[] = $label . ' entries must be 2,000 characters or fewer per field.';
                                break 2;
                            }
                        }

                        $row = array_combine($columns, $parts);
                        if ($key === 'workplan_phases') {
                            $row['items'] = array_values(array_filter(array_map('trim', explode(';', $row['items'])), static function ($item) {
                                return $item !== '';
                            }));
                        }
                        if ($key === 'projects') {
                            if (!is_numeric($row['progress'])) {
                                $validationErrors[] = 'Project progress must be a number from 0 to 100.';
                                continue;
                            }
                            $row['progress'] = (string)max(0, min(100, (int)$row['progress']));
                        }
                        if ($parts[0] === '') {
                            $validationErrors[] = $label . ' line ' . ($lineNumber + 1) . ' must not be blank.';
                            continue;
                        }
                        $parsedRows[] = $row;
                    }
                }
                if (count($parsedRows) > $maxRows) {
                    $validationErrors[] = $label . ' can contain at most ' . $maxRows . ' entries.';
                }
                $updatedContent[$key] = $parsedRows;
            }

            if ($validationErrors) {
                $flash = ['type' => 'error', 'message' => implode(' ', array_unique($validationErrors))];
                $content = $updatedContent;
            } else {
                try {
                    $jsonContent = json_encode($updatedContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    error_log('[Alumni Page Content Encode] ' . $exception->getMessage());
                    $jsonContent = false;
                }
                $save = thamani_db_prepare(
                    $conn,
                    'INSERT INTO alumni_page_content (id, content_json, updated_at) VALUES (1, ?, CURRENT_TIMESTAMP)
                     ON CONFLICT (id) DO UPDATE SET content_json = EXCLUDED.content_json, updated_at = CURRENT_TIMESTAMP'
                );
                if ($jsonContent === false) {
                    $flash = ['type' => 'error', 'message' => 'The page content could not be encoded. Check for invalid characters and try again.'];
                } elseif (!$save) {
                    error_log('[Alumni Page Content Save Prepare] ' . thamani_db_error($conn));
                    $flash = ['type' => 'error', 'message' => 'The page content could not be saved. Please try again.'];
                } else {
                    thamani_db_stmt_bind_param($save, 's', $jsonContent);
                    $saved = thamani_db_stmt_execute($save);
                    if (!$saved) {
                        error_log('[Alumni Page Content Save] ' . thamani_db_stmt_error($save));
                    }
                    thamani_db_stmt_close($save);
                    if ($saved) {
                        $_SESSION['alumni_editor_flash'] = ['type' => 'success', 'message' => 'Alumni page updated and published.'];
                        header('Location: alumni_editor.php');
                        exit;
                    }
                    $flash = ['type' => 'error', 'message' => 'The page content could not be saved. Please try again.'];
                }
            }
        } elseif ($isAdmin && $action === 'assign_editor') {
            $alumniId = (int)($_POST['alumni_id'] ?? 0);
            $password = (string)($_POST['editor_password'] ?? '');
            $passwordConfirm = (string)($_POST['editor_password_confirm'] ?? '');

            if ($alumniId <= 0 || strlen($password) < 10 || $password !== $passwordConfirm) {
                $flash = ['type' => 'error', 'message' => 'Select an alumnus and enter matching passwords of at least 10 characters.'];
            } else {
                $candidate = thamani_db_prepare($conn, "SELECT id FROM alumni WHERE id = ? AND email IS NOT NULL AND email <> '' LIMIT 1");
                if (!$candidate) {
                    error_log('[Alumni Editor Candidate Prepare] ' . thamani_db_error($conn));
                    $flash = ['type' => 'error', 'message' => 'The editor account could not be updated. Please try again.'];
                } else {
                    thamani_db_stmt_bind_param($candidate, 'i', $alumniId);
                    $candidateOk = thamani_db_stmt_execute($candidate);
                    $candidateResult = $candidateOk ? thamani_db_stmt_get_result($candidate) : false;
                    $candidateRow = $candidateResult ? thamani_db_fetch_assoc($candidateResult) : null;
                    if (!$candidateOk) {
                        error_log('[Alumni Editor Candidate Fetch] ' . thamani_db_stmt_error($candidate));
                    }
                    thamani_db_stmt_close($candidate);

                    if (!$candidateOk || !$candidateRow) {
                        $flash = ['type' => 'error', 'message' => 'Select an existing alumni profile with an email address.'];
                    } else {
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $revoke = thamani_db_prepare($conn, 'UPDATE alumni SET is_page_editor = 0, editor_active = 0');
                        $grant = thamani_db_prepare(
                            $conn,
                            'UPDATE alumni SET is_page_editor = 1, editor_active = 1, editor_password_hash = ? WHERE id = ?'
                        );
                        if (!$revoke || !$grant) {
                    error_log('[Alumni Editor Assignment Prepare] ' . thamani_db_error($conn));
                    if ($revoke) thamani_db_stmt_close($revoke);
                    if ($grant) thamani_db_stmt_close($grant);
                    $flash = ['type' => 'error', 'message' => 'The editor account could not be updated. Please try again.'];
                        } else {
                            $revoked = thamani_db_stmt_execute($revoke);
                            if (!$revoked) {
                                error_log('[Alumni Editor Revocation] ' . thamani_db_stmt_error($revoke));
                            }
                            thamani_db_stmt_close($revoke);
                            thamani_db_stmt_bind_param($grant, 'si', $passwordHash, $alumniId);
                            $granted = $revoked && thamani_db_stmt_execute($grant);
                            if (!$granted) {
                                error_log('[Alumni Editor Assignment] ' . thamani_db_stmt_error($grant));
                            }
                            thamani_db_stmt_close($grant);
                            if ($granted) {
                                $_SESSION['alumni_editor_flash'] = ['type' => 'success', 'message' => 'Alumni editor access has been assigned. The selected alumnus can sign in using their email address.'];
                                header('Location: alumni_editor.php');
                                exit;
                            }
                            $flash = ['type' => 'error', 'message' => 'Could not assign editor access. Please try again.'];
                        }
                    }
                }
            }
        } elseif ($isAdmin && $action === 'revoke_editor') {
            $revoke = thamani_db_prepare($conn, 'UPDATE alumni SET is_page_editor = 0, editor_active = 0, editor_password_hash = NULL');
            if (!$revoke) {
                error_log('[Alumni Editor Revoke Prepare] ' . thamani_db_error($conn));
                $flash = ['type' => 'error', 'message' => 'Editor access could not be revoked.'];
            } else {
                $revoked = thamani_db_stmt_execute($revoke);
                if (!$revoked) {
                    error_log('[Alumni Editor Revoke] ' . thamani_db_stmt_error($revoke));
                }
                thamani_db_stmt_close($revoke);
                if ($revoked) {
                    $_SESSION['alumni_editor_flash'] = ['type' => 'success', 'message' => 'Alumni editor access has been revoked.'];
                    header('Location: alumni_editor.php');
                    exit;
                }
                $flash = ['type' => 'error', 'message' => 'Editor access could not be revoked.'];
            }
        } elseif (!$isAdmin && $action === 'change_password') {
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['new_password_confirm'] ?? '');
            $passwordLookup = thamani_db_prepare($conn, 'SELECT editor_password_hash FROM alumni WHERE id = ? AND is_page_editor = 1 AND editor_active = 1 LIMIT 1');
            if (!$passwordLookup) {
                error_log('[Alumni Editor Password Prepare] ' . thamani_db_error($conn));
                $flash = ['type' => 'error', 'message' => 'Password could not be updated. Please try again.'];
            } else {
                $editorId = (int)$editor['id'];
                thamani_db_stmt_bind_param($passwordLookup, 'i', $editorId);
                $lookupOk = thamani_db_stmt_execute($passwordLookup);
                $lookupResult = $lookupOk ? thamani_db_stmt_get_result($passwordLookup) : false;
                $passwordRow = $lookupResult ? thamani_db_fetch_assoc($lookupResult) : null;
                thamani_db_stmt_close($passwordLookup);
                if (!$lookupOk) {
                    $flash = ['type' => 'error', 'message' => 'Password could not be updated. Please try again.'];
                } elseif (!$passwordRow || !password_verify($currentPassword, (string)$passwordRow['editor_password_hash'])) {
                    $flash = ['type' => 'error', 'message' => 'Current password is incorrect.'];
                } elseif (strlen($newPassword) < 10 || $newPassword !== $confirmPassword) {
                    $flash = ['type' => 'error', 'message' => 'New passwords must match and contain at least 10 characters.'];
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $passwordUpdate = thamani_db_prepare($conn, 'UPDATE alumni SET editor_password_hash = ? WHERE id = ? AND is_page_editor = 1 AND editor_active = 1');
                    if (!$passwordUpdate) {
                        error_log('[Alumni Editor Password Update Prepare] ' . thamani_db_error($conn));
                        $flash = ['type' => 'error', 'message' => 'Password could not be updated. Please try again.'];
                    } else {
                        thamani_db_stmt_bind_param($passwordUpdate, 'si', $newHash, $editorId);
                        $passwordUpdated = thamani_db_stmt_execute($passwordUpdate);
                        if (!$passwordUpdated) {
                            error_log('[Alumni Editor Password Update] ' . thamani_db_stmt_error($passwordUpdate));
                        }
                        thamani_db_stmt_close($passwordUpdate);
                        if ($passwordUpdated) {
                            $_SESSION['alumni_editor_flash'] = ['type' => 'success', 'message' => 'Your password has been changed.'];
                            header('Location: alumni_editor.php');
                            exit;
                        }
                        $flash = ['type' => 'error', 'message' => 'Password could not be updated. Please try again.'];
                    }
                }
            }
        } else {
            http_response_code(403);
            $flash = ['type' => 'error', 'message' => 'You are not allowed to perform that action.'];
        }
    }
}

if (!empty($_SESSION['alumni_editor_flash'])) {
    $flash = $_SESSION['alumni_editor_flash'];
    unset($_SESSION['alumni_editor_flash']);
}

$activeEditor = null;
$editorStatusError = '';
$editorStmt = thamani_db_prepare($conn, 'SELECT id, name, email, editor_last_login FROM alumni WHERE is_page_editor = 1 AND editor_active = 1 LIMIT 1');
if ($editorStmt) {
    if (thamani_db_stmt_execute($editorStmt)) {
        $editorResult = thamani_db_stmt_get_result($editorStmt);
        $activeEditor = $editorResult ? thamani_db_fetch_assoc($editorResult) : null;
    } else {
        error_log('[Alumni Editor Status Fetch] ' . thamani_db_stmt_error($editorStmt));
        $editorStatusError = 'Could not check which alumni account currently has editor access.';
    }
    thamani_db_stmt_close($editorStmt);
} else {
    error_log('[Alumni Editor Status Prepare] ' . thamani_db_error($conn));
    $editorStatusError = 'Could not check which alumni account currently has editor access.';
}

$alumniOptions = [];
$alumniOptionsError = '';
if ($isAdmin) {
    $alumniResult = thamani_db_query($conn, 'SELECT id, name, year, email FROM alumni WHERE email IS NOT NULL AND email <> \'\' ORDER BY name ASC');
    if ($alumniResult) {
        while ($row = thamani_db_fetch_assoc($alumniResult)) {
            $alumniOptions[] = $row;
        }
    } else {
        error_log('[Alumni Editor Candidates] ' . thamani_db_error($conn));
        $alumniOptionsError = 'Could not load alumni profiles. Try reloading this page.';
    }
}

$rowText = static function (array $rows, array $columns): string {
    $lines = [];
    foreach ($rows as $row) {
        $values = [];
        foreach ($columns as $column) {
            $value = $row[$column] ?? '';
            $values[] = is_array($value) ? implode('; ', $value) : (string)$value;
        }
        $lines[] = implode(' | ', $values);
    }
    return implode("\n", $lines);
};
$escape = 'alumni_page_escape';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Page Editor - Thamani High School</title>
    <link rel="stylesheet" href="css/tailwind.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
    <header class="bg-brand-green text-white shadow">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-5 sm:px-6 lg:px-8">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-brand-gold">Thamani High School</p>
                <h1 class="mt-1 text-2xl font-black">Alumni Page Editor</h1>
                <p class="mt-1 text-sm text-green-100"><?= $isAdmin ? 'Administrator access' : 'Signed in as ' . $escape($editor['name']) ?></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="alumni.php" target="_blank" class="rounded-lg bg-white/10 px-4 py-2 text-sm font-bold hover:bg-white/20">Preview public page</a>
                <a href="<?= $isAdmin ? 'admin_dashboard.php?tab=tab-admin-alumni#tab-admin-alumni' : 'alumni.php' ?>" class="rounded-lg bg-white/10 px-4 py-2 text-sm font-bold hover:bg-white/20"><?= $isAdmin ? 'Admin dashboard' : 'Public alumni page' ?></a>
                <a href="<?= $isAdmin ? 'admin_logout.php' : 'alumni_editor_logout.php' ?>" class="rounded-lg bg-brand-maroon px-4 py-2 text-sm font-bold hover:bg-red-900">Sign out</a>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <?php if ($flash): ?>
            <div class="rounded-xl border px-5 py-4 text-sm font-semibold <?= $flash['type'] === 'success' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?>">
                <?= $escape($flash['message']) ?>
            </div>
        <?php endif; ?>
        <?php if ($editorStatusError || $alumniOptionsError): ?>
            <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-semibold text-red-800">
                <?= $escape(trim($editorStatusError . ' ' . $alumniOptionsError)) ?>
            </div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
            <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-7">
                <div class="mb-5">
                    <h2 class="text-lg font-black text-brand-green">Alumni editor account</h2>
                    <p class="mt-1 text-sm text-gray-600">Assign one registered alumnus to update this page. Their email and password work on the regular Portal Login; the account only opens this editor.</p>
                </div>
                <?php if ($activeEditor): ?>
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-green-200 bg-green-50 p-4">
                        <div>
                            <p class="font-bold text-green-900">Current editor: <?= $escape($activeEditor['name']) ?></p>
                            <p class="text-sm text-green-800"><?= $escape($activeEditor['email']) ?></p>
                        </div>
                        <form method="post" onsubmit="return confirm('Revoke this alumni editor account?');">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="action" value="revoke_editor">
                            <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700">Revoke editor access</button>
                        </form>
                    </div>
                <?php else: ?>
                    <p class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">No alumni editor is currently assigned.</p>
                <?php endif; ?>
                <form method="post" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="assign_editor">
                    <label class="md:col-span-2">
                        <span class="mb-1 block text-xs font-bold uppercase text-gray-600">Registered alumnus</span>
                        <select name="alumni_id" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                            <option value="">Choose alumni</option>
                            <?php foreach ($alumniOptions as $option): ?>
                                <option value="<?= (int)$option['id'] ?>"><?= $escape($option['name']) ?> · <?= $escape((string)$option['year']) ?> · <?= $escape($option['email']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span class="mb-1 block text-xs font-bold uppercase text-gray-600">Set / reset password</span>
                        <input type="password" name="editor_password" minlength="10" required autocomplete="new-password" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                    </label>
                    <label>
                        <span class="mb-1 block text-xs font-bold uppercase text-gray-600">Confirm password</span>
                        <input type="password" name="editor_password_confirm" minlength="10" required autocomplete="new-password" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                    </label>
                    <div class="md:col-span-4">
                        <button class="rounded-lg bg-brand-green px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-darkGreen">Assign alumni editor</button>
                    </div>
                </form>
            </section>
        <?php else: ?>
            <section class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                You can edit the public Alumni page only. This login cannot access the administration dashboard, student records, or other admin tools.
            </section>
            <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-7">
                <h2 class="text-lg font-black text-brand-green">Change your editor password</h2>
                <form method="post" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="change_password">
                    <input type="password" name="current_password" placeholder="Current password" required autocomplete="current-password" class="rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                    <input type="password" name="new_password" placeholder="New password (10+ characters)" minlength="10" required autocomplete="new-password" class="rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                    <input type="password" name="new_password_confirm" placeholder="Confirm new password" minlength="10" required autocomplete="new-password" class="rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                    <div class="md:col-span-3"><button class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50">Change password</button></div>
                </form>
            </section>
        <?php endif; ?>

        <form method="post" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="save_content">

            <?php
            $sections = [
                'Main page and welcome' => ['page_eyebrow', 'page_title', 'hero_intro', 'hero_join_label', 'hero_leadership_label'],
                'President and alumni message' => ['president_eyebrow', 'president_message', 'president_name', 'president_role', 'about_heading', 'about_paragraph_one', 'about_paragraph_two', 'message_join_label'],
                'Membership benefits' => ['benefits_eyebrow', 'benefits_heading', 'benefits_intro'],
                'Achievements' => ['achievements_eyebrow', 'achievements_heading', 'achievements_intro'],
                'Executive council' => ['leadership_eyebrow', 'leadership_heading', 'leadership_intro', 'representatives_note'],
                'Projects and work plan' => ['projects_eyebrow', 'projects_heading', 'projects_intro', 'workplan_heading', 'workplan_review_note'],
                'Registration information' => ['registration_eyebrow', 'registration_heading', 'registration_intro', 'membership_form_heading', 'membership_form_intro', 'registration_name_label', 'registration_year_label', 'registration_profession_label', 'registration_email_label', 'registration_phone_label', 'registration_submit_label', 'registration_privacy_note'],
                'Footer information' => ['footer_mission', 'footer_links_heading', 'footer_office_heading', 'footer_address', 'footer_phone', 'footer_email', 'footer_copyright', 'footer_subtitle'],
            ];
            foreach ($sections as $sectionName => $keys): ?>
                <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-7">
                    <h2 class="mb-5 text-lg font-black text-brand-green"><?= $escape($sectionName) ?></h2>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <?php foreach ($keys as $key):
                            $longText = in_array($key, ['hero_intro', 'president_message', 'about_paragraph_one', 'about_paragraph_two', 'benefits_intro', 'achievements_intro', 'leadership_intro', 'representatives_note', 'projects_intro', 'registration_intro', 'registration_privacy_note', 'footer_mission'], true);
                        ?>
                            <label class="<?= $longText ? 'md:col-span-2' : '' ?>">
                                <span class="mb-1 block text-xs font-bold uppercase text-gray-600"><?= $escape($scalarFields[$key]) ?></span>
                                <?php if ($longText): ?>
                                    <textarea name="content[<?= $escape($key) ?>]" rows="3" maxlength="4000" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"><?= $escape($content[$key]) ?></textarea>
                                <?php else: ?>
                                    <input type="text" name="content[<?= $escape($key) ?>]" maxlength="4000" value="<?= $escape($content[$key]) ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php foreach ($rowFields as $key => [$label, $columns, $format, $maxRows]):
                        $sectionForRows = [
                            'stats' => 'Main page and welcome',
                            'benefits' => 'Membership benefits',
                            'achievements' => 'Achievements',
                            'leaders' => 'Executive council',
                            'projects' => 'Projects and work plan',
                            'workplan_phases' => 'Projects and work plan',
                            'registration_steps' => 'Registration information',
                        ][$key];
                        if ($sectionForRows !== $sectionName) continue;
                    ?>
                        <label class="mt-5 block">
                            <span class="mb-1 block text-xs font-bold uppercase text-gray-600"><?= $escape($label) ?></span>
                            <span class="mb-2 block text-xs text-gray-500">One entry per line: <code><?= $escape($format) ?></code></span>
                            <textarea name="content[<?= $escape($key) ?>]" rows="<?= max(4, count($content[$key]) + 1) ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 font-mono text-xs"><?= $escape($rowText($content[$key], $columns)) ?></textarea>
                        </label>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>

            <div class="sticky bottom-3 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white/95 p-4 shadow-lg backdrop-blur">
                <p class="text-xs text-gray-500">Changes are published to the public Alumni page immediately after saving.</p>
                <button class="rounded-lg bg-brand-green px-6 py-3 text-sm font-black text-white hover:bg-brand-darkGreen">Save and publish page</button>
            </div>
        </form>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>
