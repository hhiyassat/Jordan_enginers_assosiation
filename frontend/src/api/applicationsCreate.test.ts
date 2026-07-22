import { describe, it, expect, vi, beforeEach } from 'vitest';

// Spy on window.fetch — the api/client `request` helper posts JSON via fetch.
// We intercept it and inspect the outgoing body to verify project_id
// pass-through without needing a live backend.
const fetchMock = vi.fn();
beforeEach(() => {
  fetchMock.mockReset();
  fetchMock.mockResolvedValue({
    ok: true,
    status: 201,
    headers: { get: () => 'application/json' },
    json: async () => ({ application: { id: 1 } }),
  });
  vi.stubGlobal('fetch', fetchMock);
});

// Import AFTER stubbing so the client's internal fetch call uses ours.
import { applicationsApi } from './client';

async function lastPostedBody(): Promise<Record<string, unknown>> {
  const call = fetchMock.mock.calls.at(-1)!;
  const init = call[1] as RequestInit;
  return JSON.parse(String(init.body));
}

describe('applicationsApi.create', () => {
  it('sends project_id when supplied', async () => {
    await applicationsApi.create('DRW-P-001', { field: 'x' }, 42);
    expect(await lastPostedBody()).toEqual({
      service_code: 'DRW-P-001',
      data: { field: 'x' },
      project_id: 42,
    });
  });

  it('omits project_id when the argument is undefined', async () => {
    await applicationsApi.create('CERT-001', { field: 'x' });
    const body = await lastPostedBody();
    expect(body).toEqual({ service_code: 'CERT-001', data: { field: 'x' } });
    expect(body).not.toHaveProperty('project_id');
  });

  it('omits project_id when the argument is 0 (falsy)', async () => {
    // Regression pin — a spread-over-truthy expression `...(id ? {…} : {})`
    // means passing 0 also omits it. That's the intended behaviour since
    // project IDs are always positive database ids.
    await applicationsApi.create('CERT-001', {}, 0);
    expect(await lastPostedBody()).not.toHaveProperty('project_id');
  });
});
