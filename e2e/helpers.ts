import type { Page } from '@playwright/test';

/**
 * Shared login helper for E2E specs.
 *
 * All specs use the seeded demo accounts (see DemoSeeder.php):
 *   admin@demo.esp    · Demo1234!  → admin
 *   staff@demo.esp    · Demo1234!  → staff
 *   auditor@demo.esp  · Demo1234!  → auditor
 *   ahmed@demo.esp    · Demo1234!  → applicant (owns 3 seeded projects)
 *
 * CAPTCHA_ENABLED=false in the web-server env skips the SVG challenge,
 * so any 6-char captcha answer passes. Kept as an argument to
 * `login()` so a future test can prove the captcha path.
 */
export const BASE = 'http://localhost:5173';

export async function login(page: Page, email: string, password: string): Promise<void> {
  await page.goto(BASE + '/login');
  // getByRole('textbox') scopes to inputs; skips the eye-toggle button
  // that would otherwise match a label-substring like "كلمة المرور".
  await page.getByRole('textbox', { name: /البريد الإلكتروني/ }).fill(email);
  await page.getByRole('textbox', { name: /كلمة المرور/ }).fill(password);
  await page.getByRole('textbox', { name: /رمز التحقق/ }).fill('BYPASS');

  // Wait for the login POST response BEFORE clicking — the click races
  // the httpOnly cookie (JORD-30). Post-cookie, any subsequent goto
  // would see /auth/me 401 and RequireAuth would bounce back to /login.
  const [loginResponse] = await Promise.all([
    page.waitForResponse(res => res.url().endsWith('/api/v1/auth/login')),
    page.getByRole('button', { name: /تسجيل الدخول/ }).click(),
  ]);

  // If credentials were wrong the caller usually asserts on the /login
  // error banner — leave without waiting for a redirect that will never
  // happen. Wait for the URL to leave /login in the happy path.
  if (loginResponse.status() === 200) {
    await page.waitForURL(url => !url.pathname.endsWith('/login'), { timeout: 10_000 });
  }
}

/** Convenience — the four seeded demo roles as { email, password } tuples. */
export const DEMO = {
  admin:     { email: 'admin@demo.esp',   password: 'Demo1234!' },
  staff:     { email: 'staff@demo.esp',   password: 'Demo1234!' },
  auditor:   { email: 'auditor@demo.esp', password: 'Demo1234!' },
  applicant: { email: 'ahmed@demo.esp',   password: 'Demo1234!' },
} as const;
