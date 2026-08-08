module.exports = {
  content: [
    './frontend/src/index.html',
    './frontend/src/js/**/*.js',
    './plugins/**/*.js',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['IBM Plex Sans', 'system-ui', 'sans-serif'],
        mono: ['IBM Plex Mono', 'ui-monospace', 'monospace'],
      },
      colors: {
        brand: {
          50: '#eef8ff',
          100: '#d7edff',
          200: '#afd8ff',
          300: '#7dbdff',
          400: '#489bff',
          500: '#1e79ff',
          600: '#125fdc',
          700: '#124daf',
          800: '#154389',
          900: '#173c72',
        },
        slateui: {
          950: '#0b1424',
        },
      },
      boxShadow: {
        panel: '0 16px 40px rgba(11, 20, 36, 0.08)',
        float: '0 20px 48px rgba(11, 20, 36, 0.16)',
      },
    },
  },
  plugins: [],
};