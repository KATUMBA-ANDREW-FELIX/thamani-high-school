import React, { useState } from 'react';
import type { CalendarEvent } from '../types';
import { Calendar, Calculator, Clock } from 'lucide-react';

interface CalendarFeesViewProps {
  events: CalendarEvent[];
}

export const CalendarFeesView: React.FC<CalendarFeesViewProps> = ({ events }) => {
  // Fee Calculator State
  const [calcLevel, setCalcLevel] = useState<'O-Level' | 'A-Level'>('O-Level');
  const [calcHostel, setCalcHostel] = useState<'Day' | 'Boarding'>('Boarding');
  const [includeTransport, setIncludeTransport] = useState(false);
  const [includeUniformKit, setIncludeUniformKit] = useState(true);

  // Fee calculation math in UGX
  const baseTuition = calcLevel === 'O-Level' 
    ? (calcHostel === 'Boarding' ? 1450000 : 1100000) 
    : (calcHostel === 'Boarding' ? 1650000 : 1250000);
  
  const transportFee = includeTransport ? 250000 : 0;
  const uniformFee = includeUniformKit ? 350000 : 0;
  const totalEstimatedFees = baseTuition + transportFee + uniformFee;

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-12">
      
      {/* Header Banner */}
      <div className="bg-gradient-to-r from-emerald-900 via-emerald-950 to-slate-900 text-white rounded-3xl p-8 sm:p-12 shadow-xl relative overflow-hidden">
        <div className="relative z-10 space-y-3 max-w-3xl">
          <span className="inline-flex items-center gap-2 bg-brand-gold text-slate-950 px-3.5 py-1 rounded-full text-xs font-black uppercase tracking-wider">
            <Calculator className="w-4 h-4" /> Academic Financial Transparency
          </span>
          <h1 className="text-3xl sm:text-5xl font-black font-serif">Academic Calendar & Tuition Fees</h1>
          <p className="text-emerald-100 text-sm sm:text-base leading-relaxed">
            Review term schedule key dates and use our interactive fee estimator to calculate school fees and boarding fees in Ugandan Shillings (UGX).
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        {/* Fee Structure & Interactive Calculator (7 cols) */}
        <div className="lg:col-span-7 space-y-8">
          
          <div className="bg-white rounded-3xl border border-slate-200 p-8 shadow-md space-y-6">
            <div className="border-b border-slate-200 pb-3 flex justify-between items-center">
              <div>
                <h2 className="text-2xl font-black text-slate-900 font-serif flex items-center gap-2">
                  <Calculator className="w-6 h-6 text-brand-maroon" /> Tuition Fee Estimator
                </h2>
                <p className="text-xs text-slate-500">Official Termly Fee Structure for 2026/2027 Academic Year</p>
              </div>
              <span className="bg-emerald-100 text-brand-green font-bold text-xs px-3 py-1 rounded-full">UGX Currency</span>
            </div>

            {/* Interactive Calculator Inputs */}
            <div className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-700 uppercase mb-1">Academic Level</label>
                <div className="grid grid-cols-2 gap-3">
                  <button
                    type="button"
                    onClick={() => setCalcLevel('O-Level')}
                    className={`py-2.5 px-4 rounded-xl text-xs font-bold border transition-all ${
                      calcLevel === 'O-Level' ? 'bg-brand-green text-white border-brand-green shadow-sm' : 'bg-slate-50 text-slate-700 border-slate-200'
                    }`}
                  >
                    O-Level (S.1 - S.4)
                  </button>

                  <button
                    type="button"
                    onClick={() => setCalcLevel('A-Level')}
                    className={`py-2.5 px-4 rounded-xl text-xs font-bold border transition-all ${
                      calcLevel === 'A-Level' ? 'bg-brand-maroon text-white border-brand-maroon shadow-sm' : 'bg-slate-50 text-slate-700 border-slate-200'
                    }`}
                  >
                    A-Level (S.5 - S.6 STEM & Arts)
                  </button>
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 uppercase mb-1">Residence Status</label>
                <div className="grid grid-cols-2 gap-3">
                  <button
                    type="button"
                    onClick={() => setCalcHostel('Boarding')}
                    className={`py-2.5 px-4 rounded-xl text-xs font-bold border transition-all ${
                      calcHostel === 'Boarding' ? 'bg-brand-green text-white border-brand-green shadow-sm' : 'bg-slate-50 text-slate-700 border-slate-200'
                    }`}
                  >
                    Full Boarding Hostel
                  </button>

                  <button
                    type="button"
                    onClick={() => setCalcHostel('Day')}
                    className={`py-2.5 px-4 rounded-xl text-xs font-bold border transition-all ${
                      calcHostel === 'Day' ? 'bg-brand-green text-white border-brand-green shadow-sm' : 'bg-slate-50 text-slate-700 border-slate-200'
                    }`}
                  >
                    Day Scholar
                  </button>
                </div>
              </div>

              {/* Add-ons Checkboxes */}
              <div className="pt-2 space-y-2 text-xs font-medium text-slate-700">
                <label className="flex items-center gap-2 cursor-pointer p-2.5 bg-slate-50 rounded-xl border border-slate-200">
                  <input
                    type="checkbox"
                    checked={includeUniformKit}
                    onChange={(e) => setIncludeUniformKit(e.target.checked)}
                    className="text-brand-green focus:ring-brand-green rounded"
                  />
                  <span>Full Uniform & Physical Sports Kit (One-off for New Entrants) (+ UGX 350,000)</span>
                </label>

                {calcHostel === 'Day' && (
                  <label className="flex items-center gap-2 cursor-pointer p-2.5 bg-slate-50 rounded-xl border border-slate-200">
                    <input
                      type="checkbox"
                      checked={includeTransport}
                      onChange={(e) => setIncludeTransport(e.target.checked)}
                      className="text-brand-green focus:ring-brand-green rounded"
                    />
                    <span>Daily Kakiri-Wakiso School Bus Transport Pass (+ UGX 250,000)</span>
                  </label>
                )}
              </div>
            </div>

            {/* Total Estimated Fee Output */}
            <div className="bg-slate-900 text-white p-6 rounded-2xl border border-brand-gold/40 space-y-3">
              <div className="flex justify-between items-center text-xs text-slate-300">
                <span>Fee Summary Breakdown ({calcLevel} • {calcHostel})</span>
                <span className="text-brand-gold font-bold">Termly Payment Plan</span>
              </div>

              <div className="text-3xl sm:text-4xl font-black text-brand-gold font-mono">
                UGX {totalEstimatedFees.toLocaleString()}
              </div>

              <div className="text-xs text-slate-300 space-y-1 pt-2 border-t border-slate-800">
                <div className="flex justify-between">
                  <span>Base Tuition & Accommodation:</span>
                  <span>UGX {baseTuition.toLocaleString()}</span>
                </div>
                {includeUniformKit && (
                  <div className="flex justify-between">
                    <span>New Student Uniform Package:</span>
                    <span>UGX {uniformFee.toLocaleString()}</span>
                  </div>
                )}
                {includeTransport && calcHostel === 'Day' && (
                  <div className="flex justify-between">
                    <span>Daily Bus Transport Pass:</span>
                    <span>UGX {transportFee.toLocaleString()}</span>
                  </div>
                )}
              </div>
            </div>

            {/* Payment Options Guidelines */}
            <div className="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-2 text-xs text-slate-700">
              <h4 className="font-bold text-slate-900 uppercase">Official Payment Gateways</h4>
              <p>• <strong>Stanbic Bank Uganda:</strong> A/C No. 9030012345678 (Thamani Academy Kakiri)</p>
              <p>• <strong>MTN Mobile Money Pay Code:</strong> *165*3*4040# (Ref: Student Index / Ref Code)</p>
              <p>• <strong>Airtel Money Merchant ID:</strong> 606090</p>
            </div>

          </div>

        </div>

        {/* Academic Calendar Events Timeline (5 cols) */}
        <div className="lg:col-span-5 space-y-6">
          
          <div className="bg-white rounded-3xl border border-slate-200 p-6 shadow-md space-y-6">
            <div className="border-b border-slate-200 pb-3">
              <h2 className="text-2xl font-black text-slate-900 font-serif flex items-center gap-2">
                <Calendar className="w-6 h-6 text-brand-green" /> Term III Key Schedule
              </h2>
              <p className="text-xs text-slate-500">Official UNEB & School Events Timeline</p>
            </div>

            <div className="space-y-4">
              {events.map((evt) => (
                <div key={evt.id} className="relative pl-6 border-l-2 border-brand-green space-y-1">
                  <div className="absolute -left-[9px] top-0 w-4 h-4 rounded-full bg-brand-gold border-2 border-white"></div>
                  
                  <div className="flex justify-between items-center text-xs">
                    <span className="font-bold text-brand-green">{evt.date}</span>
                    <span className="bg-slate-100 text-slate-700 px-2 py-0.5 rounded-full capitalize text-[10px] font-semibold">
                      {evt.category}
                    </span>
                  </div>

                  <h4 className="font-bold text-slate-900 text-sm">{evt.title}</h4>
                  <p className="text-xs text-slate-500 flex items-center gap-1">
                    <Clock className="w-3.5 h-3.5 text-slate-400" /> {evt.location}
                  </p>
                  <p className="text-xs text-slate-600 leading-relaxed">{evt.description}</p>
                </div>
              ))}
            </div>

          </div>

        </div>

      </div>
    </div>
  );
};
