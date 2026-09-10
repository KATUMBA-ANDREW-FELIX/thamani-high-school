import type { Applicant, Student, Teacher, LibraryResource, CalendarEvent, GalleryItem, NewsArticle, StudentMarkReport, TimetableSlot, DutyRosterItem, DisciplineLogEntry, SubjectSyllabus, NoticeCircular, TimetableDocument } from './types';

// Helper to generate natural, clean SVG graphics for school photos (no AI neon gradients)
export const createSvgPlaceholder = (title: string, subtitle: string, bgColor = '#1A472A', textColor = '#ffffff') => {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="800" height="500" viewBox="0 0 800 500">
    <rect width="800" height="500" fill="${bgColor}"/>
    <rect x="40" y="40" width="720" height="420" fill="none" stroke="${textColor}" stroke-width="2" stroke-dasharray="6,6" opacity="0.2"/>
    <circle cx="400" cy="200" r="70" fill="${textColor}" opacity="0.08"/>
    <text x="400" y="210" font-family="Georgia, serif" font-size="30" font-weight="bold" fill="${textColor}" text-anchor="middle">${title}</text>
    <text x="400" y="260" font-family="sans-serif" font-size="18" fill="#D4AF37" font-weight="600" text-anchor="middle">${subtitle}</text>
    <line x1="300" y1="300" x2="500" y2="300" stroke="#D4AF37" stroke-width="3" opacity="0.6"/>
    <text x="400" y="420" font-family="sans-serif" font-size="14" fill="${textColor}" opacity="0.7" text-anchor="middle">THAMANI ACADEMY • KAKIRI (TAK)</text>
  </svg>`;
  return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
};

export const INITIAL_APPLICANTS: Applicant[] = [
  {
    id: 'APP-2026-001',
    refCode: 'TAK-2026-8921',
    fullName: 'Mugisha Ivan Kiggundu',
    gender: 'Male',
    dob: '2010-04-12',
    prevSchool: 'Bright Future Primary School, Wakiso',
    academicLevel: 'O-Level',
    pleAggregates: 6,
    selectedCombination: 'Senior 1 (General Curriculum)',
    hostelOption: 'Boarder',
    guardianName: 'Dr. Charles Kiggundu',
    guardianPhone: '+256 772 123 456',
    guardianEmail: 'c.kiggundu@health.go.ug',
    status: 'approved',
    appliedDate: '2026-08-15',
    adminNotes: 'Admitted on merit (Division 1 PLE). Assigned to Crane House.'
  },
  {
    id: 'APP-2026-002',
    refCode: 'TAK-2026-4412',
    fullName: 'Nakalema Grace Mary',
    gender: 'Female',
    dob: '2008-09-24',
    prevSchool: 'Mityana Modern Secondary School',
    academicLevel: 'A-Level',
    uceGrades: '10 Aggregates in best 8 (Physics D1, Chem D1, Bio D2, Math D1)',
    selectedCombination: 'BCM/SubMath (Biology, Chemistry, Mathematics)',
    hostelOption: 'Boarder',
    guardianName: 'Mrs. Sarah Nakalema',
    guardianPhone: '+256 701 987 654',
    guardianEmail: 'snakalema@gmail.com',
    status: 'pending',
    appliedDate: '2026-09-02',
    adminNotes: 'Application documents received. Awaiting UCE result slip verification.'
  },
  {
    id: 'APP-2026-003',
    refCode: 'TAK-2026-1093',
    fullName: 'Okello Emmanuel Patrick',
    gender: 'Male',
    dob: '2007-11-05',
    prevSchool: 'Gulu High School',
    academicLevel: 'A-Level',
    uceGrades: '14 Aggregates (Physics D2, Chem D2, Math D1, ICT D1)',
    selectedCombination: 'PCM/ICT (Physics, Chemistry, Mathematics, ICT)',
    hostelOption: 'Boarder',
    guardianName: 'Mr. Joseph Okello',
    guardianPhone: '+256 752 443 322',
    guardianEmail: 'okellojoseph@yahoo.com',
    status: 'interview',
    appliedDate: '2026-09-05',
    adminNotes: 'Invited for academic interview with Head of Science Dept on Sept 14.'
  },
  {
    id: 'APP-2026-004',
    refCode: 'TAK-2026-7834',
    fullName: 'Akello Joanita Beatrice',
    gender: 'Female',
    dob: '2010-01-18',
    prevSchool: 'Kampala Junior Academy',
    academicLevel: 'O-Level',
    pleAggregates: 8,
    selectedCombination: 'Senior 1 (General Curriculum)',
    hostelOption: 'Day Scholar',
    guardianName: 'Florence Akello',
    guardianPhone: '+256 782 555 111',
    guardianEmail: 'akelloflorence@gmail.com',
    status: 'pending',
    appliedDate: '2026-09-08',
    adminNotes: 'Day scholar application. Verifying Kakiri bus transport route availability.'
  }
];

export const INITIAL_STUDENTS: Student[] = [
  {
    id: 'STU-2026-101',
    indexNo: 'U0892/001',
    fullName: 'Kato Paul Mark',
    gender: 'Male',
    classStream: 'Senior 4 West',
    level: 'O-Level',
    guardianName: 'David Kato',
    guardianPhone: '+256 772 334 455',
    hostelStatus: 'Boarding',
    feeTotal: 1450000,
    feePaid: 1450000,
    attendancePct: 98,
    conductRating: 'Excellent'
  },
  {
    id: 'STU-2026-102',
    indexNo: 'U0892/002',
    fullName: 'Namutebi Brenda',
    gender: 'Female',
    classStream: 'Senior 4 West',
    level: 'O-Level',
    guardianName: 'Agnes Namutebi',
    guardianPhone: '+256 702 112 233',
    hostelStatus: 'Day',
    feeTotal: 1100000,
    feePaid: 850000,
    attendancePct: 94,
    conductRating: 'Very Good'
  },
  {
    id: 'STU-2026-201',
    indexNo: 'U0892/501',
    fullName: 'Ssemwogerere Joel',
    gender: 'Male',
    classStream: 'Senior 6 PCM/ICT',
    level: 'A-Level',
    combination: 'PCM/ICT',
    guardianName: 'Eng. Francis Ssemwogerere',
    guardianPhone: '+256 774 998 877',
    hostelStatus: 'Boarding',
    feeTotal: 1650000,
    feePaid: 1650000,
    attendancePct: 99,
    conductRating: 'Excellent'
  },
  {
    id: 'STU-2026-202',
    indexNo: 'U0892/502',
    fullName: 'Ainomugisha Diana',
    gender: 'Female',
    classStream: 'Senior 6 HEG/Div',
    level: 'A-Level',
    combination: 'HEG/Div',
    guardianName: 'Patrick Ainomugisha',
    guardianPhone: '+256 788 332 211',
    hostelStatus: 'Boarding',
    feeTotal: 1650000,
    feePaid: 1200000,
    attendancePct: 96,
    conductRating: 'Very Good'
  },
  {
    id: 'STU-2026-103',
    indexNo: 'U0892/015',
    fullName: 'Wasswa Brian',
    gender: 'Male',
    classStream: 'Senior 2 East',
    level: 'O-Level',
    guardianName: 'Harriet Nsubuga',
    guardianPhone: '+256 755 667 788',
    hostelStatus: 'Day',
    feeTotal: 1100000,
    feePaid: 1100000,
    attendancePct: 92,
    conductRating: 'Good'
  }
];

export const INITIAL_TEACHERS: Teacher[] = [
  {
    id: 'TCH-001',
    name: 'Mr. Musoke Raymond',
    title: 'Head of Physics Department',
    email: 'r.musoke@thamani.ac.ug',
    phone: '+256 772 400 101',
    subjects: ['Physics', 'Subsidiary Mathematics'],
    classes: ['Senior 4 West', 'Senior 6 PCM/ICT'],
    photo: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=600&q=80',
    qualifications: 'B.Sc. Education (Physics & Mathematics) - Makerere University'
  },
  {
    id: 'TCH-002',
    name: 'Mrs. Nabwire Christine',
    title: 'Senior Chemistry Teacher & Patron Science Club',
    email: 'c.nabwire@thamani.ac.ug',
    phone: '+256 701 500 202',
    subjects: ['Chemistry', 'Biology'],
    classes: ['Senior 4 West', 'Senior 6 BCM/SubMath'],
    photo: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=600&q=80',
    qualifications: 'M.Sc. Chemistry, B.Ed - Kyambogo University'
  },
  {
    id: 'TCH-003',
    name: 'Mr. Kiwanuka Patrick',
    title: 'Director of ICT & Computer Studies',
    email: 'p.kiwanuka@thamani.ac.ug',
    phone: '+256 752 600 303',
    subjects: ['ICT', 'Computer Studies'],
    classes: ['Senior 2 East', 'Senior 4 West', 'Senior 6 PCM/ICT'],
    photo: 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?auto=format&fit=crop&w=600&q=80',
    qualifications: 'B.Sc. Computer Science - Makerere University'
  },
  {
    id: 'TCH-004',
    name: 'Madam Akite Harriet',
    title: 'Head of English & Literature Department',
    email: 'h.akite@thamani.ac.ug',
    phone: '+256 782 700 404',
    subjects: ['English Language', 'Literature in English'],
    classes: ['Senior 1 North', 'Senior 4 West'],
    photo: 'https://images.unsplash.com/photo-1580894732413-a75151b96f01?auto=format&fit=crop&w=600&q=80',
    qualifications: 'B.A. Arts with Education - Ndejje University'
  }
];

export const INITIAL_LIBRARY: LibraryResource[] = [
  {
    id: 'LIB-001',
    title: 'UNEB UCE Physics Past Papers with Marking Schemes (2018 - 2025)',
    subject: 'Physics',
    level: 'O-Level',
    type: 'past_paper',
    author: 'Thamani Science Faculty & UNEB',
    year: 2025,
    fileSize: '4.8 MB',
    downloads: 412
  },
  {
    id: 'LIB-002',
    title: 'UACE Pure Mathematics P425/1 Algebra & Calculus Revision Guide',
    subject: 'Mathematics',
    level: 'A-Level',
    type: 'revision_notes',
    author: 'Mr. Musoke Raymond',
    year: 2026,
    fileSize: '5.6 MB',
    downloads: 620
  },
  {
    id: 'LIB-003',
    title: 'NCDC Lower Secondary Chemistry Curriculum Textbook (Senior 1 - 4)',
    subject: 'Chemistry',
    level: 'O-Level',
    type: 'textbook',
    author: 'National Curriculum Development Centre (NCDC)',
    year: 2024,
    fileSize: '11.2 MB',
    downloads: 940
  },
  {
    id: 'LIB-004',
    title: 'UACE Subsidiary ICT Practical Guide (P241/1 Worksheets)',
    subject: 'ICT',
    level: 'A-Level',
    type: 'revision_notes',
    author: 'Mr. Kiwanuka Patrick',
    year: 2026,
    fileSize: '3.4 MB',
    downloads: 510
  },
  {
    id: 'LIB-005',
    title: 'History of East Africa (1800-1970) UCE Paper 210/1 Summaries',
    subject: 'History',
    level: 'O-Level',
    type: 'revision_notes',
    author: 'Department of Humanities',
    year: 2025,
    fileSize: '4.2 MB',
    downloads: 310
  }
];

export const INITIAL_EVENTS: CalendarEvent[] = [
  {
    id: 'EVT-01',
    title: 'Term III Reporting Date for All Boarding Students',
    date: '2026-09-15',
    category: 'academic',
    location: 'Thamani Main Campus, Kakiri',
    description: 'All boarders are required to report by 4:00 PM with school requirements. Day students report on Wednesday Sept 16 at 7:30 AM.'
  },
  {
    id: 'EVT-02',
    title: 'Annual Inter-House Athletics & Sports Competition',
    date: '2026-10-04',
    category: 'sports',
    location: 'School Sports Grounds',
    description: 'Track and field competitions between Crane, Crest, Lion, and Shield Houses. Parents and guardians are invited.'
  },
  {
    id: 'EVT-03',
    title: 'National Science Fair & Agricultural Exhibition',
    date: '2026-10-20',
    category: 'culture',
    location: 'Main Science Complex',
    description: 'Student displays in renewable energy, chemistry projects, and computer programming solutions.'
  },
  {
    id: 'EVT-04',
    title: 'UNEB Briefing for S.4 (UCE) and S.6 (UACE) Candidates',
    date: '2026-11-06',
    category: 'exam',
    location: 'Main Assembly Hall',
    description: 'Official UNEB briefing conducted by the Station Supervisor and Headteacher.'
  }
];

export const INITIAL_GALLERY: GalleryItem[] = [
  {
    id: 'GAL-01',
    title: 'Science & Chemistry Laboratory Practical',
    category: 'science',
    imageUrl: 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?auto=format&fit=crop&w=800&q=80',
    caption: 'Senior 4 candidates conducting volumetric titration experiments in Chemistry Paper 3.'
  },
  {
    id: 'GAL-02',
    title: 'Annual Cultural Gala & Traditional Dance',
    category: 'culture',
    imageUrl: 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=800&q=80',
    caption: 'Students performing traditional Buganda and Ankole folk dances during the annual cultural festival.'
  },
  {
    id: 'GAL-03',
    title: 'Inter-School Football Championship Match',
    category: 'sports',
    imageUrl: 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=800&q=80',
    caption: 'Thamani Academy football team during the Wakiso District secondary schools tournament.'
  },
  {
    id: 'GAL-04',
    title: 'ICT & Computer Studies Laboratory',
    category: 'facilities',
    imageUrl: 'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?auto=format&fit=crop&w=800&q=80',
    caption: 'Students in the computer lab practicing spreadsheet management and website design.'
  },
  {
    id: 'GAL-05',
    title: 'UACE Graduation & Dedication Service',
    category: 'academics',
    imageUrl: 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?auto=format&fit=crop&w=800&q=80',
    caption: 'Senior 6 candidates and teaching staff during the Thanksgiving service.'
  }
];

export const INITIAL_NEWS: NewsArticle[] = [
  {
    id: 'NEWS-01',
    title: 'Thamani Academy Candidates Perform Outstandingly in UACE Examinations',
    date: '2026-08-28',
    category: 'Academics',
    summary: 'Over 40 scholars achieve 15+ points in STEM and Arts combinations.',
    content: 'The Headteacher and Management of Thamani Academy, Kakiri, congratulate the Class of 2025 upon their outstanding performance in the national UNEB UACE examinations. Over 40 candidates in PCM, BCM, and HEG scored 15 points and above, qualifying for Makerere, Kyambogo, and Mbarara University slots.',
    imageUrl: 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=800&q=80',
    author: 'Office of the Headteacher'
  },
  {
    id: 'NEWS-02',
    title: 'Term III School Circular & Re-opening Guidelines Released',
    date: '2026-08-20',
    category: 'Admissions',
    summary: 'Important guidelines for reporting dates, school requirements, and fee payment deadlines for Term III.',
    content: 'All parents and guardians are requested to review the Term III school circular. Boarding students report on Tuesday September 15th, 2026. Please ensure all bank slip payments are presented at the bursar\'s office upon entry.',
    imageUrl: 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?auto=format&fit=crop&w=800&q=80',
    author: 'School Administration'
  },
  {
    id: 'NEWS-03',
    title: 'Admissions Open for 2026/2027 Academic Year (S.1 & S.5)',
    date: '2026-09-01',
    category: 'Admissions',
    summary: 'Applications are now being accepted for Senior 1 and Senior 5 intake.',
    content: 'Thamani Academy invites applications for Senior 1 (PLE Division 1 and 2) and Senior 5 (PCM, BCM, PEM, HEG, MEG, LEG). Parents can submit online applications via our school website portal or visit the campus in Kakiri.',
    imageUrl: 'https://images.unsplash.com/photo-1509062522246-3755977927d7?auto=format&fit=crop&w=800&q=80',
    author: 'Admissions Committee'
  }
];

export const SAMPLE_MARKS_REPORT: StudentMarkReport = {
  studentId: 'STU-2026-101',
  term: 'Term II - 2026',
  year: 2026,
  classStream: 'Senior 4 West',
  marks: [
    { subject: 'English Language', bot: 18, mid: 27, eot: 45, totalScore: 90, grade: 'D1', remarks: 'Clear essay structure and good comprehension.' },
    { subject: 'Mathematics', bot: 19, mid: 28, eot: 48, totalScore: 95, grade: 'D1', remarks: 'Excellent performance in algebra and trigonometry.' },
    { subject: 'Physics', bot: 17, mid: 26, eot: 44, totalScore: 87, grade: 'D1', remarks: 'Good practical accuracy in mechanics.' },
    { subject: 'Chemistry', bot: 16, mid: 24, eot: 42, totalScore: 82, grade: 'D2', remarks: 'Satisfactory titration procedure.' },
    { subject: 'Biology', bot: 15, mid: 25, eot: 41, totalScore: 81, grade: 'D2', remarks: 'Good anatomical drawings.' },
    { subject: 'Geography', bot: 17, mid: 25, eot: 43, totalScore: 85, grade: 'D1', remarks: 'Accurate map work and photograph interpretation.' },
    { subject: 'History', bot: 16, mid: 24, eot: 40, totalScore: 80, grade: 'D2', remarks: 'Good historical essay presentation.' },
    { subject: 'ICT / Computer Studies', bot: 19, mid: 29, eot: 49, totalScore: 97, grade: 'D1', remarks: 'Very competent in practical computer skills.' }
  ],
  teacherComments: 'Paul is a focused student with consistent effort across all subjects.',
  headteacherComments: 'Promoted to Senior 4 Final Examination registration.'
};

export const INITIAL_TIMETABLE: TimetableSlot[] = [
  // Monday S.4 West
  { id: 'TT-001', day: 'Monday', period: 'P1 (8:00 - 8:40 AM)', periodIndex: 1, classStream: 'Senior 4 West', subject: 'Physics', teacherId: 'TCH-001', teacherName: 'Mr. Musoke Raymond', room: 'Physics Lab 1' },
  { id: 'TT-002', day: 'Monday', period: 'P2 (8:40 - 9:20 AM)', periodIndex: 2, classStream: 'Senior 4 West', subject: 'Physics Practical', teacherId: 'TCH-001', teacherName: 'Mr. Musoke Raymond', room: 'Physics Lab 1' },
  { id: 'TT-003', day: 'Monday', period: 'P3 (9:20 - 10:00 AM)', periodIndex: 3, classStream: 'Senior 4 West', subject: 'Chemistry', teacherId: 'TCH-002', teacherName: 'Mrs. Nabwire Christine', room: 'Chemistry Lab' },
  { id: 'TT-004', day: 'Monday', period: 'P4 (10:30 - 11:10 AM)', periodIndex: 4, classStream: 'Senior 4 West', subject: 'Mathematics', teacherId: 'TCH-001', teacherName: 'Mr. Musoke Raymond', room: 'Room S4W' },
  { id: 'TT-005', day: 'Monday', period: 'P5 (11:10 - 11:50 AM)', periodIndex: 5, classStream: 'Senior 4 West', subject: 'English Language', teacherId: 'TCH-004', teacherName: 'Madam Akite Harriet', room: 'Room S4W' },
  { id: 'TT-006', day: 'Monday', period: 'P7 (2:00 - 2:40 PM)', periodIndex: 7, classStream: 'Senior 4 West', subject: 'ICT / Computer Studies', teacherId: 'TCH-003', teacherName: 'Mr. Kiwanuka Patrick', room: 'ICT Lab 1' },

  // Wednesday S.6 PCM/ICT
  { id: 'TT-007', day: 'Wednesday', period: 'P1 (8:00 - 8:40 AM)', periodIndex: 1, classStream: 'Senior 6 PCM/ICT', subject: 'Advanced Physics P510/1', teacherId: 'TCH-001', teacherName: 'Mr. Musoke Raymond', room: 'Room S6 Science' },
  { id: 'TT-008', day: 'Wednesday', period: 'P2 (8:40 - 9:20 AM)', periodIndex: 2, classStream: 'Senior 6 PCM/ICT', subject: 'Advanced Physics P510/2', teacherId: 'TCH-001', teacherName: 'Mr. Musoke Raymond', room: 'Room S6 Science' },
  { id: 'TT-009', day: 'Wednesday', period: 'P4 (10:30 - 11:10 AM)', periodIndex: 4, classStream: 'Senior 6 PCM/ICT', subject: 'Subsidiary ICT P241/1', teacherId: 'TCH-003', teacherName: 'Mr. Kiwanuka Patrick', room: 'ICT Lab 2' },
  { id: 'TT-010', day: 'Friday', period: 'P3 (9:20 - 10:00 AM)', periodIndex: 3, classStream: 'Senior 4 West', subject: 'English Literature', teacherId: 'TCH-004', teacherName: 'Madam Akite Harriet', room: 'Room S4W' },
];

export const INITIAL_DUTY_ROSTER: DutyRosterItem[] = [
  {
    id: 'DUTY-W01',
    weekNumber: 3,
    startDate: '2026-09-14',
    endDate: '2026-09-20',
    assignedTeachers: [
      { teacherId: 'TCH-001', teacherName: 'Mr. Musoke Raymond', dutyRole: 'Assembly & Morning Prep Warden' },
      { teacherId: 'TCH-004', teacherName: 'Madam Akite Harriet', dutyRole: 'Dining Hall & Meal Inspector' }
    ],
    notes: 'Focus on student punctuality for 7:30 AM morning devotion and evening preps.'
  },
  {
    id: 'DUTY-W02',
    weekNumber: 4,
    startDate: '2026-09-21',
    endDate: '2026-09-27',
    assignedTeachers: [
      { teacherId: 'TCH-002', teacherName: 'Mrs. Nabwire Christine', dutyRole: 'Senior Matron & Dormitory Inspector' },
      { teacherId: 'TCH-003', teacherName: 'Mr. Kiwanuka Patrick', dutyRole: 'ICT Lab Security & Evening Warden' }
    ],
    notes: 'Monitor boarding dormitories check-in at 9:30 PM lights-out.'
  }
];

export const INITIAL_DISCIPLINE_LOGS: DisciplineLogEntry[] = [
  {
    id: 'DISC-001',
    studentId: 'STU-2026-101',
    studentName: 'Kato Paul Mark',
    classStream: 'Senior 4 West',
    date: '2026-09-02',
    type: 'commendation',
    category: 'academics',
    description: 'Awarded Certificate of Excellence for leading the Senior 4 Physics practical project.',
    loggedByTeacherName: 'Mr. Musoke Raymond'
  },
  {
    id: 'DISC-002',
    studentId: 'STU-2026-102',
    studentName: 'Namutebi Brenda',
    classStream: 'Senior 4 West',
    date: '2026-09-05',
    type: 'warning',
    category: 'punctuality',
    description: 'Arrived 15 minutes late for morning 7:30 AM roll call assembly.',
    loggedByTeacherName: 'Madam Akite Harriet'
  },
  {
    id: 'DISC-003',
    studentId: 'STU-2026-103',
    studentName: 'Wasswa Brian',
    classStream: 'Senior 2 East',
    date: '2026-09-07',
    type: 'commendation',
    category: 'uniform',
    description: 'Recognized for outstanding smartness and neat school uniform presentation.',
    loggedByTeacherName: 'Mrs. Nabwire Christine'
  }
];

export const INITIAL_SYLLABI: SubjectSyllabus[] = [
  {
    id: 'SYL-001',
    subject: 'Physics',
    classStream: 'Senior 4 West',
    level: 'O-Level',
    departmentHeadId: 'TCH-001',
    departmentHeadName: 'Mr. Musoke Raymond (HOD Sciences)',
    title: 'UNEB UCE Senior 4 Physics Complete Curriculum (P530)',
    topics: [
      {
        id: 'TP-101',
        topicNumber: 1,
        topicTitle: 'Mechanics: Linear Motion & Newton\'s Laws',
        subtopics: ['Velocity-time graphs', 'Equations of motion', 'Momentum & Impulse'],
        status: 'completed',
        targetDate: '2026-06-15',
        completedDate: '2026-06-12',
        notes: 'Practical experiments completed in Physics Lab 1.'
      },
      {
        id: 'TP-102',
        topicNumber: 2,
        topicTitle: 'Light & Optics: Refraction & Lenses',
        subtopics: ['Snell\'s Law', 'Total Internal Reflection', 'Lens formula & power'],
        status: 'completed',
        targetDate: '2026-07-30',
        completedDate: '2026-07-28',
        notes: 'Ray box practical diagrams verified for all candidates.'
      },
      {
        id: 'TP-103',
        topicNumber: 3,
        topicTitle: 'Electricity & Magnetism: Current & Circuits',
        subtopics: ['Ohm\'s Law', 'Series & Parallel Resistors', 'Electromagnetism'],
        status: 'in_progress',
        targetDate: '2026-09-25',
        notes: 'Currently covering Wheatstone bridge and potentiometer circuits.'
      },
      {
        id: 'TP-104',
        topicNumber: 4,
        topicTitle: 'Modern Physics: Radioactivity & Atomic Structure',
        subtopics: ['Alpha, Beta, Gamma emission', 'Half-life calculations', 'Nuclear energy'],
        status: 'pending',
        targetDate: '2026-10-15',
        notes: 'Scheduled ahead of UNEB briefing on November 6.'
      }
    ]
  },
  {
    id: 'SYL-002',
    subject: 'Chemistry',
    classStream: 'Senior 4 West',
    level: 'O-Level',
    departmentHeadId: 'TCH-002',
    departmentHeadName: 'Mrs. Nabwire Christine (HOD Chemistry)',
    title: 'UNEB UCE Senior 4 Chemistry Practical & Theory Curriculum (P545)',
    topics: [
      {
        id: 'TP-201',
        topicNumber: 1,
        topicTitle: 'Volumetric Analysis (Titration)',
        subtopics: ['Acid-base indicators', 'Molarity & Normality', 'Calculation of percentage purity'],
        status: 'completed',
        targetDate: '2026-07-10',
        completedDate: '2026-07-08',
        notes: 'Titration practical exam scored above 85% average.'
      },
      {
        id: 'TP-202',
        topicNumber: 2,
        topicTitle: 'Qualitative Analysis (Cation & Anion Tests)',
        subtopics: ['Flame tests', 'Precipitate observations', 'Ammonia gas tests'],
        status: 'in_progress',
        targetDate: '2026-09-30',
        notes: 'Qualitative practical work in progress in Chemistry Lab.'
      },
      {
        id: 'TP-203',
        topicNumber: 3,
        topicTitle: 'Organic Chemistry: Hydrocarbons & Alkanols',
        subtopics: ['Alkanes, Alkenes, Alkynes', 'Esterification', 'Polymers & Plastics'],
        status: 'pending',
        targetDate: '2026-10-20'
      }
    ]
  },
  {
    id: 'SYL-003',
    subject: 'ICT / Computer Studies',
    classStream: 'Senior 6 PCM/ICT',
    level: 'A-Level',
    departmentHeadId: 'TCH-003',
    departmentHeadName: 'Mr. Kiwanuka Patrick (Director of ICT)',
    title: 'UACE Subsidiary ICT P241/1 & P241/2 Advanced Curriculum',
    topics: [
      {
        id: 'TP-301',
        topicNumber: 1,
        topicTitle: 'Electronic Spreadsheets: Advanced Formulas & VLOOKUP',
        subtopics: ['Nested IF functions', 'Pivot tables', 'Data validation'],
        status: 'completed',
        targetDate: '2026-05-30',
        completedDate: '2026-05-25'
      },
      {
        id: 'TP-302',
        topicNumber: 2,
        topicTitle: 'Relational Database Management (MS Access)',
        subtopics: ['Primary & Foreign Keys', 'SQL Queries', 'Form & Report Design'],
        status: 'completed',
        targetDate: '2026-07-20',
        completedDate: '2026-07-18'
      },
      {
        id: 'TP-303',
        topicNumber: 3,
        topicTitle: 'Web Design & HTML/CSS Coding',
        subtopics: ['HTML5 semantic elements', 'CSS Flexbox/Grid', 'Publishing web projects'],
        status: 'in_progress',
        targetDate: '2026-09-28',
        notes: 'Candidates building modern responsive school project websites.'
      }
    ]
  }
];

export const INITIAL_TIMETABLE_DOCS: TimetableDocument[] = [
  {
    id: 'TTD-001',
    title: 'Master Weekly Teaching Timetable - Term III 2026',
    classStream: 'All Classes (Master)',
    fileType: 'pdf',
    fileName: 'Master_Weekly_Timetable_Term_III_2026.pdf',
    fileSize: '1.4 MB',
    uploadDate: '2026-09-02',
    uploadedBy: 'Dr. Ssemwanga Ronald (Headteacher)'
  },
  {
    id: 'TTD-002',
    title: 'Senior 4 West Official UNEB Class Schedule',
    classStream: 'Senior 4 West',
    fileType: 'excel',
    fileName: 'Senior_4_West_Class_Timetable.xlsx',
    fileSize: '420 KB',
    uploadDate: '2026-09-04',
    uploadedBy: 'Mr. Musoke Raymond (HOD Physics)'
  },
  {
    id: 'TTD-003',
    title: 'Senior 6 PCM/ICT A-Level Combination Timetable',
    classStream: 'Senior 6 PCM/ICT',
    fileType: 'word',
    fileName: 'Senior_6_PCM_ICT_Schedule.docx',
    fileSize: '310 KB',
    uploadDate: '2026-09-05',
    uploadedBy: 'Mr. Kiwanuka Patrick (HOD ICT)'
  },
  {
    id: 'TTD-004',
    title: 'Senior 1 East Foundation Stream Timetable',
    classStream: 'Senior 1 East',
    fileType: 'pdf',
    fileName: 'Senior_1_East_Weekly_Timetable.pdf',
    fileSize: '890 KB',
    uploadDate: '2026-09-06',
    uploadedBy: 'Mrs. Nabwire Christine (HOD Chemistry)'
  }
];

export const INITIAL_NOTICES: NoticeCircular[] = [
  {
    id: 'NOT-001',
    title: 'Official Term III Re-opening & Reporting Guidelines for Parents',
    publishDate: '2026-09-01',
    targetAudience: 'all',
    category: 'urgent',
    content: 'All boarding scholars are required to report to Thamani Academy, Kakiri, on Tuesday September 15th, 2026 before 4:00 PM. Please bring bank slip receipts for fee clearing at the Bursar\'s office.',
    isPinned: true,
    pdfAttachmentName: 'Term_III_2026_School_Circular.pdf',
    attachmentName: 'Term_III_2026_School_Circular.pdf',
    attachmentType: 'pdf',
    attachmentSize: '1.2 MB'
  },
  {
    id: 'NOT-002',
    title: 'UNEB S.4 (UCE) & S.6 (UACE) Candidate Briefing Schedule',
    publishDate: '2026-09-05',
    targetAudience: 'students',
    category: 'academic',
    content: 'The official UNEB candidate briefing will take place on Friday November 6th in the Main Assembly Hall. All examination candidates must attend in full school uniform.',
    isPinned: true,
    attachmentName: 'UNEB_2026_Candidate_Timetable.xlsx',
    attachmentType: 'excel',
    attachmentSize: '850 KB'
  },
  {
    id: 'NOT-003',
    title: 'Annual Inter-House Sports Gala & Athletics Day Invitation',
    publishDate: '2026-09-08',
    targetAudience: 'parents',
    category: 'general',
    content: 'Parents and guardians are cordially invited to attend our Annual Sports Competition between Crane, Crest, Lion, and Shield Houses on Sunday October 4th.',
    isPinned: false,
    attachmentName: 'Sports_Gala_Programme_2026.docx',
    attachmentType: 'word',
    attachmentSize: '450 KB'
  }
];


