import { test, expect } from '@playwright/test';
import { DEMO, login } from './helpers';

/**
 * JORD-9 + JORD-39: bell dropdown opens, shows the empty state on a
 * fresh session, and closes cleanly on Escape.
 *
 * The seeded demo DB has no notifications for ahmed yet (they're only
 * emitted by WorkflowEngine hooks on submit/decide/pay/issue), so we
 * assert the empty-state path here. A follow-up spec that walks the
 * full apply → submit → notification-appears loop would need extra
 * fixture setup — deferred.
 */

test.describe('NotificationBell', () => {
  test('opens the dropdown, shows the empty state, closes on Escape', async ({ page }) => {
    await login(page, DEMO.applicant.email, DEMO.applicant.password);

    // Bell is inside the header. aria-label is "الإشعارات" (or
    // "الإشعارات — N unread" when unread > 0).
    const bell = page.locator('header').getByRole('button', { name: /الإشعارات/ });
    await expect(bell).toBeVisible();
    await bell.click();

    // Dropdown is a role=dialog with aria-label="الإشعارات".
    const dialog = page.getByRole('dialog', { name: 'الإشعارات' });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByText(/لا توجد إشعارات جديدة/)).toBeVisible();

    // Escape closes it.
    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();
  });
});
