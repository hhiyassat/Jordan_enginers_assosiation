import { describe, it, expect, beforeEach } from 'vitest';

// The api client reads the token via sessionStorage. Because
// sessionStorage is per-tab (whereas localStorage is per-origin-shared),
// this test doubles as a regression pin for the "multi-tab session
// collision" bug: opening a new tab and calling setItem there does NOT
// affect the current tab's stored token in a real browser. We can't
// simulate two tabs in jsdom, but we CAN pin the storage medium so
// nobody accidentally reverts to localStorage.
//
// If this test starts failing because the client reads from localStorage,
// the fix is to switch it back to sessionStorage — not to update the test.

// Stub fetch so client.request() doesn't error out on the missing backend.
const fetchMock = () => Promise.resolve({
  ok: true, status: 200,
  headers: { get: () => 'application/json' },
  json: async () => ({}),
} as unknown as Response);

beforeEach(() => {
  sessionStorage.clear();
  localStorage.clear();
  globalThis.fetch = fetchMock as unknown as typeof fetch;
});

describe('api client token storage', () => {
  it('reads the token from sessionStorage, not localStorage', async () => {
    localStorage.setItem('esp_token',   'FROM_LOCALSTORAGE_should_be_ignored');
    sessionStorage.setItem('esp_token', 'FROM_SESSIONSTORAGE');

    const capturedHeaders: HeadersInit[] = [];
    globalThis.fetch = ((_url: unknown, init?: RequestInit) => {
      if (init?.headers) capturedHeaders.push(init.headers);
      return fetchMock();
    }) as unknown as typeof fetch;

    const { servicesApi } = await import('./client');
    await servicesApi.list();

    const headers = capturedHeaders.at(-1) as Record<string, string>;
    expect(headers.Authorization).toBe('Bearer FROM_SESSIONSTORAGE');
  });

  it('sends no Authorization header when sessionStorage is empty', async () => {
    // Even if localStorage has a token, we must NOT fall back to it —
    // otherwise a tab that never logged in would inherit another tab's session.
    localStorage.setItem('esp_token', 'FROM_LOCALSTORAGE_should_be_ignored');

    const capturedHeaders: HeadersInit[] = [];
    globalThis.fetch = ((_url: unknown, init?: RequestInit) => {
      if (init?.headers) capturedHeaders.push(init.headers);
      return fetchMock();
    }) as unknown as typeof fetch;

    const { servicesApi } = await import('./client');
    await servicesApi.list();

    const headers = capturedHeaders.at(-1) as Record<string, string>;
    expect(headers.Authorization).toBeUndefined();
  });
});
