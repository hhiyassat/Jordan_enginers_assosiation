import { execSync } from 'node:child_process';

/**
 * Runs once before the whole Playwright suite.
 *
 * Resets the backend DB with `migrate:fresh --seed` so every run starts
 * from a known baseline (the demo org, five seeded users, the 56-leaf
 * catalog). Serial-only runs mean tests can share this state and add
 * on top; parallel workers are disabled in playwright.config.ts.
 */
export default async function globalSetup() {
  const cmd = 'php artisan migrate:fresh --seed --force';
  const env = { ...process.env, APP_ENV: 'testing', CAPTCHA_ENABLED: 'false' };

  console.log('▶ Resetting E2E test DB via migrate:fresh --seed');
  execSync(cmd, {
    cwd:   'backend',
    env,
    stdio: 'inherit',
  });
  console.log('✓ E2E DB ready');
}
