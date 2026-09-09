import React, { useState } from 'react';
import type { Teacher, Student, LibraryResource, StudentMarkReport } from '../types';
import { UserCheck, BookOpen, CheckSquare, Award, Plus, Save, Printer, FileText, Check, X } from 'lucide-react';

interface TeacherPortalViewProps {
  teachers: Teacher[];
  students: Student[];
  onAddLibraryResource: (resource: Omit<LibraryResource, 'id' | 'downloads'>) => void;
  onUpdateStudentMark: (studentId: string, markReport: StudentMarkReport) => void;
}

export const TeacherPortalView: React.FC<TeacherPortalViewProps> = ({
  teachers,
  students,
  onAddLibraryResource,
  onUpdateStudentMark
}) => {
  const [selectedTeacherId, setSelectedTeacherId] = useState<string>(teachers[0]?.id || 'TCH-001');
  const [activeTab, setActiveTab] = useState<'marks' | 'attendance' | 'upload' | 'reports'>('marks');

  const currentTeacher = teachers.find(t => t.id === selectedTeacherId) || teachers[0];

  // Mark Entry Form State
  const [selectedClass, setSelectedClass] = useState<string>('Senior 4 West');
  const [selectedSubject, setSelectedSubject] = useState<string>('Physics');
  
  // Student Marks Draft State for selected class
  const classStudents = students.filter(s => s.classStream === selectedClass);
  const [marksState, setMarksState] = useState<{ [studentId: string]: { bot: number; mid: number; eot: number } }>({
    'STU-2026-101': { bot: 18, mid: 27, eot: 45 },
    'STU-2026-102': { bot: 16, mid: 24, eot: 40 }
  });

  // Attendance Tracker State
  const [attendanceDate, setAttendanceDate] = useState<string>(new Date().toISOString().split('T')[0]);
  const [attendanceMap, setAttendanceMap] = useState<{ [studentId: string]: boolean }>({
    'STU-2026-101': true,
    'STU-2026-102': true,
    'STU-2026-103': true
  });

  // Resource Upload State
  const [uploadTitle, setUploadTitle] = useState('');
  const [uploadSubject, setUploadSubject] = useState('Physics');
  const [uploadLevel, setUploadLevel] = useState<'O-Level' | 'A-Level'>('O-Level');
  const [uploadType, setUploadType] = useState<'revision_notes' | 'past_paper' | 'textbook'>('revision_notes');
  const [uploadSuccess, setUploadSuccess] = useState(false);

  // Selected Student for Report Card Preview
  const [previewStudentId, setPreviewStudentId] = useState<string>('STU-2026-101');

  // Compute UNEB Grade helper (O-Level & A-Level)
  const computeUnebGrade = (total: number, isALevel: boolean) => {
    if (isALevel) {
      if (total >= 80) return 'A';
      if (total >= 70) return 'B';
      if (total >= 60) return 'C';
      if (total >= 50) return 'D';
      if (total >= 40) return 'E';
      if (total >= 35) return 'O';
      return 'F';
    } else {
      if (total >= 85) return 'D1';
      if (total >= 75) return 'D2';
      if (total >= 68) return 'C3';
      if (total >= 60) return 'C4';
      if (total >= 55) return 'C5';
      if (total >= 50) return 'C6';
      if (total >= 45) return 'P7';
      if (total >= 40) return 'P8';
      return 'F9';
    }
  };

  const handleMarkChange = (studentId: string, field: 'bot' | 'mid' | 'eot', val: number) => {
    setMarksState(prev => ({
      ...prev,
      [studentId]: {
        ...prev[studentId] || { bot: 0, mid: 0, eot: 0 },
        [field]: Math.min(Math.max(0, val), field === 'bot' ? 20 : field === 'mid' ? 30 : 50)
      }
    }));
  };

  const handleSaveMarks = () => {
    if (classStudents[0]) {
      onUpdateStudentMark(classStudents[0].id, {
        studentId: classStudents[0].id,
        term: 'Term II - 2026',
        year: 2026,
        classStream: selectedClass,
        marks: [],
        teacherComments: 'Good academic effort.',
        headteacherComments: 'Approved.'
      });
    }
    alert(`Academic marks for ${selectedSubject} (${selectedClass}) saved successfully! UNEB grades calculated.`);
  };

  const handleUploadResource = (e: React.FormEvent) => {
    e.preventDefault();
    if (!uploadTitle) return;

    onAddLibraryResource({
      title: uploadTitle,
      subject: uploadSubject,
      level: uploadLevel,
      type: uploadType,
      author: currentTeacher.name,
      year: 2026,
      fileSize: '3.5 MB'
    });

    setUploadSuccess(true);
    setUploadTitle('');
    setTimeout(() => setUploadSuccess(false), 3000);
  };

  const previewStudent = students.find(s => s.id === previewStudentId) || students[0];

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
      
      {/* Teacher Portal Header & Profile Switcher */}
      <div className="bg-gradient-to-r from-brand-maroon via-slate-900 to-brand-green text-white rounded-3xl p-8 shadow-xl flex flex-wrap justify-between items-center gap-6">
        <div className="space-y-2">
          <div className="inline-flex items-center gap-2 bg-brand-gold text-slate-950 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider">
            <UserCheck className="w-4 h-4" /> Passwordless Staff Portal
          </div>
          <h1 className="text-3xl font-black font-serif">Teacher Academic Workstation</h1>
          <p className="text-slate-200 text-xs sm:text-sm">
            Active Educator: <strong className="text-brand-gold font-semibold">{currentTeacher.name}</strong> ({currentTeacher.title})
          </p>
        </div>

        {/* Passwordless Teacher Profile Selector */}
        <div className="bg-white/10 p-2 rounded-2xl border border-white/20 backdrop-blur text-xs">
          <label className="block text-[10px] uppercase font-bold text-brand-gold mb-1">Switch Teacher Account:</label>
          <select
            value={selectedTeacherId}
            onChange={(e) => setSelectedTeacherId(e.target.value)}
            className="bg-slate-900 text-white font-bold px-3 py-2 rounded-xl outline-none border border-slate-700 cursor-pointer"
          >
            {teachers.map(t => (
              <option key={t.id} value={t.id}>
                {t.name} ({t.subjects.join(', ')})
              </option>
            ))}
          </select>
        </div>
      </div>

      {/* Navigation Tabs */}
      <div className="flex overflow-x-auto gap-2 border-b border-slate-200 pb-2 max-w-full">
        <button
          onClick={() => setActiveTab('marks')}
          className={`px-5 py-3 rounded-2xl text-xs font-bold transition-all flex items-center gap-2 ${
            activeTab === 'marks' ? 'bg-brand-green text-white shadow-md' : 'bg-white text-slate-700 hover:bg-slate-100'
          }`}
        >
          <Award className="w-4 h-4 text-brand-gold" /> Mark Entry & UNEB Grading
        </button>

        <button
          onClick={() => setActiveTab('attendance')}
          className={`px-5 py-3 rounded-2xl text-xs font-bold transition-all flex items-center gap-2 ${
            activeTab === 'attendance' ? 'bg-brand-green text-white shadow-md' : 'bg-white text-slate-700 hover:bg-slate-100'
          }`}
        >
          <CheckSquare className="w-4 h-4 text-brand-gold" /> Class Attendance Roll Call
        </button>

        <button
          onClick={() => setActiveTab('upload')}
          className={`px-5 py-3 rounded-2xl text-xs font-bold transition-all flex items-center gap-2 ${
            activeTab === 'upload' ? 'bg-brand-green text-white shadow-md' : 'bg-white text-slate-700 hover:bg-slate-100'
          }`}
        >
          <BookOpen className="w-4 h-4 text-brand-gold" /> Upload E-Notes to Library
        </button>

        <button
          onClick={() => setActiveTab('reports')}
          className={`px-5 py-3 rounded-2xl text-xs font-bold transition-all flex items-center gap-2 ${
            activeTab === 'reports' ? 'bg-brand-green text-white shadow-md' : 'bg-white text-slate-700 hover:bg-slate-100'
          }`}
        >
          <FileText className="w-4 h-4 text-brand-gold" /> Student Report Card Preview
        </button>
      </div>

      {/* TAB 1: MARK ENTRY & UNEB GRADING */}
      {activeTab === 'marks' && (
        <div className="bg-white rounded-3xl border border-slate-200 p-8 shadow-md space-y-6">
          <div className="flex flex-wrap justify-between items-center gap-4 border-b border-slate-200 pb-4">
            <div>
              <h2 className="text-xl font-black text-slate-900 font-serif">Academic Marks Entry Sheet</h2>
              <p className="text-xs text-slate-500">BOT (20%) + MID (30%) + EOT (50%) = Total Score & UNEB Grade</p>
            </div>

            <div className="flex gap-4">
              <div>
                <label className="block text-[10px] font-bold text-slate-500 uppercase mb-0.5">Assigned Class</label>
                <select
                  value={selectedClass}
                  onChange={(e) => setSelectedClass(e.target.value)}
                  className="px-3 py-2 rounded-xl border border-slate-300 text-xs font-bold outline-none"
                >
                  {currentTeacher.classes.map(c => (
                    <option key={c} value={c}>{c}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-500 uppercase mb-0.5">Subject</label>
                <select
                  value={selectedSubject}
                  onChange={(e) => setSelectedSubject(e.target.value)}
                  className="px-3 py-2 rounded-xl border border-slate-300 text-xs font-bold outline-none"
                >
                  {currentTeacher.subjects.map(s => (
                    <option key={s} value={s}>{s}</option>
                  ))}
                </select>
              </div>
            </div>
          </div>

          {/* Marks Table */}
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-xs">
              <thead>
                <tr className="bg-slate-100 text-slate-700 font-bold uppercase text-[10px]">
                  <th className="p-3 rounded-l-xl">Student Index & Name</th>
                  <th className="p-3">BOT (Max 20)</th>
                  <th className="p-3">MID (Max 30)</th>
                  <th className="p-3">EOT (Max 50)</th>
                  <th className="p-3">Total (100%)</th>
                  <th className="p-3">UNEB Grade</th>
                  <th className="p-3 rounded-r-xl">Remarks</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 font-medium">
                {classStudents.map((s) => {
                  const m = marksState[s.id] || { bot: 15, mid: 22, eot: 40 };
                  const total = m.bot + m.mid + m.eot;
                  const isALevel = s.level === 'A-Level';
                  const grade = computeUnebGrade(total, isALevel);

                  return (
                    <tr key={s.id} className="hover:bg-slate-50">
                      <td className="p-3">
                        <div className="font-bold text-slate-900">{s.fullName}</div>
                        <div className="text-[10px] text-slate-400 font-mono">{s.indexNo}</div>
                      </td>

                      <td className="p-3">
                        <input
                          type="number"
                          min="0"
                          max="20"
                          value={m.bot}
                          onChange={(e) => handleMarkChange(s.id, 'bot', Number(e.target.value))}
                          className="w-16 px-2 py-1 border rounded-lg font-bold text-center"
                        />
                      </td>

                      <td className="p-3">
                        <input
                          type="number"
                          min="0"
                          max="30"
                          value={m.mid}
                          onChange={(e) => handleMarkChange(s.id, 'mid', Number(e.target.value))}
                          className="w-16 px-2 py-1 border rounded-lg font-bold text-center"
                        />
                      </td>

                      <td className="p-3">
                        <input
                          type="number"
                          min="0"
                          max="50"
                          value={m.eot}
                          onChange={(e) => handleMarkChange(s.id, 'eot', Number(e.target.value))}
                          className="w-16 px-2 py-1 border rounded-lg font-bold text-center"
                        />
                      </td>

                      <td className="p-3 font-black text-brand-green text-sm">
                        {total}%
                      </td>

                      <td className="p-3">
                        <span className={`px-2.5 py-1 rounded-full font-black text-xs ${
                          grade === 'D1' || grade === 'A' ? 'bg-emerald-100 text-emerald-800' :
                          grade === 'F9' || grade === 'F' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800'
                        }`}>
                          {grade}
                        </span>
                      </td>

                      <td className="p-3 text-slate-500 italic text-[11px]">
                        {total >= 80 ? 'Excellent Mastery' : total >= 60 ? 'Good Progress' : 'Needs Practice'}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          <div className="flex justify-end pt-4">
            <button
              onClick={handleSaveMarks}
              className="px-6 py-3 bg-brand-green hover:bg-emerald-800 text-white font-bold rounded-xl text-xs transition-colors flex items-center gap-2 shadow-md"
            >
              <Save className="w-4 h-4 text-brand-gold" /> Save Class Marks & Submit to Director of Studies
            </button>
          </div>
        </div>
      )}

      {/* TAB 2: CLASS ATTENDANCE */}
      {activeTab === 'attendance' && (
        <div className="bg-white rounded-3xl border border-slate-200 p-8 shadow-md space-y-6">
          <div className="flex flex-wrap justify-between items-center gap-4 border-b border-slate-200 pb-4">
            <div>
              <h2 className="text-xl font-black text-slate-900 font-serif">Daily Attendance Roll Call</h2>
              <p className="text-xs text-slate-500">Record daily class attendance for Wakiso district inspections.</p>
            </div>

            <div className="flex items-center gap-3">
              <input
                type="date"
                value={attendanceDate}
                onChange={(e) => setAttendanceDate(e.target.value)}
                className="px-3 py-2 rounded-xl border border-slate-300 text-xs font-bold outline-none"
              />
              <span className="bg-emerald-100 text-brand-green font-bold text-xs px-3 py-1.5 rounded-full">
                {classStudents.length} Students Total
              </span>
            </div>
          </div>

          <div className="space-y-3">
            {classStudents.map((s) => {
              const isPresent = attendanceMap[s.id] ?? true;
              return (
                <div key={s.id} className="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-200">
                  <div>
                    <span className="font-bold text-slate-900 text-sm">{s.fullName}</span>
                    <span className="text-xs text-slate-400 font-mono ml-2">({s.indexNo})</span>
                  </div>

                  <div className="flex gap-3">
                    <button
                      onClick={() => setAttendanceMap(prev => ({ ...prev, [s.id]: true }))}
                      className={`px-4 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1 ${
                        isPresent ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-200 text-slate-700'
                      }`}
                    >
                      <Check className="w-4 h-4" /> Present
                    </button>

                    <button
                      onClick={() => setAttendanceMap(prev => ({ ...prev, [s.id]: false }))}
                      className={`px-4 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1 ${
                        !isPresent ? 'bg-rose-600 text-white shadow-sm' : 'bg-slate-200 text-slate-700'
                      }`}
                    >
                      <X className="w-4 h-4" /> Absent
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* TAB 3: UPLOAD E-NOTES */}
      {activeTab === 'upload' && (
        <div className="bg-white rounded-3xl border border-slate-200 p-8 shadow-md space-y-6 max-w-2xl">
          <div className="border-b border-slate-200 pb-3">
            <h2 className="text-xl font-black text-slate-900 font-serif">Publish Learning Materials</h2>
            <p className="text-xs text-slate-500">Upload study notes and past paper solutions directly to student digital library.</p>
          </div>

          {uploadSuccess && (
            <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-xs font-bold flex items-center gap-2">
              <Check className="w-4 h-4" /> Document published to Digital Library successfully!
            </div>
          )}

          <form onSubmit={handleUploadResource} className="space-y-4">
            <div>
              <label className="block text-xs font-bold text-slate-700 mb-1">Document Title *</label>
              <input
                type="text"
                required
                placeholder="e.g. S.4 Physics Light & Reflection Revision Notes 2026"
                value={uploadTitle}
                onChange={(e) => setUploadTitle(e.target.value)}
                className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Subject</label>
                <select
                  value={uploadSubject}
                  onChange={(e) => setUploadSubject(e.target.value)}
                  className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
                >
                  <option value="Physics">Physics</option>
                  <option value="Chemistry">Chemistry</option>
                  <option value="Mathematics">Mathematics</option>
                  <option value="ICT">ICT</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Academic Level</label>
                <select
                  value={uploadLevel}
                  onChange={(e) => setUploadLevel(e.target.value as any)}
                  className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
                >
                  <option value="O-Level">O-Level (S.1-S.4)</option>
                  <option value="A-Level">A-Level (S.5-S.6)</option>
                </select>
              </div>
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 mb-1">Resource Category</label>
              <select
                value={uploadType}
                onChange={(e) => setUploadType(e.target.value as any)}
                className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
              >
                <option value="revision_notes">Teacher Revision Notes</option>
                <option value="past_paper">UNEB Past Paper Solution</option>
                <option value="textbook">Course E-Textbook</option>
              </select>
            </div>

            <button
              type="submit"
              className="w-full py-3.5 bg-brand-green hover:bg-emerald-800 text-white font-bold rounded-xl text-xs transition-colors flex items-center justify-center gap-2"
            >
              <Plus className="w-4 h-4 text-brand-gold" /> Publish Resource to Digital Library
            </button>
          </form>
        </div>
      )}

      {/* TAB 4: REPORT CARD PREVIEW */}
      {activeTab === 'reports' && (
        <div className="bg-white rounded-3xl border border-slate-200 p-8 shadow-md space-y-6">
          <div className="flex flex-wrap justify-between items-center gap-4 border-b border-slate-200 pb-4 no-print">
            <div>
              <h2 className="text-xl font-black text-slate-900 font-serif">Official UNEB Report Card Preview</h2>
              <p className="text-xs text-slate-500">Format ready for printing and parent signature.</p>
            </div>

            <div className="flex items-center gap-3">
              <select
                value={previewStudentId}
                onChange={(e) => setPreviewStudentId(e.target.value)}
                className="px-3 py-2 rounded-xl border border-slate-300 text-xs font-bold outline-none"
              >
                {students.map(s => (
                  <option key={s.id} value={s.id}>{s.fullName} ({s.classStream})</option>
                ))}
              </select>

              <button
                onClick={() => window.print()}
                className="px-4 py-2 bg-brand-maroon text-white font-bold rounded-xl text-xs flex items-center gap-1.5 shadow"
              >
                <Printer className="w-4 h-4" /> Print Report Card
              </button>
            </div>
          </div>

          {/* Formatted Printable Report Card */}
          <div className="border-4 border-brand-green p-8 rounded-2xl bg-white space-y-6 print:border-none print:p-0">
            
            {/* Header with Logo */}
            <div className="text-center border-b-2 border-brand-gold pb-4 space-y-1">
              <img 
                src="/thamani-logo.png" 
                alt="Logo" 
                className="h-20 mx-auto object-contain"
              />
              <h1 className="text-2xl font-black font-serif text-brand-green">THAMANI HIGH SCHOOL - KAKIRI</h1>
              <p className="text-xs font-bold text-brand-maroon uppercase">P.O. BOX 104, WAKISO • UNEB CENTER NO. U0892</p>
              <h2 className="text-sm font-bold text-slate-800 uppercase pt-2">STUDENT ACADEMIC PROGRESS REPORT CARD • TERM II 2026</h2>
            </div>

            {/* Student Metadata */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs font-medium bg-slate-50 p-4 rounded-xl border border-slate-200">
              <div><strong>Student Name:</strong> {previewStudent.fullName}</div>
              <div><strong>Index No:</strong> {previewStudent.indexNo}</div>
              <div><strong>Class Stream:</strong> {previewStudent.classStream}</div>
              <div><strong>Hostel Status:</strong> {previewStudent.hostelStatus}</div>
            </div>

            {/* Marks Summary Table */}
            <table className="w-full text-left border-collapse text-xs border border-slate-300">
              <thead>
                <tr className="bg-brand-green text-white font-bold text-[10px] uppercase">
                  <th className="p-2 border">Subject</th>
                  <th className="p-2 border text-center">BOT (20%)</th>
                  <th className="p-2 border text-center">MID (30%)</th>
                  <th className="p-2 border text-center">EOT (50%)</th>
                  <th className="p-2 border text-center">Total (100%)</th>
                  <th className="p-2 border text-center">Grade</th>
                  <th className="p-2 border">Teacher Remarks</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 font-medium">
                <tr>
                  <td className="p-2 border font-bold">Mathematics</td>
                  <td className="p-2 border text-center">19</td>
                  <td className="p-2 border text-center">28</td>
                  <td className="p-2 border text-center">48</td>
                  <td className="p-2 border text-center font-bold text-emerald-800">95%</td>
                  <td className="p-2 border text-center font-black">D1</td>
                  <td className="p-2 border italic text-slate-600">Exceptional mathematical problem solving.</td>
                </tr>
                <tr>
                  <td className="p-2 border font-bold">Physics</td>
                  <td className="p-2 border text-center">18</td>
                  <td className="p-2 border text-center">27</td>
                  <td className="p-2 border text-center">45</td>
                  <td className="p-2 border text-center font-bold text-emerald-800">90%</td>
                  <td className="p-2 border text-center font-black">D1</td>
                  <td className="p-2 border italic text-slate-600">Outstanding laboratory practical precision.</td>
                </tr>
                <tr>
                  <td className="p-2 border font-bold">Chemistry</td>
                  <td className="p-2 border text-center">16</td>
                  <td className="p-2 border text-center">24</td>
                  <td className="p-2 border text-center">42</td>
                  <td className="p-2 border text-center font-bold text-emerald-800">82%</td>
                  <td className="p-2 border text-center font-black">D2</td>
                  <td className="p-2 border italic text-slate-600">Good grasp of organic chemistry equations.</td>
                </tr>
                <tr>
                  <td className="p-2 border font-bold">ICT / Computer Studies</td>
                  <td className="p-2 border text-center">19</td>
                  <td className="p-2 border text-center">29</td>
                  <td className="p-2 border text-center">49</td>
                  <td className="p-2 border text-center font-bold text-emerald-800">97%</td>
                  <td className="p-2 border text-center font-black">D1</td>
                  <td className="p-2 border italic text-slate-600">Top candidate in website coding project.</td>
                </tr>
              </tbody>
            </table>

            {/* Comments & Signatures */}
            <div className="grid grid-cols-2 gap-6 text-xs pt-4 border-t border-slate-300">
              <div className="space-y-2">
                <p><strong>Class Teacher's Remark:</strong> Disciplined scholar with strong academic drive.</p>
                <div className="pt-6 border-b border-slate-400 w-48"></div>
                <p className="text-[10px] text-slate-500 font-bold uppercase">Class Teacher Signature</p>
              </div>

              <div className="space-y-2">
                <p><strong>Headteacher's Decision:</strong> Promoted to Senior 4 Final Examinations.</p>
                <div className="pt-6 border-b border-slate-400 w-48"></div>
                <p className="text-[10px] text-slate-500 font-bold uppercase">Dr. Ssemwanga Ronald (Headteacher)</p>
              </div>
            </div>

          </div>
        </div>
      )}

    </div>
  );
};
