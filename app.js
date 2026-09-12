// THAMANI HIGH SCHOOL - Application State & Operations Engine

// State Initialization with localStorage Persistence & Safe Fallback Protection
const defaultState = {
    students: [
        { id: 1, name: "Kato Brian", class: "Senior 4", stream: "North", lin: "U0034/501", contact: "+256 772 123456" },
        { id: 2, name: "Nakalema Brenda", class: "Senior 4", stream: "South", lin: "U0034/502", contact: "+256 701 987654" },
        { id: 3, name: "Mwesigwa Isaac", class: "Senior 6", stream: "East", lin: "A0012/604", contact: "+256 782 555111" },
        { id: 4, name: "Akello Joan", class: "Senior 1", stream: "West", lin: "U0034/109", contact: "+256 754 333222" },
        { id: 5, name: "Okello Denis", class: "Senior 3", stream: "North", lin: "U0034/315", contact: "+256 778 444999" }
    ],
    timetables: [
        { id: 1, title: "Senior 4 Term III Master Examination Timetable", class: "Senior 4", stream: "All Streams", format: "pdf", fileName: "S4_Term3_Timetable.pdf", date: "2026-09-08" },
        { id: 2, title: "Senior 6 Science Practical Teaching Schedule", class: "Senior 6", stream: "North", format: "xlsx", fileName: "S6_Science_Schedule.xlsx", date: "2026-09-05" },
        { id: 3, title: "Lower Secondary General Routine & Prep Roster", class: "Senior 1", stream: "All Streams", format: "docx", fileName: "S1_Prep_Routine.docx", date: "2026-09-02" }
    ],
    circulars: [
        { id: 1, title: "End of Term III UNEB Examination Requirements & Fees", body: "Parents and guardians are reminded that all candidate clearance cards must be collected from the Bursar's office before 15th October 2026.", date: "2026-09-09", attachment: "UNEB_Requirements_2026.pdf" },
        { id: 2, title: "Annual Cultural Festival & Parents Visitation Day", body: "Thamani High School will host the 2026 Cultural Gala on 18th October. Traditional music, dance, and regional cuisine will be showcased.", date: "2026-09-04", attachment: "Cultural_Festival_Program.docx" }
    ],
    disciplineLogs: [
        { id: 1, date: "2026-09-08", name: "Ssemwanga Timothy", class: "Senior 3", stream: "South", category: "Lateness", note: "Late for 7:00 AM Morning Prep by 45 minutes", action: "Verbal warning & 30 min campus cleanup" },
        { id: 2, date: "2026-09-07", name: "Katusiime Grace", class: "Senior 4", stream: "North", category: "Commendation", note: "Best overall score in National Physics Olympiad", action: "Awarded Certificate of Excellence & House Points" },
        { id: 3, date: "2026-09-05", name: "Ochieng Emmanuel", class: "Senior 2", stream: "East", category: "Dress Code", note: "Attended assembly without official school necktie", action: "Uniform confiscated & parent notified" }
    ],
    syllabi: {
        "Senior 4-North-Physics": [
            { id: "p1", topic: "Mechanics: Newton's Laws & Linear Motion", completed: true },
            { id: "p2", topic: "Thermal Physics: Heat Transfer & Gas Laws", completed: true },
            { id: "p3", topic: "Waves: Optics, Reflection & Refraction", completed: true },
            { id: "p4", topic: "Electricity & Magnetism: Ohm's Law & Circuits", completed: false },
            { id: "p5", topic: "Atomic & Nuclear Physics: Radioactivity", completed: false }
        ],
        "Senior 4-North-Mathematics": [
            { id: "m1", topic: "Algebra: Quadratic Equations & Matrices", completed: true },
            { id: "m2", topic: "Geometry: Vectors & Transformations", completed: true },
            { id: "m3", topic: "Trigonometry: Sine & Cosine Rules", completed: false },
            { id: "m4", topic: "Calculus: Differentiation & Integration", completed: false }
        ],
        "Senior 1-North-Mathematics": [
            { id: "s1m1", topic: "Number Bases & Place Values", completed: true },
            { id: "s1m2", topic: "Fractions, Decimals & Percentages", completed: true },
            { id: "s1m3", topic: "Introductory Geometry & Angle Properties", completed: false }
        ]
    },
    attachedSyllabusDocs: [
        { subject: "Physics", class: "Senior 4", stream: "North", fileName: "Physics_UNEB_Syllabus_2026.pdf", uploadedBy: "HOD Physics Dept" }
    ]
};

