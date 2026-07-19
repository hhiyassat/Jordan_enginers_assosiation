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
  token: string | null;
  login: (token: string, user: User) => void;
  logout: () => void;
}

export const AuthContext = createContext<AuthContextType>({
  user: null, token: null,
  login: () => {}, logout: () => {},
});

export const useAuth = (): AuthContextType => useContext(AuthContext);
