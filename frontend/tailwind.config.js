/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        // Legacy alias — points at JEA primary so any lingering `bg-navy` still fits the palette.
        navy: '#1A77BC',
        jea: {
          primary:    '#1A77BC',
          topbar:     '#0F5A99',
          topbarDeep: '#0B4A7A',
          hover:      '#115F9C',
          bg:         '#EEF7FC',
          accent:     '#DCF0FA',
          accent2:    '#C8E4F5',
          border:     '#BDD9EF',
          text:       '#1e2c3a',
          muted:      '#4a6a85',
          danger:     '#F44336',
        },
      },
      fontFamily: {
        sans: ['Cairo', 'Segoe UI', 'Arial', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
