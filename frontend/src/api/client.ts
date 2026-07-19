// ESP v2 — API Client
// All requests go through this client for consistent error handling.

import type { Application, ApplicationDocument, Certificate, DashboardStats, Engineer, Project, ServiceDefinition, User } from '../types';

const BASE = '/api/v1';

/**
 * Default per-request timeout. Anything over this cancels via AbortController
 * — network unavailability used to hang UI spinners forever because fetch()
 * has no default timeout. Uploads (FormData) skip this cap since they can
 * take longer than the default; individual callers can pass a shorter one.
 */
const DEFAULT_TIMEOUT_MS = 30_000;

function getToken(): string | null {
  // sessionStorage is per-tab. localStorage is shared across every tab on
  // the same origin, which meant logging in as a different user in tab 2
  // silently clobbered tab 1's admin session and every subsequent request
  // used the newer token. Per-tab isolation lets a demo/dev workflow keep
  // admin + staff + applicant sessions open side by side.
  return sessionStorage.getItem('esp_token');
}

/**
 * The AuthProvider registers a callback here on mount so the client can
 * clear session state when the server hands us a 401. Kept off React
 * context so it's usable inside plain modules that don't have hooks.
 */
type SessionInvalidator = () => void;
let onUnauthorized: SessionInvalidator | null = null;
export function setUnauthorizedHandler(fn: SessionInvalidator | null): void {
  onUnauthorized = fn;
}

/** Human-readable message keyed off HTTP status. Never leaks stack traces. */
function friendlyMessage(status: number, backendMessage?: string): string {
  // Trust bilingual/Arabic backend messages from our own API — those are
  // authored for end users. Only fall back to generic strings when the
  // backend gave us nothing useful.
  if (backendMessage && backendMessage.trim().length > 0 && !/^HTTP\s\d/.test(backendMessage)) {
    return backendMessage;
  }
  if (status === 401) return 'انتهت جلستك — يرجى تسجيل الدخول مجدداً.';
  if (status === 403) return 'ليست لديك صلاحية لتنفيذ هذا الإجراء.';
  if (status === 404) return 'العنصر المطلوب غير موجود.';
  if (status === 422) return 'البيانات المدخلة غير صحيحة.';
  if (status === 429) return 'عدد الطلبات كبير — حاول مرة أخرى بعد قليل.';
  if (status >= 500)  return 'حدث خطأ في الخادم. يرجى المحاولة لاحقاً.';
  return 'حدث خطأ غير متوقع.';
}

async function request<T>(
  method: string,
  path: string,
  body?: unknown,
  isFormData = false,
): Promise<T> {
  const headers: Record<string, string> = {};
  const token = getToken();

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  // Always tell Laravel we expect JSON — without this, validation errors
  // return 302 redirects instead of 422 JSON responses.
  headers['Accept'] = 'application/json';

  if (!isFormData && body) {
    headers['Content-Type'] = 'application/json';
  }

  // AbortController + timeout: raw fetch() has no timeout. A dead server
  // or dropped connection would hang UI spinners forever. Skip the cap on
  // FormData (uploads can legitimately take longer).
  const controller = new AbortController();
  const timeoutId = isFormData
    ? null
    : setTimeout(() => controller.abort(), DEFAULT_TIMEOUT_MS);

  let res: Response;
  try {
    res = await fetch(`${BASE}${path}`, {
      method,
      headers,
      body: isFormData ? (body as FormData) : body ? JSON.stringify(body) : undefined,
      signal: controller.signal,
    });
  } catch (fetchErr) {
    if (timeoutId) clearTimeout(timeoutId);
    // Distinguish timeout from other network failures so the UI can
    // show something useful instead of DOMException / TypeError.
    const isAbort = fetchErr instanceof DOMException && fetchErr.name === 'AbortError';
    const err = new Error(
      isAbort
        ? 'انتهت مهلة الطلب. يرجى المحاولة مرة أخرى.'
        : 'تعذّر الاتصال بالخادم. تحقق من الاتصال.'
    ) as Error & { status?: number };
    err.status = 0;
    throw err;
  }
  if (timeoutId) clearTimeout(timeoutId);

  const json = await res.json().catch(() => ({}));

  if (!res.ok) {
    // JORD-29: central 401 handler — clear the session once and let the
    // AuthProvider trigger the redirect via its normal `!user` guard, so
    // callers don't each reinvent the wheel.
    if (res.status === 401 && onUnauthorized) {
      try { onUnauthorized(); } catch { /* swallow — invalidator must never throw */ }
    }

    // EDA-10: Surface field-level errors and Hukm governance fields to the caller.
    // JORD-43: friendlyMessage() replaces raw `HTTP 500` etc. with a
    // localized string; backend-provided messages pass through.
    const err = new Error(friendlyMessage(res.status, json.message)) as Error & {
      errors?:   Record<string, string>;
      status?:   number;
      blockers?: unknown[];
      verdict?:  string;
    };
    err.errors   = json.errors;
    err.status   = res.status;
    err.blockers = json.blockers;   // Hukm: fatal blockers that halted generation
    err.verdict  = json.verdict;    // Hukm: verdict when halted (always 'batil')
    throw err;
  }

  return json as T;
}

