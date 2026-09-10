import { useState, useEffect } from 'react';
import type { 
  UserRole, 
  Applicant, 
  Student, 
  Teacher, 
  LibraryResource, 
  CalendarEvent, 
  GalleryItem, 
  NewsArticle,
  TimetableSlot,
  DutyRosterItem,
  DisciplineLogEntry,
  SubjectSyllabus,
  SyllabusTopic,
  NoticeCircular
} from './types';

import { 
  INITIAL_APPLICANTS, 
  INITIAL_STUDENTS, 
  INITIAL_TEACHERS, 
  INITIAL_LIBRARY, 
  INITIAL_EVENTS, 
  INITIAL_GALLERY, 
  INITIAL_NEWS,
  INITIAL_TIMETABLE,
  INITIAL_DUTY_ROSTER,
  INITIAL_DISCIPLINE_LOGS,
  INITIAL_SYLLABI,
  INITIAL_NOTICES
} from './mockData';

import { Navbar } from './components/Navbar';
import { Footer } from './components/Footer';

import { HomeView } from './views/HomeView';
import { AdmissionsView } from './views/AdmissionsView';
import { LibraryView } from './views/LibraryView';
import { CampusMapView } from './views/CampusMapView';
import { CalendarFeesView } from './views/CalendarFeesView';
import { GalleryView } from './views/GalleryView';
import { AlumniView } from './views/AlumniView';
import { TeacherPortalView } from './views/TeacherPortalView';
import { AdminPortalView } from './views/AdminPortalView';