// Safe LocalStorage Loading with Try/Catch Fallback
let state;
try {
    const storedState = localStorage.getItem('thamani_app_state');
    state = storedState ? JSON.parse(storedState) : defaultState;
} catch (e) {
    console.warn("Corrupted localStorage detected. Resetting to default state.", e);
    state = defaultState;
    localStorage.removeItem('thamani_app_state');
}

function saveState() {
    localStorage.setItem('thamani_app_state', JSON.stringify(state));
}

// Global Page Router
function switchPage(pageId) {
    document.querySelectorAll('.page-view').forEach(el => el.classList.add('hidden'));

    const target = document.getElementById(`page-${pageId}`);
    if (target) {
        target.classList.remove('hidden');
    }

    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('text-white', 'bg-brand-green');
        link.classList.add('text-gray-700');
    });

    const activeNav = document.getElementById(`nav-${pageId}`);
    if (activeNav) {
        activeNav.classList.remove('text-gray-700');
        activeNav.classList.add('text-white', 'bg-brand-green');
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });

    if (window.lucide) {
        lucide.createIcons();
    }

    if (pageId === 'home') renderHomeCirculars();
    if (pageId === 'enrollment') renderStudentDirectory();
    if (pageId === 'library') renderLibraryResources();
    if (pageId === 'teacher') renderSyllabusTracker();
    if (pageId === 'admin') {
        renderAdminTimetables();
        renderAdminCirculars();
    }
}

function toggleMobileMenu() {
    const menu = document.getElementById('mobile-menu');
    menu.classList.toggle('hidden');
}

// Robust Form Input Extraction
async function registerAlumni(event, form) {
    event.preventDefault();

    const nameInput = form.querySelector('input[name="name"]') || form.querySelectorAll('input')[0];
    const yearInput = form.querySelector('input[name="year"]') || form.querySelectorAll('input')[1];
    const professionInput = form.querySelector('input[name="profession"]') || form.querySelectorAll('input')[2];
    const emailInput = form.querySelector('input[name="email"]') || form.querySelectorAll('input')[3];
    const phoneInput = form.querySelector('input[name="phone"]') || form.querySelectorAll('input')[4];

    if (!nameInput || !yearInput || !professionInput || !emailInput || !phoneInput) {
        alert('Please fill in all required registration fields.');
        return;
    }

    try {
        const response = await fetch('backend/register_alumni.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: nameInput.value,
                year: yearInput.value,
                profession: professionInput.value,
                email: emailInput.value,
                phone: phoneInput.value
            })
        });

        const responseText = await response.text();
        let data = {};
        if (responseText) {
            try {
                data = JSON.parse(responseText);
            } catch {
                throw new Error(`Backend returned an invalid response (HTTP ${response.status})`);
            }
        }

        if (!response.ok || !data.success) {
            throw new Error(data.error || `Could not save alumni record (HTTP ${response.status})`);
        }

        alert('Thank you for registering with the Thamani High School Alumni Network!');
        form.reset();
    } catch (error) {
        alert(`Registration failed: ${error.message}`);
    }
}

// Modal Helpers
function openModal(modalId) {
    document.getElementById(modalId)?.classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId)?.classList.add('hidden');
}

