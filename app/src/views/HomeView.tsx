import React from 'react';
import type { NewsArticle, CalendarEvent } from '../types';
import { GraduationCap, Award, BookOpen, Users, ArrowRight, CheckCircle2, Calendar, ShieldCheck, MapPin } from 'lucide-react';

interface HomeViewProps {
  news: NewsArticle[];
  events: CalendarEvent[];
  setActiveView: (view: string) => void;
}

export const HomeView: React.FC<HomeViewProps> = ({ news, events, setActiveView }) => {
  return (
    <div className="space-y-16 pb-16">
      {/* Hero Section */}
      <section className="relative bg-gradient-to-br from-brand-green via-emerald-950 to-slate-900 text-white overflow-hidden py-20 lg:py-28">
        {/* Subtle grid pattern background */}
        <div className="absolute inset-0 opacity-10 bg-[radial-gradient(#D4AF37_1px,transparent_1px)] [background-size:16px_16px]"></div>

        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
            
            {/* Left Content */}
            <div className="lg:col-span-7 space-y-6 text-center lg:text-left">
              <div className="inline-flex items-center gap-2 bg-emerald-900/90 text-brand-gold px-4 py-1.5 rounded-full text-xs sm:text-sm font-bold border border-brand-gold/40 shadow-lg">
                <GraduationCap className="w-4 h-4 text-brand-gold" />
                Premier Secondary Education in Wakiso District
              </div>
              
              <h1 className="text-4xl sm:text-6xl font-black font-serif tracking-tight leading-tight">
                Crown With <span className="text-brand-gold italic underline decoration-brand-maroon decoration-4">Excellence</span>
              </h1>
              
              <p className="text-lg sm:text-xl text-slate-200 font-light leading-relaxed max-w-2xl">
                Welcome to <strong className="text-white font-semibold">Thamani High School (Kakiri)</strong>. We nurture visionary leaders equipped with holistic academic rigor, STEM skills, and grounded Ugandan values.
              </p>

              {/* Call to action buttons */}
              <div className="pt-4 flex flex-wrap justify-center lg:justify-start gap-4">
                <button
                  onClick={() => setActiveView('admissions')}
                  className="px-8 py-3.5 bg-brand-gold hover:bg-amber-400 text-slate-950 font-black rounded-xl shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all flex items-center gap-2 text-base"
                >
                  <GraduationCap className="w-5 h-5" /> Enroll for 2026/2027 Cohort
                </button>

                <button
                  onClick={() => setActiveView('map')}
                  className="px-8 py-3.5 bg-white/10 hover:bg-white/20 text-white font-bold rounded-xl border border-white/30 backdrop-blur transition-all flex items-center gap-2 text-base"
                >
                  <MapPin className="w-5 h-5 text-brand-gold" /> Explore Kakiri Campus 2D
                </button>
              </div>

              {/* Badges */}
              <div className="pt-6 border-t border-emerald-800/80 grid grid-cols-3 gap-4 text-center lg:text-left">
                <div>
                  <div className="text-2xl sm:text-3xl font-black text-brand-gold">98.4%</div>
                  <div className="text-xs text-emerald-200 font-medium">UACE Direct Pass Rate</div>
                </div>
                <div>
                  <div className="text-2xl sm:text-3xl font-black text-brand-gold">1,200+</div>
                  <div className="text-xs text-emerald-200 font-medium">Enrolled Scholars</div>
                </div>
                <div>
                  <div className="text-2xl sm:text-3xl font-black text-brand-gold">60+</div>
                  <div className="text-xs text-emerald-200 font-medium">Certified Educators</div>
                </div>
              </div>
            </div>

            {/* Right Card with Official Crest */}
            <div className="lg:col-span-5 flex justify-center">
              <div className="relative glass-panel bg-white/95 p-8 rounded-3xl shadow-2xl border border-white/40 max-w-md w-full text-slate-900 text-center space-y-6 transform hover:scale-[1.01] transition-transform">
                <div className="absolute -top-4 -right-4 bg-brand-maroon text-brand-gold text-xs font-black px-4 py-1.5 rounded-full shadow-lg border border-brand-gold">
                  UNEB Center U0892
                </div>

                <img 
                  src="/thamani-logo.png" 
                  alt="Thamani High School Crest Logo" 
                  className="h-44 mx-auto object-contain drop-shadow-md"
                />

                <div>
                  <h2 className="text-2xl font-black text-brand-green font-serif">THAMANI HIGH SCHOOL</h2>
                  <p className="text-xs font-bold text-brand-maroon tracking-widest uppercase mt-1">
                    Kakiri Town Council • Wakiso
                  </p>
                </div>

                <div className="bg-slate-50 p-4 rounded-2xl border border-slate-200 text-left space-y-2.5 text-xs font-medium text-slate-700">
                  <div className="flex items-center gap-2">
                    <CheckCircle2 className="w-4 h-4 text-emerald-600 flex-shrink-0" />
                    <span>Lower Secondary NCDC Competency Curriculum</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <CheckCircle2 className="w-4 h-4 text-emerald-600 flex-shrink-0" />
                    <span>A-Level Science & Arts Combinations (PCM, BCM, HEG)</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <CheckCircle2 className="w-4 h-4 text-emerald-600 flex-shrink-0" />
                    <span>100-Seat High Speed Fiber ICT Hub</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <CheckCircle2 className="w-4 h-4 text-emerald-600 flex-shrink-0" />
                    <span>Modern Boarding Dormitories & Athletic Pitch</span>
                  </div>
                </div>

                <button
                  onClick={() => setActiveView('calendar')}
                  className="w-full py-3 bg-brand-green hover:bg-emerald-800 text-white font-bold rounded-xl text-sm transition-colors flex items-center justify-center gap-2"
                >
                  View Term Fees & Calendar <ArrowRight className="w-4 h-4" />
                </button>
              </div>
            </div>

          </div>
        </div>
      </section>

      {/* Headteacher Welcome Address */}
      <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="bg-white rounded-3xl shadow-lg border border-slate-200 p-8 sm:p-12">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
            
            <div className="lg:col-span-4 text-center">
              <div className="relative inline-block">
                <div className="w-48 h-48 mx-auto rounded-full bg-emerald-100 p-1 shadow-md border-4 border-brand-green overflow-hidden">
                  <img 
                    src="https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=600&q=80" 
                    alt="Dr. Ssemwanga Ronald - Headteacher" 
                    className="w-full h-full object-cover"
                  />
                </div>
                <span className="absolute bottom-2 right-2 bg-brand-gold text-slate-950 p-2 rounded-full shadow-lg border border-white">
                  <Award className="w-5 h-5" />
                </span>
              </div>
              <h3 className="text-xl font-bold text-slate-900 mt-4">Dr. Ssemwanga Ronald</h3>
              <p className="text-xs font-bold text-brand-maroon uppercase">Headteacher & Chief Administrator</p>
              <p className="text-xs text-slate-500 mt-1">Ph.D. Educational Leadership (Makerere)</p>
            </div>

            <div className="lg:col-span-8 space-y-4 text-slate-700">
              <span className="text-xs font-black tracking-widest text-brand-green uppercase bg-emerald-50 px-3 py-1 rounded-full">
                Headteacher's Welcome Message
              </span>
              <h2 className="text-3xl font-black text-slate-900 font-serif leading-tight">
                "Nurturing Academic Greatness and Unwavering Integrity"
              </h2>
              <p className="text-slate-600 leading-relaxed text-sm sm:text-base">
                On behalf of the Board of Governors, teaching staff, and student body, it is my distinct honor to welcome you to Thamani High School, Kakiri. We believe that true education goes beyond textbooks—it encompasses moral fortitude, technical mastery, and leadership preparedness.
              </p>
              <p className="text-slate-600 leading-relaxed text-sm sm:text-base">
                With state-of-the-art science laboratories, a fiber-powered ICT innovation hub, and dedicated sports facilities, we offer our scholars an environment where talent is refined into national leadership.
              </p>

              <div className="pt-2 flex flex-wrap gap-4">
                <button
                  onClick={() => setActiveView('admissions')}
                  className="px-6 py-2.5 bg-brand-green hover:bg-emerald-800 text-white font-bold rounded-xl text-sm transition-colors flex items-center gap-2"
                >
                  Apply For Next Cohort <ArrowRight className="w-4 h-4 text-brand-gold" />
                </button>
              </div>
            </div>

          </div>
        </div>
      </section>

      {/* Core Features Grid */}
      <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        <div className="text-center space-y-2">
          <h2 className="text-3xl font-black text-slate-900 font-serif">Why Choose Thamani High School?</h2>
          <p className="text-slate-600 text-sm max-w-xl mx-auto">
            Discover the key pillars that position Thamani High School among Wakiso's most prestigious academic centers.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          
          <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow space-y-4">
            <div className="w-12 h-12 rounded-xl bg-emerald-100 text-brand-green flex items-center justify-center font-bold">
              <BookOpen className="w-6 h-6" />
            </div>
            <h3 className="font-bold text-lg text-slate-900">Digital Learning Library</h3>
            <p className="text-slate-600 text-xs leading-relaxed">
              Full access to UNEB past papers, e-books, and teacher revision notes available 24/7 for all students.
            </p>
            <button
              onClick={() => setActiveView('library')}
              className="text-xs font-bold text-brand-maroon hover:underline flex items-center gap-1"
            >
              Access Library <ArrowRight className="w-3.5 h-3.5" />
            </button>
          </div>

          <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow space-y-4">
            <div className="w-12 h-12 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
              <GraduationCap className="w-6 h-6" />
            </div>
            <h3 className="font-bold text-lg text-slate-900">STEM & Arts Excellence</h3>
            <p className="text-slate-600 text-xs leading-relaxed">
              Specialized coaching for PCM, BCM, and Arts subject combinations with 100% lab practical exposure.
            </p>
            <button
              onClick={() => setActiveView('admissions')}
              className="text-xs font-bold text-brand-maroon hover:underline flex items-center gap-1"
            >
              Explore Combinations <ArrowRight className="w-3.5 h-3.5" />
            </button>
          </div>

          <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow space-y-4">
            <div className="w-12 h-12 rounded-xl bg-rose-100 text-brand-maroon flex items-center justify-center font-bold">
              <ShieldCheck className="w-6 h-6" />
            </div>
            <h3 className="font-bold text-lg text-slate-900">Secure Boarding Campus</h3>
            <p className="text-slate-600 text-xs leading-relaxed">
              Modern dormitories with 24/7 security personnel, resident matrons, and nutritious meal plans.
            </p>
            <button
              onClick={() => setActiveView('calendar')}
              className="text-xs font-bold text-brand-maroon hover:underline flex items-center gap-1"
            >
              Boarding Details <ArrowRight className="w-3.5 h-3.5" />
            </button>
          </div>

          <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow space-y-4">
            <div className="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-800 flex items-center justify-center font-bold">
              <Users className="w-6 h-6" />
            </div>
            <h3 className="font-bold text-lg text-slate-900">Active Alumni Network</h3>
            <p className="text-slate-600 text-xs leading-relaxed">
              Connecting our graduates with university bursaries, career mentorship, and professional networking.
            </p>
            <button
              onClick={() => setActiveView('alumni')}
              className="text-xs font-bold text-brand-maroon hover:underline flex items-center gap-1"
            >
              Join Network <ArrowRight className="w-3.5 h-3.5" />
            </button>
          </div>

        </div>
      </section>

      {/* Latest News & Upcoming Events */}
      <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
          
          {/* News Stream (8 cols) */}
          <div className="lg:col-span-8 space-y-6">
            <div className="flex justify-between items-center border-b border-slate-200 pb-3">
              <h2 className="text-2xl font-black text-slate-900 font-serif">Latest School News</h2>
              <span className="text-xs font-bold text-brand-green">Official Updates</span>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
              {news.map((item) => (
                <div key={item.id} className="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                  <img 
                    src={item.imageUrl} 
                    alt={item.title} 
                    className="w-full h-44 object-cover"
                  />
                  <div className="p-5 space-y-3">
                    <div className="flex justify-between items-center text-xs text-slate-500">
                      <span className="bg-emerald-100 text-brand-green font-bold px-2 py-0.5 rounded-full">{item.category}</span>
                      <span>{item.date}</span>
                    </div>
                    <h3 className="font-bold text-slate-900 text-base line-clamp-2 leading-snug">{item.title}</h3>
                    <p className="text-slate-600 text-xs line-clamp-2">{item.summary}</p>
                    <div className="pt-1 text-xs text-slate-400 font-medium">By {item.author}</div>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Upcoming Events Calendar Widget (4 cols) */}
          <div className="lg:col-span-4 space-y-6">
            <div className="flex justify-between items-center border-b border-slate-200 pb-3">
              <h2 className="text-2xl font-black text-slate-900 font-serif flex items-center gap-2">
                <Calendar className="w-5 h-5 text-brand-maroon" /> Events Schedule
              </h2>
            </div>

            <div className="bg-white rounded-2xl border border-slate-200 p-5 space-y-4 shadow-sm">
              {events.map((evt) => (
                <div key={evt.id} className="flex gap-4 items-start p-3 rounded-xl hover:bg-slate-50 border border-slate-100 transition-colors">
                  <div className="bg-brand-green text-white rounded-xl p-2.5 text-center min-w-[60px] shadow-sm">
                    <span className="block text-xs font-bold uppercase">{new Date(evt.date).toLocaleString('default', { month: 'short' })}</span>
                    <span className="block text-xl font-black text-brand-gold">{new Date(evt.date).getDate()}</span>
                  </div>
                  <div className="space-y-1">
                    <h4 className="font-bold text-slate-900 text-xs leading-snug">{evt.title}</h4>
                    <p className="text-[11px] text-slate-500 flex items-center gap-1">
                      <MapPin className="w-3 h-3 text-slate-400" /> {evt.location}
                    </p>
                    <p className="text-[11px] text-slate-600 line-clamp-2">{evt.description}</p>
                  </div>
                </div>
              ))}

              <button
                onClick={() => setActiveView('calendar')}
                className="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold rounded-xl text-xs transition-colors"
              >
                View Complete Academic Term Calendar
              </button>
            </div>
          </div>

        </div>
      </section>
    </div>
  );
};
