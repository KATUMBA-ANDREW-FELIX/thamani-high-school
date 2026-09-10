import React from 'react';
import { MapPin, Phone, Mail, Globe, Award, Shield, Share2, MessageCircle } from 'lucide-react';

interface FooterProps {
  setActiveView: (view: string) => void;
}

export const Footer: React.FC<FooterProps> = ({ setActiveView }) => {
  return (
    <footer className="bg-brand-green text-white pt-16 pb-12 border-t-4 border-brand-gold">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-10">
          
          {/* Col 1: Brand & Logo */}
          <div className="space-y-4 md:col-span-1">
            <div className="flex items-center gap-3">
              <img 
                src="/thamani-logo.png" 
                alt="Thamani Academy Logo" 
                className="h-16 w-auto bg-white p-1.5 rounded-xl shadow-md"
              />
              <div>
                <h3 className="font-serif font-black text-lg text-white">THAMANI ACADEMY</h3>
                <p className="text-xs text-brand-gold font-bold uppercase tracking-wider">KAKIRI • UGANDA</p>
              </div>
            </div>
            <p className="text-emerald-100 text-sm leading-relaxed">
              Empowering the next generation of Ugandan leaders through academic excellence, innovation, Christian principles, and holistic character building.
            </p>
            <div className="pt-2">
              <span className="inline-flex items-center gap-2 bg-emerald-900/80 px-3 py-1.5 rounded-lg text-xs font-serif italic text-brand-gold border border-emerald-700">
                <Award className="w-4 h-4 text-brand-gold" /> "Crown With Excellence"
              </span>
            </div>
          </div>

          {/* Col 2: Quick Links */}
          <div>
            <h4 className="font-bold text-white uppercase tracking-wider text-sm mb-4 border-b border-emerald-800 pb-2">
              Quick Navigation
            </h4>
            <ul className="space-y-2.5 text-sm text-emerald-100">
              <li>
                <button onClick={() => setActiveView('admissions')} className="hover:text-brand-gold transition-colors">
                  • Student Admissions 2026/2027
                </button>
              </li>
              <li>
                <button onClick={() => setActiveView('library')} className="hover:text-brand-gold transition-colors">
                  • UNEB Digital Resource Library
                </button>
              </li>
              <li>
                <button onClick={() => setActiveView('calendar')} className="hover:text-brand-gold transition-colors">
                  • Term Schedule & Fee Calculator
                </button>
              </li>
              <li>
                <button onClick={() => setActiveView('map')} className="hover:text-brand-gold transition-colors">
                  • Kakiri Campus 2D Tour
                </button>
              </li>
              <li>
                <button onClick={() => setActiveView('gallery')} className="hover:text-brand-gold transition-colors">
                  • Photo Gallery & Campus Life
                </button>
              </li>
              <li>
                <button onClick={() => setActiveView('alumni')} className="hover:text-brand-gold transition-colors">
                  • Old Students Alumni Association
                </button>
              </li>
            </ul>
          </div>

          {/* Col 3: Academic Programs */}
          <div>
            <h4 className="font-bold text-white uppercase tracking-wider text-sm mb-4 border-b border-emerald-800 pb-2">
              Academic Divisions
            </h4>
            <ul className="space-y-2 text-sm text-emerald-100">
              <li className="font-semibold text-brand-gold">O-Level Division (S.1 - S.4)</li>
              <li className="text-xs text-emerald-200 pl-2">• Lower Secondary Competency-Based Curriculum</li>
              <li className="text-xs text-emerald-200 pl-2">• UNEB UCE Examination Center No. U0892</li>
              
              <li className="font-semibold text-brand-gold pt-2">A-Level STEM & Arts (S.5 - S.6)</li>
              <li className="text-xs text-emerald-200 pl-2">• STEM: PCM/ICT, BCM/SubMath, PEM/ICT</li>
              <li className="text-xs text-emerald-200 pl-2">• Arts & Humanities: HEG/Div, MEG/ICT, LEG/Art</li>
              <li className="text-xs text-emerald-200 pl-2">• UNEB UACE Examination Center</li>
            </ul>
          </div>

          {/* Col 4: Contact & Location */}
          <div className="space-y-3">
            <h4 className="font-bold text-white uppercase tracking-wider text-sm mb-4 border-b border-emerald-800 pb-2">
              Contact & Location
            </h4>
            <div className="text-sm text-emerald-100 space-y-2">
              <p className="flex items-start gap-2.5">
                <MapPin className="w-5 h-5 text-brand-gold flex-shrink-0 mt-0.5" />
                <span>Plot 45, Education Road, Kakiri Town Council, Wakiso District, Uganda</span>
              </p>
              <p className="flex items-center gap-2.5">
                <Phone className="w-4 h-4 text-brand-gold flex-shrink-0" />
                <span>+256 414 123 456 / +256 772 100 200</span>
              </p>
              <p className="flex items-center gap-2.5">
                <Mail className="w-4 h-4 text-brand-gold flex-shrink-0" />
                <span>info@thamani.ac.ug / admissions@thamani.ac.ug</span>
              </p>
              <p className="flex items-center gap-2.5">
                <Globe className="w-4 h-4 text-brand-gold flex-shrink-0" />
                <span>www.thamani.ac.ug</span>
              </p>
            </div>
            
            <div className="pt-2 flex items-center gap-3">
              <span className="text-xs text-emerald-200">Connect with us:</span>
              <div className="flex gap-2">
                <span className="p-1.5 bg-emerald-900 rounded-full text-brand-gold hover:bg-emerald-800 cursor-pointer">
                  <Globe className="w-4 h-4" />
                </span>
                <span className="p-1.5 bg-emerald-900 rounded-full text-brand-gold hover:bg-emerald-800 cursor-pointer">
                  <Share2 className="w-4 h-4" />
                </span>
                <span className="p-1.5 bg-emerald-900 rounded-full text-brand-gold hover:bg-emerald-800 cursor-pointer">
                  <MessageCircle className="w-4 h-4" />
                </span>
              </div>
            </div>
          </div>

        </div>

        <div className="mt-12 pt-6 border-t border-emerald-900/80 text-center text-xs text-emerald-300 flex flex-col sm:flex-row justify-between items-center gap-4">
          <p>© {new Date().getFullYear()} Thamani Academy - Kakiri (TAK). All rights reserved.</p>
          <div className="flex items-center gap-4 text-emerald-200">
            <span className="flex items-center gap-1">
              <Shield className="w-3.5 h-3.5 text-brand-gold" /> UNEB Registered Center
            </span>
            <span>•</span>
            <span>Ministry of Education & Sports Approved</span>
          </div>
        </div>
      </div>
    </footer>
  );
};