// ── Auth ─────────────────────────────────────────────────────────────

export const authApi = {
  login: (email: string, password: string, captcha?: { id: string; answer: string }) =>
    request<{ token: string; user: User }>('POST', '/auth/login', {
      email,
      password,
      captcha_id:     captcha?.id,
      captcha_answer: captcha?.answer,
    }),
  me:     () => request<{ user: User }>('GET', '/auth/me'),
  logout: () => request<void>('POST', '/auth/logout'),
  changePassword: (current_password: string, password: string, password_confirmation: string, email?: string) =>
    request<{ message: string }>('POST', '/auth/password/change', {
      current_password, password, password_confirmation,
      ...(email ? { email } : {}),
    }),
};

// ── User Management (superuser-only) ──────────────────────────────────

export const userManagementApi = {
  list:   () => request<{ users: User[] }>('GET', '/admin/users'),
  create: (data: { name: string; email: string; password: string; role: User['role']; phone?: string }) =>
    request<{ user: User }>('POST', '/admin/users', data),
  update: (id: number, data: Partial<{ name: string; email: string; role: User['role']; is_active: boolean; password: string }>) =>
    request<{ user: User }>('PUT', `/admin/users/${id}`, data),
  destroy: (id: number) =>
    request<{ message: string }>('DELETE', `/admin/users/${id}`),
};

// ── Services ──────────────────────────────────────────────────────────

export const servicesApi = {
  list: () => request<{ services: ServiceDefinition[] }>('GET', '/services'),
  get:  (code: string) => request<{ service: ServiceDefinition }>('GET', `/services/${code}`),
};

// ── Projects ──────────────────────────────────────────────────────────

export const projectsApi = {
  list:   () => request<{ projects: Project[] }>('GET', '/projects'),
  get:    (id: number) => request<{ project: Project }>('GET', `/projects/${id}`),
  create: (data: Partial<Pick<Project, 'name_ar' | 'name_en' | 'type' | 'area_m2' | 'city' | 'contract_no'>> & { engineer_id: number }) =>
    request<{ project: Project }>('POST', '/projects', data),
  quota:  () => request<OfficeQuota>('GET', '/projects/quota'),
};

/** Per-engineer quota row (returned inside OfficeQuota.engineers). */
export interface EngineerQuota {
  engineer_id: number;
  engineer_name_ar: string;
  year: number;
  quota_m2: number | null;
  used_m2: number;
  remaining_m2: number | null;
  percent_used: number | null;
  projects_count: number;
  unlimited: boolean;
}

export interface OfficeQuota {
  year: number;
  totals: {
    quota_m2: number | null;
    used_m2: number;
    remaining_m2: number | null;
    percent_used: number | null;
    projects_count: number;
    unlimited: boolean;
    engineers_count: number;
  };
  engineers: EngineerQuota[];
}