// Download File Generator with Delayed URL Revocation for Mobile/Slow Browsers
function downloadDocument(fileName, fileType) {
    let content = `THAMANI HIGH SCHOOL - KAKIRI CAMPUS\nOfficial School Document: ${fileName}\nGenerated Date: ${new Date().toLocaleDateString()}\nStatus: Verified UNEB Compliant Document\n\n`;
    content += "=========================================================\n";
    content += "This is an official document from Thamani High School Uganda.\n";
    content += "For enquiries, contact info@thamani.ac.ug\n";
    content += "=========================================================\n";

    let mimeType = "application/pdf";
    if (fileType === 'xlsx' || fileName.endsWith('.xlsx')) mimeType = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
    if (fileType === 'docx' || fileName.endsWith('.docx')) mimeType = "application/vnd.openxmlformats-officedocument.wordprocessingml.document";

    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    
    setTimeout(() => {
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }, 100);
}

// RENDERERS

// 1. Home Page Circulars
function renderHomeCirculars() {
    const container = document.getElementById('home-circulars-container');
    if (!container) return;

    container.innerHTML = state.circulars.map(c => `
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition-shadow flex flex-col justify-between">
            <div>
                <span class="inline-block px-3 py-1 rounded-full bg-brand-lightMaroon text-brand-maroon font-bold text-xs mb-3">Circular • ${c.date}</span>
                <h3 class="text-xl font-bold text-brand-green mb-2">${c.title}</h3>
                <p class="text-gray-600 text-sm line-clamp-3 mb-4">${c.body}</p>
            </div>
            <div class="pt-4 border-t border-gray-100 flex justify-between items-center">
                <span class="text-xs text-gray-500 font-semibold flex items-center gap-1"><i data-lucide="paperclip" class="w-3.5 h-3.5"></i> ${c.attachment}</span>
                <button onclick="downloadDocument('${c.attachment}', 'pdf');" class="text-brand-maroon font-bold text-xs hover:underline flex items-center gap-1">
                    Download <i data-lucide="download" class="w-3.5 h-3.5"></i>
                </button>
            </div>
        </div>
    `).join('');

    if (window.lucide) lucide.createIcons();
}

// 2. Student Directory
function renderStudentDirectory() {
    const tbody = document.getElementById('student-table-body');
    if (!tbody) return;

    const searchTerm = (document.getElementById('filter-student-search')?.value || '').toLowerCase();
    const classFilter = document.getElementById('filter-student-class')?.value || 'ALL';

    const filtered = state.students.filter(s => {
        const matchesSearch = s.name.toLowerCase().includes(searchTerm) || s.lin.toLowerCase().includes(searchTerm);
        const matchesClass = classFilter === 'ALL' || s.class === classFilter;
        return matchesSearch && matchesClass;
    });

    tbody.innerHTML = filtered.length ? filtered.map(s => `
        <tr class="hover:bg-gray-50">
            <td class="p-3 font-bold text-brand-green">${s.name}</td>
            <td class="p-3"><span class="px-2.5 py-0.5 rounded bg-brand-lightGreen text-brand-green font-bold text-xs">${s.class} (${s.stream})</span></td>
            <td class="p-3 font-mono text-xs text-gray-700">${s.lin}</td>
            <td class="p-3 text-xs text-gray-600">${s.contact}</td>
            <td class="p-3 text-right">
                <button onclick="deleteStudent(${s.id});" class="text-red-600 hover:text-red-800 text-xs font-bold">Remove</button>
            </td>
        </tr>
    `).join('') : `<tr><td colspan="5" class="p-4 text-center text-gray-500 text-sm">No student records found matching filter.</td></tr>`;
}

function handleStudentEnrollment(e) {
    e.preventDefault();
    const nameEl = document.getElementById('enroll-name');
    const classEl = document.getElementById('enroll-class');
    const streamEl = document.getElementById('enroll-stream');
    const linEl = document.getElementById('enroll-lin');
    const contactEl = document.getElementById('enroll-contact');

    if (!nameEl || !classEl || !streamEl || !linEl || !contactEl) return;

    const newStudent = { 
        id: Date.now(), 
        name: nameEl.value, 
        class: classEl.value, 
        stream: streamEl.value, 
        lin: linEl.value, 
        contact: contactEl.value 
    };
    
    state.students.unshift(newStudent);
    saveState();
    e.target.reset();
    renderStudentDirectory();
    alert(`Student ${newStudent.name} successfully enrolled in ${newStudent.class} Stream ${newStudent.stream}!`);
}

