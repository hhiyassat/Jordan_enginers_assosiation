import { describe, it, expect, beforeEach } from 'vitest';

/**
 * JORD-30 regression pin.
 *
 * Post-migration, the Sanctum token lives in a backend-managed
 * httpOnly + SameSite=Strict cookie and is invisible to JavaScript.
 * Two rules the api client MUST hold to:
 *
 *   1. Never read a token from sessionStorage / localStorage — even
 *      if some legacy code left one behind, it must not be attached
 *      to outgoing requests. (Otherwise an old logged-out session
 *      would leak into a new one.)
 *   2. Every fetch must be issued with `credentials: 'include'` so
 *      the browser attaches the cookie on same-origin calls.
 *
 * The old pre-JORD-30 assertions live on git blame — the file was
 * previously named "read the token from sessionStorage, not localStorage".
 */

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

describe('api client — JORD-30 cookie-only auth', () => {
  it('never sends an Authorization header even if legacy storage has a token', async () => {
    localStorage.setItem('esp_token', 'legacy-should-be-ignored');
    sessionStorage.setItem('esp_token', 'legacy-should-be-ignored');

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

  it('sends credentials: "include" so the browser attaches the httpOnly cookie', async () => {
    const capturedInits: RequestInit[] = [];
    globalThis.fetch = ((_url: unknown, init?: RequestInit) => {
      if (init) capturedInits.push(init);
      return fetchMock();
    }) as unknown as typeof fetch;

    const { servicesApi } = await import('./client');
    await servicesApi.list();

    const init = capturedInits.at(-1)!;
    expect(init.credentials).toBe('include');
  });
});