export function App() {
  const [currentRole, setCurrentRole] = useState<UserRole>('public');
  const [activeView, setActiveView] = useState<string>('home');

  // Local Storage State Engines
  const [applicants, setApplicants] = useState<Applicant[]>(() => {
    const saved = localStorage.getItem('thamani_applicants');
    return saved ? JSON.parse(saved) : INITIAL_APPLICANTS;
  });

  const [students, setStudents] = useState<Student[]>(() => {
    const saved = localStorage.getItem('thamani_students');
    return saved ? JSON.parse(saved) : INITIAL_STUDENTS;
  });

  const [teachers] = useState<Teacher[]>(INITIAL_TEACHERS);

  const [libraryResources, setLibraryResources] = useState<LibraryResource[]>(() => {
    const saved = localStorage.getItem('thamani_library');
    return saved ? JSON.parse(saved) : INITIAL_LIBRARY;
  });

  const [events] = useState<CalendarEvent[]>(INITIAL_EVENTS);
  const [gallery] = useState<GalleryItem[]>(INITIAL_GALLERY);

  const [news, setNews] = useState<NewsArticle[]>(() => {
    const saved = localStorage.getItem('thamani_news');
    return saved ? JSON.parse(saved) : INITIAL_NEWS;
  });

  const [timetableSlots, setTimetableSlots] = useState<TimetableSlot[]>(() => {
    const saved = localStorage.getItem('thamani_timetable');
    return saved ? JSON.parse(saved) : INITIAL_TIMETABLE;
  });

  const [dutyRosters, setDutyRosters] = useState<DutyRosterItem[]>(() => {
    const saved = localStorage.getItem('thamani_duty_roster');
    return saved ? JSON.parse(saved) : INITIAL_DUTY_ROSTER;
  });

  const [disciplineLogs, setDisciplineLogs] = useState<DisciplineLogEntry[]>(() => {
    const saved = localStorage.getItem('thamani_discipline_logs');
    return saved ? JSON.parse(saved) : INITIAL_DISCIPLINE_LOGS;
  });

  const [subjectSyllabi, setSubjectSyllabi] = useState<SubjectSyllabus[]>(() => {
    const saved = localStorage.getItem('thamani_syllabi');
    return saved ? JSON.parse(saved) : INITIAL_SYLLABI;
  });

  const [notices, setNotices] = useState<NoticeCircular[]>(() => {
    const saved = localStorage.getItem('thamani_notices');
    return saved ? JSON.parse(saved) : INITIAL_NOTICES;
  });

  // Sync state to local storage
  useEffect(() => {
    localStorage.setItem('thamani_applicants', JSON.stringify(applicants));
  }, [applicants]);

  useEffect(() => {
    localStorage.setItem('thamani_students', JSON.stringify(students));
  }, [students]);

  useEffect(() => {
    localStorage.setItem('thamani_library', JSON.stringify(libraryResources));
  }, [libraryResources]);

  useEffect(() => {
    localStorage.setItem('thamani_news', JSON.stringify(news));
  }, [news]);

  useEffect(() => {
    localStorage.setItem('thamani_timetable', JSON.stringify(timetableSlots));
  }, [timetableSlots]);

  useEffect(() => {
    localStorage.setItem('thamani_duty_roster', JSON.stringify(dutyRosters));
  }, [dutyRosters]);

  useEffect(() => {
    localStorage.setItem('thamani_discipline_logs', JSON.stringify(disciplineLogs));
  }, [disciplineLogs]);

  useEffect(() => {
    localStorage.setItem('thamani_syllabi', JSON.stringify(subjectSyllabi));
  }, [subjectSyllabi]);

  useEffect(() => {
    localStorage.setItem('thamani_notices', JSON.stringify(notices));
  }, [notices]);

  // Actions
  const handleAddApplicant = (data: Omit<Applicant, 'id' | 'refCode' | 'status' | 'appliedDate'>): Applicant => {
    const randomNum = Math.floor(1000 + Math.random() * 9000);
    const refCode = `TAK-2026-${randomNum}`;
    const newApp: Applicant = {
      ...data,
      id: `APP-2026-${Date.now()}`,
      refCode,
      status: 'pending',
      appliedDate: new Date().toISOString().split('T')[0]
    };
    setApplicants(prev => [newApp, ...prev]);
    return newApp;
  };

  const handleUpdateApplicantStatus = (id: string, status: Applicant['status'], notes?: string) => {
    setApplicants(prev => prev.map(app => {
      if (app.id === id) {
        return { ...app, status, adminNotes: notes || app.adminNotes };
      }
      return app;
    }));
  };

  const handleAddLibraryResource = (data: Omit<LibraryResource, 'id' | 'downloads'>) => {
    const newRes: LibraryResource = {
      ...data,
      id: `LIB-${Date.now()}`,
      downloads: 0
    };
    setLibraryResources(prev => [newRes, ...prev]);
  };

  const handleIncrementDownload = (id: string) => {
    setLibraryResources(prev => prev.map(r => {
      if (r.id === id) {
        return { ...r, downloads: r.downloads + 1 };
      }
      return r;
    }));
  };

  const handleAddNewsArticle = (data: Omit<NewsArticle, 'id'>) => {
    const newArticle: NewsArticle = {
      ...data,
      id: `NEWS-${Date.now()}`
    };
    setNews(prev => [newArticle, ...prev]);
  };

  // Timetable Handlers
  const handleAddTimetableSlot = (slot: Omit<TimetableSlot, 'id'>) => {
    const newSlot: TimetableSlot = {
      ...slot,
      id: `TT-${Date.now()}`
    };
    setTimetableSlots(prev => [...prev, newSlot]);
  };

  const handleDeleteTimetableSlot = (id: string) => {
    setTimetableSlots(prev => prev.filter(s => s.id !== id));
  };

  // Duty Roster Handlers
  const handleAddDutyRoster = (item: Omit<DutyRosterItem, 'id'>) => {
    const newItem: DutyRosterItem = {
      ...item,
      id: `DUTY-${Date.now()}`
    };
    setDutyRosters(prev => [newItem, ...prev]);
  };

  const handleDeleteDutyRoster = (id: string) => {
    setDutyRosters(prev => prev.filter(r => r.id !== id));
  };

  // Discipline Log Handlers
  const handleAddDisciplineLog = (log: Omit<DisciplineLogEntry, 'id'>) => {
    const newLog: DisciplineLogEntry = {
      ...log,
      id: `DISC-${Date.now()}`
    };
    setDisciplineLogs(prev => [newLog, ...prev]);
  };

  // Syllabus Handlers
  const handleAddSyllabus = (syllable: Omit<SubjectSyllabus, 'id'>) => {
    const newSyl: SubjectSyllabus = {
      ...syllable,
      id: `SYL-${Date.now()}`
    };
    setSubjectSyllabi(prev => [newSyl, ...prev]);
  };

  const handleToggleTopicStatus = (syllabusId: string, topicId: string, status: SyllabusTopic['status']) => {
    setSubjectSyllabi(prev => prev.map(syl => {
      if (syl.id === syllabusId) {
        return {
          ...syl,
          topics: syl.topics.map(t => {
            if (t.id === topicId) {
              return {
                ...t,
                status,
                completedDate: status === 'completed' ? new Date().toISOString().split('T')[0] : undefined
              };
            }
            return t;
          })
        };
      }
      return syl;
    }));
  };

  // Notice Board Handlers
  const handleAddNotice = (notice: Omit<NoticeCircular, 'id'>) => {
    const newNotice: NoticeCircular = {
      ...notice,
      id: `NOT-${Date.now()}`
    };
    setNotices(prev => [newNotice, ...prev]);
  };

  const handleTogglePinNotice = (id: string) => {
    setNotices(prev => prev.map(n => n.id === id ? { ...n, isPinned: !n.isPinned } : n));
  };

  const handleDeleteNotice = (id: string) => {
    setNotices(prev => prev.filter(n => n.id !== id));
  };

  const handleResetDemoData = () => {
    if (confirm('Reset all applicant, student, library, timetable, duty roster, discipline, syllabus, and notice data back to default demo state?')) {
      localStorage.removeItem('thamani_applicants');
      localStorage.removeItem('thamani_students');
      localStorage.removeItem('thamani_library');
      localStorage.removeItem('thamani_news');
      localStorage.removeItem('thamani_timetable');
      localStorage.removeItem('thamani_duty_roster');
      localStorage.removeItem('thamani_discipline_logs');
      localStorage.removeItem('thamani_syllabi');
      localStorage.removeItem('thamani_notices');

      setApplicants(INITIAL_APPLICANTS);
      setStudents(INITIAL_STUDENTS);
      setLibraryResources(INITIAL_LIBRARY);
      setNews(INITIAL_NEWS);
      setTimetableSlots(INITIAL_TIMETABLE);
      setDutyRosters(INITIAL_DUTY_ROSTER);
      setDisciplineLogs(INITIAL_DISCIPLINE_LOGS);
      setSubjectSyllabi(INITIAL_SYLLABI);
      setNotices(INITIAL_NOTICES);

      alert('Demo data has been reset to default.');
    }
  };

  return (
    <div className="min-h-screen flex flex-col bg-slate-50 font-sans text-slate-900">
      
      {/* Sticky Navigation Header with Logo */}
      <Navbar
        currentRole={currentRole}
        setCurrentRole={setCurrentRole}
        activeView={activeView}
        setActiveView={setActiveView}
        applicantCount={applicants.filter(a => a.status === 'pending').length}
      />

      {/* Main Viewport Router */}
      <main className="flex-grow">
        {currentRole === 'teacher' ? (
          <TeacherPortalView
            teachers={teachers}
            students={students}
            timetableSlots={timetableSlots}
            dutyRosters={dutyRosters}
            disciplineLogs={disciplineLogs}
            subjectSyllabi={subjectSyllabi}
            onAddLibraryResource={handleAddLibraryResource}
            onUpdateStudentMark={() => {}}
            onAddDisciplineLog={handleAddDisciplineLog}
            onAddSyllabus={handleAddSyllabus}
            onToggleTopicStatus={handleToggleTopicStatus}
          />
        ) : currentRole === 'admin' ? (
          <AdminPortalView
            applicants={applicants}
            students={students}
            teachers={teachers}
            news={news}
            timetableSlots={timetableSlots}
            dutyRosters={dutyRosters}
            notices={notices}
            onUpdateApplicantStatus={handleUpdateApplicantStatus}
            onAddNewsArticle={handleAddNewsArticle}
            onAddTimetableSlot={handleAddTimetableSlot}
            onDeleteTimetableSlot={handleDeleteTimetableSlot}
            onAddDutyRoster={handleAddDutyRoster}
            onDeleteDutyRoster={handleDeleteDutyRoster}
            onAddNotice={handleAddNotice}
            onTogglePinNotice={handleTogglePinNotice}
            onDeleteNotice={handleDeleteNotice}
            onResetDemoData={handleResetDemoData}
          />
        ) : (
          /* Public Views Router */
          <>
            {activeView === 'home' && (
              <HomeView news={news} events={events} notices={notices} setActiveView={setActiveView} />
            )}

            {activeView === 'admissions' && (
              <AdmissionsView onAddApplicant={handleAddApplicant} applicants={applicants} />
            )}

            {activeView === 'library' && (
              <LibraryView resources={libraryResources} onIncrementDownload={handleIncrementDownload} />
            )}

            {activeView === 'calendar' && (
              <CalendarFeesView events={events} />
            )}

            {activeView === 'map' && (
              <CampusMapView />
            )}

            {activeView === 'gallery' && (
              <GalleryView gallery={gallery} />
            )}

            {activeView === 'alumni' && (
              <AlumniView />
            )}
          </>
        )}
      </main>

      {/* Global Footer */}
      <Footer setActiveView={setActiveView} />

    </div>
  );
}

export default App;