function deleteStudent(id) {
    if (confirm("Are you sure you want to remove this student record?")) {
        state.students = state.students.filter(s => s.id !== id);
        saveState();
        renderStudentDirectory();
    }
}

// 3. Digital Library
function renderLibraryResources() {
    const grid = document.getElementById('library-resources-grid');
    if (!grid) return;

    const resources = [
        { title: "UNEB UCE Physics Past Papers (2018 - 2025)", category: "Past Papers", size: "4.2 MB", format: "pdf" },
        { title: "UNEB UACE Pure Mathematics Sample Solutions", category: "Marking Guides", size: "2.8 MB", format: "pdf" },
        { title: "S1 - S4 Chemistry Practical Manual & Safety", category: "Textbooks", size: "6.1 MB", format: "pdf" },
        { title: "New Lower Secondary Biology Curriculum Guide", category: "Syllabus", size: "1.9 MB", format: "docx" },
        { title: "English Language Essay & Summary Writing Guide", category: "Revision Notes", size: "3.4 MB", format: "pdf" },
        { title: "A-Level Economics Formulas & Micro/Macro Sheets", category: "Formula Sheets", size: "1.2 MB", format: "xlsx" }
    ];

    grid.innerHTML = resources.map(r => `
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between hover:shadow-md transition-shadow">
            <div>
                <div class="flex justify-between items-center mb-3">
                    <span class="px-2.5 py-0.5 rounded bg-brand-lightGreen text-brand-green text-xs font-bold uppercase">${r.category}</span>
                    <span class="text-xs font-mono text-gray-400">${r.size}</span>
                </div>
                <h3 class="text-lg font-bold text-brand-green mb-2">${r.title}</h3>
            </div>
            <button onclick="downloadDocument('${r.title}.${r.format}', '${r.format}');" class="w-full mt-4 py-2 bg-brand-green text-white font-bold rounded-lg text-xs hover:bg-brand-darkGreen transition-colors flex items-center justify-center gap-1.5 shadow">
                <i data-lucide="download" class="w-4 h-4"></i> Download File (${r.format.toUpperCase()})
            </button>
        </div>
    `).join('');

    if (window.lucide) lucide.createIcons();
}

// 4. Teacher Workstation Router & Syllabus Tracker
function switchTeacherTab(tabId) {
    document.querySelectorAll('.teacher-tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active', 'bg-brand-green', 'text-white'));

    const activeTab = document.getElementById(tabId);
    if (activeTab) activeTab.classList.remove('hidden');

    const activeBtn = document.getElementById(`btn-${tabId}`);
    if (activeBtn) activeBtn.classList.add('active', 'bg-brand-green', 'text-white');

    if (tabId === 'tab-teacher-syllabus') renderSyllabusTracker();
    if (tabId === 'tab-teacher-schedule') renderTeacherSchedule();
    if (tabId === 'tab-teacher-discipline') renderDisciplineLogs();
}

