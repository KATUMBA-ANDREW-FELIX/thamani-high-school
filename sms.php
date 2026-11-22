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
            <div class="flex flex-wrap items-center gap-2.5">
                <span class="rounded-lg bg-gray-900 border border-gray-800 px-3 py-1.5 text-xs font-bold text-gray-200 sms-tabular">
                    SMS Credits: <strong id="sms-page-wallet-balance" class="text-orange-400">—</strong>
                </span>
                <button type="button" id="sms-open-bitlink-modal" class="rounded-lg bg-orange-600 hover:bg-orange-500 px-3.5 py-1.5 text-xs font-black text-white">
                    + Buy More SMS · bitlink pay
                </button>
                <span class="text-xs font-bold uppercase tracking-wide text-gray-300"><?= $isAdmin ? 'Administrator' : 'Authorized teacher' ?></span>
            </div>
        </div>
    </header>

    <div id="sms-bitlink-modal" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl overflow-hidden border border-gray-200">
            <div class="px-5 py-4 bg-[#0b1220] text-white flex justify-between items-center">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-widest text-[#fb923c]">bitlink pay</div>
                    <h2 class="text-base font-black mt-0.5">Buy More SMS Credits</h2>
                </div>
                <button type="button" id="sms-close-bitlink-modal" class="text-gray-400 hover:text-white text-sm font-bold">✕</button>
            </div>
            <form id="sms-bitlink-form" class="p-5 space-y-4">
                <div id="sms-bitlink-status" class="hidden rounded-lg border px-3.5 py-2.5 text-xs font-semibold"></div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="text-xs font-bold text-gray-700">SMS Credits to Buy *
                        <input type="number" id="sms-bitlink-units" name="sms_units" value="600" min="20" max="500000" step="10" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono font-bold">
                    </label>
                    <label class="text-xs font-bold text-gray-700">Mobile Money Phone (MTN / Airtel) *
                        <input type="tel" name="payer_phone" placeholder="0772 123 456" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono">
                    </label>
                </div>
                <label class="block text-xs font-bold text-gray-700">Thamani Receipt Email (bitlink pay receipt) *
                    <input type="email" id="sms-bitlink-email" name="receipt_email" value="info@thamani.ac.ug" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                </label>
                <div class="rounded-lg bg-gray-50 border border-gray-200 p-3.5 space-y-1 text-xs">
                    <div class="flex justify-between text-gray-600"><span>SMS Subtotal</span><span id="sms-bitlink-sub" class="font-mono font-bold">UGX 30,000</span></div>
                    <div class="flex justify-between text-gray-600"><span>Processing &amp; transactional fees</span><span id="sms-bitlink-fee" class="font-mono font-bold">UGX 1,900</span></div>
                    <div class="flex justify-between text-gray-900 font-black text-sm pt-1.5 border-t border-gray-200"><span>Total to Pay</span><span id="sms-bitlink-tot" class="font-mono text-orange-600">UGX 31,900</span></div>
                </div>
                <div class="flex items-center justify-between pt-1">
                    <span class="text-[11px] text-gray-400">Powered by <strong class="text-gray-700">bitlink pay</strong></span>
                    <button type="submit" id="sms-bitlink-submit" class="rounded-lg bg-[#0b1220] hover:bg-gray-800 text-white px-4 py-2 text-xs font-black">Pay with bitlink pay</button>
                </div>
            </form>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6">
        <div id="sms-notice" class="hidden rounded-lg border px-4 py-3 text-sm" role="status" aria-live="polite"></div>

        <?php if ($isAdmin): ?>
        <section class="bg-white border border-amber-200 rounded-lg p-5 space-y-4">
            <div>
                <h2 class="font-bold text-lg">Send portal login credentials</h2>
                <p class="text-xs text-gray-500 mt-1">Dispatch personalized portal login IDs, passwords, and PayCodes to Parents/Guardians, Teachers, Alumni Editors, or any person via Ego SMS.</p>
            </div>
            <form id="sms-credentials-dispatch-form" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <label class="text-xs font-bold text-gray-700">Recipient group
                        <select id="sms-cred-group" name="target_group" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm font-semibold">
                            <option value="parents">Parents / Guardians (Student Portal)</option>
                            <option value="teachers">Teachers (Staff Portal)</option>
                            <option value="other">Other Person / Alumni Editor / Custom</option>
                        </select>
                    </label>
                    <label id="sms-cred-class-wrap" class="text-xs font-bold text-gray-700">Filter by class
                        <select id="sms-cred-class" name="class_level" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="ALL">All Enrolled Classes (S1–S6)</option>
                            <option value="Senior 1">Senior 1</option>
                            <option value="Senior 2">Senior 2</option>
                            <option value="Senior 3">Senior 3</option>
                            <option value="Senior 4">Senior 4</option>
                            <option value="Senior 5">Senior 5</option>
                            <option value="Senior 6">Senior 6</option>
                        </select>
                    </label>
                    <label id="sms-cred-student-wrap" class="text-xs font-bold text-gray-700">Or specific student/parent
                        <select id="sms-cred-student" name="student_id" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="0">All in selected class</option>
                        </select>
                    </label>
                    <label id="sms-cred-teacher-wrap" class="hidden text-xs font-bold text-gray-700 sm:col-span-2">Select teacher(s)
                        <select id="sms-cred-teacher" name="teacher_id" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="0">All Active Teachers</option>
                        </select>
                    </label>
                    <label id="sms-cred-other-picker-wrap" class="hidden text-xs font-bold text-gray-700 sm:col-span-2">Quick-fill from contact / alumni
                        <select id="sms-cred-other-preset" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="">-- Type manually below or pick a saved person --</option>
                        </select>
                    </label>
                    <div class="text-xs font-bold text-gray-700">Portal password to send
                        <div class="mt-1 flex gap-1.5">
                            <input type="text" id="sms-cred-pwd" name="temp_password" value="Admin@2026" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono">
                            <button type="button" id="sms-cred-gen-pwd" class="rounded border border-gray-300 px-2.5 py-2 text-xs font-bold hover:bg-gray-50 shrink-0">Random</button>
                        </div>
                    </div>
                </div>
                <div id="sms-cred-other-fields" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 p-3.5 rounded bg-gray-50 border border-gray-200">
                    <input type="hidden" id="sms-cred-alumnus-id" name="alumnus_id" value="0">
                    <label class="text-xs font-bold text-gray-700">Recipient name
                        <input type="text" id="sms-cred-name" name="recipient_name" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm bg-white">
                    </label>
                    <label class="text-xs font-bold text-gray-700">Phone number *
                        <input type="text" id="sms-cred-phone" name="recipient_phone" placeholder="0772 123 456" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm bg-white font-mono">
                    </label>
                    <label class="text-xs font-bold text-gray-700">Login ID / Email *
                        <input type="text" id="sms-cred-login" name="login_identifier" placeholder="Email, Staff ID, or LIN" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm bg-white font-mono">
                    </label>
                    <label class="text-xs font-bold text-gray-700">Portal role label
                        <select id="sms-cred-role" name="role_label" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm bg-white">
                            <option value="Alumni Page Editor">Alumni Page Editor</option>
                            <option value="Staff Portal">Staff Portal</option>
                            <option value="Student Portal">Student Portal</option>
                            <option value="Admin Portal">Admin Portal</option>
                            <option value="Portal Access">General Portal Access</option>
                        </select>
                    </label>
                    <label class="sm:col-span-2 lg:col-span-4 inline-flex items-center gap-2 text-xs font-semibold text-amber-900 cursor-pointer">
                        <input type="checkbox" id="sms-cred-grant-alumni" name="grant_alumni_editor" value="1" class="rounded border-gray-300">
                        <span>Also activate this Alumnus in the database as an <strong>Alumni Page Editor</strong> with the password above</span>
                    </label>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label id="sms-cred-sync-wrap" class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 cursor-pointer">
                        <input type="checkbox" id="sms-cred-sync" name="sync_password" value="1" checked class="rounded border-gray-300">
                        <span>Set / reset recipient portal password(s) to the password above and require password change on first login</span>
                    </label>
                    <button type="submit" id="sms-cred-send" class="rounded bg-amber-500 px-4 py-2.5 text-sm font-bold text-gray-950 hover:bg-amber-400">Send portal credentials SMS</button>
                </div>
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

        let smsCredTargets = {students: [], teachers: [], alumni: []};
        async function loadSmsCredentialTargets() {
            if (!SMS_IS_ADMIN) return;
            const d = await smsGet('get_credential_targets');
            smsCredTargets = {students: d.students || [], teachers: d.teachers || [], alumni: d.alumni || []};
            populateSmsCredPickers();
        }
        function populateSmsCredPickers() {
            const cls = document.getElementById('sms-cred-class')?.value || 'ALL';
            const stSel = document.getElementById('sms-cred-student');
            if (stSel) {
                const filtered = smsCredTargets.students.filter(s => cls === 'ALL' || s.class_level === cls);
                const withPhone = filtered.filter(s => s.normalized_phone).length;
                stSel.innerHTML = `<option value="0">All in selection (${withPhone} with valid phone)</option>` +
                    filtered.map(s => `<option value="${Number(s.id)}">${smsEscape(s.full_name)} (${smsEscape(s.class_level)}) · LIN: ${smsEscape(s.lin_number || 'N/A')} · ${smsEscape(s.guardian_phone || 'No phone')}</option>`).join('');
            }
            const tSel = document.getElementById('sms-cred-teacher');
            if (tSel) {
                const withPhone = smsCredTargets.teachers.filter(t => t.normalized_phone).length;
                tSel.innerHTML = `<option value="0">All Active Teachers (${withPhone} of ${smsCredTargets.teachers.length} with valid phone)</option>` +
                    smsCredTargets.teachers.map(t => `<option value="${Number(t.id)}">${smsEscape(t.full_name)} · ${smsEscape(t.staff_id)} · ${smsEscape(t.phone || 'No phone')}</option>`).join('');
            }
            const oSel = document.getElementById('sms-cred-other-preset');
            if (oSel) {
                const alOpts = smsCredTargets.alumni.map(a =>
                    `<option value="alumni:${Number(a.id)}" data-name="${smsEscape(a.name)}" data-phone="${smsEscape(a.phone || '')}" data-login="${smsEscape(a.email || '')}" data-role="Alumni Page Editor" data-alumnus="${Number(a.id)}">[Alumni] ${smsEscape(a.name)} · ${smsEscape(a.email || 'No email')} · ${smsEscape(a.phone || 'No phone')}</option>`
                ).join('');
                const ctOpts = smsContacts.map(c =>
                    `<option value="contact:${Number(c.id)}" data-name="${smsEscape(c.full_name)}" data-phone="${smsEscape(c.phone || '')}" data-login="" data-role="Portal Access" data-alumnus="0">[${smsEscape(c.category)}] ${smsEscape(c.full_name)} · ${smsEscape(c.phone)}</option>`
                ).join('');
                oSel.innerHTML = '<option value="">-- Type manually below or pick a saved person --</option>' + alOpts + ctOpts;
            }
        }

        if (SMS_IS_ADMIN) {
            document.getElementById('sms-cred-group').addEventListener('change', ev => {
                const g = ev.target.value;
                document.getElementById('sms-cred-class-wrap').classList.toggle('hidden', g !== 'parents');
                document.getElementById('sms-cred-student-wrap').classList.toggle('hidden', g !== 'parents');
                document.getElementById('sms-cred-teacher-wrap').classList.toggle('hidden', g !== 'teachers');
                document.getElementById('sms-cred-other-picker-wrap').classList.toggle('hidden', g !== 'other');
                document.getElementById('sms-cred-other-fields').classList.toggle('hidden', g !== 'other');
                document.getElementById('sms-cred-sync-wrap').classList.toggle('hidden', g === 'other');
            });
            document.getElementById('sms-cred-class').addEventListener('change', populateSmsCredPickers);
            document.getElementById('sms-cred-other-preset').addEventListener('change', ev => {
                const opt = ev.target.selectedOptions[0];
                if (!opt || !opt.value) return;
                document.getElementById('sms-cred-name').value = opt.dataset.name || '';
                document.getElementById('sms-cred-phone').value = opt.dataset.phone || '';
                if (opt.dataset.login) document.getElementById('sms-cred-login').value = opt.dataset.login;
                if (opt.dataset.role) document.getElementById('sms-cred-role').value = opt.dataset.role;
                document.getElementById('sms-cred-alumnus-id').value = opt.dataset.alumnus || '0';
                document.getElementById('sms-cred-grant-alumni').checked = Number(opt.dataset.alumnus || 0) > 0;
            });
            document.getElementById('sms-cred-gen-pwd').addEventListener('click', () => {
                const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
                let out = 'Thm@';
                for (let i = 0; i < 6; i++) out += chars[Math.floor(Math.random() * chars.length)];
                document.getElementById('sms-cred-pwd').value = out;
            });
            document.getElementById('sms-credentials-dispatch-form').addEventListener('submit', async event => {
                event.preventDefault();
                if (!window.confirm('Send portal login credentials via Ego SMS to the selected recipient(s)?')) return;
                const values = Object.fromEntries(new FormData(event.currentTarget));
                values.sync_password = document.getElementById('sms-cred-sync').checked ? '1' : '0';
                values.grant_alumni_editor = document.getElementById('sms-cred-grant-alumni').checked ? '1' : '0';
                try {
                    const result = await smsPost('send_portal_credentials', values);
                    showSmsNotice(result.message, result.success);
                    await loadSmsLogs();
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
                    for (const field of ['full_name', 'phone', 'category', 'class_level', 'stream', 'notes']) {
                        if (form.elements[field]) form.elements[field].value = contact[field] || '';
                    }
                    document.getElementById('sms-contact-id').value = contact.id;
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

        let smsPageWalletCfg = {unit_price_ugx: 50, collection_fee_percent: 4.0, thamani_receipt_email: 'info@thamani.ac.ug'};
        let smsPagePollTimer = null;
        async function loadSmsPageWallet() {
            const d = await smsGet('get_sms_wallet');
            document.getElementById('sms-page-wallet-balance').textContent = Number(d.wallet_balance || 0).toLocaleString();
            if (d.config) {
                smsPageWalletCfg = d.config;
                document.getElementById('sms-bitlink-email').value = d.config.thamani_receipt_email || 'info@thamani.ac.ug';
            }
            recalcSmsPageBitlink();
        }
        function recalcSmsPageBitlink() {
            const units = Math.max(20, parseInt(document.getElementById('sms-bitlink-units').value || '600', 10) || 600);
            const sub = units * Number(smsPageWalletCfg.unit_price_ugx || 50);
            const coll = Math.round(sub * (Number(smsPageWalletCfg.collection_fee_percent || 4.0) / 100));
            const clr = sub > 1000000 ? 2100 : (sub > 500000 ? 1700 : (sub > 60000 ? 1100 : 700));
            document.getElementById('sms-bitlink-sub').textContent = 'UGX ' + sub.toLocaleString();
            document.getElementById('sms-bitlink-fee').textContent = 'UGX ' + (coll + clr).toLocaleString();
            document.getElementById('sms-bitlink-tot').textContent = 'UGX ' + (sub + coll + clr).toLocaleString();
        }
        document.getElementById('sms-open-bitlink-modal').addEventListener('click', () => {
            recalcSmsPageBitlink();
            document.getElementById('sms-bitlink-modal').classList.remove('hidden');
        });
        document.getElementById('sms-close-bitlink-modal').addEventListener('click', () => {
            clearInterval(smsPagePollTimer);
            document.getElementById('sms-bitlink-modal').classList.add('hidden');
        });
        document.getElementById('sms-bitlink-units').addEventListener('input', recalcSmsPageBitlink);
        document.getElementById('sms-bitlink-form').addEventListener('submit', async ev => {
            ev.preventDefault();
            clearInterval(smsPagePollTimer);
            const btn = document.getElementById('sms-bitlink-submit');
            const st = document.getElementById('sms-bitlink-status');
            btn.disabled = true;
            try {
                const d = await smsPost('initiate_sms_topup', Object.fromEntries(new FormData(ev.currentTarget)));
                st.textContent = d.message;
                st.className = 'rounded-lg border px-3.5 py-2.5 text-xs font-semibold ' + (d.payment_status === 'PAID' ? 'border-green-200 bg-green-50 text-green-900' : 'border-orange-200 bg-orange-50 text-orange-950');
                st.classList.remove('hidden');
                if (d.wallet_balance !== undefined) document.getElementById('sms-page-wallet-balance').textContent = Number(d.wallet_balance).toLocaleString();
                if (d.payment_status === 'PAID') { btn.disabled = false; return; }
                let tries = 0;
                smsPagePollTimer = setInterval(async () => {
                    tries++;
                    try {
                        const p = await smsPost('poll_sms_topup', {order_number: d.order_number});
                        if (p.wallet_balance !== undefined) document.getElementById('sms-page-wallet-balance').textContent = Number(p.wallet_balance).toLocaleString();
                        if (p.payment_status === 'PAID' || p.payment_status === 'FAILED' || tries >= 40) {
                            clearInterval(smsPagePollTimer);
                            st.textContent = p.message;
                            st.className = 'rounded-lg border px-3.5 py-2.5 text-xs font-semibold ' + (p.payment_status === 'PAID' ? 'border-green-200 bg-green-50 text-green-900' : 'border-red-200 bg-red-50 text-red-900');
                            btn.disabled = false;
                        }
                    } catch (err) {}
                }, 3500);
            } catch (e) {
                st.textContent = e.message;
                st.className = 'rounded-lg border px-3.5 py-2.5 text-xs font-semibold border-red-200 bg-red-50 text-red-900';
                st.classList.remove('hidden');
                btn.disabled = false;
            }
        });

        (async () => {
            try {
                await Promise.all([loadSmsContacts(), loadSmsPageWallet()]);
                if (SMS_IS_ADMIN) await Promise.all([loadSmsCredentialTargets(), loadSmsLogs()]);
            } catch (error) { showSmsNotice(error.message); }
        })();
    </script>
</body>
</html>
