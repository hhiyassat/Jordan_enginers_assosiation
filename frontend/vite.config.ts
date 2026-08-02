/// <reference types="vitest" />
import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'

// fix/frontend-vite-proxy-port · dev proxy target is env-driven.
//
// The docker-compose stack exposes the Laravel `app` service on
// http://localhost:8080 (see docker-compose.yml:35). Vite (5173) proxies
// `/api` requests to it during local development.
//
// If you run the backend on a different host/port (CI, a remote dev
// server, a non-default docker mapping), export
// `VITE_DEV_PROXY_TARGET` before starting Vite. Example:
//
//   VITE_DEV_PROXY_TARGET=http://127.0.0.1:9000 npm run dev
//
// This is a Vite server-time variable — NOT a browser bundle variable.
// The browser never sees it; only the dev server uses it to route
// `/api/*` requests. Changing it requires restarting `npm run dev`.
//
// Historical note: earlier revisions hardcoded `http://localhost:8002`
// which no longer matches the docker-compose port mapping and produced
// `[vite] http proxy error AggregateError [ECONNREFUSED]` for every
// login attempt. Don't reintroduce a hardcoded target.
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), 'VITE_');
  const devProxyTarget = env.VITE_DEV_PROXY_TARGET || 'http://localhost:8080';

  return {
    plugins: [react()],
    server: {
      // Bind to 0.0.0.0 so mobile devices on the same LAN (and reverse-
      // proxied hostnames like jea.nocode.eqratech.com) can reach the
      // dev server, not just localhost.
      host: true,
      // Vite blocks unknown Host headers by default (defense against DNS
      // rebinding). `true` disables the check entirely — safe for dev
      // where the reverse-proxied hostname (jea.nocode.eqratech.com etc.)
      // is under our control. DO NOT set this in production.
      allowedHosts: true,
      proxy: {
        '/api': devProxyTarget,
      },
    },
    test: {
      environment: 'jsdom',
      globals: true,
      setupFiles: ['./src/test/setup.ts'],
      css: false,
      include: ['src/**/*.test.{ts,tsx}'],
    },
  };
})
