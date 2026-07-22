import { BrowserRouter } from 'react-router-dom';
import { QueryClientProvider } from '@tanstack/react-query';
import { AuthProvider } from './auth/AuthProvider';
import { ErrorBoundary } from './platform/components/ErrorBoundary';
import { RouteSuspense } from './layout/RouteSuspense';
import { AppRoutes } from './routes';
import { queryClient } from './api/queryClient';

/**
 * App — pure composition root (JORD-25).
 *
 * Everything the shell needs sits in its own module now:
 *   • auth/         — context, provider, guards, LoginPage
 *   • layout/       — Layout, Header, sidebar, RouteSuspense
 *   • routes.tsx    — code-split route table
 *   • components/   — reusable UI + ErrorBoundary + LanguageSwitcher
 *   • i18n/         — react-i18next bootstrap + locales
 *
 * This file just wires them together. It should stay this small.
 */
export default function App(): JSX.Element {
  return (
    <QueryClientProvider client={queryClient}>
      {/* JORD-86: opt into the v7 startTransition behaviour now so the
          React Router future-flag warning stops firing on every load
          and we're already on the code path v7 will make default. */}
      <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
        <ErrorBoundary>
          <AuthProvider>
            <RouteSuspense>
              <AppRoutes />
            </RouteSuspense>
          </AuthProvider>
        </ErrorBoundary>
      </BrowserRouter>
    </QueryClientProvider>
  );
}
