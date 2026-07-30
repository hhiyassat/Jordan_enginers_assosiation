# Performance & Load Testing Suite — ESP v2

This directory contains reproducible k6 load test scripts and benchmark documentation.

## Running Smoke Load Tests

```bash
k6 run performance/k6/smoke_test.js
```

To run against a specific environment:

```bash
BASE_URL=https://staging.esp.jea.org.jo k6 run performance/k6/smoke_test.js
```

## Performance Targets

- **p95 Latency**: < 500 ms for read operations
- **p95 Latency**: < 1000 ms for submit operations
- **Failure Rate**: < 1%
