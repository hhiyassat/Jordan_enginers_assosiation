import React, { useEffect, useState } from 'react';
import { authApi, setUnauthorizedHandler } from '../api/client';
import type { User } from '../types';
import { AuthContext } from './AuthContext';

/**
 * AuthProvider — the stateful half of the auth split (JORD-25).
 *
 * Responsibilities:
 *   • Bootstrap the session from sessionStorage (per-tab isolation —
 *     see api/client.ts for why not localStorage).
 *   • Re-verify on tab focus so a role change in another tab lands here.
 *   • Register a 401 invalidator with the api client (JORD-29) so any
 *     request that comes back unauthorized clears state exactly once.
 */
export function AuthProvider({ children }: { children: React.ReactNode }): JSX.Element {
  const [token, setToken] = useState<string | null>(sessionStorage.getItem('esp_token'));
  const [user, setUser]   = useState<User | null>(null);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    if (token) {
      authApi.me()
        .then(r => setUser(r.user))
        .catch(() => { sessionStorage.removeItem('esp_token'); setToken(null); })
        .finally(() => setReady(true));
    } else {
      setReady(true);
    }
  }, [token]);

  // Re-verify the session whenever the tab regains focus. Fixes the
  // stale-role bug: if the user logged in as a different role in another
  // tab (single-session policy revoked this tab's token), calling /auth/me
  // will either update the cached user or clear the session cleanly.
  useEffect(() => {
    if (!token) return;
    const onFocus = () => {
      authApi.me()
        .then(r => setUser(r.user))
        .catch(() => { sessionStorage.removeItem('esp_token'); setToken(null); });
    };
    window.addEventListener('focus', onFocus);
    return () => window.removeEventListener('focus', onFocus);
  }, [token]);

  const login = (t: string, u: User): void => {
    sessionStorage.setItem('esp_token', t);
    setToken(t);
    setUser(u);
  };

  const logout = (): void => {
    authApi.logout().catch(() => {});
    sessionStorage.removeItem('esp_token');
    setToken(null);
    setUser(null);
  };

  // JORD-29: give the api client a way to invalidate the session when it
  // sees a 401, so callers don't have to check status codes themselves.
  // The api client fires this once; RequireAuth then bounces to /login on
  // the next render because user becomes null.
  useEffect(() => {
    setUnauthorizedHandler(() => {
      sessionStorage.removeItem('esp_token');
      setToken(null);
      setUser(null);
    });
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
    <AuthContext.Provider value={{ user, token, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}
