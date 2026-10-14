<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth_admin.php';
require_once __DIR__ . '/auth_teacher.php';

$isAdmin = !empty($_SESSION['admin_id']);
$isSmsTeacher = false;
if (!$isAdmin && !empty($_SESSION['teacher_id'])) {
    $permission = thamani_db_prepare($conn, "SELECT id FROM teachers WHERE id = ? AND is_active = 1 AND can_send_sms = 1 LIMIT 1");
    if ($permission) {
        $teacherId = (int)$_SESSION['teacher_id'];
        thamani_db_stmt_bind_param($permission, 'i', $teacherId);
        thamani_db_stmt_execute($permission);
        $result = thamani_db_stmt_get_result($permission);
        $isSmsTeacher = (bool)($result && thamani_db_fetch_assoc($result));
        thamani_db_stmt_close($permission);
    }
}

if (!$isAdmin && !$isSmsTeacher) {
    http_response_code(403);
    exit('You do not have permission to access SMS.');
}

$csrfToken = bin2hex(random_bytes(32));
$_SESSION['sms_csrf_token'] = $csrfToken;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Messaging | Thamani High School</title>
    <link rel="stylesheet" href="css/tailwind.min.css">
    <style>
        :root { color-scheme: light; }
        .sms-tabular { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body class="min-h-screen bg-gray-100 text-gray-900">
    <header class="bg-gray-950 text-white border-b-4 border-amber-500">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="<?= $isAdmin ? 'admin_dashboard.php' : 'teacher_dashboard.php' ?>" class="text-xs text-amber-300 hover:text-white">← Back to dashboard</a>
                <h1 class="text-xl sm:text-2xl font-black mt-1">SMS Messaging</h1>
            </div>
            <span class="text-xs font-bold uppercase tracking-wide text-gray-300"><?= $isAdmin ? 'Administrator' : 'Authorized teacher' ?></span>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6">
        <div id="sms-notice" class="hidden rounded-lg border px-4 py-3 text-sm" role="status" aria-live="polite"></div>

        <?php if ($isAdmin): ?>
        <section class="bg-white border border-gray-200 rounded-lg p-5">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                <div>
                    <h2 class="font-bold text-lg">Provider settings</h2>
                    <p id="sms-credential-state" class="text-xs text-gray-500 mt-1">Credentials are stored in school settings.</p>
                </div>
            </div>
            <form id="sms-credentials-form" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                <label class="text-xs font-bold text-gray-700">Ego SMS username
                    <input id="ego-username" name="ego_username" autocomplete="username" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                </label>
                <label class="text-xs font-bold text-gray-700">Ego SMS password
                    <input id="ego-password" name="ego_password" type="password" autocomplete="new-password" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" placeholder="Leave blank to keep saved password">
                </label>
                <label class="text-xs font-bold text-gray-700">Sender ID
                    <input id="ego-sender-id" name="ego_sender_id" maxlength="11" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" value="THAMANI">
                </label>
                <button class="rounded bg-gray-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-gray-700">Save credentials</button>
            </form>
            <form id="sms-test-form" class="mt-4 flex flex-wrap items-end gap-3 border-t border-gray-100 pt-4">
                <label class="text-xs font-bold text-gray-700">Test phone
                    <input name="test_phone" inputmode="tel" placeholder="0772 123 456" required class="mt-1 w-56 rounded border border-gray-300 px-3 py-2 text-sm">
                </label>
                <button class="rounded border border-gray-300 px-4 py-2 text-sm font-bold hover:bg-gray-50">Send test SMS</button>
            </form>
        </section>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <section class="bg-white border border-gray-200 rounded-lg p-5">
            <div class="mb-4">
                <h2 class="font-bold text-lg">Contact book</h2>
                <p class="text-xs text-gray-500 mt-1">Active contacts are available to SMS-authorized users.</p>
            </div>
            <form id="sms-contact-form" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <input type="hidden" name="contact_id" id="sms-contact-id">
                <label class="text-xs font-bold text-gray-700">Full name
                    <input name="full_name" required maxlength="255" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                </label>
                <label class="text-xs font-bold text-gray-700">Phone
                    <input name="phone" required inputmode="tel" placeholder="0772 123 456" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                </label>
                <label class="text-xs font-bold text-gray-700">Category
                    <select name="category" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                        <option>Parents</option><option>Teachers</option><option>Staff</option><option>Alumni</option><option>Other</option>
                    </select>
                </label>
                <label class="text-xs font-bold text-gray-700">Class / stream
                    <input name="class_level" maxlength="50" placeholder="Optional" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                </label>
                <label class="text-xs font-bold text-gray-700">Stream
                    <input name="stream" maxlength="50" placeholder="Optional" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                </label>
                <label class="text-xs font-bold text-gray-700 sm:col-span-2">Notes
                    <input name="notes" maxlength="1000" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                </label>
                <div class="flex items-end gap-2">
                    <button id="sms-contact-save" class="rounded bg-amber-500 px-4 py-2.5 text-sm font-bold text-gray-950 hover:bg-amber-400">Add contact</button>
                    <button type="button" id="sms-contact-cancel" class="hidden rounded border border-gray-300 px-3 py-2.5 text-sm font-bold">Cancel</button>
                </div>
            </form>
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4">
                <label class="text-xs font-bold text-gray-700">Filter
                    <select id="sms-contact-filter" class="ml-2 rounded border border-gray-300 px-3 py-2 text-sm font-normal">
                        <option>All</option><option>Parents</option><option>Teachers</option><option>Staff</option><option>Alumni</option><option>Other</option>
                    </select>
                </label>
                <div class="flex flex-wrap gap-2">
                    <button type="button" data-import="parents" class="sms-import rounded border border-gray-300 px-3 py-2 text-xs font-bold hover:bg-gray-50">Import guardians</button>
                    <button type="button" data-import="teachers" class="sms-import rounded border border-gray-300 px-3 py-2 text-xs font-bold hover:bg-gray-50">Import teachers</button>
                    <button type="button" id="sms-refresh-contacts" class="rounded bg-gray-900 px-3 py-2 text-xs font-bold text-white hover:bg-gray-700">Refresh</button>
                </div>
            </div>
            <div class="overflow-x-auto mt-3">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-y border-gray-200 bg-gray-50 text-xs uppercase text-gray-500"><th class="p-2.5">Name</th><th class="p-2.5">Phone</th><th class="p-2.5">Category</th><th class="p-2.5">Class</th><th class="p-2.5">Status</th><th class="p-2.5 text-right">Actions</th></tr></thead>
                    <tbody id="sms-contact-rows"><tr><td colspan="6" class="p-5 text-center text-gray-500">Loading contacts...</td></tr></tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <section class="bg-white border border-gray-200 rounded-lg p-5">
            <h2 class="font-bold text-lg">Compose message</h2>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mt-4">
                <div class="lg:col-span-2 space-y-3">
                    <label class="block text-xs font-bold text-gray-700">Recipients
                        <select id="sms-category" class="mt-1 w-full rounded border border-gray-300 px-3 py-2.5 text-sm">
                            <option value="All">All active contacts</option><option>Parents</option><option>Teachers</option><option>Staff</option><option>Alumni</option><option>Other</option>
                        </select>
                    </label>
                    <label class="block text-xs font-bold text-gray-700">Send to one contact instead
                        <select id="sms-individual" class="mt-1 w-full rounded border border-gray-300 px-3 py-2.5 text-sm"><option value="0">No individual selected</option></select>
                    </label>
                    <label class="block text-xs font-bold text-gray-700">Message
                        <textarea id="sms-message" rows="5" maxlength="1600" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2.5 text-sm" placeholder="Write your message"></textarea>
                    </label>
                    <div class="flex flex-wrap items-center gap-2">
                        <span id="sms-character-count" class="text-xs text-gray-500 sms-tabular">0 / 1600</span>
                        <button id="sms-preview" type="button" class="ml-auto rounded border border-gray-300 px-4 py-2 text-sm font-bold hover:bg-gray-50">Preview recipients</button>
                        <button id="sms-send" type="button" class="rounded bg-rose-700 px-4 py-2 text-sm font-bold text-white hover:bg-rose-800">Send SMS</button>
                    </div>
                </div>
                <div class="border-t border-gray-100 pt-4 lg:border-t-0 lg:border-l lg:pl-5 lg:pt-0">
                    <h3 class="text-sm font-bold">Recipient preview <span id="sms-recipient-count" class="text-gray-500"></span></h3>
                    <ul id="sms-recipient-preview" class="mt-2 max-h-64 overflow-y-auto divide-y divide-gray-100 text-xs text-gray-600"><li class="py-3">Preview recipients before sending.</li></ul>
                </div>
            </div>
        </section>

        <?php if ($isAdmin): ?>
        <section class="bg-white border border-gray-200 rounded-lg p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-bold text-lg">Recent SMS batches</h2>
                <button type="button" id="sms-refresh-logs" class="rounded border border-gray-300 px-3 py-2 text-xs font-bold hover:bg-gray-50">Refresh history</button>
            </div>
            <div class="overflow-x-auto mt-3">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-y border-gray-200 bg-gray-50 text-xs uppercase text-gray-500"><th class="p-2.5">Time</th><th class="p-2.5">Recipients</th><th class="p-2.5">Filter</th><th class="p-2.5">Message</th><th class="p-2.5">Status</th></tr></thead>
                    <tbody id="sms-log-rows"><tr><td colspan="5" class="p-5 text-center text-gray-500">Loading history...</td></tr></tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <script>
        const SMS_IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
        const SMS_CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
        const smsNotice = document.getElementById('sms-notice');
        const smsEscape = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));
        let smsContacts = [];

        function showSmsNotice(message, success = false) {
            smsNotice.textContent = message;
            smsNotice.className = `rounded-lg border px-4 py-3 text-sm ${success ? 'border-green-200 bg-green-50 text-green-900' : 'border-red-200 bg-red-50 text-red-900'}`;
        }

        async function smsPost(action, values = {}) {
            const body = new URLSearchParams({action, ...values, csrf_token: SMS_CSRF_TOKEN});
            const response = await fetch('admin_sms_actions.php', {method: 'POST', body, credentials: 'same-origin'});
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.error || 'SMS request failed.');
            return data;
        }

        async function smsGet(action, values = {}) {
            const query = new URLSearchParams({action, ...values});
            const response = await fetch(`admin_sms_actions.php?${query}`, {credentials: 'same-origin'});
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.error || 'SMS request failed.');
            return data;
        }

        async function loadSmsContacts() {
            const category = SMS_IS_ADMIN ? document.getElementById('sms-contact-filter').value : 'All';
            const data = await smsGet('get_contacts', {category});
            smsContacts = data.contacts || [];
            const individual = document.getElementById('sms-individual');
            individual.innerHTML = '<option value="0">No individual selected</option>' + smsContacts.filter(contact => Number(contact.is_active) === 1).map(contact => `<option value="${Number(contact.id)}">${smsEscape(contact.full_name)} · ${smsEscape(contact.phone)}</option>`).join('');
            if (SMS_IS_ADMIN) {
                const rows = document.getElementById('sms-contact-rows');
                rows.innerHTML = smsContacts.length ? smsContacts.map(contact => `<tr class="border-b border-gray-100"><td class="p-2.5 font-semibold">${smsEscape(contact.full_name)}</td><td class="p-2.5 font-mono text-xs">${smsEscape(contact.phone)}</td><td class="p-2.5">${smsEscape(contact.category)}</td><td class="p-2.5">${smsEscape(contact.class_level || '')} ${smsEscape(contact.stream || '')}</td><td class="p-2.5">${Number(contact.is_active) === 1 ? 'Active' : 'Inactive'}</td><td class="p-2.5 text-right whitespace-nowrap"><button type="button" data-edit-contact="${Number(contact.id)}" class="px-2 py-1 text-xs font-bold text-amber-800">Edit</button><button type="button" data-toggle-contact="${Number(contact.id)}" class="px-2 py-1 text-xs font-bold text-gray-700">${Number(contact.is_active) === 1 ? 'Disable' : 'Enable'}</button><button type="button" data-delete-contact="${Number(contact.id)}" class="px-2 py-1 text-xs font-bold text-red-700">Delete</button></td></tr>`).join('') : '<tr><td colspan="6" class="p-5 text-center text-gray-500">No contacts found.</td></tr>';
            }
        }

        async function loadSmsSettings() {
            const data = await smsGet('get_sms_settings');
            document.getElementById('ego-username').value = data.username || '';
            document.getElementById('ego-sender-id').value = data.sender_id || 'THAMANI';
            document.getElementById('sms-credential-state').textContent = data.password_configured ? 'A provider password is saved. It is never displayed here.' : 'No provider password is saved.';
        }

        async function loadSmsLogs() {
            const data = await smsGet('get_sms_logs', {limit: '50'});
            const rows = document.getElementById('sms-log-rows');
            rows.innerHTML = data.logs.length ? data.logs.map(log => `<tr class="border-b border-gray-100"><td class="p-2.5 text-xs">${smsEscape(log.sent_at)}</td><td class="p-2.5 sms-tabular">${Number(log.recipient_count)}</td><td class="p-2.5">${smsEscape(log.category_filter)}</td><td class="p-2.5 max-w-md truncate">${smsEscape(log.message_preview)}</td><td class="p-2.5 font-bold">${smsEscape(log.status)}</td></tr>`).join('') : '<tr><td colspan="5" class="p-5 text-center text-gray-500">No SMS batches have been sent.</td></tr>';
        }

        async function previewSmsRecipients() {
            const individualId = document.getElementById('sms-individual').value;
            const data = await smsPost('preview_recipients', {category: document.getElementById('sms-category').value, individual_id: individualId});
            document.getElementById('sms-recipient-count').textContent = `(${data.total})`;
            const list = document.getElementById('sms-recipient-preview');
            list.innerHTML = data.recipients.length ? data.recipients.slice(0, 100).map(contact => `<li class="py-2"><span class="font-semibold text-gray-800">${smsEscape(contact.full_name)}</span><span class="block font-mono">${smsEscape(contact.phone)}</span></li>`).join('') : '<li class="py-3">No active recipients.</li>';
            if (data.total > 100) list.insertAdjacentHTML('beforeend', `<li class="py-2 text-gray-500">And ${data.total - 100} more...</li>`);
            return data.total;
        }

        document.getElementById('sms-category').addEventListener('change', () => { document.getElementById('sms-individual').value = '0'; });
        document.getElementById('sms-individual').addEventListener('change', () => { if (document.getElementById('sms-individual').value !== '0') document.getElementById('sms-category').value = 'All'; });
        document.getElementById('sms-message').addEventListener('input', event => { document.getElementById('sms-character-count').textContent = `${Array.from(event.target.value).length} / 1600`; });
        document.getElementById('sms-preview').addEventListener('click', async () => { try { await previewSmsRecipients(); } catch (error) { showSmsNotice(error.message); } });
        document.getElementById('sms-send').addEventListener('click', async () => {
            const message = document.getElementById('sms-message').value.trim();
            if (!message) return showSmsNotice('Enter a message first.');
            try {
                const recipientCount = await previewSmsRecipients();
                if (!recipientCount || !window.confirm(`Send this SMS to ${recipientCount} recipient(s)?`)) return;
                const result = await smsPost('send_bulk_sms', {message, category: document.getElementById('sms-category').value, individual_id: document.getElementById('sms-individual').value});
                showSmsNotice(result.message, result.success);
                if (SMS_IS_ADMIN) await loadSmsLogs();
            } catch (error) { showSmsNotice(error.message); }
        });

        if (SMS_IS_ADMIN) {
            document.getElementById('sms-credentials-form').addEventListener('submit', async event => {
                event.preventDefault();
                const values = Object.fromEntries(new FormData(event.currentTarget));
                if (!values.ego_password) delete values.ego_password;
                try {
                    const result = await smsPost('save_sms_credentials', values);
                    document.getElementById('ego-password').value = '';
                    showSmsNotice(result.message, true);
                    await loadSmsSettings();
                } catch (error) { showSmsNotice(error.message); }
            });
            document.getElementById('sms-test-form').addEventListener('submit', async event => {
                event.preventDefault();
                try {
                    const result = await smsPost('test_sms_credentials', Object.fromEntries(new FormData(event.currentTarget)));
                    showSmsNotice(result.success ? `Test SMS sent (${result.sent_count}).` : result.error, result.success);
                } catch (error) { showSmsNotice(error.message); }
            });
            document.getElementById('sms-contact-form').addEventListener('submit', async event => {
                event.preventDefault();
                try {
                    const result = await smsPost('save_contact', Object.fromEntries(new FormData(event.currentTarget)));
                    showSmsNotice(result.message || 'Contact saved.', true);
                    event.currentTarget.reset();
                    document.getElementById('sms-contact-id').value = '';
                    document.getElementById('sms-contact-save').textContent = 'Add contact';
                    document.getElementById('sms-contact-cancel').classList.add('hidden');
                    await loadSmsContacts();
                } catch (error) { showSmsNotice(error.message); }
            });
            document.getElementById('sms-contact-cancel').addEventListener('click', () => {
                document.getElementById('sms-contact-form').reset();
                document.getElementById('sms-contact-id').value = '';
                document.getElementById('sms-contact-save').textContent = 'Add contact';
                document.getElementById('sms-contact-cancel').classList.add('hidden');
            });
            document.getElementById('sms-contact-filter').addEventListener('change', () => loadSmsContacts().catch(error => showSmsNotice(error.message)));
            document.getElementById('sms-refresh-contacts').addEventListener('click', () => loadSmsContacts().catch(error => showSmsNotice(error.message)));
            document.getElementById('sms-refresh-logs').addEventListener('click', () => loadSmsLogs().catch(error => showSmsNotice(error.message)));
            document.querySelectorAll('.sms-import').forEach(button => button.addEventListener('click', async () => {
                try {
                    const result = await smsPost('import_contacts', {source: button.dataset.import});
                    showSmsNotice(`Imported ${result.imported} contact(s); skipped ${result.skipped}.`, true);
                    await loadSmsContacts();
                } catch (error) { showSmsNotice(error.message); }
            }));
            document.getElementById('sms-contact-rows').addEventListener('click', async event => {
                const editId = event.target.dataset.editContact;
                const toggleId = event.target.dataset.toggleContact;
                const deleteId = event.target.dataset.deleteContact;
                if (editId) {
                    const contact = smsContacts.find(item => Number(item.id) === Number(editId));
                    if (!contact) return;
                    const form = document.getElementById('sms-contact-form');
                    for (const field of ['contact_id', 'full_name', 'phone', 'category', 'class_level', 'notes']) form.elements[field].value = contact[field] || '';
                    form.elements.stream.value = contact.stream || '';
                    document.getElementById('sms-contact-save').textContent = 'Save changes';
                    document.getElementById('sms-contact-cancel').classList.remove('hidden');
                } else if (toggleId) {
                    try { await smsPost('toggle_contact', {contact_id: toggleId}); await loadSmsContacts(); }
                    catch (error) { showSmsNotice(error.message); }
                } else if (deleteId && window.confirm('Delete this SMS contact?')) {
                    try { await smsPost('delete_contact', {contact_id: deleteId}); await loadSmsContacts(); }
                    catch (error) { showSmsNotice(error.message); }
                }
            });
        }

        (async () => {
            try {
                await loadSmsContacts();
                if (SMS_IS_ADMIN) await Promise.all([loadSmsSettings(), loadSmsLogs()]);
            } catch (error) { showSmsNotice(error.message); }
        })();
    </script>
</body>
</html>
