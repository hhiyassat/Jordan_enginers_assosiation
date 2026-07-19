import { BrowserRouter } from 'react-router-dom';
import { AuthProvider } from './auth/AuthProvider';
import { ErrorBoundary } from './components/ErrorBoundary';
import { RouteSuspense } from './layout/RouteSuspense';
import { AppRoutes } from './routes';

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
    <BrowserRouter>
      <ErrorBoundary>
        <AuthProvider>
          <RouteSuspense>
            <AppRoutes />
          </RouteSuspense>
        </AuthProvider>
      </ErrorBoundary>
    </BrowserRouter>
  );
}
