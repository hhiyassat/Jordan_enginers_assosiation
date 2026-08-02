/**
 * fix/frontend-vite-proxy-port · guard against reintroducing a hardcoded
 * proxy target. The previous revision pinned the proxy to :8002 which
 * no longer matches the docker-compose port mapping (:8080), producing
 * `[vite] http proxy error AggregateError [ECONNREFUSED]` for every
 * login attempt.
 *
 * This test reads `vite.config.ts` as text and asserts:
 *   • the deprecated port 8002 is NOT present as a proxy target
 *   • the proxy target is derived from an env variable (VITE_DEV_PROXY_TARGET)
 */
import { describe, it, expect } from 'vitest';

// Load fs/path via a runtime `require` — `@types/node` isn't in the
// frontend `tsconfig.json` `types` list, so `import 'node:fs'` would
// break `npm run build`'s tsc pass. Vitest runs in Node so `require`
// is available at test time.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const _require = (0, eval)('require') as (m: string) => any;
const fs = _require('fs') as { readFileSync: (p: string, e: string) => string };
const path = _require('path') as { resolve: (...s: string[]) => string };
const configSource = fs.readFileSync(
  path.resolve(path.resolve(), 'vite.config.ts'),
  'utf8',
);

describe('vite.config.ts · dev proxy is env-driven, not hardcoded', () => {
  it('does NOT reintroduce the deprecated localhost:8002 target', () => {
    // Comments about the historical failure are allowed to mention 8002
    // so we look for the string in a proxy-value context.
    const proxyLines = configSource
      .split('\n')
      .filter((line: string) => /(proxy|VITE_DEV_PROXY_TARGET)/.test(line));
    for (const line of proxyLines) {
      const isCommentLine = /^\s*(\/\/|\*|\/\*)/.test(line);
      if (isCommentLine) continue;
      expect(line).not.toMatch(/:\s*8002\b/);
    }
  });

  it('reads the proxy target from VITE_DEV_PROXY_TARGET with an :8080 fallback', () => {
    expect(configSource).toMatch(/VITE_DEV_PROXY_TARGET/);
    expect(configSource).toMatch(/http:\/\/localhost:8080/);
  });

  it('routes /api through the resolved proxy target (not a bare URL)', () => {
    // The proxy config key must remain `/api` — the browser calls
    // /api/v1/... via the Vite dev server. If someone changes this to
    // an absolute base URL the dev-time cookie flow breaks (cross-origin
    // credentials without CORS).
    expect(configSource).toMatch(/['"]\/api['"]/);
  });
});
