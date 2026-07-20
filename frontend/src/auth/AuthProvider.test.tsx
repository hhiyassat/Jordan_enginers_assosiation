import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor, act } from '@testing-library/react';
import { AuthProvider } from './AuthProvider';
import { useAuth } from './AuthContext';
import type { User } from '../types';

/**
 * JORD-50 regression: cross-tab session-swap guard.
 *
 * The real bug: opening two tabs, signing in as user A in tab 1 and
 * user B in tab 2, left tab 1 still rendering user A's UI even though
 * the shared cookie now identified user B. Every next request in
 * tab 1 hit the API as user B — an authorization hazard on shared
 * machines. AuthProvider now broadcasts on login/logout and, on the
 * receiving side, freezes the tab behind a modal that names the new
 * identity and demands a manual reload.
 *
 * We stub /auth/me through the module-level authApi mock and drive
 * the peer-tab side by dispatching a BroadcastChannel message from
 * a second channel object bound to the same channel name (that's
 * exactly how a real second tab would look from tab 1's side).
 */

vi.mock('../api/client', () => ({
  authApi: {
    me: vi.fn(),
    logout: vi.fn().mockResolvedValue(undefined),
  },
  setUnauthorizedHandler: vi.fn(),
}));

import { authApi } from '../api/client';

const AUTH_CHANNEL = 'esp:auth';

const USER_A: User = {
  id: 1, name: 'Applicant A', email: 'a@test.esp', role: 'applicant',
  organization_id: 1, must_change_password: false,
} as User;

const USER_B: User = {
  id: 2, name: 'Admin B', email: 'b@test.esp', role: 'admin',
  organization_id: 1, must_change_password: false,
} as User;

function Consumer() {
  const { user } = useAuth();
  return <div data-testid="who">{user?.name ?? 'anon'}</div>;
}

async function bootAs(u: User | null) {
  (authApi.me as ReturnType<typeof vi.fn>).mockResolvedValue({ user: u });
  const utils = render(
    <AuthProvider>
      <Consumer />
    </AuthProvider>
  );
  // Wait for the initial /auth/me to resolve and the provider to leave
  // its loading spinner.
  await waitFor(() => expect(screen.getByTestId('who')).toBeInTheDocument());
  return utils;
}

describe('AuthProvider — JORD-50 cross-tab session guard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    // Prevent test-to-test leakage on window/reload spies.
    vi.restoreAllMocks();
  });

  it('locks the tab behind a modal when another tab signs in as a different user', async () => {
    await bootAs(USER_A);
    expect(screen.getByTestId('who')).toHaveTextContent('Applicant A');

    // Simulate tab 2 signing in as USER_B: /auth/me now returns USER_B
    // (the cookie was rewritten by the peer), and the peer broadcasts.
    (authApi.me as ReturnType<typeof vi.fn>).mockResolvedValue({ user: USER_B });
    const peer = new BroadcastChannel(AUTH_CHANNEL);
    await act(async () => {
      peer.postMessage({ type: 'auth-changed', userId: USER_B.id });
      // Yield to microtasks so the AuthProvider handler runs.
      await new Promise(r => setTimeout(r, 0));
    });
    peer.close();

    // Modal appears and names the new user by name.
    await waitFor(() => expect(screen.getByTestId('stale-tab-modal')).toBeInTheDocument());
    expect(screen.getByText(/Admin B/)).toBeInTheDocument();
    // We deliberately DO NOT swap the cached user — the tab stays
    // frozen so the user can decide when to lose whatever they had
    // on screen. The modal is the only way forward.
    expect(screen.getByTestId('who')).toHaveTextContent('Applicant A');
  });

  it('silently clears the user when another tab signs out (no lock modal)', async () => {
    // JORD-53: peer logout is an intentional symmetric signal — showing a
    // scary lock modal for it is disproportionate and reads as confusing
    // ("why does clicking Sign Out over there give me an error over here?").
    // We just clear the user; RequireAuth will bounce this tab to /login
    // the same as any other unauthenticated visit.
    await bootAs(USER_A);
    (authApi.me as ReturnType<typeof vi.fn>).mockClear();
    const peer = new BroadcastChannel(AUTH_CHANNEL);
    await act(async () => {
      peer.postMessage({ type: 'auth-changed', userId: null });
      await new Promise(r => setTimeout(r, 0));
    });
    peer.close();
    // User is cleared → the Consumer renders 'anon'.
    await waitFor(() => expect(screen.getByTestId('who')).toHaveTextContent('anon'));
    // No lock modal for the logout path.
    expect(screen.queryByTestId('stale-tab-modal')).toBeNull();
    // We didn't need /auth/me for this — the peer's logout claim is
    // already the correct outcome (the cookie is gone).
    expect(authApi.me).not.toHaveBeenCalled();
  });

  it('ignores a broadcast whose userId matches ours (no unnecessary /auth/me trip)', async () => {
    await bootAs(USER_A);
    (authApi.me as ReturnType<typeof vi.fn>).mockClear();
    const peer = new BroadcastChannel(AUTH_CHANNEL);
    await act(async () => {
      peer.postMessage({ type: 'auth-changed', userId: USER_A.id });
      await new Promise(r => setTimeout(r, 0));
    });
    peer.close();
    // Same identity → no reconciliation, no modal.
    expect(authApi.me).not.toHaveBeenCalled();
    expect(screen.queryByTestId('stale-tab-modal')).toBeNull();
  });

  it('ignores foreign messages on the channel (defensive against future senders)', async () => {
    await bootAs(USER_A);
    (authApi.me as ReturnType<typeof vi.fn>).mockClear();
    const peer = new BroadcastChannel(AUTH_CHANNEL);
    await act(async () => {
      peer.postMessage({ type: 'not-us', payload: 'garbage' });
      await new Promise(r => setTimeout(r, 0));
    });
    peer.close();
    expect(authApi.me).not.toHaveBeenCalled();
    expect(screen.queryByTestId('stale-tab-modal')).toBeNull();
  });
});
