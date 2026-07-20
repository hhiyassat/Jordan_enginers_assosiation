import { test, expect } from '@playwright/test';
import { DEMO, login } from './helpers';

/**
 * JORD-11 + JORD-39: the admin dashboard renders stat tiles, the
 * recent applications section, the by-status breakdown, and the
 * quick-action links.
 */

test.describe('Admin dashboard', () => {
  test('renders stats + recent applications + by-status', async ({ page }) => {
    await login(page, DEMO.admin.email, DEMO.admin.password);

    // Stat tiles.
    await expect(page.getByText(/إجمالي الطلبات/)).toBeVisible();
    await expect(page.getByText(/في انتظار المراجعة/)).toBeVisible();
    await expect(page.getByText(/الخدمات النشطة/)).toBeVisible();

    // JORD-11 additions.
    await expect(page.getByText(/أحدث الطلبات/)).toBeVisible();
    await expect(page.getByText(/الطلبات حسب الحالة/)).toBeVisible();

    // Quick actions block.
    await expect(page.getByText(/إجراءات سريعة/)).toBeVisible();
    await expect(page.getByRole('link', { name: /قائمة المراجعة/ })).toBeVisible();
  });

  test('admin can navigate to the paginated applications list', async ({ page }) => {
    await login(page, DEMO.admin.email, DEMO.admin.password);
    // Any of the "View all" links in the stat cards + recent section
    // route to /admin/applications.
    await page.getByRole('link', { name: /عرض كل الطلبات/ }).first().click();
    await expect(page).toHaveURL(/\/admin\/applications/);
    // Search input from JORD-35.
    await expect(page.getByRole('searchbox', { name: /بحث في الطلبات/ })).toBeVisible();
    // Filter dropdown.
    await expect(page.getByRole('combobox', { name: /فلترة حسب الحالة/ })).toBeVisible();
  });
});
