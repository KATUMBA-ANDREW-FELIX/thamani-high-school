import React, { useState } from 'react';
import type { GalleryItem } from '../types';
import { X, ZoomIn } from 'lucide-react';

interface GalleryViewProps {
  gallery: GalleryItem[];
}

export const GalleryView: React.FC<GalleryViewProps> = ({ gallery }) => {
  const [activeCategory, setActiveCategory] = useState<string>('All');
  const [activeLightboxItem, setActiveLightboxItem] = useState<GalleryItem | null>(null);

  const categories = ['All', 'science', 'sports', 'culture', 'facilities', 'academics'];

  const filteredItems = activeCategory === 'All' 
    ? gallery 
    : gallery.filter(item => item.category === activeCategory);

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-10">
      
      {/* Header Banner */}
      <div className="bg-gradient-to-r from-brand-green to-slate-900 text-white rounded-3xl p-8 sm:p-12 shadow-xl relative overflow-hidden">
        <div className="relative z-10 space-y-3 max-w-3xl">
          <span className="inline-flex items-center gap-2 bg-brand-gold text-slate-950 px-3.5 py-1 rounded-full text-xs font-black uppercase tracking-wider">
            <ZoomIn className="w-4 h-4" /> Campus Achievements & Life
          </span>
          <h1 className="text-3xl sm:text-5xl font-black font-serif">Photo & Video Gallery</h1>
          <p className="text-emerald-100 text-sm sm:text-base leading-relaxed">
            Explore moments of academic victory, science innovations, cultural gala performances, and athletic championships at Thamani High School.
          </p>
        </div>
      </div>

      {/* Category Tabs */}
      <div className="flex flex-wrap gap-2 justify-center">
        {categories.map((cat) => (
          <button
            key={cat}
            onClick={() => setActiveCategory(cat)}
            className={`px-5 py-2.5 rounded-2xl text-xs font-bold capitalize transition-all ${
              activeCategory === cat
                ? 'bg-brand-green text-white shadow-md'
                : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'
            }`}
          >
            {cat}
          </button>
        ))}
      </div>

      {/* Gallery Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        {filteredItems.map((item) => (
          <div 
            key={item.id}
            onClick={() => setActiveLightboxItem(item)}
            className="group bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-xl transition-all cursor-pointer relative"
          >
            <div className="relative h-60 overflow-hidden">
              <img 
                src={item.imageUrl} 
                alt={item.title} 
                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
              />
              <div className="absolute inset-0 bg-slate-950/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                <span className="p-3 bg-brand-gold text-slate-950 rounded-full shadow-lg">
                  <ZoomIn className="w-6 h-6" />
                </span>
              </div>

              <span className="absolute top-3 left-3 bg-slate-950/80 text-brand-gold px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider backdrop-blur">
                {item.category}
              </span>
            </div>

            <div className="p-5 space-y-1.5">
              <h3 className="font-bold text-slate-900 text-base">{item.title}</h3>
              <p className="text-xs text-slate-600 line-clamp-2">{item.caption}</p>
            </div>
          </div>
        ))}
      </div>

      {/* Lightbox Modal */}
      {activeLightboxItem && (
        <div className="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-3xl w-full overflow-hidden shadow-2xl relative animate-in fade-in zoom-in-95 duration-200">
            <button
              onClick={() => setActiveLightboxItem(null)}
              className="absolute top-4 right-4 z-10 text-slate-400 hover:text-white bg-slate-900/60 p-2 rounded-full backdrop-blur"
            >
              <X className="w-6 h-6" />
            </button>

            <img 
              src={activeLightboxItem.imageUrl} 
              alt={activeLightboxItem.title} 
              className="w-full max-h-[480px] object-cover"
            />

            <div className="p-6 bg-slate-900 text-white space-y-2">
              <span className="text-xs font-bold text-brand-gold uppercase tracking-wider">
                {activeLightboxItem.category} Category
              </span>
              <h3 className="text-xl font-bold font-serif">{activeLightboxItem.title}</h3>
              <p className="text-xs text-slate-300 leading-relaxed">{activeLightboxItem.caption}</p>
            </div>
          </div>
        </div>
      )}

    </div>
  );
};
