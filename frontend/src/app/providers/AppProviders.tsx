import { BrowserRouter } from 'react-router-dom';
import { QueryClientProvider } from '@tanstack/react-query';
import { AuthProvider } from '../../auth/AuthProvider';
import { ErrorBoundary } from '../../platform/components/ErrorBoundary';
import { RouteSuspense } from '../../layout/RouteSuspense';
import { queryClient } from './queryClient';
import type { ReactNode } from 'react';

interface AppProvidersProps {
  children: ReactNode;
}

/**
 * AppProviders — wraps the entire application with all top-level providers.
 * Extracted from App.tsx (Task 3 FSD migration).
 */
export function AppProviders({ children }: AppProvidersProps): JSX.Element {
  return (
    <QueryClientProvider client={queryClient}>
      {/* JORD-86: opt into the v7 startTransition behaviour */}
      <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
        <ErrorBoundary>
          <AuthProvider>
            <RouteSuspense>
              {children}
            </RouteSuspense>
          </AuthProvider>
        </ErrorBoundary>
      </BrowserRouter>
    </QueryClientProvider>
  );
}
