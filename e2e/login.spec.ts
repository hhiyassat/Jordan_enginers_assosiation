import { test, expect } from '@playwright/test';
import { BASE, DEMO, login } from './helpers';

/**
 * Every seeded demo account can log in through the SPA and lands on
 * the right home route for their role. These are the smallest possible
 * end-to-end assertions — they prove the auth loop, the httpOnly-cookie
 * handoff (JORD-30), and the RequireAdmin/RequireApplicant/RequireReviewer
 * gates all agree on where each role belongs.
 *
 * CAPTCHA_ENABLED=false in the web-server env skips the SVG challenge.
 */

test('applicant lands on their dashboard', async ({ page }) => {
  await login(page, DEMO.applicant.email, DEMO.applicant.password);
  await expect(page).toHaveURL(new RegExp(`${BASE}/dashboard`));
  // Applicant-only sidebar item: طلباتي — proves RequireApplicant + the
  // navItemsForRole helper matched the role from /auth/me.
  await expect(page.getByRole('button', { name: /طلباتي/ })).toBeVisible();
});

test('staff lands on the review queue', async ({ page }) => {
  await login(page, DEMO.staff.email, DEMO.staff.password);
  await expect(page).toHaveURL(new RegExp(`${BASE}/review/queue`));
  await expect(page.getByRole('heading', { name: /قائمة المراجعة/ })).toBeVisible();
});

test('admin lands on the admin dashboard with the full nav', async ({ page }) => {
  await login(page, DEMO.admin.email, DEMO.admin.password);
  await expect(page).toHaveURL(new RegExp(`${BASE}/admin`));
  // Admin sees إدارة الخدمات + إدارة المستخدمين per Phase 1's tier system.
  await expect(page.getByRole('button', { name: /إدارة الخدمات/ })).toBeVisible();
  await expect(page.getByRole('button', { name: /إدارة المستخدمين/ })).toBeVisible();
});

test('wrong password stays on /login with an Arabic error', async ({ page }) => {
  await login(page, DEMO.admin.email, 'this-is-wrong');
  await expect(page).toHaveURL(new RegExp(`${BASE}/login`));
  await expect(page.getByText(/بيانات الاعتماد غير صحيحة/)).toBeVisible();
});
