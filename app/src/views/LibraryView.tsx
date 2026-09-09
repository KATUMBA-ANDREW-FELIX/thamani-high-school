import React, { useState } from 'react';
import type { LibraryResource, AcademicLevel } from '../types';
import { BookOpen, Search, Download, Sparkles } from 'lucide-react';

interface LibraryViewProps {
  resources: LibraryResource[];
  onIncrementDownload: (id: string) => void;
}

export const LibraryView: React.FC<LibraryViewProps> = ({ resources, onIncrementDownload }) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedLevel, setSelectedLevel] = useState<AcademicLevel | 'All'>('All');
  const [selectedType, setSelectedType] = useState<string>('All');

  const filteredResources = resources.filter(res => {
    const matchesSearch = res.title.toLowerCase().includes(searchTerm.toLowerCase()) || 
                          res.subject.toLowerCase().includes(searchTerm.toLowerCase()) ||
                          res.author.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesLevel = selectedLevel === 'All' || res.level === selectedLevel;
    const matchesType = selectedType === 'All' || res.type === selectedType;
    return matchesSearch && matchesLevel && matchesType;
  });

  const handleDownload = (id: string, title: string) => {
    onIncrementDownload(id);
    alert(`Downloading "${title}"...\nFile saved to your local device.`);
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-10">
      
      {/* Header Banner */}
      <div className="bg-gradient-to-r from-brand-green via-emerald-900 to-slate-900 text-white rounded-3xl p-8 sm:p-12 shadow-xl relative overflow-hidden">
        <div className="relative z-10 space-y-3 max-w-3xl">
          <span className="inline-flex items-center gap-2 bg-brand-gold text-slate-950 px-3.5 py-1 rounded-full text-xs font-black uppercase tracking-wider">
            <Sparkles className="w-4 h-4" /> UNEB Digital E-Learning Hub
          </span>
          <h1 className="text-3xl sm:text-5xl font-black font-serif">Digital Study Resource Library</h1>
          <p className="text-emerald-100 text-sm sm:text-base leading-relaxed">
            Search and download official UNEB past examination papers, teachers' revision summaries, e-textbooks, and syllabus outlines for O-Level & A-Level.
          </p>
        </div>
      </div>

      {/* Search & Filter Toolbar */}
      <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
          
          {/* Search Box (6 cols) */}
          <div className="md:col-span-6 relative">
            <Search className="w-5 h-5 text-slate-400 absolute left-4 top-3.5" />
            <input
              type="text"
              placeholder="Search by title, subject (e.g. Physics, Math, Chemistry)..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
            />
          </div>

          {/* Level Filter (3 cols) */}
          <div className="md:col-span-3">
            <select
              value={selectedLevel}
              onChange={(e) => setSelectedLevel(e.target.value as any)}
              className="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
            >
              <option value="All">All Academic Levels</option>
              <option value="O-Level">O-Level (S.1 - S.4)</option>
              <option value="A-Level">A-Level (S.5 - S.6)</option>
            </select>
          </div>

          {/* Type Filter (3 cols) */}
          <div className="md:col-span-3">
            <select
              value={selectedType}
              onChange={(e) => setSelectedType(e.target.value)}
              className="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-green outline-none"
            >
              <option value="All">All Resource Types</option>
              <option value="past_paper">UNEB Past Papers</option>
              <option value="revision_notes">Revision Notes</option>
              <option value="textbook">Textbooks</option>
              <option value="syllabus">Syllabus Outlines</option>
            </select>
          </div>

        </div>
      </div>

      {/* Resources Cards Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {filteredResources.map((res) => (
          <div 
            key={res.id}
            className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-all flex flex-col justify-between space-y-4"
          >
            <div className="space-y-3">
              <div className="flex justify-between items-center text-xs">
                <span className={`px-2.5 py-0.5 rounded-full font-bold uppercase ${
                  res.level === 'A-Level' ? 'bg-brand-maroon text-white' : 'bg-brand-green text-white'
                }`}>
                  {res.level}
                </span>

                <span className="bg-slate-100 text-slate-700 font-semibold px-2.5 py-0.5 rounded-full capitalize">
                  {res.type.replace('_', ' ')}
                </span>
              </div>

              <h3 className="font-bold text-slate-900 text-base leading-snug">{res.title}</h3>
              
              <div className="text-xs text-slate-500 space-y-1 font-medium">
                <p><strong>Subject:</strong> <span className="text-brand-green font-bold">{res.subject}</span></p>
                <p><strong>Author/Publisher:</strong> {res.author}</p>
                <p><strong>Year:</strong> {res.year} • <strong>Size:</strong> {res.fileSize}</p>
              </div>
            </div>

            <div className="pt-3 border-t border-slate-100 flex items-center justify-between">
              <span className="text-xs text-slate-400 font-medium">
                {res.downloads} downloads
              </span>

              <button
                onClick={() => handleDownload(res.id, res.title)}
                className="px-4 py-2 bg-brand-green hover:bg-emerald-800 text-white font-bold rounded-xl text-xs transition-colors flex items-center gap-1.5 shadow-sm"
              >
                <Download className="w-4 h-4 text-brand-gold" /> Download Document
              </button>
            </div>
          </div>
        ))}
      </div>

      {filteredResources.length === 0 && (
        <div className="bg-white rounded-3xl border border-slate-200 p-12 text-center text-slate-500 space-y-3">
          <BookOpen className="w-12 h-12 text-slate-300 mx-auto" />
          <h3 className="text-lg font-bold text-slate-800">No matching library resources found</h3>
          <p className="text-xs">Try adjusting your search terms or filter selection.</p>
        </div>
      )}

    </div>
  );
};
