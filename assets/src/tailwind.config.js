/**
 * Tailwind CSS build for the plugin's admin screens.
 * Made safe for wp-admin: every class starts with "lz-", every rule only applies inside .lzcrs-app,
 * and Tailwind's global reset (preflight) is off, so WordPress's own styles are untouched.
 * Build: npm run wp:css:free (from the repository root).
 */
module.exports = {
  content: ['./includes/**/*.php'],
  prefix: 'lz-',
  important: '.lzcrs-app',
  corePlugins: { preflight: false },
  theme: {
    extend: {
      colors: {
        brand: { 50: '#eef6f8', 100: '#d5e8ed', 600: '#14637a', 700: '#0f4c5c', 900: '#0a2f39' },
        coral: { 50: '#fff1f3', 500: '#ff5a6e', 600: '#e33a55' },
        ink: { DEFAULT: '#13222a', muted: '#586a73', line: '#e3e9ec', soft: '#f6f8f9' },
        hot: { bg: '#fde6ea', fg: '#b0102a' },
        warm: { bg: '#fff1d9', fg: '#8a5000' },
        cold: { bg: '#e8eef1', fg: '#40525b' },
      },
      fontFamily: { sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'sans-serif'] },
      boxShadow: { card: '0 1px 2px rgba(16,24,40,.05), 0 1px 3px rgba(16,24,40,.06)' },
    },
  },
};
