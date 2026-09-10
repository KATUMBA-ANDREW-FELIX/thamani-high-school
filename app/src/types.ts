export type UserRole = 'public' | 'teacher' | 'admin';

export type AcademicLevel = 'O-Level' | 'A-Level';

export type ApplicantStatus = 'pending' | 'approved' | 'rejected' | 'interview';

export interface Applicant {
  id: string;
  refCode: string;
  fullName: string;
  gender: 'Male' | 'Female';
  dob: string;
  prevSchool: string;
  academicLevel: AcademicLevel;
  pleAggregates?: number;
  uceGrades?: string;
  selectedCombination: string;
  hostelOption: 'Day Scholar' | 'Boarder';
  guardianName: string;
  guardianPhone: string;
  guardianEmail: string;
  status: ApplicantStatus;
  appliedDate: string;
  adminNotes?: string;
}

export interface Student {
  id: string;
  indexNo: string;
  fullName: string;
  gender: 'Male' | 'Female';
  classStream: string; // e.g. "Senior 4 West", "Senior 6 PCM/ICT"
  level: AcademicLevel;
  combination?: string;
  guardianName: string;
  guardianPhone: string;
  hostelStatus: 'Day' | 'Boarding';
  feeTotal: number;
  feePaid: number;
  attendancePct: number;
  conductRating: 'Excellent' | 'Very Good' | 'Good' | 'Needs Improvement';
}

export interface SubjectMark {
  subject: string;
  bot: number; // Beginning of Term (20%)
  mid: number; // Mid Term (30%)
  eot: number; // End of Term (50%)
  totalScore: number; // 100%
  grade: string; // D1, D2, C3, C4, C5, C6, P7, P8, F9 or A, B, C, D, E, O, F
  remarks: string;
}

export interface StudentMarkReport {
  studentId: string;
  term: string; // "Term I - 2026"
  year: number;
  classStream: string;
  marks: SubjectMark[];
  teacherComments: string;
  headteacherComments: string;
}

export interface Teacher {
  id: string;
  name: string;
  title: string;
  email: string;
  phone: string;
  subjects: string[];
  classes: string[];
  photo: string;
  qualifications: string;
}

export interface AttendanceRecord {
  id: string;
  date: string;
  classStream: string;
  subject: string;
  presentIds: string[];
  absentIds: string[];
  excusedIds: string[];
}

export interface LibraryResource {
  id: string;
  title: string;
  subject: string;
  level: AcademicLevel;
  type: 'past_paper' | 'revision_notes' | 'textbook' | 'syllabus';
  author: string;
  year: number;
  fileSize: string;
  downloads: number;
  downloadUrl?: string;
}

export interface CalendarEvent {
  id: string;
  title: string;
  date: string;
  category: 'academic' | 'sports' | 'culture' | 'exam' | 'holiday';
  location: string;
  description: string;
}

export interface GalleryItem {
  id: string;
  title: string;
  category: 'sports' | 'academics' | 'culture' | 'science' | 'facilities';
  imageUrl: string;
  caption: string;
}

export interface NewsArticle {
  id: string;
  title: string;
  date: string;
  category: string;
  summary: string;
  content: string;
  imageUrl: string;
  author: string;
}

export type DayOfWeek = 'Monday' | 'Tuesday' | 'Wednesday' | 'Thursday' | 'Friday';

export interface TimetableSlot {
  id: string;
  day: DayOfWeek;
  period: string; // e.g. "P1 (8:00 - 8:40 AM)", "P2 (8:40 - 9:20 AM)"
  periodIndex: number; // 1..8
  classStream: string; // e.g. "Senior 4 West"
  subject: string;
  teacherId: string;
  teacherName: string;
  room: string;
}

export interface DutyRosterTeacher {
  teacherId: string;
  teacherName: string;
  dutyRole: string; // e.g. "Assembly & Morning Prep Warden", "Dormitory Inspector"
}

export interface DutyRosterItem {
  id: string;
  weekNumber: number;
  startDate: string;
  endDate: string;
  assignedTeachers: DutyRosterTeacher[];
  notes: string;
}

export interface DisciplineLogEntry {
  id: string;
  studentId: string;
  studentName: string;
  classStream: string;
  date: string;
  type: 'commendation' | 'warning' | 'demerit';
  category: 'punctuality' | 'academics' | 'uniform' | 'conduct';
  description: string;
  loggedByTeacherName: string;
}

export interface SyllabusTopic {
  id: string;
  topicNumber: number;
  topicTitle: string;
  subtopics: string[];
  status: 'pending' | 'in_progress' | 'completed';
  targetDate: string;
  completedDate?: string;
  notes?: string;
}

export interface SubjectSyllabus {
  id: string;
  subject: string;
  classStream: string; // Specific class and stream (e.g. "Senior 4 West")
  level: AcademicLevel;
  departmentHeadId: string;
  departmentHeadName: string;
  title: string;
  topics: SyllabusTopic[];
}

export interface NoticeCircular {
  id: string;
  title: string;
  publishDate: string;
  targetAudience: 'all' | 'parents' | 'teachers' | 'students';
  category: 'general' | 'fees' | 'academic' | 'urgent';
  content: string;
  isPinned: boolean;
  pdfAttachmentName?: string;
}

