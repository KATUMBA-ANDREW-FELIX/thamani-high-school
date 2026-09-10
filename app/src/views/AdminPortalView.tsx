import React, { useState } from 'react';
import type { 
  Applicant, 
  Student, 
  Teacher, 
  NewsArticle, 
  ApplicantStatus,
  TimetableSlot, 
  DutyRosterItem, 
  NoticeCircular, 
  DayOfWeek 
} from '../types';
import { 
  Shield, 
  Users, 
  GraduationCap, 
  CheckCircle2, 
  XCircle, 
  Clock, 
  UserCheck, 
  FileText, 
  RefreshCw, 
  Eye, 
  Check, 
  Plus, 
  Calendar, 
  Pin, 
  Trash2, 
  Bell 
} from 'lucide-react';

interface AdminPortalViewProps {
  applicants: Applicant[];
  students: Student[];
  teachers: Teacher[];
  news: NewsArticle[];
  timetableSlots: TimetableSlot[];
  dutyRosters: DutyRosterItem[];
  notices: NoticeCircular[];
  onUpdateApplicantStatus: (id: string, status: ApplicantStatus, notes?: string) => void;
  onAddNewsArticle: (article: Omit<NewsArticle, 'id'>) => void;
  onAddTimetableSlot: (slot: Omit<TimetableSlot, 'id'>) => void;
  onDeleteTimetableSlot: (id: string) => void;
  onAddDutyRoster: (item: Omit<DutyRosterItem, 'id'>) => void;
  onDeleteDutyRoster: (id: string) => void;
  onAddNotice: (notice: Omit<NoticeCircular, 'id'>) => void;
  onTogglePinNotice: (id: string) => void;
  onDeleteNotice: (id: string) => void;
  onResetDemoData: () => void;
}

