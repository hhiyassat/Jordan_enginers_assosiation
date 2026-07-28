import React from 'react';
import type { User } from '../../shared/types';
import { useAuth } from '../../auth/AuthContext';
import { Navigate, useLocation } from 'react-router-dom';

/**
 * Route guards + role helpers — moved to app/router (Task 2 FSD migration).
 * Original: src/auth/guards.tsx
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
 * the /login page.
 */
export function RequireGuest({ children }: { children: React.ReactNode }): JSX.Element {
  const { user } = useAuth();
  if (user) return <Navigate to="/" replace />;
  return <>{children}</>;
}

export function canReachAdmin(role: User['role'] | undefined): boolean {
  return role === 'admin' || role === 'superuser';
}

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

/** Blocks non-reviewers from /review/* */
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

export function RequireUserManager({ children }: { children: React.ReactNode }): JSX.Element {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  if (!user.can_manage_users) return <Navigate to="/" replace />;
  return <>{children}</>;
}

export function HomeRedirect(): JSX.Element {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  if (user.role === 'superuser')                        return <Navigate to="/admin/users" replace />;
  if (user.role === 'admin')                            return <Navigate to="/admin" replace />;
  if (user.role === 'staff' || user.role === 'auditor') return <Navigate to="/review/dashboard" replace />;
  return <Navigate to="/dashboard" replace />;
}
