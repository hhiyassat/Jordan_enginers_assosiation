/**
 * Nashmi integration cycles — uses a shared integration key rather than
 * the applicant bearer token. Kept on a separate base URL so the key is
 * never sent to /api/v1 endpoints by accident.
 *
 * Split out of client.ts (JORD-22).
 */

const INTEGRATION_KEY = import.meta.env.VITE_INTEGRATION_KEY ?? '';
const INT_BASE = '/api/integration';

async function integrationRequest<T>(
  method: string,
  path: string,
  body?: unknown,
): Promise<T> {
  const res = await fetch(`${INT_BASE}${path}`, {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-Integration-Key': INTEGRATION_KEY,
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) {
    const err = new Error(json.message || `HTTP ${res.status}`) as Error & { status?: number };
    err.status = res.status;
    throw err;
  }
  return json as T;
}

export interface IntegrationCycle {
  id: number;
  cycle_ref: string;
  service_name: string;
  requirements_source: string;
  status: 'requirements_received' | 'code_done' | 'feedback_received' | 'closed';
  nashmi_project_id: number | null;
  requirements_meta: Record<string, unknown> | null;
  code_summary: Record<string, unknown> | null;
  feedback: Record<string, unknown> | null;
  notes: string | null;
  requirements_received_at: string | null;
  code_done_notified_at: string | null;
  feedback_received_at: string | null;
  created_at: string;
  updated_at: string;
}

export const integrationApi = {
  cycles: () =>
    integrationRequest<{ data: IntegrationCycle[] }>('GET', '/cycles'),
  cycle: (id: number) =>
    integrationRequest<{ data: IntegrationCycle }>('GET', `/cycles/${id}`),
  notifyCodeDone: (id: number, payload: {
    git_branch?: string;
    git_commit?: string;
    files_changed?: string[];
    api_endpoints?: string[];
    frontend_pages?: string[];
    db_tables?: string[];
    notes?: string;
  }) =>
    integrationRequest<{ message: string; cycle_ref: string; nashmi_project: unknown }>
      ('POST', `/cycles/${id}/notify-done`, payload),
};
