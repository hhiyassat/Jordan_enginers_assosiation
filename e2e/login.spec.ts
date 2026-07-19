import { test, expect } from '@playwright/test';

/**
 * Every seeded demo account can log in through the SPA and lands on
 * the right home route for their role. These are the smallest possible
 * end-to-end assertions — they prove the auth loop, sessionStorage
 * token handoff, and the RequireAdmin/RequireApplicant/RequireReviewer
 * gates all agree on where each role belongs.
 *
 * CAPTCHA_ENABLED=false in the web-server env skips the SVG challenge.
 */

const BASE = 'http://127.0.0.1:5173';

async function login(page, email: string, password: string) {
  await page.goto(BASE + '/login');
  // getByRole('textbox') scopes to inputs; skips the eye-toggle button
  // that would otherwise match a label-substring like "كلمة المرور".
  await page.getByRole('textbox', { name: /البريد الإلكتروني/ }).fill(email);
  await page.getByRole('textbox', { name: /كلمة المرور/ }).fill(password);
  // Captcha bypass on the backend means any non-empty 6-char answer passes.
  await page.getByRole('textbox', { name: /رمز التحقق/ }).fill('BYPASS');
  await page.getByRole('button', { name: /تسجيل الدخول/ }).click();
}

test('applicant lands on their dashboard', async ({ page }) => {
  await login(page, 'ahmed@demo.esp', 'Demo1234!');
  await expect(page).toHaveURL(new RegExp(`${BASE}/dashboard`));
  // Applicant-only sidebar item: طلباتي — proves RequireApplicant + the
  // navItemsForRole helper matched the role from /auth/me.
  await expect(page.getByRole('link', { name: /طلباتي/ })).toBeVisible();
});

test('staff lands on the review queue', async ({ page }) => {
  await login(page, 'staff@demo.esp', 'Demo1234!');
  await expect(page).toHaveURL(new RegExp(`${BASE}/review/queue`));
  await expect(page.getByRole('heading', { name: /قائمة المراجعة/ })).toBeVisible();
});

test('admin lands on the admin dashboard with the full nav', async ({ page }) => {
  await login(page, 'admin@demo.esp', 'Demo1234!');
  await expect(page).toHaveURL(new RegExp(`${BASE}/admin`));
  // Admin sees إدارة الخدمات + إدارة المستخدمين per Phase 1's tier system.
  await expect(page.getByRole('link', { name: /إدارة الخدمات/ })).toBeVisible();
  await expect(page.getByRole('link', { name: /إدارة المستخدمين/ })).toBeVisible();
});

test('wrong password stays on /login with an Arabic error', async ({ page }) => {
  await login(page, 'admin@demo.esp', 'this-is-wrong');
  await expect(page).toHaveURL(new RegExp(`${BASE}/login`));
  await expect(page.getByText(/بيانات الاعتماد غير صحيحة/)).toBeVisible();
});
