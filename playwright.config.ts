import { defineConfig, devices } from '@playwright/test';

/**
 * ESP v2 end-to-end config.
 *
 * The tests exercise the full stack: Laravel (php artisan serve on
 * :8002) + Vite dev server (:5173) + a fresh SQLite DB seeded per
 * suite. Everything runs in a single Chromium project — the
 * multi-browser matrix is a follow-up once we've stabilised on the
 * shape of the specs.
 *
 * Captcha bypass: the config launches Laravel with CAPTCHA_ENABLED=false
 * so login flows can post without solving the SVG challenge. This
 * relies on the existing bypass hook in VerifyCaptcha::handle() +
 * config/esp.php — not an E2E-specific back door.
 *
 * File uploads: tests pin small PDF/DWG fixtures under e2e/fixtures/
 * that pass the PdfOrDwgFile magic-bytes check.
 */
export default defineConfig({
  testDir: './e2e',
  timeout: 30_000,
  expect: { timeout: 5_000 },
  fullyParallel: false, // tests share DB state, must run serially
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: process.env.CI ? [['github'], ['list']] : 'list',

  use: {
    baseURL: 'http://localhost:5173',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    locale: 'ar-JO',
    // Bearer-token auth uses sessionStorage per tab — each test gets a
    // fresh context so tokens don't leak across specs.
    storageState: undefined,
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  // Boot both servers before running the suite. globalSetup then does
  // the migrate:fresh --seed once. Each server gets ~30s to come up.
  webServer: [
    {
      // Bind Laravel to localhost (the default) so Vite's proxy target
      // http://localhost:8002 resolves cleanly. Explicitly pinning to
      // 127.0.0.1 broke on IPv6-preferring hosts (GitHub runners resolve
      // `localhost` to ::1 first; --host=127.0.0.1 would bind IPv4 only).
      command: 'cd backend && APP_ENV=testing CAPTCHA_ENABLED=false php artisan serve --port=8002',
      // /up is Laravel's built-in health route (see bootstrap/app.php).
      // Do NOT use /api/v1/captcha here — it's rate-limited at 30/min,
      // and Playwright's poll-until-200 loop would burn through the
      // bucket in ~30 seconds and never see a 2xx again.
      url: 'http://localhost:8002/up',
      reuseExistingServer: !process.env.CI,
      timeout: 60_000,
      stdout: 'pipe',
      stderr: 'pipe',
    },
    {
      command: 'cd frontend && npm run dev -- --port 5173',
      url: 'http://localhost:5173',
      reuseExistingServer: !process.env.CI,
      timeout: 60_000,
      stdout: 'pipe',
      stderr: 'pipe',
    },
  ],

  globalSetup: './e2e/global-setup.ts',
});
