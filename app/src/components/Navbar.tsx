import React, { useState } from 'react';
import type { UserRole } from '../types';
import { Menu, X, Shield, GraduationCap, UserCheck, Phone, BookOpen, Calendar, MapPin, Image as ImageIcon, Users, Home } from 'lucide-react';

interface NavbarProps {
  currentRole: UserRole;
  setCurrentRole: (role: UserRole) => void;
  activeView: string;
  setActiveView: (view: string) => void;
  applicantCount: number;
}

export const Navbar: React.FC<NavbarProps> = ({
  currentRole,
  setCurrentRole,
  activeView,
  setActiveView,
  applicantCount
}) => {
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  const publicNavItems = [
    { id: 'home', label: 'Home', icon: Home },
    { id: 'admissions', label: 'Admissions', icon: GraduationCap },
    { id: 'library', label: 'Digital Library', icon: BookOpen },
    { id: 'calendar', label: 'Calendar & Fees', icon: Calendar },
    { id: 'map', label: 'Campus Map', icon: MapPin },
    { id: 'gallery', label: 'Gallery', icon: ImageIcon },
    { id: 'alumni', label: 'Alumni', icon: Users },
  ];

  const handleNavClick = (viewId: string) => {
    setActiveView(viewId);
    setMobileMenuOpen(false);
  };

  return (
    <header className="sticky top-0 z-50 bg-white border-b border-slate-200 shadow-md">
      {/* Top Banner / Announcement Bar */}
      <div className="bg-brand-green text-white text-xs py-2 px-4 flex flex-wrap justify-between items-center border-b border-emerald-900">
        <div className="flex items-center gap-4">
          <span className="flex items-center gap-1.5 font-medium">
            <MapPin className="w-3.5 h-3.5 text-brand-gold" /> Kakiri Campus, Wakiso District, Uganda
          </span>
          <span className="hidden sm:inline-flex items-center gap-1.5">
            <Phone className="w-3.5 h-3.5 text-brand-gold" /> +256 414 123 456
          </span>
        </div>
        <div className="flex items-center gap-3">
          <span className="bg-emerald-900 text-brand-gold px-2.5 py-0.5 rounded-full font-semibold border border-emerald-700/50">
            Admissions Open 2026/2027
          </span>
          <span className="text-emerald-200 font-serif italic hidden md:inline">
            "Crown With Excellence"
          </span>
        </div>
      </div>

      {/* Main Navbar */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center h-20">
          
          {/* Logo Brand */}
          <div className="flex items-center cursor-pointer" onClick={() => handleNavClick('home')}>
            <img 
              src="/thamani-logo.png" 
              alt="Thamani Academy Crest Logo" 
              className="h-12 sm:h-14 w-auto object-contain drop-shadow"
            />
          </div>

          {/* Desktop Public Navigation Links */}
          {currentRole === 'public' && (
            <nav className="hidden lg:flex items-center space-x-1">
              {publicNavItems.map((item) => {
                const Icon = item.icon;
                const isActive = activeView === item.id;
                return (
                  <button
                    key={item.id}
                    onClick={() => handleNavClick(item.id)}
                    className={`flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold transition-all ${
                      isActive 
                        ? 'bg-brand-green text-white shadow' 
                        : 'text-slate-700 hover:bg-slate-100 hover:text-brand-green'
                    }`}
                  >
                    <Icon className={`w-4 h-4 ${isActive ? 'text-brand-gold' : 'text-slate-500'}`} />
                    {item.label}
                  </button>
                );
              })}
            </nav>
          )}

          {/* Role Switcher Toolbar (Passwordless Access) */}
          <div className="hidden sm:flex items-center gap-2 bg-slate-100 p-1.5 rounded-xl border border-slate-200">
            <button
              onClick={() => {
                setCurrentRole('public');
                setActiveView('home');
              }}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
                currentRole === 'public'
                  ? 'bg-white text-slate-900 shadow-sm border border-slate-200'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
              title="View Public Website"
            >
              <Home className="w-3.5 h-3.5 text-emerald-600" />
              Public View
            </button>

            <button
              onClick={() => {
                setCurrentRole('teacher');
                setActiveView('teacher-portal');
              }}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
                currentRole === 'teacher'
                  ? 'bg-brand-maroon text-white shadow-sm'
                  : 'text-slate-600 hover:text-brand-maroon'
              }`}
              title="Access Teacher Portal"
            >
              <UserCheck className="w-3.5 h-3.5 text-brand-gold" />
              Teacher Portal
            </button>

            <button
              onClick={() => {
                setCurrentRole('admin');
                setActiveView('admin-portal');
              }}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all relative ${
                currentRole === 'admin'
                  ? 'bg-brand-green text-white shadow-sm'
                  : 'text-slate-600 hover:text-brand-green'
              }`}
              title="Access Administration Panel"
            >
              <Shield className="w-3.5 h-3.5 text-brand-gold" />
              Admin Panel
              {applicantCount > 0 && (
                <span className="ml-1 bg-amber-500 text-slate-950 font-extrabold text-[10px] px-1.5 py-0.2 rounded-full">
                  {applicantCount}
                </span>
              )}
            </button>
          </div>

          {/* Mobile Menu Toggle Button */}
          <div className="flex sm:hidden items-center gap-2">
            <button
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              className="p-2 rounded-lg text-slate-700 hover:bg-slate-100 focus:outline-none"
            >
              {mobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
            </button>
          </div>
        </div>
      </div>

      {/* Mobile Drawer Menu */}
      {mobileMenuOpen && (
        <div className="sm:hidden border-t border-slate-200 bg-white px-4 pt-3 pb-6 space-y-3 shadow-lg">
          {/* Role Switcher in Mobile */}
          <div className="grid grid-cols-3 gap-1.5 p-1 bg-slate-100 rounded-lg mb-3">
            <button
              onClick={() => {
                setCurrentRole('public');
                setActiveView('home');
                setMobileMenuOpen(false);
              }}
              className={`py-2 text-xs font-bold rounded-md ${currentRole === 'public' ? 'bg-white shadow text-slate-900' : 'text-slate-600'}`}
            >
              Public
            </button>
            <button
              onClick={() => {
                setCurrentRole('teacher');
                setActiveView('teacher-portal');
                setMobileMenuOpen(false);
              }}
              className={`py-2 text-xs font-bold rounded-md ${currentRole === 'teacher' ? 'bg-brand-maroon text-white' : 'text-slate-600'}`}
            >
              Teacher
            </button>
            <button
              onClick={() => {
                setCurrentRole('admin');
                setActiveView('admin-portal');
                setMobileMenuOpen(false);
              }}
              className={`py-2 text-xs font-bold rounded-md ${currentRole === 'admin' ? 'bg-brand-green text-white' : 'text-slate-600'}`}
            >
              Admin
            </button>
          </div>

          {/* Public Nav items in mobile */}
          {currentRole === 'public' && (
            <div className="space-y-1">
              {publicNavItems.map((item) => {
                const Icon = item.icon;
                return (
                  <button
                    key={item.id}
                    onClick={() => handleNavClick(item.id)}
                    className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold text-left ${
                      activeView === item.id 
                        ? 'bg-brand-green text-white' 
                        : 'text-slate-700 hover:bg-slate-100'
                    }`}
                  >
                    <Icon className="w-5 h-5" />
                    {item.label}
                  </button>
                );
              })}
            </div>
          )}
        </div>
      )}
    </header>
  );
};
