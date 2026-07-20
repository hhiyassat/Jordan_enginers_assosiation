import React, { useEffect, useState } from 'react';
import { authApi, setUnauthorizedHandler } from '../api/client';
import type { User } from '../types';
import { AuthContext } from './AuthContext';

/**
 * AuthProvider — the stateful half of the auth split (JORD-25).
 *
 * JORD-30 rewrite
 * ---------------
 * The bearer token used to live in sessionStorage. It now lives in a
 * backend-managed httpOnly + SameSite=Strict cookie, so JavaScript
 * can no longer see it (which was the XSS-exposure the review flagged).
 *
 * Provider responsibilities:
 *   • On mount, blind-call /auth/me. If the cookie is present the
 *     browser attaches it automatically and we get a user; if not,
 *     the 401 handler (registered below) clears state.
 *   • Re-verify on tab focus so a role change in another tab lands
 *     here on the next window-focus event.
 *   • Register a 401 invalidator with the api client (JORD-29) so any
 *     request that comes back unauthorized clears state exactly once.
 *
 * The context still exposes a `token` field for backward compatibility
 * (some legacy tests + the ChangeCredentials profile-refresh path
 * pass it back through login()). Its value is always null now.
 */
export function AuthProvider({ children }: { children: React.ReactNode }): JSX.Element {
  const [user, setUser]   = useState<User | null>(null);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    // Blind /auth/me — cookie either exists or doesn't. The 401 handler
    // handles the missing-cookie case; setReady runs in every path so
    // the AuthProvider loading spinner clears.
    authApi.me()
      .then(r => setUser(r.user))
      .catch(() => setUser(null))
      .finally(() => setReady(true));
  }, []);

  // Re-verify the session whenever the tab regains focus. Fixes the
  // stale-role bug: if the user logged in as a different role in another
  // tab (single-session policy revoked this tab's session), calling
  // /auth/me will either update the cached user or clear it cleanly.
  useEffect(() => {
    if (!user) return;
    const onFocus = () => {
      authApi.me()
        .then(r => setUser(r.user))
        .catch(() => setUser(null));
    };
    window.addEventListener('focus', onFocus);
    return () => window.removeEventListener('focus', onFocus);
  }, [user]);

  const login = (_ignoredToken: string | null, u: User): void => {
    // Token argument is intentionally ignored — the backend has
    // already set the httpOnly cookie on the /auth/login response.
    // The signature stays (t, u) so existing callers don't break.
    void _ignoredToken;
    setUser(u);
  };

  const logout = (): void => {
    authApi.logout().catch(() => {});
    setUser(null);
  };

  // JORD-29: give the api client a way to invalidate the session when it
  // sees a 401 so callers don't have to check status codes themselves.
  useEffect(() => {
    setUnauthorizedHandler(() => setUser(null));
    return () => setUnauthorizedHandler(null);
  }, []);

  if (!ready) {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="animate-spin w-10 h-10 border-4 border-blue-500 border-t-transparent rounded-full" />
      </div>
    );
  }

  return (
    <AuthContext.Provider value={{ user, token: null, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}