// ── Engineers ────────────────────────────────────────────────────────

export const engineersApi = {
  list:   () => request<{ engineers: Engineer[] }>('GET', '/engineers'),
  get:    (id: number) => request<{ engineer: Engineer }>('GET', `/engineers/${id}`),
  create: (data: Partial<Pick<Engineer, 'name_ar' | 'name_en' | 'membership_number' | 'specialization' | 'phone' | 'email' | 'annual_quota_m2'>>) =>
    request<{ engineer: Engineer }>('POST', '/engineers', data),
  quota:  (id: number) => request<EngineerQuota>('GET', `/engineers/${id}/quota`),
};

// ── Applications ──────────────────────────────────────────────────────

export interface StageAction {
  id: string;
  label_ar: string;
  label_en: string;
  variant: 'primary' | 'success' | 'warn' | 'danger' | 'neutral';
  requires_notes: boolean;
  decision: string | null;
  annotation: Record<string, unknown>;
  allowed_roles: string[];
}

export const applicationsApi = {
  list:   () => request<{ applications: Application[] }>('GET', '/applications'),
  get:    (id: number) => request<{ application: Application; available_actions?: StageAction[] }>('GET', `/applications/${id}`),
  create: (service_code: string, data: Record<string, unknown>, project_id?: number) =>
    request<{ application: Application }>('POST', '/applications', {
      service_code, data,
      ...(project_id ? { project_id } : {}),
    }),
  update: (id: number, data: Record<string, unknown>) =>
    request<{ application: Application }>('PUT', `/applications/${id}`, { data }),
  submit: (id: number) => request<{ application: Application }>('POST', `/applications/${id}/submit`),
  uploadDocument: (id: number, document_id: string, file: File) => {
    const fd = new FormData();
    fd.append('document_id', document_id);
    fd.append('file', file);
    return request<{ document: ApplicationDocument }>('POST', `/applications/${id}/documents`, fd, true);
  },
  reviewQueue: () => request<{ applications: Application[] }>('GET', '/review/queue'),
  claim:  (id: number) => request<{ application: Application }>('POST', `/applications/${id}/claim`),
  decide: (id: number, decision: string, notes?: string, annotations?: unknown) =>
    request<{ review: unknown; application: Application }>('POST', `/applications/${id}/decide`, { decision, notes, annotations }),
  confirmPayment: (id: number, payment_reference: string) =>
    request<{ application: Application }>('POST', `/applications/${id}/confirm-payment`, { payment_reference }),
  issueCertificate: (id: number) =>
    request<{ certificate: Certificate; application: Application }>('POST', `/applications/${id}/issue-certificate`),
};

// ── Review API (alias for reviewer pages) ────────────────────────────

export const reviewApi = {
  queue:            () => applicationsApi.reviewQueue(),
  get:              (id: number) => applicationsApi.get(id),
  claim:            (id: number) => applicationsApi.claim(id),
  decide:           (id: number, decision: string, notes?: string, annotations?: unknown) =>
                      applicationsApi.decide(id, decision, notes, annotations),
  confirmPayment:   (id: number, ref: string) => applicationsApi.confirmPayment(id, ref),
  issueCertificate: (id: number) => applicationsApi.issueCertificate(id),
};

// ── Integration / Nashmi (uses integration key, not bearer token) ─────
// Integration key read from Vite env var — set VITE_INTEGRATION_KEY in .env
// Never hardcode secrets in source code.

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

// ── Admin ─────────────────────────────────────────────────────────────

