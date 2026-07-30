import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '30s', target: 10 },
    { duration: '1m', target: 20 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'],
    http_req_failed: ['rate<0.01'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

export default function () {
  // 1. Health check
  const healthRes = http.get(`${BASE_URL}/up`);
  check(healthRes, {
    'health status is 200': (r) => r.status === 200,
  });

  // 2. Service Catalog Index
  const catalogRes = http.get(`${BASE_URL}/api/services`);
  check(catalogRes, {
    'catalog status is 200': (r) => r.status === 200,
  });

  sleep(1);
}
