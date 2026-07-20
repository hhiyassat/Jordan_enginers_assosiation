import { test, expect } from '@playwright/test';
import { BASE, DEMO, login } from './helpers';

/**
 * JORD-10 + JORD-39: the profile page round-trip. Header avatar
 * clicks land on /profile, the form saves name + phone, the header
 * refreshes with the new name (JORD-10's login-refresh path).
 */

test.describe('Profile page', () => {
  test('applicant edits their name and the header avatar refreshes', async ({ page }) => {
    await login(page, DEMO.applicant.email, DEMO.applicant.password);
    await expect(page).toHaveURL(new RegExp(`${BASE}/dashboard`));

    // Click the header avatar chip — a Link to /profile.
    await page.getByRole('link', { name: /الملف الشخصي|المستخدم:/ }).click();
    await expect(page).toHaveURL(new RegExp(`${BASE}/profile`));

    // The name input carries the seeded value.
    const nameInput = page.getByRole('textbox', { name: /^الاسم$/ });
    await expect(nameInput).toBeVisible();

    // Edit + save.
    await nameInput.fill('أحمد المقدم — E2E');
    await page.getByRole('button', { name: /حفظ التغييرات/ }).click();
    // Success banner from profile.savedBanner.
    await expect(page.getByText(/تم حفظ بياناتك/)).toBeVisible();

    // Header avatar carries the first character of the new name — the
    // AuthContext.login() refresh path (JORD-10) makes this immediate,
    // no page reload needed. Scope to the top nav (role=banner) since
    // the profile page has its own <header> section too.
    await expect(page.getByRole('banner').getByRole('link', { name: /المستخدم:/ })).toContainText('أ');
  });

  test('email field is disabled on the profile page', async ({ page }) => {
    await login(page, DEMO.applicant.email, DEMO.applicant.password);
    await page.goto(BASE + '/profile');
    await expect(page.getByRole('textbox', { name: /البريد الإلكتروني/ })).toBeDisabled();
    // Locked hint below the field.
    await expect(page.getByText(/يجب التواصل مع الإدارة/)).toBeVisible();
  });
});
