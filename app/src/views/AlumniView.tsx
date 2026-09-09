import React, { useState } from 'react';
import { Award, Send, CheckCircle2 } from 'lucide-react';

export const AlumniView: React.FC = () => {
  const [name, setName] = useState('');
  const [completionYear, setCompletionYear] = useState('2023');
  const [currentRole, setCurrentRole] = useState('');
  const [phone, setPhone] = useState('');
  const [email, setEmail] = useState('');
  const [submitted, setSubmitted] = useState(false);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name || !phone) {
      alert('Please fill out your name and contact phone.');
      return;
    }
    setSubmitted(true);
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-12">
      
      {/* Header Banner */}
      <div className="bg-gradient-to-r from-brand-maroon via-slate-900 to-brand-green text-white rounded-3xl p-8 sm:p-12 shadow-xl relative overflow-hidden">
        <div className="relative z-10 space-y-3 max-w-3xl">
          <span className="inline-flex items-center gap-2 bg-brand-gold text-slate-950 px-3.5 py-1 rounded-full text-xs font-black uppercase tracking-wider">
            <Award className="w-4 h-4" /> Thamani Old Students Association (TOSA)
          </span>
          <h1 className="text-3xl sm:text-5xl font-black font-serif">Alumni Network & Guild</h1>
          <p className="text-slate-200 text-sm sm:text-base leading-relaxed">
            Reconnecting thousands of Thamani High School graduates across Makerere, Kyambogo, international universities, and professional sectors worldwide.
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        {/* Alumni Registration Form (7 cols) */}
        <div className="lg:col-span-7">
          <div className="bg-white rounded-3xl border border-slate-200 p-8 shadow-md space-y-6">
            <div className="border-b border-slate-200 pb-3">
              <h2 className="text-2xl font-black text-slate-900 font-serif">Register with TOSA Alumni Directory</h2>
              <p className="text-xs text-slate-500">Stay connected for mentorship programs, reunions, and bursary networking.</p>
            </div>

            {submitted ? (
              <div className="p-8 bg-emerald-50 border border-emerald-200 rounded-2xl text-center space-y-3">
                <CheckCircle2 className="w-12 h-12 text-emerald-600 mx-auto" />
                <h3 className="text-xl font-bold text-slate-900 font-serif">Registration Recorded!</h3>
                <p className="text-xs text-slate-600">
                  Thank you, <strong>{name}</strong> (Class of {completionYear}). You have been added to the Thamani Old Students Network.
                </p>
                <button
                  onClick={() => setSubmitted(false)}
                  className="px-6 py-2 bg-brand-green text-white font-bold text-xs rounded-xl"
                >
                  Register Another Member
                </button>
              </div>
            ) : (
              <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">Full Name *</label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. Eng. Mukasa Arthur"
                      value={name}
                      onChange={(e) => setName(e.target.value)}
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">Year of UACE/UCE Completion</label>
                    <select
                      value={completionYear}
                      onChange={(e) => setCompletionYear(e.target.value)}
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
                    >
                      <option value="2025">2025</option>
                      <option value="2024">2024</option>
                      <option value="2023">2023</option>
                      <option value="2022">2022</option>
                      <option value="2021">2021</option>
                      <option value="2020">2020 & Earlier</option>
                    </select>
                  </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">Current University / Profession *</label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. Makerere University - Civil Eng."
                      value={currentRole}
                      onChange={(e) => setCurrentRole(e.target.value)}
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">Phone Contact *</label>
                    <input
                      type="tel"
                      required
                      placeholder="+256 700 000 000"
                      value={phone}
                      onChange={(e) => setPhone(e.target.value)}
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">Email Address</label>
                  <input
                    type="email"
                    placeholder="name@gmail.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    className="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
                  />
                </div>

                <button
                  type="submit"
                  className="w-full py-3.5 bg-brand-maroon hover:bg-red-900 text-white font-bold rounded-xl text-sm transition-colors flex items-center justify-center gap-2"
                >
                  <Send className="w-4 h-4 text-brand-gold" /> Register with Alumni Network
                </button>
              </form>
            )}
          </div>
        </div>

        {/* Alumni Spotlights (5 cols) */}
        <div className="lg:col-span-5 space-y-6">
          <div className="bg-slate-900 text-white rounded-3xl p-6 shadow-md space-y-4">
            <h3 className="font-bold text-white text-lg flex items-center gap-2 border-b border-slate-800 pb-3 font-serif">
              <Award className="w-5 h-5 text-brand-gold" /> Alumni Spotlight & Impact
            </h3>

            <div className="space-y-4 text-xs">
              <div className="bg-slate-800 p-4 rounded-2xl border border-slate-700 space-y-1">
                <span className="text-brand-gold font-bold">Dr. Kaberuka Sheila (Class of 2019)</span>
                <p className="text-slate-300 font-medium">Head Resident Medical Officer - Mulago National Hospital</p>
                <p className="text-slate-400 italic text-[11px]">"The rigorous chemistry practicals at Thamani laid the bedrock for my medical career."</p>
              </div>

              <div className="bg-slate-800 p-4 rounded-2xl border border-slate-700 space-y-1">
                <span className="text-brand-gold font-bold">Eng. Kintu Moses (Class of 2021)</span>
                <p className="text-slate-300 font-medium">Software Systems Architect - Silicon Valley Tech</p>
                <p className="text-slate-400 italic text-[11px]">"Thamani's ICT hub gave me my first exposure to computer programming in Senior 2."</p>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  );
};
