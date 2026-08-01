import http from 'k6/http';
import { check, sleep } from 'k6';

/**
 * ESP v2 smoke test — public endpoints only.
 *
 * The full 16-scenario load-test plan (Phase 12 of the architecture
 * review) needs seeded data + authenticated bearer tokens; this
 * smoke test proves the app is up and serving the two public health
 * surfaces:
 *
 *   GET /up           — Laravel liveness probe (bootstrap/app.php)
 *   GET /api/ready    — readiness probe (DB + cache round-trip, L-12)
 *
 * Run:
 *   BASE_URL=http://host.docker.internal:8002 \
 *   docker run --rm -v $PWD/performance/k6:/scripts grafana/k6 \
 *     run --duration 30s --vus 10 -e BASE_URL=$BASE_URL /scripts/smoke_test.js
 */
export const options = {
  stages: [
    { duration: '10s', target: 5 },
    { duration: '30s', target: 10 },
    { duration: '10s', target: 0 },
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'],
    http_req_failed:   ['rate<0.01'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8002';

export default function () {
  const health = http.get(`${BASE_URL}/up`);
  check(health, { 'liveness /up returns 200': (r) => r.status === 200 });

  const ready = http.get(`${BASE_URL}/api/ready`);
  check(ready, {
    'readiness /api/ready returns 200': (r) => r.status === 200,
    'readiness body says ready':        (r) => r.json('status') === 'ready',
  });

  sleep(1);
}