export const AdminPortalView: React.FC<AdminPortalViewProps> = ({
  applicants,
  students,
  teachers,
  news,
  timetableSlots,
  dutyRosters,
  notices,
  onUpdateApplicantStatus,
  onAddNewsArticle,
  onAddTimetableSlot,
  onDeleteTimetableSlot,
  onAddDutyRoster,
  onDeleteDutyRoster,
  onAddNotice,
  onTogglePinNotice,
  onDeleteNotice,
  onResetDemoData
}) => {
  const [adminTab, setAdminTab] = useState<'overview' | 'timetable' | 'tod' | 'notices' | 'applicants' | 'students' | 'teachers' | 'news'>('overview');

  // Applicant Filter & Modal State
  const [applicantFilterStatus, setApplicantFilterStatus] = useState<string>('All');
  const [selectedApplicantModal, setSelectedApplicantModal] = useState<Applicant | null>(null);
  const [adminNoteInput, setAdminNoteInput] = useState('');

  // Student Roster Filter State
  const [studentSearch, setStudentSearch] = useState('');
  const [studentClassFilter, setStudentClassFilter] = useState('All');

  // News Publisher State
  const [newsTitle, setNewsTitle] = useState('');
  const [newsCategory, setNewsCategory] = useState('Academics');
  const [newsSummary, setNewsSummary] = useState('');
  const [newsContent, setNewsContent] = useState('');
  const [newsSuccess, setNewsSuccess] = useState(false);

  // Timetable Form State
  const [ttDay, setTtDay] = useState<DayOfWeek>('Monday');
  const [ttPeriodIndex, setTtPeriodIndex] = useState<number>(1);
  const [ttPeriodText, setTtPeriodText] = useState('P1 (8:00 - 8:40 AM)');
  const [ttClassStream, setTtClassStream] = useState('Senior 4 West');
  const [ttSubject, setTtSubject] = useState('Physics');
  const [ttTeacherId, setTtTeacherId] = useState(teachers[0]?.id || 'TCH-001');
  const [ttRoom, setTtRoom] = useState('Physics Lab 1');
  const [ttFilterClass, setTtFilterClass] = useState('All');

  // Duty Roster Form State
  const [dutyWeekNum, setDutyWeekNum] = useState<number>(5);
  const [dutyStartDate, setDutyStartDate] = useState('2026-09-28');
  const [dutyEndDate, setDutyEndDate] = useState('2026-10-04');
  const [dutyTeacher1, setDutyTeacher1] = useState(teachers[0]?.id || '');
  const [dutyRole1, setDutyRole1] = useState('Assembly & Morning Prep Warden');
  const [dutyTeacher2, setDutyTeacher2] = useState(teachers[1]?.id || '');
  const [dutyRole2, setDutyRole2] = useState('Dining Hall & Meal Inspector');
  const [dutyNotes, setDutyNotes] = useState('Ensure full attendance during evening preps.');

  // Notice Board Form State
  const [notTitle, setNotTitle] = useState('');
  const [notCategory, setNotCategory] = useState<'general' | 'fees' | 'academic' | 'urgent'>('general');
  const [notTarget, setNotTarget] = useState<'all' | 'parents' | 'teachers' | 'students'>('all');
  const [notContent, setNotContent] = useState('');
  const [notIsPinned, setNotIsPinned] = useState(false);
  const [notPdfName, setNotPdfName] = useState('');
  const [notSuccess, setNotSuccess] = useState(false);

  // Calculations
  const pendingApplicantsCount = applicants.filter(a => a.status === 'pending').length;
  const approvedApplicantsCount = applicants.filter(a => a.status === 'approved').length;

  const filteredApplicants = applicantFilterStatus === 'All'
    ? applicants
    : applicants.filter(a => a.status === applicantFilterStatus);

  const filteredStudents = students.filter(s => {
    const matchesSearch = s.fullName.toLowerCase().includes(studentSearch.toLowerCase()) ||
                          s.indexNo.toLowerCase().includes(studentSearch.toLowerCase());
    const matchesClass = studentClassFilter === 'All' || s.classStream === studentClassFilter;
    return matchesSearch && matchesClass;
  });

  const filteredTimetable = ttFilterClass === 'All'
    ? timetableSlots
    : timetableSlots.filter(s => s.classStream === ttFilterClass);

  const handleApproveApplicant = (id: string) => {
    onUpdateApplicantStatus(id, 'approved', adminNoteInput || 'Approved by Admissions Board.');
    setSelectedApplicantModal(null);
    setAdminNoteInput('');
  };

  const handleRejectApplicant = (id: string) => {
    onUpdateApplicantStatus(id, 'rejected', adminNoteInput || 'Application not successful for this cohort.');
    setSelectedApplicantModal(null);
    setAdminNoteInput('');
  };

  const handleInterviewApplicant = (id: string) => {
    onUpdateApplicantStatus(id, 'interview', adminNoteInput || 'Scheduled for academic interview at Kakiri.');
    setSelectedApplicantModal(null);
    setAdminNoteInput('');
  };

  const handlePublishNews = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newsTitle || !newsSummary) return;

    const svgBg = newsCategory === 'Academics' ? '#1A472A' : newsCategory === 'Infrastructure' ? '#800000' : '#1E293B';
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="800" height="500" viewBox="0 0 800 500">
      <rect width="800" height="500" fill="${svgBg}"/>
      <text x="400" y="240" font-family="sans-serif" font-size="32" font-weight="bold" fill="#ffffff" text-anchor="middle">${newsTitle.slice(0, 35)}</text>
      <text x="400" y="290" font-family="sans-serif" font-size="20" fill="#D4AF37" text-anchor="middle">${newsCategory} • THAMANI ACADEMY</text>
    </svg>`;

    onAddNewsArticle({
      title: newsTitle,
      date: new Date().toISOString().split('T')[0],
      category: newsCategory,
      summary: newsSummary,
      content: newsContent || newsSummary,
      imageUrl: `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`,
      author: 'Administration Board'
    });

    setNewsSuccess(true);
    setNewsTitle('');
    setNewsSummary('');
    setNewsContent('');
    setTimeout(() => setNewsSuccess(false), 3000);
  };

  const handleAddTimetableSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const t = teachers.find(tch => tch.id === ttTeacherId);
    if (!t) return;

    onAddTimetableSlot({
      day: ttDay,
      periodIndex: Number(ttPeriodIndex),
      period: ttPeriodText,
      classStream: ttClassStream,
      subject: ttSubject,
      teacherId: t.id,
      teacherName: t.name,
      room: ttRoom
    });

    alert(`Timetable slot added for ${ttSubject} (${ttClassStream})!`);
  };

  const handleAddDutyRosterSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const t1 = teachers.find(t => t.id === dutyTeacher1);
    const t2 = teachers.find(t => t.id === dutyTeacher2);
    if (!t1 || !t2) return;

    onAddDutyRoster({
      weekNumber: Number(dutyWeekNum),
      startDate: dutyStartDate,
      endDate: dutyEndDate,
      assignedTeachers: [
        { teacherId: t1.id, teacherName: t1.name, dutyRole: dutyRole1 },
        { teacherId: t2.id, teacherName: t2.name, dutyRole: dutyRole2 }
      ],
      notes: dutyNotes
    });

    alert(`Duty roster for Week #${dutyWeekNum} published successfully!`);
  };

  const handleAddNoticeSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!notTitle || !notContent) return;

    onAddNotice({
      title: notTitle,
      publishDate: new Date().toISOString().split('T')[0],
      targetAudience: notTarget,
      category: notCategory,
      content: notContent,
      isPinned: notIsPinned,
      pdfAttachmentName: notPdfName || undefined
    });

    setNotSuccess(true);
    setNotTitle('');
    setNotContent('');
    setNotPdfName('');
    setTimeout(() => setNotSuccess(false), 3000);
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
      
      {/* Executive Admin Header */}
      <div className="bg-gradient-to-r from-emerald-950 via-slate-900 to-red-950 text-white rounded-3xl p-8 shadow-xl flex flex-wrap justify-between items-center gap-6">
        <div className="space-y-2">
          <div className="inline-flex items-center gap-2 bg-brand-gold text-slate-950 px-3.5 py-1 rounded-full text-xs font-black uppercase tracking-wider">
            <Shield className="w-4 h-4" /> Chief Executive Administration Panel
          </div>
          <h1 className="text-3xl font-black font-serif">Thamani High Control Center</h1>
          <p className="text-emerald-100 text-xs sm:text-sm">
            Managing <strong className="text-brand-gold font-semibold">{students.length} Enrolled Students</strong>, <strong className="text-brand-gold font-semibold">{applicants.length} Cohort Applicants</strong>, and <strong className="text-brand-gold font-semibold">{teachers.length} Staff Members</strong>.
          </p>
        </div>

        <div className="flex gap-3">
          <button
            onClick={onResetDemoData}
            className="px-4 py-2 bg-white/10 hover:bg-white/20 text-white font-bold rounded-xl text-xs border border-white/20 backdrop-blur transition-all flex items-center gap-1.5"
            title="Reset system to default demo state"
          >
            <RefreshCw className="w-3.5 h-3.5 text-brand-gold" /> Reset Demo State
          </button>
        </div>
      </div>

      {/* Admin Sub-Navigation Tabs */}
      <div className="flex overflow-x-auto gap-2 border-b border-slate-200 pb-2 max-w-full">
        <button
          onClick={() => setAdminTab('overview')}
          className={`px-4 py-3 rounded-2xl text-xs font-bold transition-all flex items-center gap-1.5 ${
            adminTab === 'overview' ? 'bg-brand-green text-white shadow-md' : 'bg-white text-slate-700 hover:bg-slate-100'
          }`}
        >
          <Shield className="w-4 h-4 text-brand-gold" /> Executive Metrics
        </button>

        <button
          onClick={() => setAdminTab('timetable')}
          className={`px-4 py-3 rounded-2xl text-xs font-bold transition-all flex items-center gap-1.5 ${
            adminTab === 'timetable' ? 'bg-brand-green text-white shadow-md' : 'bg-white text-slate-700 hover:bg-slate-100'
          }`}
        >
          <Calendar className="w-4 h-4 text-brand-gold" /> Master Timetable Manager
        </button>

        <button
          onClick={() => setAdminTab('tod')}
          className={`px-4 py-3 rounded-2xl text-xs font-bold transition-all flex items-center gap-1.5 ${
            adminTab === 'tod' ? 'bg-brand-green text-white shadow-md' : 'bg-white text-slate-700 hover:bg-slate-100'
          }`}
        >
          <Clock className="w-4 h-4 text-brand-gold" /> TOD Roster Manager
        </button>

        <button
          onClick={() => setAdminTab('notices')}
          className={`px-4 py-3 rounded-2xl text-xs font-bold transition-all flex items-center gap-1.5 ${
            adminTab === 'notices' ? 'bg-brand-green text-white shadow-md' : 'bg-white text-slate-700 hover:bg-slate-100'
          }`}
        >
          <Bell className="w-4 h-4 text-brand-gold" /> Notice Board & Circulars
        </button>

        <button
          onClick={() => setAdminTab('applicants')}
          className={`px-4 py-3 rounded-2xl text-xs font-bold transition-all flex items-center gap-1.5 relative ${
            adminTab === 'applicants' ? 'bg-brand-green text-white shadow-md' : 'bg-white text-slate-700 hover:bg-slate-100'
          }`}
        >
          <GraduationCap className="w-4 h-4 text-brand-gold" /> Applicants Board
          {pendingApplicantsCount > 0 && (
            <span className="bg-amber-500 text-slate-950 text-[10px] font-extrabold px-1.5 py-0.2 rounded-full">
              {pendingApplicantsCount}
            </span>
          )}
        </button>

        <button
          onClick={() => setAdminTab('students')}
          className={`px-4 py-3 rounded-2xl text-xs font-bold transition-all flex items-center gap-1.5 ${
            adminTab === 'students' ? 'bg-brand-green text-white shadow-md' : 'bg-white text-slate-700 hover:bg-slate-100'
          }`}
        >
          <Users className="w-4 h-4 text-brand-gold" /> Student Directory
        </button>

        <button
          onClick={() => setAdminTab('teachers')}
          className={`px-4 py-3 rounded-2xl text-xs font-bold transition-all flex items-center gap-1.5 ${
            adminTab === 'teachers' ? 'bg-brand-green text-white shadow-md' : 'bg-white text-slate-700 hover:bg-slate-100'
          }`}
        >
          <UserCheck className="w-4 h-4 text-brand-gold" /> Staff Roster
        </button>

        <button
          onClick={() => setAdminTab('news')}
          className={`px-4 py-3 rounded-2xl text-xs font-bold transition-all flex items-center gap-1.5 ${
            adminTab === 'news' ? 'bg-brand-green text-white shadow-md' : 'bg-white text-slate-700 hover:bg-slate-100'
          }`}
        >
          <FileText className="w-4 h-4 text-brand-gold" /> News Publisher
        </button>
      </div>

      {/* TAB 1: EXECUTIVE METRICS OVERVIEW */}
      {adminTab === 'overview' && (
        <div className="space-y-8">
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div className="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-2">
              <div className="flex justify-between items-center text-slate-500">
                <span className="text-xs font-bold uppercase tracking-wider">Total Enrolled Scholars</span>
                <Users className="w-5 h-5 text-brand-green" />
              </div>
              <div className="text-3xl font-black text-slate-900 font-mono">{students.length}</div>
              <p className="text-[11px] text-emerald-600 font-semibold">• 100% active academic status</p>
            </div>

            <div className="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-2">
              <div className="flex justify-between items-center text-slate-500">
                <span className="text-xs font-bold uppercase tracking-wider">Cohort Registrations</span>
                <GraduationCap className="w-5 h-5 text-brand-maroon" />
              </div>
              <div className="text-3xl font-black text-slate-900 font-mono">{applicants.length}</div>
              <p className="text-[11px] text-amber-600 font-semibold">• {pendingApplicantsCount} pending, {approvedApplicantsCount} approved</p>
            </div>

            <div className="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-2">
              <div className="flex justify-between items-center text-slate-500">
                <span className="text-xs font-bold uppercase tracking-wider">Certified Teaching Staff</span>
                <UserCheck className="w-5 h-5 text-brand-green" />
              </div>
              <div className="text-3xl font-black text-slate-900 font-mono">{teachers.length}</div>
              <p className="text-[11px] text-emerald-600 font-semibold">• Active Department Heads</p>
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div className="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
              <div className="flex justify-between items-center border-b border-slate-100 pb-3">
                <h3 className="font-bold text-slate-900 text-base">Pending Applicant Dossiers</h3>
                <button
                  onClick={() => setAdminTab('applicants')}
                  className="text-xs font-bold text-brand-maroon hover:underline"
                >
                  View All ({applicants.length})
                </button>
              </div>

              <div className="space-y-3">
                {applicants.filter(a => a.status === 'pending').slice(0, 3).map((app) => (
                  <div key={app.id} className="p-4 bg-slate-50 rounded-2xl border border-slate-200 flex justify-between items-center text-xs">
                    <div>
                      <div className="font-bold text-slate-900 text-sm">{app.fullName}</div>
                      <div className="text-slate-500 font-medium">{app.academicLevel} • {app.selectedCombination}</div>
                      <div className="text-slate-400 font-mono text-[10px]">Ref: {app.refCode}</div>
                    </div>

                    <button
                      onClick={() => {
                        setSelectedApplicantModal(app);
                        setAdminTab('applicants');
                      }}
                      className="px-3 py-1.5 bg-brand-green text-white font-bold rounded-lg text-xs"
                    >
                      Review
                    </button>
                  </div>
                ))}
              </div>
            </div>

            <div className="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
              <h3 className="font-bold text-slate-900 text-base border-b border-slate-100 pb-3">Academic Stream Statistics</h3>
              <div className="space-y-3 text-xs font-medium text-slate-700">
                <div className="flex justify-between items-center p-3 bg-slate-50 rounded-xl">
                  <span>Senior 4 West (O-Level Candidates)</span>
                  <span className="font-bold text-brand-green">{students.filter(s => s.classStream === 'Senior 4 West').length} Students</span>
                </div>
                <div className="flex justify-between items-center p-3 bg-slate-50 rounded-xl">
                  <span>Senior 6 PCM/ICT (A-Level STEM)</span>
                  <span className="font-bold text-brand-green">{students.filter(s => s.classStream === 'Senior 6 PCM/ICT').length} Students</span>
                </div>
                <div className="flex justify-between items-center p-3 bg-slate-50 rounded-xl">
                  <span>Senior 6 HEG/Div (A-Level Humanities)</span>
                  <span className="font-bold text-brand-green">{students.filter(s => s.classStream === 'Senior 6 HEG/Div').length} Students</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB: MASTER TIMETABLE MANAGER */}
      {adminTab === 'timetable' && (
        <div className="bg-white rounded-3xl border border-slate-200 p-8 shadow-md space-y-6">
          <div className="flex flex-wrap justify-between items-center gap-4 border-b border-slate-200 pb-4">
            <div>
              <h2 className="text-xl font-black text-slate-900 font-serif">Master School Timetable Manager</h2>
              <p className="text-xs text-slate-500">Configure weekly class schedules, period allocations, and room assignments.</p>
            </div>

            <div>
              <label className="block text-[10px] font-bold text-slate-500 uppercase mb-0.5">Filter by Class Stream:</label>
              <select
                value={ttFilterClass}
                onChange={(e) => setTtFilterClass(e.target.value)}
                className="px-3 py-2 rounded-xl border border-slate-300 text-xs font-bold outline-none"
              >
                <option value="All">All Streams ({timetableSlots.length} slots)</option>
                <option value="Senior 4 West">Senior 4 West</option>
                <option value="Senior 6 PCM/ICT">Senior 6 PCM/ICT</option>
                <option value="Senior 6 HEG/Div">Senior 6 HEG/Div</option>
                <option value="Senior 2 East">Senior 2 East</option>
              </select>
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            {/* Form to add slot */}
            <div className="lg:col-span-4 bg-slate-50 p-6 rounded-2xl border border-slate-200 space-y-4 text-xs">
              <h3 className="font-bold text-slate-900 text-base border-b border-slate-200 pb-2">Add Timetable Slot</h3>
              
              <form onSubmit={handleAddTimetableSubmit} className="space-y-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Day of Week</label>
                  <select
                    value={ttDay}
                    onChange={(e) => setTtDay(e.target.value as DayOfWeek)}
                    className="w-full px-3 py-2 rounded-xl border border-slate-300 font-bold outline-none"
                  >
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                  </select>
                </div>

                <div className="grid grid-cols-2 gap-2">
                  <div>
                    <label className="block font-bold text-slate-700 mb-1">Period Index</label>
                    <input
                      type="number"
                      min="1"
                      max="8"
                      value={ttPeriodIndex}
                      onChange={(e) => setTtPeriodIndex(Number(e.target.value))}
                      className="w-full px-3 py-2 rounded-xl border border-slate-300 font-bold outline-none"
                    />
                  </div>

                  <div>
                    <label className="block font-bold text-slate-700 mb-1">Class Stream</label>
                    <select
                      value={ttClassStream}
                      onChange={(e) => setTtClassStream(e.target.value)}
                      className="w-full px-3 py-2 rounded-xl border border-slate-300 font-bold outline-none"
                    >
                      <option value="Senior 4 West">Senior 4 West</option>
                      <option value="Senior 6 PCM/ICT">Senior 6 PCM/ICT</option>
                      <option value="Senior 6 HEG/Div">Senior 6 HEG/Div</option>
                      <option value="Senior 2 East">Senior 2 East</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label className="block font-bold text-slate-700 mb-1">Period Time Text</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. P1 (8:00 - 8:40 AM)"
                    value={ttPeriodText}
                    onChange={(e) => setTtPeriodText(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl border border-slate-300 font-medium outline-none"
                  />
                </div>

                <div>
                  <label className="block font-bold text-slate-700 mb-1">Subject Title</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Advanced Physics P510/1"
                    value={ttSubject}
                    onChange={(e) => setTtSubject(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl border border-slate-300 font-medium outline-none"
                  />
                </div>

                <div>
                  <label className="block font-bold text-slate-700 mb-1">Assigned Educator</label>
                  <select
                    value={ttTeacherId}
                    onChange={(e) => setTtTeacherId(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl border border-slate-300 font-bold outline-none"
                  >
                    {teachers.map(t => (
                      <option key={t.id} value={t.id}>{t.name} ({t.subjects.join(', ')})</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block font-bold text-slate-700 mb-1">Room / Lab Location</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Physics Lab 1"
                    value={ttRoom}
                    onChange={(e) => setTtRoom(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl border border-slate-300 font-medium outline-none"
                  />
                </div>

                <button
                  type="submit"
                  className="w-full py-3 bg-brand-green hover:bg-emerald-800 text-white font-bold rounded-xl flex items-center justify-center gap-1.5 shadow"
                >
                  <Plus className="w-4 h-4 text-brand-gold" /> Assign Timetable Slot
                </button>
              </form>
            </div>

            {/* List of slots */}
            <div className="lg:col-span-8 space-y-3">
              <h3 className="font-bold text-slate-900 text-base border-b border-slate-200 pb-2">Active Timetable Matrix</h3>
              
              <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse text-xs">
                  <thead>
                    <tr className="bg-slate-100 text-slate-700 font-bold uppercase text-[10px]">
                      <th className="p-3 rounded-l-xl">Day & Period</th>
                      <th className="p-3">Class Stream</th>
                      <th className="p-3">Subject & Room</th>
                      <th className="p-3">Educator Name</th>
                      <th className="p-3 rounded-r-xl text-center">Action</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 font-medium">
                    {filteredTimetable.map((slot) => (
                      <tr key={slot.id} className="hover:bg-slate-50">
                        <td className="p-3">
                          <span className="font-bold text-brand-green block">{slot.day}</span>
                          <span className="text-[10px] text-slate-500 font-mono">{slot.period}</span>
                        </td>
                        <td className="p-3 font-bold text-slate-800">{slot.classStream}</td>
                        <td className="p-3">
                          <div className="font-bold text-slate-900">{slot.subject}</div>
                          <div className="text-[10px] text-slate-400 font-mono">{slot.room}</div>
                        </td>
                        <td className="p-3 text-slate-700 font-semibold">{slot.teacherName}</td>
                        <td className="p-3 text-center">
                          <button
                            onClick={() => onDeleteTimetableSlot(slot.id)}
                            className="p-1.5 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-lg text-xs font-bold"
                            title="Delete Slot"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </div>
      )}

      {/* TAB: TOD ROSTER MANAGER */}
      {adminTab === 'tod' && (
        <div className="bg-white rounded-3xl border border-slate-200 p-8 shadow-md space-y-6">
          <div className="border-b border-slate-200 pb-3">
            <h2 className="text-xl font-black text-slate-900 font-serif">Teacher on Duty (TOD) Roster Publisher</h2>
            <p className="text-xs text-slate-500">Assign weekly duty wardens for student assembly, dining hall, and dormitories.</p>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <div className="lg:col-span-5 bg-slate-50 p-6 rounded-2xl border border-slate-200 space-y-3 text-xs">
              <h3 className="font-bold text-slate-900 text-base border-b border-slate-200 pb-2">Publish New Weekly TOD Allocation</h3>

              <form onSubmit={handleAddDutyRosterSubmit} className="space-y-3">
                <div className="grid grid-cols-3 gap-2">
                  <div>
                    <label className="block font-bold text-slate-700 mb-1">Week #</label>
                    <input
                      type="number"
                      value={dutyWeekNum}
                      onChange={(e) => setDutyWeekNum(Number(e.target.value))}
                      className="w-full px-3 py-2 rounded-xl border border-slate-300 font-bold outline-none"
                    />
                  </div>
                  <div>
                    <label className="block font-bold text-slate-700 mb-1">Start Date</label>
                    <input
                      type="date"
                      value={dutyStartDate}
                      onChange={(e) => setDutyStartDate(e.target.value)}
                      className="w-full px-3 py-2 rounded-xl border border-slate-300 font-bold outline-none text-[11px]"
                    />
                  </div>
                  <div>
                    <label className="block font-bold text-slate-700 mb-1">End Date</label>
                    <input
                      type="date"
                      value={dutyEndDate}
                      onChange={(e) => setDutyEndDate(e.target.value)}
                      className="w-full px-3 py-2 rounded-xl border border-slate-300 font-bold outline-none text-[11px]"
                    />
                  </div>
                </div>

                <div className="space-y-2 border-t border-slate-200 pt-2">
                  <h4 className="font-bold text-slate-800">Primary Duty Educator 1:</h4>
                  <select
                    value={dutyTeacher1}
                    onChange={(e) => setDutyTeacher1(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl border border-slate-300 font-bold outline-none"
                  >
                    {teachers.map(t => (
                      <option key={t.id} value={t.id}>{t.name}</option>
                    ))}
                  </select>
                  <input
                    type="text"
                    placeholder="Duty Role / Designation"
                    value={dutyRole1}
                    onChange={(e) => setDutyRole1(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl border border-slate-300 font-medium outline-none"
                  />
                </div>

                <div className="space-y-2 border-t border-slate-200 pt-2">
                  <h4 className="font-bold text-slate-800">Assisting Duty Educator 2:</h4>
                  <select
                    value={dutyTeacher2}
                    onChange={(e) => setDutyTeacher2(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl border border-slate-300 font-bold outline-none"
                  >
                    {teachers.map(t => (
                      <option key={t.id} value={t.id}>{t.name}</option>
                    ))}
                  </select>
                  <input
                    type="text"
                    placeholder="Duty Role / Designation"
                    value={dutyRole2}
                    onChange={(e) => setDutyRole2(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl border border-slate-300 font-medium outline-none"
                  />
                </div>

                <div>
                  <label className="block font-bold text-slate-700 mb-1">Weekly Directives & Notes</label>
                  <textarea
                    rows={2}
                    value={dutyNotes}
                    onChange={(e) => setDutyNotes(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl border border-slate-300 font-medium outline-none"
                  />
                </div>

                <button
                  type="submit"
                  className="w-full py-3 bg-brand-green hover:bg-emerald-800 text-white font-bold rounded-xl flex items-center justify-center gap-1.5 shadow"
                >
                  <Plus className="w-4 h-4 text-brand-gold" /> Publish TOD Roster
                </button>
              </form>
            </div>

            <div className="lg:col-span-7 space-y-4">
              <h3 className="font-bold text-slate-900 text-base border-b border-slate-200 pb-2">Active Duty Roster Records</h3>
              <div className="space-y-4">
                {dutyRosters.map((roster) => (
                  <div key={roster.id} className="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2 text-xs">
                    <div className="flex justify-between items-center border-b border-slate-200 pb-2">
                      <span className="font-black text-brand-maroon">Week #{roster.weekNumber} ({roster.startDate} to {roster.endDate})</span>
                      <button
                        onClick={() => onDeleteDutyRoster(roster.id)}
                        className="text-rose-600 hover:text-rose-800 font-bold"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>

                    <div className="space-y-1">
                      {roster.assignedTeachers.map((t, idx) => (
                        <div key={idx} className="flex justify-between text-slate-700">
                          <span className="font-bold">{t.teacherName}</span>
                          <span className="text-brand-green italic">{t.dutyRole}</span>
                        </div>
                      ))}
                    </div>
                    <p className="text-slate-500 italic text-[11px]">"{roster.notes}"</p>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB: NOTICE BOARD & CIRCULAR MANAGER */}
      {adminTab === 'notices' && (
        <div className="bg-white rounded-3xl border border-slate-200 p-8 shadow-md space-y-6">
          <div className="border-b border-slate-200 pb-3 flex justify-between items-center">
            <div>
              <h2 className="text-xl font-black text-slate-900 font-serif">Notice Board & Official Circular Manager</h2>
              <p className="text-xs text-slate-500">Publish school circulars with PDF attachments visible on the public noticeboard.</p>
            </div>
            <span className="bg-brand-maroon text-white font-bold text-xs px-3 py-1 rounded-full">
              {notices.length} Published Notices
            </span>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <div className="lg:col-span-5 bg-slate-50 p-6 rounded-2xl border border-slate-200 space-y-3 text-xs">
              <h3 className="font-bold text-slate-900 text-base border-b border-slate-200 pb-2">Create & Pin Official Notice</h3>

              {notSuccess && (
                <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-xs font-bold flex items-center gap-2">
                  <Check className="w-4 h-4" /> Notice published to Home Page noticeboard!
                </div>
              )}

              <form onSubmit={handleAddNoticeSubmit} className="space-y-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Notice / Circular Title *</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Official Term III Re-opening & Fee Guidelines"
                    value={notTitle}
                    onChange={(e) => setNotTitle(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl border border-slate-300 font-bold outline-none"
                  />
                </div>

                <div className="grid grid-cols-2 gap-2">
                  <div>
                    <label className="block font-bold text-slate-700 mb-1">Category</label>
                    <select
                      value={notCategory}
                      onChange={(e) => setNotCategory(e.target.value as any)}
                      className="w-full px-3 py-2 rounded-xl border border-slate-300 font-bold outline-none"
                    >
                      <option value="general">General</option>
                      <option value="urgent">Urgent</option>
                      <option value="fees">Fees & Finance</option>
                      <option value="academic">Academic</option>
                    </select>
                  </div>

                  <div>
                    <label className="block font-bold text-slate-700 mb-1">Target Audience</label>
                    <select
                      value={notTarget}
                      onChange={(e) => setNotTarget(e.target.value as any)}
                      className="w-full px-3 py-2 rounded-xl border border-slate-300 font-bold outline-none"
                    >
                      <option value="all">All Stakeholders</option>
                      <option value="parents">Parents & Guardians</option>
                      <option value="teachers">Teaching Staff</option>
                      <option value="students">Students</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label className="block font-bold text-slate-700 mb-1">Content / Announcement Text *</label>
                  <textarea
                    required
                    rows={3}
                    placeholder="Detailed notice text..."
                    value={notContent}
                    onChange={(e) => setNotContent(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl border border-slate-300 font-medium outline-none"
                  />
                </div>

                <div>
                  <label className="block font-bold text-slate-700 mb-1">Mock Attachment Filename (Optional)</label>
                  <input
                    type="text"
                    placeholder="e.g. Term_III_2026_School_Circular.pdf"
                    value={notPdfName}
                    onChange={(e) => setNotPdfName(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl border border-slate-300 font-mono text-[11px] outline-none"
                  />
                </div>

                <div className="flex items-center gap-2 pt-1">
                  <input
                    type="checkbox"
                    id="pinCheck"
                    checked={notIsPinned}
                    onChange={(e) => setNotIsPinned(e.target.checked)}
                    className="w-4 h-4 rounded text-brand-green"
                  />
                  <label htmlFor="pinCheck" className="font-bold text-slate-800 cursor-pointer">Pin to top of Home Page noticeboard</label>
                </div>

                <button
                  type="submit"
                  className="w-full py-3 bg-brand-green hover:bg-emerald-800 text-white font-bold rounded-xl flex items-center justify-center gap-1.5 shadow"
                >
                  <Plus className="w-4 h-4 text-brand-gold" /> Publish Circular
                </button>
              </form>
            </div>

            <div className="lg:col-span-7 space-y-3">
              <h3 className="font-bold text-slate-900 text-base border-b border-slate-200 pb-2">Active School Notices</h3>

              <div className="space-y-3">
                {notices.map((n) => (
                  <div key={n.id} className="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2 text-xs">
                    <div className="flex justify-between items-start">
                      <div>
                        <div className="flex items-center gap-2">
                          <span className={`px-2 py-0.5 rounded-full font-black text-[10px] uppercase ${
                            n.category === 'urgent' ? 'bg-rose-500 text-white' : 'bg-emerald-600 text-white'
                          }`}>
                            {n.category}
                          </span>
                          <span className="font-bold text-slate-500">{n.publishDate}</span>
                          <span className="text-slate-400">• Audience: <strong className="capitalize text-slate-700">{n.targetAudience}</strong></span>
                        </div>
                        <h4 className="font-bold text-slate-900 text-sm mt-1">{n.title}</h4>
                      </div>

                      <div className="flex items-center gap-2">
                        <button
                          onClick={() => onTogglePinNotice(n.id)}
                          className={`p-1.5 rounded-lg text-xs font-bold flex items-center gap-1 ${
                            n.isPinned ? 'bg-brand-gold text-slate-950' : 'bg-slate-200 text-slate-600'
                          }`}
                          title="Toggle Pin"
                        >
                          <Pin className="w-3.5 h-3.5" /> {n.isPinned ? 'Pinned' : 'Pin'}
                        </button>
                        <button
                          onClick={() => onDeleteNotice(n.id)}
                          className="p-1.5 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-lg"
                          title="Delete Notice"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </div>

                    <p className="text-slate-700 font-medium">{n.content}</p>
                    {n.pdfAttachmentName && (
                      <div className="text-[11px] text-brand-green font-bold flex items-center gap-1">
                        <FileText className="w-3.5 h-3.5" /> Attachment: {n.pdfAttachmentName}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB 2: APPLICANTS REVIEW BOARD */}
      {adminTab === 'applicants' && (
        <div className="bg-white rounded-3xl border border-slate-200 p-8 shadow-md space-y-6">
          <div className="flex flex-wrap justify-between items-center gap-4 border-b border-slate-200 pb-4">
            <div>
              <h2 className="text-xl font-black text-slate-900 font-serif">Cohort Applications Review Board</h2>
              <p className="text-xs text-slate-500">Applicant records are strictly confidential and managed inside the Admin Portal.</p>
            </div>

            <div className="flex gap-2 bg-slate-100 p-1.5 rounded-2xl text-xs font-bold">
              {['All', 'pending', 'approved', 'interview', 'rejected'].map((st) => (
                <button
                  key={st}
                  onClick={() => setApplicantFilterStatus(st)}
                  className={`px-3 py-1.5 rounded-xl capitalize transition-all ${
                    applicantFilterStatus === st ? 'bg-brand-green text-white shadow' : 'text-slate-600 hover:text-slate-900'
                  }`}
                >
                  {st}
                </button>
              ))}
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-xs">
              <thead>
                <tr className="bg-slate-100 text-slate-700 font-bold uppercase text-[10px]">
                  <th className="p-3 rounded-l-xl">Ref Code & Date</th>
                  <th className="p-3">Applicant Name</th>
                  <th className="p-3">Level & Choice</th>
                  <th className="p-3">Prior Aggregates/Grades</th>
                  <th className="p-3">Guardian Contact</th>
                  <th className="p-3">Status</th>
                  <th className="p-3 rounded-r-xl text-center">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 font-medium">
                {filteredApplicants.map((app) => (
                  <tr key={app.id} className="hover:bg-slate-50">
                    <td className="p-3">
                      <div className="font-mono font-bold text-brand-green">{app.refCode}</div>
                      <div className="text-[10px] text-slate-400">{app.appliedDate}</div>
                    </td>

                    <td className="p-3">
                      <div className="font-bold text-slate-900 text-sm">{app.fullName}</div>
                      <div className="text-[10px] text-slate-400">{app.gender} • {app.prevSchool}</div>
                    </td>

                    <td className="p-3">
                      <span className="font-bold text-slate-800">{app.academicLevel}</span>
                      <div className="text-[11px] text-slate-500">{app.selectedCombination}</div>
                    </td>

                    <td className="p-3 text-slate-700">
                      {app.pleAggregates ? `PLE: ${app.pleAggregates} Agg.` : app.uceGrades || 'N/A'}
                    </td>

                    <td className="p-3">
                      <div className="font-semibold text-slate-800">{app.guardianName}</div>
                      <div className="text-[11px] text-slate-500">{app.guardianPhone}</div>
                    </td>

                    <td className="p-3">
                      <span className={`px-2.5 py-1 rounded-full font-black text-[10px] uppercase ${
                        app.status === 'approved' ? 'bg-emerald-100 text-emerald-800' :
                        app.status === 'rejected' ? 'bg-rose-100 text-rose-800' :
                        app.status === 'interview' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-800'
                      }`}>
                        {app.status}
                      </span>
                    </td>

                    <td className="p-3 text-center">
                      <button
                        onClick={() => setSelectedApplicantModal(app)}
                        className="px-3 py-1.5 bg-brand-green hover:bg-emerald-800 text-white font-bold rounded-lg text-xs flex items-center gap-1 mx-auto shadow-sm"
                      >
                        <Eye className="w-3.5 h-3.5" /> Full Dossier
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 3: STUDENT DIRECTORY */}
      {adminTab === 'students' && (
        <div className="bg-white rounded-3xl border border-slate-200 p-8 shadow-md space-y-6">
          <div className="flex flex-wrap justify-between items-center gap-4 border-b border-slate-200 pb-4">
            <div>
              <h2 className="text-xl font-black text-slate-900 font-serif">Enrolled Student Directory</h2>
              <p className="text-xs text-slate-500">Official student registry for Thamani Academy.</p>
            </div>

            <div className="flex gap-4">
              <select
                value={studentClassFilter}
                onChange={(e) => setStudentClassFilter(e.target.value)}
                className="px-3 py-2 rounded-xl border border-slate-300 text-xs font-bold outline-none"
              >
                <option value="All">All Streams</option>
                <option value="Senior 4 West">Senior 4 West</option>
                <option value="Senior 6 PCM/ICT">Senior 6 PCM/ICT</option>
                <option value="Senior 6 HEG/Div">Senior 6 HEG/Div</option>
                <option value="Senior 2 East">Senior 2 East</option>
              </select>

              <input
                type="text"
                placeholder="Search student name or index..."
                value={studentSearch}
                onChange={(e) => setStudentSearch(e.target.value)}
                className="px-3 py-2 rounded-xl border border-slate-300 text-xs font-bold outline-none"
              />
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-xs">
              <thead>
                <tr className="bg-slate-100 text-slate-700 font-bold uppercase text-[10px]">
                  <th className="p-3 rounded-l-xl">Index No & Name</th>
                  <th className="p-3">Class Stream</th>
                  <th className="p-3">Hostel Status</th>
                  <th className="p-3">Attendance</th>
                  <th className="p-3">Conduct</th>
                  <th className="p-3">Fee Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 font-medium">
                {filteredStudents.map((s) => {
                  const isCleared = s.feePaid >= s.feeTotal;
                  return (
                    <tr key={s.id} className="hover:bg-slate-50">
                      <td className="p-3">
                        <div className="font-bold text-slate-900 text-sm">{s.fullName}</div>
                        <div className="text-[10px] text-slate-400 font-mono">{s.indexNo}</div>
                      </td>

                      <td className="p-3 font-semibold text-slate-800">{s.classStream}</td>

                      <td className="p-3">
                        <span className={`px-2.5 py-0.5 rounded-full text-[10px] font-bold ${
                          s.hostelStatus === 'Boarding' ? 'bg-brand-green text-white' : 'bg-slate-200 text-slate-800'
                        }`}>
                          {s.hostelStatus}
                        </span>
                      </td>

                      <td className="p-3 font-bold text-emerald-700">{s.attendancePct}%</td>

                      <td className="p-3 font-semibold text-slate-700">{s.conductRating}</td>

                      <td className="p-3">
                        <span className={`px-2.5 py-1 rounded-full font-black text-[10px] uppercase ${
                          isCleared ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'
                        }`}>
                          {isCleared ? 'Cleared' : `Balance: UGX ${(s.feeTotal - s.feePaid).toLocaleString()}`}
                        </span>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 4: STAFF ROSTER */}
      {adminTab === 'teachers' && (
        <div className="bg-white rounded-3xl border border-slate-200 p-8 shadow-md space-y-6">
          <div className="border-b border-slate-200 pb-3">
            <h2 className="text-xl font-black text-slate-900 font-serif">Certified Teaching Faculty</h2>
            <p className="text-xs text-slate-500">Official registry of teachers and department heads.</p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {teachers.map((t) => (
              <div key={t.id} className="p-6 bg-slate-50 rounded-2xl border border-slate-200 flex gap-4 items-center">
                <img 
                  src={t.photo} 
                  alt={t.name} 
                  className="w-16 h-16 rounded-full object-cover border-2 border-brand-green"
                />
                <div className="space-y-1 text-xs">
                  <h3 className="font-bold text-slate-900 text-sm">{t.name}</h3>
                  <p className="text-brand-maroon font-bold">{t.title}</p>
                  <p className="text-slate-600"><strong>Subjects:</strong> {t.subjects.join(', ')}</p>
                  <p className="text-slate-500"><strong>Classes:</strong> {t.classes.join(', ')}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* TAB 6: NEWS PUBLISHER */}
      {adminTab === 'news' && (
        <div className="bg-white rounded-3xl border border-slate-200 p-8 shadow-md space-y-6 max-w-2xl">
          <div className="border-b border-slate-200 pb-3 flex justify-between items-center">
            <div>
              <h2 className="text-xl font-black text-slate-900 font-serif">Publish Official School Announcement</h2>
              <p className="text-xs text-slate-500">Post news directly to the public home page stream ({news.length} articles published).</p>
            </div>
            <span className="bg-emerald-100 text-brand-green text-xs font-bold px-3 py-1 rounded-full">
              {news.length} Live News Items
            </span>
          </div>

          {newsSuccess && (
            <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-xs font-bold flex items-center gap-2">
              <Check className="w-4 h-4" /> Announcement published to home page!
            </div>
          )}

          <form onSubmit={handlePublishNews} className="space-y-4">
            <div>
              <label className="block text-xs font-bold text-slate-700 mb-1">Article Title *</label>
              <input
                type="text"
                required
                placeholder="e.g. Kakiri Inter-House Sports Gala Results 2026"
                value={newsTitle}
                onChange={(e) => setNewsTitle(e.target.value)}
                className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 mb-1">Category</label>
              <select
                value={newsCategory}
                onChange={(e) => setNewsCategory(e.target.value)}
                className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
              >
                <option value="Academics">Academics</option>
                <option value="Infrastructure">Infrastructure</option>
                <option value="Admissions">Admissions</option>
                <option value="Sports">Sports & Athletics</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 mb-1">Short Excerpt / Summary *</label>
              <textarea
                required
                rows={2}
                placeholder="Brief sentence for news grid summary..."
                value={newsSummary}
                onChange={(e) => setNewsSummary(e.target.value)}
                className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 mb-1">Full Content</label>
              <textarea
                rows={4}
                placeholder="Detailed announcement content..."
                value={newsContent}
                onChange={(e) => setNewsContent(e.target.value)}
                className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
              />
            </div>

            <button
              type="submit"
              className="w-full py-3.5 bg-brand-green hover:bg-emerald-800 text-white font-bold rounded-xl text-xs transition-colors flex items-center justify-center gap-2"
            >
              <Plus className="w-4 h-4 text-brand-gold" /> Post Announcement to Public Website
            </button>
          </form>
        </div>
      )}

      {/* APPLICANT FULL DOSSIER MODAL */}
      {selectedApplicantModal && (
        <div className="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border border-slate-200 space-y-6 relative animate-in fade-in zoom-in-95 duration-200 max-h-[90vh] overflow-y-auto">
            
            <div className="flex justify-between items-start border-b border-slate-200 pb-4">
              <div>
                <span className="text-xs font-mono font-bold text-brand-green">{selectedApplicantModal.refCode}</span>
                <h3 className="text-2xl font-black text-slate-900 font-serif">{selectedApplicantModal.fullName}</h3>
                <p className="text-xs text-slate-500">Applied on {selectedApplicantModal.appliedDate}</p>
              </div>

              <span className={`px-3 py-1 rounded-full font-black text-xs uppercase ${
                selectedApplicantModal.status === 'approved' ? 'bg-emerald-100 text-emerald-800' :
                selectedApplicantModal.status === 'rejected' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800'
              }`}>
                {selectedApplicantModal.status}
              </span>
            </div>

            <div className="grid grid-cols-2 gap-4 text-xs font-medium bg-slate-50 p-4 rounded-2xl border border-slate-200">
              <div><strong>Academic Level:</strong> {selectedApplicantModal.academicLevel}</div>
              <div><strong>Gender:</strong> {selectedApplicantModal.gender}</div>
              <div><strong>Choice / Combination:</strong> {selectedApplicantModal.selectedCombination}</div>
              <div><strong>Hostel Option:</strong> {selectedApplicantModal.hostelOption}</div>
              <div><strong>Previous School:</strong> {selectedApplicantModal.prevSchool}</div>
              <div><strong>Prior Scores:</strong> {selectedApplicantModal.pleAggregates ? `PLE: ${selectedApplicantModal.pleAggregates} Agg.` : selectedApplicantModal.uceGrades || 'N/A'}</div>
              <div><strong>Guardian Name:</strong> {selectedApplicantModal.guardianName}</div>
              <div><strong>Guardian Contact:</strong> {selectedApplicantModal.guardianPhone}</div>
            </div>

            {/* Decision Notes Input */}
            <div className="space-y-2">
              <label className="block text-xs font-bold text-slate-700 uppercase">Board Action Notes</label>
              <input
                type="text"
                placeholder="e.g. Approved. Allocated Crane House Dormitory."
                value={adminNoteInput}
                onChange={(e) => setAdminNoteInput(e.target.value)}
                className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-brand-green outline-none"
              />
            </div>

            {/* Decision Action Buttons */}
            <div className="grid grid-cols-3 gap-3 pt-2">
              <button
                onClick={() => handleApproveApplicant(selectedApplicantModal.id)}
                className="py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-1"
              >
                <CheckCircle2 className="w-4 h-4" /> Approve Admission
              </button>

              <button
                onClick={() => handleInterviewApplicant(selectedApplicantModal.id)}
                className="py-3 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold rounded-xl text-xs flex items-center justify-center gap-1"
              >
                <Clock className="w-4 h-4" /> Call for Interview
              </button>

              <button
                onClick={() => handleRejectApplicant(selectedApplicantModal.id)}
                className="py-3 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-1"
              >
                <XCircle className="w-4 h-4" /> Reject Application
              </button>
            </div>

            <button
              onClick={() => setSelectedApplicantModal(null)}
              className="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs"
            >
              Close Dossier
            </button>
          </div>
        </div>
      )}

    </div>
  );
};
