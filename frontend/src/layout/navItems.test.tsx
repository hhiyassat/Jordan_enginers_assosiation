import { describe, it, expect } from 'vitest';
import { isActivePath, navItemsForRole } from './navItems';
import { canReachAdmin, canReachReviewer } from '../auth/guards';

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
    expect(links).toEqual(['/dashboard', '/services', '/my-applications', '/my-office']);
  });

  it('gives staff only the review lanes — no admin surface', () => {
    const links = paths('staff');
    // JORD-88: reviewers now get both the dashboard and the queue.
    expect(links).toEqual(['/review/dashboard', '/review/queue']);
    expect(links).not.toContain('/admin');
    expect(links).not.toContain('/admin/services');
    expect(links).not.toContain('/admin/users');
  });

  it('gives auditor only the review lanes — no admin surface', () => {
    expect(paths('auditor')).toEqual(['/review/dashboard', '/review/queue']);
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

/**
 * JORD-65 (PM): sibling nav entries `/admin/services` and
 * `/admin/services/new` were both highlighted on the New Service
 * page because a naive startsWith made "/admin/services/new" match
 * both. Pin the fix so a future refactor can't reintroduce the bug.
 */
describe('isActivePath — JORD-65 sibling nav highlight', () => {
  it('lights only "New Service" on /admin/services/new', () => {
    expect(isActivePath('/admin/services/new', '/admin/services/new')).toBe(true);
    expect(isActivePath('/admin/services/new', '/admin/services')).toBe(false);
  });

  it('lights only "Services" on /admin/services itself', () => {
    expect(isActivePath('/admin/services', '/admin/services')).toBe(true);
    expect(isActivePath('/admin/services', '/admin/services/new')).toBe(false);
  });

  it('lights only "Services" on the edit sub-route', () => {
    expect(isActivePath('/admin/services/42/edit', '/admin/services')).toBe(true);
    expect(isActivePath('/admin/services/42/edit', '/admin/services/new')).toBe(false);
  });

  it('does not light "Services" on the fees editor sibling', () => {
    expect(isActivePath('/admin/service-fees', '/admin/services')).toBe(false);
    expect(isActivePath('/admin/service-fees', '/admin/service-fees')).toBe(true);
  });
});