function renderSyllabusTracker() {
    const sClass = document.getElementById('syllabus-filter-class')?.value || 'Senior 4';
    const stream = document.getElementById('syllabus-filter-stream')?.value || 'North';
    const subject = document.getElementById('syllabus-filter-subject')?.value || 'Physics';

    const key = `${sClass}-${stream}-${subject}`;
    let topics = state.syllabi[key];

    if (!topics) {
        topics = [
            { id: "t1", topic: "Introductory Principles & Core Concepts", completed: true },
            { id: "t2", topic: "Intermediate Problem Solving & Practical Work", completed: false },
            { id: "t3", topic: "UNEB Past Exam Revision & Mock Assessment", completed: false }
        ];
        state.syllabi[key] = topics;
        saveState();
    }

    const completedCount = topics.filter(t => t.completed).length;
    const percentage = Math.round((completedCount / topics.length) * 100);

    const summaryBox = document.getElementById('syllabus-summary-box');
    if (summaryBox) {
        const attachedDoc = state.attachedSyllabusDocs.find(d => d.subject === subject && d.class === sClass && d.stream === stream);
        summaryBox.innerHTML = `
            <div class="w-full">
                <div class="flex flex-wrap justify-between items-center mb-2">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-brand-green">${sClass} • Stream ${stream} • ${subject}</span>
                        <div class="text-lg font-bold text-brand-green">UNEB Syllabus Completion Progress: ${percentage}%</div>
                    </div>
                    ${attachedDoc ? `
                        <button onclick="downloadDocument('${attachedDoc.fileName}', 'pdf');" class="px-3 py-1.5 bg-brand-green text-white font-bold rounded-lg text-xs flex items-center gap-1">
                            <i data-lucide="file-text" class="w-3.5 h-3.5"></i> Download Official Syllabus (${attachedDoc.uploadedBy})
                        </button>
                    ` : '<span class="text-xs text-gray-500 italic">No syllabus document attached yet</span>'}
                </div>
                <div class="w-full bg-gray-200 h-3 rounded-full overflow-hidden">
                    <div class="bg-brand-gold h-full transition-all duration-500" style="width: ${percentage}%"></div>
                </div>
            </div>
        `;
    }

    const checklist = document.getElementById('syllabus-topics-checklist');
    if (checklist) {
        checklist.innerHTML = topics.map((t, idx) => `
            <div class="p-4 rounded-xl border border-gray-200 flex justify-between items-center hover:bg-gray-50">
                <div class="flex items-center gap-3">
                    <input type="checkbox" ${t.completed ? 'checked' : ''} onchange="toggleSyllabusTopic('${key}', '${t.id}');" class="w-5 h-5 text-brand-green rounded focus:ring-brand-green cursor-pointer">
                    <span class="text-sm font-semibold ${t.completed ? 'line-through text-gray-400' : 'text-gray-800'}">${idx + 1}. ${t.topic}</span>
                </div>
                <span class="text-xs font-bold px-2.5 py-1 rounded ${t.completed ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                    ${t.completed ? 'Completed' : 'In Progress'}
                </span>
            </div>
        `).join('');
    }

    if (window.lucide) lucide.createIcons();
}

function toggleSyllabusTopic(key, topicId) {
    const topics = state.syllabi[key];
    if (topics) {
        const topic = topics.find(t => t.id === topicId);
        if (topic) {
            topic.completed = !topic.completed;
            saveState();
            renderSyllabusTracker();
        }
    }
}

function handleUploadSyllabus(e) {
    e.preventDefault();
    const subjectEl = document.getElementById('syl-subject');
    const classEl = document.getElementById('syl-class');
    const streamEl = document.getElementById('syl-stream');
    const fileInput = document.getElementById('syl-file');

    if (!subjectEl || !classEl || !streamEl) return;

    const subject = subjectEl.value;
    const sClass = classEl.value;
    const stream = streamEl.value;
    const fileName = fileInput && fileInput.files[0] ? fileInput.files[0].name : `${subject}_Syllabus_${sClass}.pdf`;

    state.attachedSyllabusDocs.push({
        subject, class: sClass, stream, fileName, uploadedBy: "Department Head"
    });

    saveState();
    closeModal('modal-upload-syllabus');
    e.target.reset();
    renderSyllabusTracker();
    alert(`Syllabus document for ${subject} (${sClass} ${stream}) uploaded successfully!`);
}

