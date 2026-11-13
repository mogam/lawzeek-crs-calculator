/**
 * Tailwind CSS build for the plugin's admin screens.
 * Made safe for wp-admin: every class starts with "lz-", every rule only applies inside .lzcrs-app,
 * and Tailwind's global reset (preflight) is off, so WordPress's own styles are untouched.
 * Build: npm run wp:css:free (from the repository root).
 *
 * Palette from the Lawzeek logo: dark brown #2a2219, cream #f7f4ed, purple #7473ed, yellow #f5bc41,
 * plus a mint "live" green for status.
 * Fonts are system fonts only (no downloads): a serif for big headings, the system sans for UI.
 */
module.exports = {
  content: ['./includes/**/*.php'],
  prefix: 'lz-',
  important: '.lzcrs-app',
  corePlugins: { preflight: false },
  theme: {
    extend: {
      colors: {
        paper: { DEFAULT: '#faf8f3', 2: '#f7f4ed' },
        beige: { 100: '#f4e9da', 200: '#ead9c3' },
        clay: '#8b5a44',
        // Logo purple #7473ed; 600/700 are darker so white text on buttons meets WCAG AA.
        indigo: { 50: '#f0f0fd', 100: '#e3e3fb', 200: '#cbcaf7', 500: '#7473ed', 600: '#5a58d6', 700: '#4644b8', 900: '#27265f' },
        sun: { 50: '#fff8e8', 400: '#f5bc41' },
        mint: { 50: '#ecf6ef', 100: '#d7eedd', 500: '#22c55e', 700: '#15803d' },
        lilac: '#eef1f8',
        ink: { DEFAULT: '#2a2219', muted: '#6b6156', line: '#e9e3d8', soft: '#f5f1e8' },
        hot: { bg: '#fde8ea', fg: '#b4232f' },
        warm: { bg: '#fff1d9', fg: '#8a5000' },
        cold: { bg: '#ecebf3', fg: '#4b4a66' },
      },
      fontFamily: {
        sans: ['-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Inter', 'Roboto', '"Helvetica Neue"', 'sans-serif'],
        serif: ['"Iowan Old Style"', '"Palatino Linotype"', 'Palatino', 'Georgia', 'ui-serif', 'serif'],
        mono: ['ui-monospace', '"SF Mono"', 'Menlo', 'Consolas', 'monospace'],
      },
      boxShadow: {
        card: '0 1px 2px rgba(42,34,25,.04), 0 2px 8px rgba(42,34,25,.04)',
        lift: '0 12px 32px -12px rgba(70,68,184,.28)',
        ring: '0 0 0 4px rgba(116,115,237,.16)',
      },
    },
  },
};
