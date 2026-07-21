import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
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
 * JORD-50: cross-tab session-swap guard
 * -------------------------------------
 * Cookies are shared across every tab on the same origin, so logging
 * in as user B in tab 2 silently rewrites tab 1's session. Previously
 * tab 1 kept rendering user A's UI (own name, own permissions, own
 * cached data) even though every next request would hit the API as
 * user B — a real hazard on a mixed superuser + engineering-office
 * machine. We now broadcast a message on every login/logout and, in
 * peer tabs, re-verify /auth/me. If the identity actually changed we
 * lock the tab behind a modal that forces a manual reload — never a
 * silent auto-reload, so any half-typed form is not thrown away
 * without the user's consent.
 */
const AUTH_CHANNEL = 'esp:auth';

interface AuthMessage {
  type: 'auth-changed';
  userId: number | null;
}

/** True when the runtime exposes a working BroadcastChannel (all
 *  modern browsers do; jsdom's default test env does not, so we
 *  degrade gracefully — the on-focus /auth/me path from JORD-30 is
 *  still there as a fallback). */
function hasBroadcast(): boolean {
  return typeof BroadcastChannel !== 'undefined';
}

export function AuthProvider({ children }: { children: React.ReactNode }): JSX.Element {
  const { t } = useTranslation();

  const [user, setUser]   = useState<User | null>(null);
  const [ready, setReady] = useState(false);
  // When another tab swaps the session out from under us we don't
  // mutate `user` — we surface a lock-screen instead and wait for a
  // manual reload. Storing the incoming identity lets the modal name
  // the new user so the reader understands what just happened.
  const [staleTabNotice, setStaleTabNotice] = useState<{ newUser: User | null } | null>(null);

  // Keep the latest user in a ref so the BroadcastChannel handler
  // (installed once) can compare against the current identity without
  // re-subscribing on every user change (which would race with the
  // very message that changes the user).
  const userRef = useRef<User | null>(null);
  useEffect(() => { userRef.current = user; }, [user]);

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

  // JORD-50 / JORD-53: subscribe to cross-tab auth messages. The two
  // cases have very different UX weight:
  //   • Peer signed in as a DIFFERENT user (identity swap): dangerous.
  //     This tab's cached user is now wrong; any click here mutates
  //     the peer's account. Freeze behind the lock modal and demand a
  //     manual reload so the user consents to losing local state.
  //   • Peer signed OUT (or was signed out): symmetric intentional
  //     signal. Just clear our state — RequireAuth will bounce this
  //     tab to /login the same as any other unauthenticated visit.
  //     No modal — the lock screen was disproportionate for the
  //     "user hit logout" path and read as scary/confusing.
  useEffect(() => {
    if (!hasBroadcast()) return;
    const ch = new BroadcastChannel(AUTH_CHANNEL);
    ch.addEventListener('message', (ev: MessageEvent<AuthMessage>) => {
      const msg = ev.data;
      if (!msg || msg.type !== 'auth-changed') return;
      const current = userRef.current;
      // Fast path: the broadcast matches who we already are — nothing
      // to reconcile, no network trip.
      if ((current?.id ?? null) === (msg.userId ?? null)) return;

      // Peer explicitly signed out — clear our session in-line, no
      // /auth/me trip (the cookie is gone anyway). This is symmetric
      // with the peer's intent.
      if (msg.userId === null) {
        if (current) setUser(null);
        return;
      }

      // Peer signed in as someone else — verify with the server (the
      // broadcast is untrusted for identity claims) and lock the tab
      // only if the answer really is a different user AND we already
      // held an identity here.
      authApi.me()
        .then(r => {
          // JORD-84 (PM): /auth/me now returns {user: null} for guests
          // instead of 401. Treat null the same as a peer sign-out —
          // clear the local session, no lock modal.
          if (r.user === null) {
            if (current) setUser(null);
            return;
          }
          // JORD-55 (PM): if this tab was NOT logged in at all
          // (current === null), silently adopt the new identity
          // rather than throwing a "session changed" lock modal at
          // an idle guest tab. The lock modal exists to prevent an
          // *authenticated* user from unknowingly acting on someone
          // else's account — a never-authenticated tab has no
          // stale local state to protect.
          if (current === null) {
            setUser(r.user);
            return;
          }
          if (r.user.id !== current.id) {
            setStaleTabNotice({ newUser: r.user });
          }
        })
        .catch(() => {
          // Network / server error. Treat as a silent logout — no lock modal.
          if (current) setUser(null);
        });
    });
    return () => ch.close();
  }, []);

  const broadcastAuthChange = useCallback((userId: number | null): void => {
    if (!hasBroadcast()) return;
    const ch = new BroadcastChannel(AUTH_CHANNEL);
    ch.postMessage({ type: 'auth-changed', userId } satisfies AuthMessage);
    ch.close();
  }, []);

  const login = (_ignoredToken: string | null, u: User): void => {
    // Token argument is intentionally ignored — the backend has
    // already set the httpOnly cookie on the /auth/login response.
    // The signature stays (t, u) so existing callers don't break.
    void _ignoredToken;
    setUser(u);
    broadcastAuthChange(u.id);
  };

  const logout = (): void => {
    // JORD-80: the backend call is fire-and-forget from the UI's
    // perspective — the local session must clear even if the network
    // is down — but a swallowed error hid genuine failures (revoked
    // token still valid on the backend, cookie couldn't be forgotten,
    // etc.). Route the failure through console.warn so it lands in
    // the same channel any error-reporter (Sentry, etc.) picks up
    // without blocking the local logout flow.
    authApi.logout().catch((err: unknown) => {
      const msg = err instanceof Error ? err.message : String(err);
      console.warn('[auth] logout request failed (local session cleared anyway):', msg);
    });
    setUser(null);
    broadcastAuthChange(null);
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
      {staleTabNotice && (
        <div
          className="fixed inset-0 z-[9999] bg-black/60 flex items-center justify-center p-4"
          role="dialog"
          aria-modal="true"
          aria-labelledby="stale-tab-title"
          data-testid="stale-tab-modal"
        >
          <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6 text-center" dir="rtl">
            <h2 id="stale-tab-title" className="text-lg font-bold text-jea-text mb-2">
              {t('auth.sessionChangedTitle')}
            </h2>
            <p className="text-sm text-jea-muted mb-5 leading-relaxed">
              {staleTabNotice.newUser
                ? t('auth.sessionChangedToUser', { name: staleTabNotice.newUser.name })
                : t('auth.sessionChangedLoggedOut')}
            </p>
            <button
              type="button"
              onClick={() => window.location.reload()}
              className="px-6 py-2.5 rounded-lg bg-jea-primary text-white text-sm font-bold hover:opacity-90"
            >
              {t('auth.sessionChangedReload')}
            </button>
          </div>
        </div>
      )}
    </AuthContext.Provider>
  );
}
