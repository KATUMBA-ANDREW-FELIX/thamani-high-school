/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.{html,php,js}",
    "./calendar_docs/**/*.{html,php,js}",
    "./gallery_images/**/*.{html,php,js}",
    "./library/**/*.{html,php,js}"
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          slate: '#1F2937',
          charcoal: '#111827',
          gold: '#D4AF37',
          darkGold: '#B8860B',
          lightGold: '#FEF3C7',
          lightGrey: '#F3F4F6',
          green: '#1F2937',
          darkGreen: '#111827',
          lightGreen: '#F3F4F6',
          maroon: '#800000',
          lightMaroon: '#FDF2F2'
        }
      }
    },
  },
  plugins: [],
}
