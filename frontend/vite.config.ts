/// <reference types="vitest" />
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
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
      '/api': 'http://localhost:8002',
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
    css: false,
    include: ['src/**/*.test.{ts,tsx}'],
  },
})