export const adminApi = {
  dashboard:       () => request<{ stats: DashboardStats }>('GET', '/admin/dashboard'),
  listUsers:       () => request<{ users: User[] }>('GET', '/admin/users'),
  createUser:      (data: unknown) => request<{ user: User }>('POST', '/admin/users', data),
  updateUser:      (id: number, data: unknown) => request<{ user: User }>('PUT', `/admin/users/${id}`, data),
  allApplications: (status?: string) =>
    request<{ data: Application[] }>('GET', `/admin/applications${status ? `?status=${status}` : ''}`),
  auditLogs: () => request<{ data: unknown[] }>('GET', '/admin/audit-logs'),

  /** List all services including drafts */
  listServices: () =>
    request<{ services: ServiceDefinition[] }>('GET', '/admin/services'),

  /** Get single service with full schema */
  getService: (id: number) =>
    request<{ service: ServiceDefinition }>('GET', `/admin/services/${id}`),

  /** Update service status */
  updateServiceStatus: (id: number, status: 'active' | 'inactive' | 'draft') =>
    request<{ service: ServiceDefinition }>('PATCH', `/services/${id}/status`, { status }),

  /** Update service schema/metadata */
  updateService: (id: number, data: Partial<{
    name_ar: string; name_en: string; description_ar: string;
    description_en: string; schema: Record<string, unknown>; status: string;
  }>) => request<{ service: ServiceDefinition }>('PUT', `/services/${id}`, data),

  /** Lock / unlock a service. Every content mutation is refused with 423
   *  while the service is locked — call unlock before editing. */
  lockService:   (id: number) =>
    request<{ service: ServiceDefinition; message: string }>('POST', `/admin/services/${id}/lock`),
  unlockService: (id: number) =>
    request<{ service: ServiceDefinition; message: string }>('POST', `/admin/services/${id}/unlock`),

  /** FR-019: Apply a natural-language change to an existing schema via Claude */
  chatUpdateSchema: (current_schema: Record<string, unknown>, message: string) =>
    request<{ updated_schema: Record<string, unknown>; explanation: string; changes: string[]; tokens_used: number }>(
      'POST', '/admin/services/chat-schema', { current_schema, message }
    ),

  /** FR-018: Generate ESP v2 schema from SRS text via Claude API (server-side).
   *  Applies full Hukm Governance Layer — returns verdict, validation_report,
   *  blockers, hukm_ir, and generation_audit alongside the schema. */
  generateSchema: (srs_text: string, service_code?: string, mode: 'azimah' | 'rukhsa' = 'azimah', cycle_id?: number) =>
    request<{
      schema:             Record<string, unknown>;
      verdict:            'sahih' | 'fasid' | 'batil';
      validation_report:  Record<string, unknown>;
      blockers:           Array<{ type: string; severity: string; decision: string; message: string; resolution: string }>;
      hukm_ir:            Array<Record<string, unknown>> | null;
      generation_audit:   Record<string, unknown>;
      mode:               'azimah' | 'rukhsa';
      tokens_used:        number;
      model:              string;
    }>('POST', '/admin/services/generate-schema', { srs_text, service_code, mode, cycle_id }),

  generateSchemaFromFile: (srsFile: File, nfrFile?: File | null, service_code?: string, mode: 'azimah' | 'rukhsa' = 'azimah', cycle_id?: number) => {
    const fd = new FormData();
    fd.append('srs_file', srsFile);
    if (nfrFile) fd.append('nfr_file', nfrFile);
    if (service_code) fd.append('service_code', service_code);
    fd.append('mode', mode);
    if (cycle_id) fd.append('cycle_id', String(cycle_id));
    return request<{
      schema:             Record<string, unknown>;
      verdict:            'sahih' | 'fasid' | 'batil';
      validation_report:  Record<string, unknown>;
      blockers:           Array<{ type: string; severity: string; decision: string; message: string; resolution: string }>;
      hukm_ir:            Array<Record<string, unknown>> | null;
      generation_audit:   Record<string, unknown>;
      mode:               'azimah' | 'rukhsa';
      tokens_used:        number;
      model:              string;
    }>('POST', '/admin/services/generate-schema-from-file', fd, true);
  },

  /** Save a generated schema as a new ServiceDefinition */
  saveService: (data: {
    code: string; name_ar: string; name_en: string;
    description_ar?: string; description_en?: string;
    currency?: string; schema: Record<string, unknown>;
    status: 'draft' | 'active';
  }) => request<{ service: ServiceDefinition }>('POST', '/services', data),
};
