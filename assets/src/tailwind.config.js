/**
 * Tailwind CSS build for the plugin's admin screens.
 * Made safe for wp-admin: every class starts with "lz-", every rule only applies inside .lzcrs-app,
 * and Tailwind's global reset (preflight) is off, so WordPress's own styles are untouched.
 * Build: npm run build:css (from the repository root).
 *
 * Look: crisp product UI. Cool blue-grey canvas, white cards with hairline borders and soft layered
 * shadows, deep navy text, the Lawzeek purple as the accent, and a purple → pink → orange gradient
 * for highlights. System fonts only (no downloads).
 */
module.exports = {
  content: ['./includes/**/*.php'],
  prefix: 'lz-',
  important: '.lzcrs-app',
  corePlugins: { preflight: false },
  theme: {
    extend: {
      colors: {
        paper: { DEFAULT: '#f6f9fc', 2: '#eef3f8' },
        // Lawzeek purple #7473ed; 600/700 are darker so white text on buttons meets WCAG AA.
        indigo: { 50: '#f4f3ff', 100: '#e8e7fd', 200: '#d2d0fb', 500: '#7473ed', 600: '#5a58d6', 700: '#4644b8', 900: '#27265f' },
        sun: { 50: '#fff8e8', 400: '#f5bc41' },
        mint: { 50: '#e6f7ef', 100: '#c9eedb', 500: '#1ea672', 700: '#0e7a50' },
        ink: { DEFAULT: '#0a2540', muted: '#425466', faint: '#8792a2', line: '#e3e8ee', soft: '#f6f9fc' },
        hot: { bg: '#ffe9ee', fg: '#c0123c' },
        warm: { bg: '#fff3e0', fg: '#9c5400' },
        cold: { bg: '#eef2f6', fg: '#425466' },
      },
      fontFamily: {
        sans: ['"Google Sans"', 'Inter', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', '"Helvetica Neue"', 'sans-serif'],
        mono: ['ui-monospace', '"SF Mono"', 'Menlo', 'Consolas', 'monospace'],
      },
      boxShadow: {
        card: '0 0 0 1px rgba(10,37,64,.03), 0 1px 2px rgba(10,37,64,.05), 0 6px 16px -10px rgba(10,37,64,.14)',
        lift: '0 30px 60px -12px rgba(50,50,93,.22), 0 18px 36px -18px rgba(0,0,0,.24)',
        ring: '0 0 0 4px rgba(116,115,237,.18)',
      },
      backgroundImage: {
        brand: 'linear-gradient(90deg, #7473ed 0%, #b86ee0 45%, #f27ca6 75%, #fca46a 100%)',
      },
    },
  },
};
