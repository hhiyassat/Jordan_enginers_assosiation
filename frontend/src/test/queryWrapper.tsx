import type { ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

/**
 * Test helper for pages that use React Query hooks.
 *
 * Each caller gets a FRESH QueryClient so cached data from one test
 * doesn't leak into the next. Retries are turned off — no test should
 * take longer just because the mocked fetch rejected once. Silences
 * React Query's own error logging for cleaner CI output.
 */
export function makeQueryWrapper(): {
  client: QueryClient;
  Wrapper: (props: { children: ReactNode }) => JSX.Element;
} {
  const client = new QueryClient({
    defaultOptions: {
      queries:   { retry: false, staleTime: Infinity, gcTime: Infinity },
      mutations: { retry: false },
    },
  });
  return {
    client,
    Wrapper: ({ children }) => (
      <QueryClientProvider client={client}>{children}</QueryClientProvider>
    ),
  };
}
