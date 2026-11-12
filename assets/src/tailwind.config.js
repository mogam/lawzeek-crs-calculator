/**
 * Tailwind CSS build for the plugin's admin screens.
 * Made safe for wp-admin: every class starts with "lz-", every rule only applies inside .lzcrs-app,
 * and Tailwind's global reset (preflight) is off, so WordPress's own styles are untouched.
 * Build: npm run wp:css:free (from the repository root).
 *
 * Palette: warm paper neutrals (cream, beige) with an indigo accent and a mint "live" green.
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
        paper: { DEFAULT: '#fbf8f3', 2: '#f6f0e6' },
        beige: { 100: '#f4e9da', 200: '#ead9c3' },
        clay: '#8b5a44',
        indigo: { 50: '#eef0fd', 100: '#e0e3fb', 200: '#c7ccf7', 500: '#6366f1', 600: '#5457e5', 700: '#4338ca', 900: '#262a7a' },
        mint: { 50: '#ecf6ef', 100: '#d7eedd', 500: '#22c55e', 700: '#15803d' },
        lilac: '#eef1f8',
        ink: { DEFAULT: '#231d18', muted: '#6b6259', line: '#e9e2d7', soft: '#f6f1e9' },
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
        card: '0 1px 2px rgba(35,29,24,.04), 0 2px 8px rgba(35,29,24,.04)',
        lift: '0 12px 32px -12px rgba(67,56,202,.28)',
        ring: '0 0 0 4px rgba(99,102,241,.14)',
      },
    },
  },
};
