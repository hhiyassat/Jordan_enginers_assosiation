import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { AuthContext, RequireGuest } from './App';
import type { User } from './types';

/**
 * JORD-42 regression: an authenticated user hitting /login must be
 * bounced to /. Wrapping RequireGuest in a synthetic AuthContext value
 * lets us drive the guard directly, without booting the real
 * AuthProvider (which fires network calls on mount).
 */

const makeUser = (): User => ({
  id: 1, name: 'a', email: 'a@t.esp', role: 'applicant',
  organization_id: 1, must_change_password: false,
} as User);

function mount(user: User | null) {
  return render(
    <AuthContext.Provider value={{ user, token: null, login: () => {}, logout: () => {} }}>
      <MemoryRouter initialEntries={['/login']}>
        <Routes>
          <Route path="/login" element={<RequireGuest><span>login-screen</span></RequireGuest>} />
          <Route path="/" element={<span>home-screen</span>} />
        </Routes>
      </MemoryRouter>
    </AuthContext.Provider>
  );
}

describe('RequireGuest (JORD-42)', () => {
  it('renders the child when there is no session', () => {
    mount(null);
    expect(screen.getByText('login-screen')).toBeInTheDocument();
    expect(screen.queryByText('home-screen')).not.toBeInTheDocument();
  });

  it('redirects an authenticated user to /', () => {
    mount(makeUser());
    expect(screen.queryByText('login-screen')).not.toBeInTheDocument();
    expect(screen.getByText('home-screen')).toBeInTheDocument();
  });
});
