import { QueryClient } from '@tanstack/react-query';

/**
 * Shared QueryClient — created once per browser session (JORD-33).
 *
 * Defaults chosen deliberately:
 *   • staleTime 30s — most JEA endpoints don't mutate under a user's
 *     feet mid-page, so a short stale window kills the vast majority of
 *     duplicate fetches without making the UI feel stale.
 *   • gcTime 5 minutes — how long a cache entry sticks around after
 *     nobody's subscribed. Long enough that navigating away and back
 *     doesn't re-fetch, short enough that memory doesn't balloon.
 *   • retry: 1 — one silent retry papers over the occasional flaky
 *     network hop, but two would compound rate-limit hits.
 *   • refetchOnWindowFocus: false — the review flagged aggressive
 *     re-fetching as noisy. AuthProvider already re-verifies /auth/me
 *     on focus; other data doesn't need to poll every tab-switch.
 */
export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      gcTime:    5 * 60_000,
      retry:     1,
      refetchOnWindowFocus: false,
    },
    mutations: {
      retry: 0,
    },
  },
});
