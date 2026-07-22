import { test, expect } from '@playwright/test';
import { DEMO, login } from './helpers';

/**
 * JORD-35 + JORD-39: paginated + searchable admin listing behaviour.
 * The search box debounces at 300ms so we wait past that threshold
 * before asserting the filtered result set.
 */

test.describe('Admin applications list', () => {
  test('search + per-page + prev/next controls are visible', async ({ page }) => {
    await login(page, DEMO.admin.email, DEMO.admin.password);
    await page.goto('/admin/applications');

    await expect(page.getByRole('heading', { name: /إدارة الطلبات/ })).toBeVisible();
    // Search input.
    const search = page.getByRole('searchbox', { name: /بحث في الطلبات/ });
    await expect(search).toBeVisible();
    // Per-page selector.
    await expect(page.getByRole('combobox', { name: /عدد النتائج/ })).toBeVisible();
    // Prev / next buttons (aria-labels come from common.previousPage /
    // common.nextPage in the i18n bundle).
    await expect(page.getByRole('button', { name: /الصفحة السابقة/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /الصفحة التالية/ })).toBeVisible();
  });

  test('typing in the search box debounces + narrows the table', async ({ page }) => {
    await login(page, DEMO.admin.email, DEMO.admin.password);
    await page.goto('/admin/applications');

    const search = page.getByRole('searchbox', { name: /بحث في الطلبات/ });
    // Type a value unlikely to match anything in the seeded fixture set.
    await search.fill('zzzzzzz-no-match');
    // Wait past the 300ms debounce + one network tick.
    await page.waitForTimeout(600);
    await expect(page.getByText(/لا توجد نتائج مطابقة/)).toBeVisible();
  });
});
