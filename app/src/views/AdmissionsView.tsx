import React, { useState } from 'react';
import type { Applicant, AcademicLevel } from '../types';
import { GraduationCap, CheckCircle2, Search, UserCheck, AlertCircle, Phone } from 'lucide-react';

interface AdmissionsViewProps {
  onAddApplicant: (applicantData: Omit<Applicant, 'id' | 'refCode' | 'status' | 'appliedDate'>) => Applicant;
  applicants: Applicant[];
}

export const AdmissionsView: React.FC<AdmissionsViewProps> = ({ onAddApplicant, applicants }) => {
  // Form State
  const [academicLevel, setAcademicLevel] = useState<AcademicLevel>('O-Level');
  const [fullName, setFullName] = useState('');
  const [gender, setGender] = useState<'Male' | 'Female'>('Male');
  const [dob, setDob] = useState('');
  const [prevSchool, setPrevSchool] = useState('');
  const [pleAggregates, setPleAggregates] = useState<number>(8);
  const [uceGrades, setUceGrades] = useState('');
  const [selectedCombination, setSelectedCombination] = useState('Senior 1 (General Science & Arts)');
  const [hostelOption, setHostelOption] = useState<'Day Scholar' | 'Boarder'>('Boarder');
  const [guardianName, setGuardianName] = useState('');
  const [guardianPhone, setGuardianPhone] = useState('');
  const [guardianEmail, setGuardianEmail] = useState('');

  // Submission Feedback State
  const [submittedApplicant, setSubmittedApplicant] = useState<Applicant | null>(null);

  // Status Search State
  const [searchRefCode, setSearchRefCode] = useState('');
  const [foundApplicant, setFoundApplicant] = useState<Applicant | null | 'not_found'>(null);

  const handleLevelChange = (level: AcademicLevel) => {
    setAcademicLevel(level);
    if (level === 'O-Level') {
      setSelectedCombination('Senior 1 (General Science & Arts)');
    } else {
      setSelectedCombination('PCM/ICT (Physics, Chemistry, Mathematics, ICT)');
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!fullName || !prevSchool || !guardianName || !guardianPhone) {
      alert('Please fill out all required fields.');
      return;
    }

    const newApplicant = onAddApplicant({
      fullName,
      gender,
      dob: dob || '2010-01-01',
      prevSchool,
      academicLevel,
      pleAggregates: academicLevel === 'O-Level' ? pleAggregates : undefined,
      uceGrades: academicLevel === 'A-Level' ? uceGrades : undefined,
      selectedCombination,
      hostelOption,
      guardianName,
      guardianPhone,
      guardianEmail
    });

    setSubmittedApplicant(newApplicant);
  };

  const handleSearchStatus = (e: React.FormEvent) => {
    e.preventDefault();
    const match = applicants.find(a => a.refCode.toLowerCase().trim() === searchRefCode.toLowerCase().trim());
    if (match) {
      setFoundApplicant(match);
    } else {
      setFoundApplicant('not_found');
    }
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-12">
      
      {/* Header Banner */}
      <div className="bg-gradient-to-r from-emerald-900 via-emerald-950 to-slate-900 text-white rounded-3xl p-8 sm:p-12 shadow-xl relative overflow-hidden">
        <div className="relative z-10 space-y-4 max-w-3xl">
          <span className="inline-flex items-center gap-2 bg-brand-gold text-slate-950 px-3.5 py-1 rounded-full text-xs font-black uppercase tracking-wider">
            <GraduationCap className="w-4 h-4" /> Official 2026/2027 Admissions
          </span>
          <h1 className="text-3xl sm:text-5xl font-black font-serif">Apply For Student Admission</h1>
          <p className="text-emerald-100 text-sm sm:text-base leading-relaxed">
            Join Thamani High School Kakiri. Complete the online cohort registration form below. All applications are directly logged into our admissions review board.
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        {/* Main Application Form (8 Cols) */}
        <div className="lg:col-span-8 space-y-8">
          
          {submittedApplicant ? (
            /* Success Feedback Card */
            <div className="bg-white rounded-3xl border border-emerald-200 p-8 sm:p-10 shadow-lg text-center space-y-6">
              <div className="w-20 h-20 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center mx-auto shadow-inner">
                <CheckCircle2 className="w-10 h-10" />
              </div>

              <div>
                <span className="text-xs font-bold text-brand-maroon uppercase tracking-widest">Application Submitted Successfully</span>
                <h2 className="text-3xl font-black text-slate-900 font-serif mt-1">Welcome to Thamani Cohort 2026!</h2>
              </div>

              <div className="bg-slate-50 border border-slate-200 p-6 rounded-2xl max-w-md mx-auto space-y-2">
                <p className="text-xs text-slate-500 font-medium">Your Official Application Reference Code:</p>
                <div className="text-3xl font-black text-brand-green font-mono tracking-wider bg-white p-3 rounded-xl border border-brand-green/30 shadow-sm">
                  {submittedApplicant.refCode}
                </div>
                <p className="text-xs text-amber-700 font-semibold pt-1">
                  ⚠️ Save this reference code to track your application status.
                </p>
              </div>

              <div className="text-left text-xs text-slate-600 space-y-2 bg-slate-50 p-4 rounded-xl border border-slate-200">
                <p><strong>Applicant Name:</strong> {submittedApplicant.fullName}</p>
                <p><strong>Academic Level:</strong> {submittedApplicant.academicLevel} ({submittedApplicant.selectedCombination})</p>
                <p><strong>Hostel Status:</strong> {submittedApplicant.hostelOption}</p>
                <p><strong>Guardian Contact:</strong> {submittedApplicant.guardianName} ({submittedApplicant.guardianPhone})</p>
              </div>

              <div className="pt-4 flex flex-wrap gap-4 justify-center">
                <button
                  onClick={() => setSubmittedApplicant(null)}
                  className="px-6 py-2.5 bg-brand-green hover:bg-emerald-800 text-white font-bold rounded-xl text-sm transition-colors"
                >
                  Submit Another Application
                </button>
              </div>
            </div>
          ) : (
            /* Registration Form */
            <form onSubmit={handleSubmit} className="bg-white rounded-3xl border border-slate-200 p-8 sm:p-10 shadow-md space-y-8">
              
              <div className="border-b border-slate-200 pb-4">
                <h2 className="text-2xl font-black text-slate-900 font-serif">Cohort Registration Form</h2>
                <p className="text-xs text-slate-500">All information submitted is kept strictly confidential.</p>
              </div>

              {/* Academic Level Toggle */}
              <div className="space-y-3">
                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                  Select Target Academic Division <span className="text-rose-500">*</span>
                </label>
                <div className="grid grid-cols-2 gap-4">
                  <button
                    type="button"
                    onClick={() => handleLevelChange('O-Level')}
                    className={`py-3.5 px-4 rounded-2xl font-bold text-sm border transition-all flex items-center justify-center gap-2 ${
                      academicLevel === 'O-Level'
                        ? 'bg-brand-green text-white border-brand-green shadow-md'
                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'
                    }`}
                  >
                    <GraduationCap className="w-5 h-5 text-brand-gold" /> O-Level (Senior 1 - Senior 4)
                  </button>

                  <button
                    type="button"
                    onClick={() => handleLevelChange('A-Level')}
                    className={`py-3.5 px-4 rounded-2xl font-bold text-sm border transition-all flex items-center justify-center gap-2 ${
                      academicLevel === 'A-Level'
                        ? 'bg-brand-maroon text-white border-brand-maroon shadow-md'
                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'
                    }`}
                  >
                    <GraduationCap className="w-5 h-5 text-brand-gold" /> A-Level STEM & Arts (S.5 - S.6)
                  </button>
                </div>
              </div>

              {/* Personal Details */}
              <div className="space-y-4">
                <h3 className="font-bold text-slate-900 text-sm border-b border-slate-100 pb-2">1. Student Personal Details</h3>
                
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">Full Student Name *</label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. Kiggundu Ivan Mark"
                      value={fullName}
                      onChange={(e) => setFullName(e.target.value)}
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">Gender *</label>
                    <select
                      value={gender}
                      onChange={(e) => setGender(e.target.value as 'Male' | 'Female')}
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
                    >
                      <option value="Male">Male</option>
                      <option value="Female">Female</option>
                    </select>
                  </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">Date of Birth</label>
                    <input
                      type="date"
                      value={dob}
                      onChange={(e) => setDob(e.target.value)}
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">Previous School Attended *</label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. Kampala Junior Academy"
                      value={prevSchool}
                      onChange={(e) => setPrevSchool(e.target.value)}
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
                    />
                  </div>
                </div>
              </div>

              {/* Academic Background & Combination */}
              <div className="space-y-4">
                <h3 className="font-bold text-slate-900 text-sm border-b border-slate-100 pb-2">2. Academic Qualifications & Class Selection</h3>

                {academicLevel === 'O-Level' ? (
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-bold text-slate-700 mb-1">PLE Aggregates (4 - 36)</label>
                      <input
                        type="number"
                        min="4"
                        max="36"
                        value={pleAggregates}
                        onChange={(e) => setPleAggregates(Number(e.target.value))}
                        className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
                      />
                    </div>

                    <div>
                      <label className="block text-xs font-bold text-slate-700 mb-1">Target O-Level Class</label>
                      <select
                        value={selectedCombination}
                        onChange={(e) => setSelectedCombination(e.target.value)}
                        className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
                      >
                        <option value="Senior 1 (General Science & Arts)">Senior 1 (General Curriculum)</option>
                        <option value="Senior 2 Transfer">Senior 2 Transfer</option>
                        <option value="Senior 3 Option Stream">Senior 3 Option Stream</option>
                        <option value="Senior 4 Candidate Transfer">Senior 4 Candidate Transfer</option>
                      </select>
                    </div>
                  </div>
                ) : (
                  <div className="space-y-4">
                    <div>
                      <label className="block text-xs font-bold text-slate-700 mb-1">UCE Grades Summary</label>
                      <input
                        type="text"
                        placeholder="e.g. 8 Aggregates (Physics D1, Chem D1, Bio D2, Math D1)"
                        value={uceGrades}
                        onChange={(e) => setUceGrades(e.target.value)}
                        className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
                      />
                    </div>

                    <div>
                      <label className="block text-xs font-bold text-slate-700 mb-1">Desired A-Level Subject Combination *</label>
                      <select
                        value={selectedCombination}
                        onChange={(e) => setSelectedCombination(e.target.value)}
                        className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none font-semibold text-brand-green"
                      >
                        <option value="PCM/ICT (Physics, Chemistry, Mathematics, ICT)">PCM/ICT (Physics, Chemistry, Mathematics, ICT)</option>
                        <option value="BCM/SubMath (Biology, Chemistry, Mathematics, SubMath)">BCM/SubMath (Biology, Chemistry, Mathematics)</option>
                        <option value="PEM/ICT (Physics, Economics, Mathematics, ICT)">PEM/ICT (Physics, Economics, Mathematics, ICT)</option>
                        <option value="HEG/Div (History, Economics, Geography, Divinity)">HEG/Div (History, Economics, Geography, Divinity)</option>
                        <option value="MEG/ICT (Mathematics, Economics, Geography, ICT)">MEG/ICT (Mathematics, Economics, Geography, ICT)</option>
                        <option value="LEG/Art (Literature, Economics, Geography, Fine Art)">LEG/Art (Literature, Economics, Geography, Fine Art)</option>
                      </select>
                    </div>
                  </div>
                )}

                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">Accommodation Option *</label>
                  <div className="flex gap-6">
                    <label className="flex items-center gap-2 cursor-pointer text-sm font-medium">
                      <input
                        type="radio"
                        name="hostel"
                        checked={hostelOption === 'Boarder'}
                        onChange={() => setHostelOption('Boarder')}
                        className="text-brand-green focus:ring-brand-green"
                      />
                      <span>Boarding Hostel (Recommended)</span>
                    </label>

                    <label className="flex items-center gap-2 cursor-pointer text-sm font-medium">
                      <input
                        type="radio"
                        name="hostel"
                        checked={hostelOption === 'Day Scholar'}
                        onChange={() => setHostelOption('Day Scholar')}
                        className="text-brand-green focus:ring-brand-green"
                      />
                      <span>Day Scholar</span>
                    </label>
                  </div>
                </div>
              </div>

              {/* Guardian Information */}
              <div className="space-y-4">
                <h3 className="font-bold text-slate-900 text-sm border-b border-slate-100 pb-2">3. Parent / Guardian Contact Information</h3>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">Guardian Full Name *</label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. Dr. Charles Kiggundu"
                      value={guardianName}
                      onChange={(e) => setGuardianName(e.target.value)}
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">Guardian Telephone (Mobile Money enabled) *</label>
                    <input
                      type="tel"
                      required
                      placeholder="e.g. +256 772 123 456"
                      value={guardianPhone}
                      onChange={(e) => setGuardianPhone(e.target.value)}
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">Guardian Email Address</label>
                  <input
                    type="email"
                    placeholder="e.g. c.kiggundu@health.go.ug"
                    value={guardianEmail}
                    onChange={(e) => setGuardianEmail(e.target.value)}
                    className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
                  />
                </div>
              </div>

              <div className="pt-4 border-t border-slate-200">
                <button
                  type="submit"
                  className="w-full py-4 bg-brand-green hover:bg-emerald-800 text-white font-black rounded-2xl text-base shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2"
                >
                  <UserCheck className="w-5 h-5 text-brand-gold" /> Submit Application to Admissions Board
                </button>
              </div>

            </form>
          )}

        </div>

        {/* Status Checker Sidebar (4 Cols) */}
        <div className="lg:col-span-4 space-y-6">
          
          <div className="bg-white rounded-3xl border border-slate-200 p-6 shadow-md space-y-4">
            <h3 className="font-bold text-slate-900 text-base flex items-center gap-2 border-b border-slate-100 pb-3">
              <Search className="w-5 h-5 text-brand-maroon" /> Application Status Checker
            </h3>
            
            <p className="text-xs text-slate-600">
              Already applied? Enter your Application Reference Code below to check your admission progress.
            </p>

            <form onSubmit={handleSearchStatus} className="space-y-3">
              <input
                type="text"
                required
                placeholder="e.g. TAK-2026-8921"
                value={searchRefCode}
                onChange={(e) => setSearchRefCode(e.target.value)}
                className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm font-mono focus:ring-2 focus:ring-brand-green outline-none uppercase"
              />

              <button
                type="submit"
                className="w-full py-2.5 bg-brand-maroon hover:bg-red-900 text-white font-bold rounded-xl text-xs transition-colors"
              >
                Lookup Application
              </button>
            </form>

            {/* Found Result Display */}
            {foundApplicant === 'not_found' && (
              <div className="p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-xs flex items-center gap-2">
                <AlertCircle className="w-4 h-4 flex-shrink-0" />
                <span>Reference Code not found. Please check spelling or submit a new application.</span>
              </div>
            )}

            {foundApplicant && foundApplicant !== 'not_found' && (
              <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-xl space-y-2 text-xs text-slate-800">
                <div className="flex justify-between items-center">
                  <span className="font-mono font-bold text-brand-green">{foundApplicant.refCode}</span>
                  <span className={`px-2 py-0.5 rounded-full font-bold uppercase text-[10px] ${
                    foundApplicant.status === 'approved' ? 'bg-emerald-700 text-white' :
                    foundApplicant.status === 'rejected' ? 'bg-rose-700 text-white' :
                    foundApplicant.status === 'interview' ? 'bg-amber-500 text-slate-950' : 'bg-slate-200 text-slate-800'
                  }`}>
                    {foundApplicant.status}
                  </span>
                </div>
                <p><strong>Applicant:</strong> {foundApplicant.fullName}</p>
                <p><strong>Level:</strong> {foundApplicant.academicLevel} ({foundApplicant.selectedCombination})</p>
                {foundApplicant.adminNotes && (
                  <div className="pt-2 border-t border-emerald-200 text-[11px] italic text-emerald-900">
                    "{foundApplicant.adminNotes}"
                  </div>
                )}
              </div>
            )}
          </div>

          <div className="bg-slate-900 text-white rounded-3xl p-6 shadow-md space-y-4">
            <h3 className="font-bold text-white text-base flex items-center gap-2">
              <Phone className="w-4 h-4 text-brand-gold" /> Admissions Help Desk
            </h3>
            <p className="text-xs text-slate-300 leading-relaxed">
              Have questions regarding subject combinations or bursaries? Contact our Kakiri admissions officers directly.
            </p>
            <div className="text-xs text-brand-gold space-y-1 font-semibold">
              <p>• Hotline: +256 414 123 456</p>
              <p>• WhatsApp: +256 772 100 200</p>
              <p>• Email: admissions@thamani.ac.ug</p>
            </div>
          </div>

        </div>

      </div>
    </div>
  );
};