// 5. Personal Teaching Schedule
function renderTeacherSchedule() {
    const container = document.getElementById('teacher-schedule-container');
    if (!container) return;

    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    const periods = [
        { time: "08:00 AM - 09:20 AM", mon: "S4 Physics (North)", tue: "S6 Physics (Lab)", wed: "S4 Physics (North)", thu: "Free Period", fri: "S2 General Science" },
        { time: "09:20 AM - 10:40 AM", mon: "Free Period", tue: "S4 Physics (South)", wed: "Staff Meeting", thu: "S6 Physics Practical", fri: "S4 Physics (North)" },
        { time: "11:00 AM - 12:20 PM", mon: "S6 Physics Theory", tue: "Free Period", wed: "S3 Physics (East)", thu: "S4 Physics (South)", fri: "Department Planning" },
        { time: "02:00 PM - 03:30 PM", mon: "S1 Science Intro", tue: "S4 Lab Practical", wed: "Free Period", thu: "S6 Revision Class", fri: "Sports Supervision" }
    ];

    container.innerHTML = `
        <table class="w-full text-left text-sm border-collapse">
            <thead>
                <tr class="bg-brand-green text-white font-bold">
                    <th class="p-3">Time Slot</th>
                    ${days.map(d => `<th class="p-3">${d}</th>`).join('')}
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                ${periods.map(p => `
                    <tr class="hover:bg-gray-50">
                        <td class="p-3 font-bold text-gray-700 bg-gray-50 text-xs">${p.time}</td>
                        <td class="p-3 ${p.mon.includes('Physics') ? 'font-bold text-brand-green bg-green-50' : 'text-gray-600'}">${p.mon}</td>
                        <td class="p-3 ${p.tue.includes('Physics') ? 'font-bold text-brand-green bg-green-50' : 'text-gray-600'}">${p.tue}</td>
                        <td class="p-3 ${p.wed.includes('Physics') ? 'font-bold text-brand-green bg-green-50' : 'text-gray-600'}">${p.wed}</td>
                        <td class="p-3 ${p.thu.includes('Physics') ? 'font-bold text-brand-green bg-green-50' : 'text-gray-600'}">${p.thu}</td>
                        <td class="p-3 ${p.fri.includes('Physics') ? 'font-bold text-brand-green bg-green-50' : 'text-gray-600'}">${p.fri}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

// 6. Discipline Logs
function renderDisciplineLogs() {
    const tbody = document.getElementById('discipline-table-body');
    if (!tbody) return;

    tbody.innerHTML = state.disciplineLogs.map(d => `
        <tr class="hover:bg-gray-50">
            <td class="p-3 font-mono text-xs text-gray-600">${d.date}</td>
            <td class="p-3 font-bold text-brand-green">${d.name}</td>
            <td class="p-3"><span class="px-2 py-0.5 rounded bg-gray-100 text-gray-800 text-xs font-semibold">${d.class} (${d.stream})</span></td>
            <td class="p-3"><span class="px-2.5 py-0.5 rounded text-xs font-bold ${d.category === 'Commendation' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${d.category}</span></td>
            <td class="p-3 text-xs text-gray-700 max-w-xs">${d.note}</td>
            <td class="p-3 text-xs font-medium text-brand-maroon">${d.action}</td>
        </tr>
    `).join('');
}

function handleAddDiscipline(e) {
    e.preventDefault();
    const nameEl = document.getElementById('disc-name');
    const classEl = document.getElementById('disc-class');
    const streamEl = document.getElementById('disc-stream');
    const catEl = document.getElementById('disc-category');
    const noteEl = document.getElementById('disc-note');
    const actionEl = document.getElementById('disc-action');

    if (!nameEl || !classEl || !streamEl || !catEl || !noteEl || !actionEl) return;

    const newLog = {
        id: Date.now(),
        date: new Date().toISOString().split('T')[0],
        name: nameEl.value,
        class: classEl.value,
        stream: streamEl.value,
        category: catEl.value,
        note: noteEl.value,
        action: actionEl.value
    };

    state.disciplineLogs.unshift(newLog);
    saveState();
    closeModal('modal-add-discipline');
    e.target.reset();
    renderDisciplineLogs();
    alert(`Behavioral record for ${newLog.name} logged successfully!`);
}

// 7. Admin Control Panel
function renderAdminTimetables() {
    const tbody = document.getElementById('admin-timetables-body');
    if (!tbody) return;

    tbody.innerHTML = state.timetables.map(t => `
        <tr class="hover:bg-gray-50">
            <td class="p-3 font-bold text-brand-green flex items-center gap-2">
                <i data-lucide="file-text" class="w-4 h-4 text-brand-maroon"></i> ${t.title}
            </td>
            <td class="p-3 text-xs font-semibold text-gray-700">${t.class} (${t.stream})</td>
            <td class="p-3"><span class="uppercase text-[10px] font-bold px-2 py-0.5 rounded bg-gray-200 text-gray-800">${t.format}</span></td>
            <td class="p-3 text-xs font-mono text-gray-500">${t.date}</td>
            <td class="p-3 text-right">
                <button onclick="downloadDocument('${t.fileName}', '${t.format}');" class="px-3 py-1 bg-brand-green text-white font-bold rounded text-xs hover:bg-brand-darkGreen inline-flex items-center gap-1">
                    <i data-lucide="download" class="w-3 h-3"></i> Download
                </button>
            </td>
        </tr>
    `).join('');

    if (window.lucide) lucide.createIcons();
}

function handleUploadTimetable(e) {
    e.preventDefault();
    const titleEl = document.getElementById('tt-title');
    const classEl = document.getElementById('tt-class');
    const streamEl = document.getElementById('tt-stream');
    const fileInput = document.getElementById('tt-file');

    if (!titleEl || !classEl || !streamEl) return;

    const title = titleEl.value;
    const sClass = classEl.value;
    const stream = streamEl.value;

    let format = 'pdf';
    let fileName = `${title.replace(/\s+/g, '_')}.pdf`;
    if (fileInput && fileInput.files[0]) {
        fileName = fileInput.files[0].name;
        if (fileName.endsWith('.xlsx') || fileName.endsWith('.xls')) format = 'xlsx';
        if (fileName.endsWith('.docx') || fileName.endsWith('.doc')) format = 'docx';
    }

    const newTT = {
        id: Date.now(),
        title, class: sClass, stream, format, fileName,
        date: new Date().toISOString().split('T')[0]
    };

    state.timetables.unshift(newTT);
    saveState();
    closeModal('modal-upload-timetable');
    e.target.reset();
    renderAdminTimetables();
    alert(`Timetable document "${title}" uploaded successfully!`);
}

function renderAdminCirculars() {
    const list = document.getElementById('admin-circulars-list');
    if (!list) return;

    list.innerHTML = state.circulars.map(c => `
        <div class="p-4 rounded-xl border border-gray-200 bg-gray-50 flex justify-between items-start">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="font-mono text-xs text-gray-500">${c.date}</span>
                    <span class="font-bold text-sm text-brand-maroon">${c.title}</span>
                </div>
                <p class="text-xs text-gray-600 mb-2">${c.body}</p>
                <span class="text-xs text-brand-green font-semibold flex items-center gap-1"><i data-lucide="paperclip" class="w-3.5 h-3.5"></i> ${c.attachment}</span>
            </div>
            <button onclick="downloadDocument('${c.attachment}', 'pdf');" class="px-3 py-1.5 bg-brand-maroon text-white font-bold rounded text-xs hover:bg-red-900 flex items-center gap-1">
                <i data-lucide="download" class="w-3 h-3"></i> Download File
            </button>
        </div>
    `).join('');

    if (window.lucide) lucide.createIcons();
}

function handlePublishCircular(e) {
    e.preventDefault();
    const titleEl = document.getElementById('circ-title');
    const bodyEl = document.getElementById('circ-body');
    const fileInput = document.getElementById('circ-file');

    if (!titleEl || !bodyEl) return;

    const title = titleEl.value;
    const body = bodyEl.value;
    const attachment = fileInput && fileInput.files[0] ? fileInput.files[0].name : `Circular_${Date.now()}.pdf`;

    const newCirc = {
        id: Date.now(),
        title, body,
        date: new Date().toISOString().split('T')[0],
        attachment
    };

    state.circulars.unshift(newCirc);
    saveState();
    closeModal('modal-publish-circular');
    e.target.reset();
    renderAdminCirculars();
    renderHomeCirculars();
    alert(`Circular "${title}" published successfully!`);
}

// Initial App Startup
document.addEventListener('DOMContentLoaded', () => {
    switchPage('home');
});