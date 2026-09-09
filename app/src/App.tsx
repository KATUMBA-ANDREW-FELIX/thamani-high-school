import { useState, useEffect } from 'react';
import type { UserRole, Applicant, Student, Teacher, LibraryResource, CalendarEvent, GalleryItem, NewsArticle } from './types';
import { 
  INITIAL_APPLICANTS, 
  INITIAL_STUDENTS, 
  INITIAL_TEACHERS, 
  INITIAL_LIBRARY, 
  INITIAL_EVENTS, 
  INITIAL_GALLERY, 
  INITIAL_NEWS 
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

  const handleResetDemoData = () => {
    if (confirm('Reset all applicant, student, and library data back to default demo state?')) {
      localStorage.removeItem('thamani_applicants');
      localStorage.removeItem('thamani_students');
      localStorage.removeItem('thamani_library');
      localStorage.removeItem('thamani_news');
      setApplicants(INITIAL_APPLICANTS);
      setStudents(INITIAL_STUDENTS);
      setLibraryResources(INITIAL_LIBRARY);
      setNews(INITIAL_NEWS);
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
            onAddLibraryResource={handleAddLibraryResource}
            onUpdateStudentMark={() => {}}
          />
        ) : currentRole === 'admin' ? (
          <AdminPortalView
            applicants={applicants}
            students={students}
            teachers={teachers}
            news={news}
            onUpdateApplicantStatus={handleUpdateApplicantStatus}
            onAddNewsArticle={handleAddNewsArticle}
            onResetDemoData={handleResetDemoData}
          />
        ) : (
          /* Public Views Router */
          <>
            {activeView === 'home' && (
              <HomeView news={news} events={events} setActiveView={setActiveView} />
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
