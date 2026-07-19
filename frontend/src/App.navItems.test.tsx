import { describe, it, expect } from 'vitest';
import { navItemsForRole } from './App';

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
