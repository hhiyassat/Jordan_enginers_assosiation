import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import type { User } from '../types';
import { useAuth } from './AuthContext';

/**
 * Route guards + role helpers — hoisted out of App.tsx (JORD-25).
 *
 * All guards return the same shape: children through if OK, <Navigate>
 * away if not. Pure predicates (canReach*) sit next to them so tests
 * can pin the role-boundary policy without mounting the router.
 */

/** Blocks unauthenticated users; also enforces the first-login password change. */
export function RequireAuth({ children }: { children: React.ReactNode }): JSX.Element {
  const { user } = useAuth();
  const location = useLocation();
  if (!user) return <Navigate to="/login" replace />;
  if (user.must_change_password && location.pathname !== '/auth/change-credentials') {
    return <Navigate to="/auth/change-credentials" replace />;
  }
  return <>{children}</>;
}

/**
 * JORD-42: the inverse guard — sends an already-authenticated user off
 * the /login page. Without this, hitting the browser Back button after
 * signing in would drop the user on the login form even though they
 * still had a valid session.
 */
export function RequireGuest({ children }: { children: React.ReactNode }): JSX.Element {
  const { user } = useAuth();
  if (user) return <Navigate to="/" replace />;
  return <>{children}</>;
}

/**
 * Which roles are allowed on /admin/*. Pure so the boundary is testable
 * without mounting the router — RequireAdmin just wraps it.
 */
export function canReachAdmin(role: User['role'] | undefined): boolean {
  return role === 'admin' || role === 'superuser';
}

/**
 * Which roles are allowed on /review/*. Superuser is deliberately
 * excluded — the superuser role is user-management only, not god-mode.
 * Backend route middleware matches (role:staff,auditor,admin).
 */
export function canReachReviewer(role: User['role'] | undefined): boolean {
  return role === 'staff' || role === 'auditor' || role === 'admin';
}

/** Blocks non-admins from admin-only routes (admin AND superuser pass). */
export function RequireAdmin({ children }: { children: React.ReactNode }): JSX.Element {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  if (!canReachAdmin(user.role)) return <Navigate to="/" replace />;
  return <>{children}</>;
}

/** Blocks non-reviewers from /review/* — same UX story as RequireAdmin. */
export function RequireReviewer({ children }: { children: React.ReactNode }): JSX.Element {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  if (!canReachReviewer(user.role)) return <Navigate to="/" replace />;
  return <>{children}</>;
}

/** Blocks non-applicants from applicant-only routes. */
export function RequireApplicant({ children }: { children: React.ReactNode }): JSX.Element {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  if (user.role !== 'applicant') return <Navigate to="/" replace />;
  return <>{children}</>;
}

/**
 * Blocks users who can't manage the roster. Admin AND superuser both
 * pass — the page itself filters actions by the actor's tier.
 */
export function RequireUserManager({ children }: { children: React.ReactNode }): JSX.Element {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  if (!user.can_manage_users) return <Navigate to="/" replace />;
  return <>{children}</>;
}

/**
 * Root-path landing: dispatch to the right home for each role. Kept as
 * a component (not a hook) so it can be dropped straight into a Route.
 */
export function HomeRedirect(): JSX.Element {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  if (user.role === 'superuser')                        return <Navigate to="/admin/users" replace />;
  if (user.role === 'admin')                            return <Navigate to="/admin" replace />;
  if (user.role === 'staff' || user.role === 'auditor') return <Navigate to="/review/queue" replace />;
  return <Navigate to="/dashboard" replace />;
}
