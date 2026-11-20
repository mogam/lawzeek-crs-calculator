import { defineConfig } from '@playwright/test';
import { BASE } from './tests/e2e/wp';

// Makes the WordPress.org screenshots (.wordpress-org/screenshot-N.png). Run: npm run screenshots
export default defineConfig({
  testDir: 'tests/screenshots',
  workers: 1,
  timeout: 180_000,
  expect: { timeout: 15_000 },
  reporter: 'list',
  globalSetup: './tests/e2e/global-setup.ts',
  use: { baseURL: BASE, viewport: { width: 1280, height: 860 }, deviceScaleFactor: 2, browserName: 'chromium' },
});
