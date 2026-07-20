import { test, expect } from '@playwright/test';
import { DEMO, login } from './helpers';

/**
 * JORD-5/38 + JORD-39: language switcher flips the whole shell
 * from Arabic to English and back. Pinning this at the E2E layer
 * catches regressions where a page-body retrofit gets undone —
 * unit-level snapshot tests only cover component-level slices,
 * this one asserts the full flow.
 */

test.describe('LanguageSwitcher', () => {
  test('flips shell + sidebar labels to English on click, and back', async ({ page }) => {
    await login(page, DEMO.applicant.email, DEMO.applicant.password);

    // Applicant sees "طلباتي" in the sidebar by default.
    await expect(page.getByRole('button', { name: /طلباتي/ })).toBeVisible();

    // Click the compact language chip. Arabic → EN chip is shown as "EN".
    await page.locator('header').getByRole('button', { name: /Switch to English|التبديل إلى الإنجليزية/ }).click();

    // Sidebar label flips.
    await expect(page.getByRole('button', { name: /My Requests/ })).toBeVisible();
    // <html dir> follows.
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');

    // Flip back to Arabic — chip is now "ع".
    await page.locator('header').getByRole('button', { name: /Switch to Arabic|التبديل إلى العربية/ }).click();
    await expect(page.getByRole('button', { name: /طلباتي/ })).toBeVisible();
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  });
});
