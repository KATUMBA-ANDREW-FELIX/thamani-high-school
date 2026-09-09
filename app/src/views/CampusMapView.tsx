import React, { useState } from 'react';
import { Building2, FlaskConical, Laptop, BookOpen, ShieldCheck, Trophy, X } from 'lucide-react';

interface CampusLocation {
  id: string;
  name: string;
  category: string;
  description: string;
  features: string[];
  xPercent: number; // For positioning on map
  yPercent: number;
  icon: any;
}

export const CampusMapView: React.FC = () => {
  const [selectedLocation, setSelectedLocation] = useState<CampusLocation | null>(null);

  const LOCATIONS: CampusLocation[] = [
    {
      id: 'admin',
      name: 'Main Administration Block',
      category: 'Administrative',
      description: 'Houses the Headteacher\'s Executive Office, Admissions Bureau, Finance Registry, and Board Room.',
      features: ['Headteacher\'s Office', 'Bursar & Fee Registry', 'Admissions Desk', 'Conference Suite'],
      xPercent: 48,
      yPercent: 78,
      icon: Building2
    },
    {
      id: 'labs',
      name: 'Science & Innovation Complex',
      category: 'Academics',
      description: 'Modern 3-story block containing dedicated Physics, Chemistry, and Biology practical laboratories.',
      features: ['Physics Practical Lab', 'Chemistry Titration Suite', 'Biology Specimens Room', 'Robotics Innovation Corner'],
      xPercent: 25,
      yPercent: 35,
      icon: FlaskConical
    },
    {
      id: 'ict',
      name: 'Digital ICT & Computing Center',
      category: 'Technology',
      description: 'Fiber-connected computer lab equipped with 100 high-performance workstations for computer studies and coding.',
      features: ['100 Desktop PCs', 'High Speed Fiber Connection', 'Smart Interactive Displays', '3D Printer'],
      xPercent: 72,
      yPercent: 32,
      icon: Laptop
    },
    {
      id: 'library',
      name: 'Main Academic Library',
      category: 'Academics',
      description: 'Spacious quiet study arena holding over 15,000 physical reference volumes and electronic UNEB archives.',
      features: ['15,000+ Textbooks', 'Individual Study Carrels', 'E-Library Kiosks', 'Current Periodicals Desk'],
      xPercent: 50,
      yPercent: 45,
      icon: BookOpen
    },
    {
      id: 'dorm',
      name: 'Boarding Dormitories (Crane & Crest Houses)',
      category: 'Boarding',
      description: 'Secure multi-story residential halls with hot water facilities, resident wardens, and 24/7 security guard post.',
      features: ['Boys & Girls Residential Halls', 'Matron & Warden Quarters', '24/7 CCTV & Guards', 'Solar Water Heating'],
      xPercent: 82,
      yPercent: 70,
      icon: ShieldCheck
    },
    {
      id: 'sports',
      name: 'Thamani Sports Stadium & Courts',
      category: 'Recreation',
      description: 'Standard grass football pitch, basketball court, volleyball arena, and 400m athletics running track.',
      features: ['Full-size Football Pitch', 'Basketball Court', 'Volleyball Arena', '400m Running Track'],
      xPercent: 20,
      yPercent: 75,
      icon: Trophy
    }
  ];

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-10">
      
      {/* Header Banner */}
      <div className="bg-gradient-to-r from-emerald-900 via-emerald-950 to-slate-900 text-white rounded-3xl p-8 sm:p-12 shadow-xl relative overflow-hidden">
        <div className="relative z-10 space-y-3 max-w-3xl">
          <span className="inline-flex items-center gap-2 bg-brand-gold text-slate-950 px-3.5 py-1 rounded-full text-xs font-black uppercase tracking-wider">
            <Building2 className="w-4 h-4" /> Kakiri Campus Navigation
          </span>
          <h1 className="text-3xl sm:text-5xl font-black font-serif">Interactive Campus Map</h1>
          <p className="text-emerald-100 text-sm sm:text-base leading-relaxed">
            Click on any hotspot pin on the 2D layout below to inspect our science laboratories, ICT center, sports stadium, and boarding dormitories.
          </p>
        </div>
      </div>

      {/* 2D Map Container */}
      <div className="bg-white rounded-3xl border border-slate-200 p-6 shadow-lg relative overflow-hidden">
        
        {/* Map Header Controls */}
        <div className="flex flex-wrap justify-between items-center pb-4 mb-6 border-b border-slate-200 gap-4">
          <div>
            <h2 className="text-lg font-black text-slate-900 font-serif">Kakiri Main Campus Layout</h2>
            <p className="text-xs text-slate-500">Wakiso District, Uganda • 35 Acres Campus Plot</p>
          </div>

          <div className="flex flex-wrap gap-2">
            {LOCATIONS.map((loc) => (
              <button
                key={loc.id}
                onClick={() => setSelectedLocation(loc)}
                className="px-3 py-1.5 bg-slate-100 hover:bg-emerald-100 hover:text-brand-green text-slate-700 text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5"
              >
                <loc.icon className="w-3.5 h-3.5" /> {loc.name.split(' ')[0]}
              </button>
            ))}
          </div>
        </div>

        {/* Visual Map Canvas Representation */}
        <div className="relative w-full h-[400px] sm:h-[520px] bg-emerald-950 rounded-2xl overflow-hidden border-2 border-emerald-800 shadow-inner">
          
          {/* Map Background Grid & Roads */}
          <div className="absolute inset-0 opacity-20 bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:24px_24px]"></div>
          
          {/* Decorative Campus Features */}
          {/* Football Field Area */}
          <div className="absolute left-[8%] top-[60%] w-[26%] h-[30%] bg-emerald-800/80 rounded-xl border-2 border-emerald-500/50 flex items-center justify-center text-emerald-300 text-xs font-bold uppercase tracking-wider">
            Sports Field & Track
          </div>

          {/* Academic Block Area */}
          <div className="absolute left-[38%] top-[25%] w-[28%] h-[35%] bg-slate-900/90 rounded-2xl border-2 border-brand-gold/40 flex items-center justify-center text-brand-gold text-xs font-bold uppercase tracking-wider text-center p-2">
            Central Quadrangle & Academic Suites
          </div>

          {/* Hostels Area */}
          <div className="absolute right-[6%] top-[55%] w-[25%] h-[35%] bg-brand-maroon/70 rounded-2xl border-2 border-red-500/40 flex items-center justify-center text-red-200 text-xs font-bold uppercase tracking-wider text-center p-2">
            Boarding Residential Halls
          </div>

          {/* Interactive Hotspot Pins */}
          {LOCATIONS.map((loc) => {
            const Icon = loc.icon;
            const isSelected = selectedLocation?.id === loc.id;
            return (
              <button
                key={loc.id}
                onClick={() => setSelectedLocation(loc)}
                style={{ left: `${loc.xPercent}%`, top: `${loc.yPercent}%` }}
                className={`absolute transform -translate-x-1/2 -translate-y-1/2 group transition-all z-20 ${
                  isSelected ? 'scale-125 z-30' : 'hover:scale-110'
                }`}
              >
                <div className={`relative p-3 rounded-full shadow-2xl flex items-center justify-center transition-all ${
                  isSelected 
                    ? 'bg-brand-gold text-slate-950 ring-4 ring-white' 
                    : 'bg-brand-green text-white border-2 border-brand-gold'
                }`}>
                  <Icon className="w-5 h-5" />
                </div>

                {/* Tooltip Label */}
                <div className="absolute left-1/2 bottom-full mb-2 transform -translate-x-1/2 whitespace-nowrap bg-slate-900 text-white text-[11px] font-bold px-2.5 py-1 rounded-lg shadow-lg opacity-90 group-hover:opacity-100 transition-opacity">
                  {loc.name}
                </div>
              </button>
            );
          })}

        </div>

      </div>

      {/* Selected Location Modal Detail */}
      {selectedLocation && (
        <div className="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 space-y-6 relative animate-in fade-in zoom-in-95 duration-200">
            
            <button
              onClick={() => setSelectedLocation(null)}
              className="absolute top-6 right-6 text-slate-400 hover:text-slate-700 p-1 rounded-full hover:bg-slate-100"
            >
              <X className="w-6 h-6" />
            </button>

            <div className="flex items-center gap-3">
              <div className="w-12 h-12 rounded-2xl bg-brand-green text-white flex items-center justify-center font-bold shadow-md">
                <selectedLocation.icon className="w-6 h-6 text-brand-gold" />
              </div>
              <div>
                <span className="text-xs font-bold text-brand-maroon uppercase tracking-wider">{selectedLocation.category} Facility</span>
                <h3 className="text-xl font-black text-slate-900 font-serif">{selectedLocation.name}</h3>
              </div>
            </div>

            <p className="text-slate-600 text-sm leading-relaxed">
              {selectedLocation.description}
            </p>

            <div className="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
              <h4 className="text-xs font-bold text-slate-700 uppercase">Key Features & Amenities</h4>
              <ul className="grid grid-cols-2 gap-2 text-xs text-slate-700 font-medium">
                {selectedLocation.features.map((feat, i) => (
                  <li key={i} className="flex items-center gap-1.5">
                    <span className="w-1.5 h-1.5 rounded-full bg-brand-green"></span>
                    <span>{feat}</span>
                  </li>
                ))}
              </ul>
            </div>

            <button
              onClick={() => setSelectedLocation(null)}
              className="w-full py-3 bg-brand-green hover:bg-emerald-800 text-white font-bold rounded-xl text-sm transition-colors"
            >
              Close Facility View
            </button>
          </div>
        </div>
      )}

    </div>
  );
};
