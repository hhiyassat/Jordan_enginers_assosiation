import { createContext, useContext } from 'react';
import type { User } from '../types';

/**
 * Auth context — split out of App.tsx (JORD-25).
 *
 * The context object + its hook live here so any consumer can pull
 * `useAuth` without importing from the root shell, and tests can wrap
 * components in a synthetic provider without booting the full
 * AuthProvider (which fires network calls on mount).
 */
export interface AuthContextType {
  user: User | null;
  /**
   * JORD-30: token is always null now — it lives in a httpOnly cookie
   * the browser attaches automatically. Kept on the interface for
   * backward compat with existing consumers; new code should ignore it.
   */
  token: string | null;
  /**
   * Refresh the session cache with the given user. The `token` argument
   * is preserved for backward compat but ignored — the auth cookie is
   * already on the response that produced this user payload.
   */
  login: (token: string | null, user: User) => void;
  logout: () => void;
}

export const AuthContext = createContext<AuthContextType>({
  user: null, token: null,
  login: () => {}, logout: () => {},
});

export const useAuth = (): AuthContextType => useContext(AuthContext);
