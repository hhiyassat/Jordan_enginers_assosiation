import { describe, it, expect } from 'vitest';
import { navItemsForRole, canReachAdmin, canReachReviewer } from './App';

/**
 * Pins the per-role sidebar visibility. Regression that motivated this
 * test: a staff user was seeing admin nav items (bug caused by stale
 * client state after a single-session token swap, not the nav function
 * itself — but the nav function is a critical policy boundary so this
 * lock keeps it correct forever).
 */
describe('navItemsForRole', () => {
  const paths = (role: Parameters<typeof navItemsForRole>[0]) =>
    navItemsForRole(role).map(i => i.to);

  it('returns nothing when there is no role (unauthenticated)', () => {
    expect(navItemsForRole(undefined)).toEqual([]);
  });

  it('gives applicants only the applicant lanes', () => {
    const links = paths('applicant');
    expect(links).toEqual(['/dashboard', '/services', '/my-applications']);
  });

  it('gives staff only the review lane — no admin surface', () => {
    const links = paths('staff');
    expect(links).toEqual(['/review/queue']);
    expect(links).not.toContain('/admin');
    expect(links).not.toContain('/admin/services');
    expect(links).not.toContain('/admin/users');
  });

  it('gives auditor only the review lane — no admin surface', () => {
    expect(paths('auditor')).toEqual(['/review/queue']);
  });

  it('gives admin review + every admin lane including user management', () => {
    const links = paths('admin');
    expect(links).toContain('/review/queue');
    expect(links).toContain('/admin');
    expect(links).toContain('/admin/services');
    expect(links).toContain('/admin/services/new');
    expect(links).toContain('/admin/integration');
    expect(links).toContain('/admin/users');
  });

  it('gives superuser every admin lane (superuser is above admin)', () => {
    const links = paths('superuser');
    expect(links).toContain('/admin');
    expect(links).toContain('/admin/services');
    expect(links).toContain('/admin/users');
    // Superuser does NOT sit in the review-role bucket, so the review
    // lane is absent — HomeRedirect sends them to /admin/users anyway.
    expect(links).not.toContain('/review/queue');
  });
});

/**
 * canReachAdmin is the pure boundary used by RequireAdmin. Regression
 * that motivated pinning it here: /admin/* routes were originally guarded
 * by RequireAuth (not by role), so a staff user could reach the Admin
 * Dashboard and see admin quick-actions, then bounce to /review/queue
 * when they clicked "إدارة المستخدمين".
 */
describe('canReachAdmin', () => {
  it('lets admin and superuser through', () => {
    expect(canReachAdmin('admin')).toBe(true);
    expect(canReachAdmin('superuser')).toBe(true);
  });

  it('blocks every other role', () => {
    expect(canReachAdmin('staff')).toBe(false);
    expect(canReachAdmin('auditor')).toBe(false);
    expect(canReachAdmin('applicant')).toBe(false);
    expect(canReachAdmin(undefined)).toBe(false);
  });
});

/**
 * canReachReviewer must match the backend's role:staff,auditor,admin
 * middleware on /api/v1/review/*. Superuser is deliberately excluded —
 * superuser is a user-management role, not a god-mode.
 */
describe('canReachReviewer', () => {
  it('lets staff, auditor, admin through', () => {
    expect(canReachReviewer('staff')).toBe(true);
    expect(canReachReviewer('auditor')).toBe(true);
    expect(canReachReviewer('admin')).toBe(true);
  });

  it('blocks superuser (separation of duties)', () => {
    expect(canReachReviewer('superuser')).toBe(false);
  });

  it('blocks applicant and unauthenticated', () => {
    expect(canReachReviewer('applicant')).toBe(false);
    expect(canReachReviewer(undefined)).toBe(false);
  });
});
