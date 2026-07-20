import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { setUnauthorizedHandler } from './client';

/**
 * JORD-29 regression: when the server hands back a 401, the client must
 * call the registered handler exactly once. That handler is how the
 * AuthProvider clears the token + user state so RequireAuth bounces the
 * next render to /login. Callers should not have to check status codes
 * themselves.
 */
describe('api client — 401 central handler', () => {
  afterEach(() => {
    setUnauthorizedHandler(null);
    vi.restoreAllMocks();
  });

  it('invokes the registered handler on a 401 response', async () => {
    // Post JORD-30 the invalidator just clears in-memory user state
    // (the httpOnly cookie is cleared by the backend on logout / by
    // the browser on expiry). No sessionStorage side-effect to check.
    const invalidator = vi.fn();
    setUnauthorizedHandler(invalidator);

    vi.stubGlobal('fetch', vi.fn(async () => new Response(
      JSON.stringify({ message: 'Unauthenticated' }),
      { status: 401, headers: { 'Content-Type': 'application/json' } }
    )));

    // Re-import inside the test so the stubbed fetch is picked up.
    const { authApi } = await import('./client');
    await expect(authApi.me()).rejects.toThrow();
    expect(invalidator).toHaveBeenCalledTimes(1);
  });

  it('surfaces a localized message instead of raw HTTP status', async () => {
    setUnauthorizedHandler(vi.fn());
    vi.stubGlobal('fetch', vi.fn(async () => new Response(
      '',
      { status: 500, headers: { 'Content-Type': 'application/json' } }
    )));
    const { authApi } = await import('./client');
    try {
      await authApi.me();
      expect.fail('should have thrown');
    } catch (err) {
      // JORD-43: never leaks raw "HTTP 500" to the user.
      expect(String((err as Error).message)).not.toMatch(/^HTTP\s\d/);
      expect((err as Error).message.length).toBeGreaterThan(0);
    }
  });

  it('does not throw if no handler is registered', async () => {
    setUnauthorizedHandler(null);
    vi.stubGlobal('fetch', vi.fn(async () => new Response(
      JSON.stringify({ message: 'nope' }),
      { status: 401, headers: { 'Content-Type': 'application/json' } }
    )));
    const { authApi } = await import('./client');
    await expect(authApi.me()).rejects.toThrow();
    // If it got here it didn't crash on the missing handler.
  });
});
