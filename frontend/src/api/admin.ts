import type { Application, DashboardStats, ServiceDefinition, User } from '../types';
import { request } from './http';

/**
 * Admin domain — dashboard, user CRUD, application listing, audit log,
 * service catalog, AI schema generator, service locking. Split out of
 * client.ts (JORD-22).
 *
 * JORD-35: `allApplicationsPaginated()` returns a Laravel-style meta
 * envelope so consumers can render page controls + server-side search.
 * The legacy `allApplications()` shim is preserved so pre-migration
 * callers still work while pages are moved over one at a time.
 */

/**
 * Laravel paginator envelope — matches ->paginate()'s top-level shape.
 * `data` is the current page's rows; the sibling fields carry paging
 * state so the UI can render prev/next controls without an extra
 * count call. Extra Laravel fields (first_page_url, path, …) are
 * intentionally omitted; add them here as pages start needing them.
 */
export interface Paginated<T> {
  data: T[];
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number | null;
  to: number | null;
}

export interface AllApplicationsFilters {
  status?: string;
  /** Free-text search — matches applicant name, reference number, service name. */
  q?: string;
  page?: number;
  per_page?: number;
}

function toQuery(filters: AllApplicationsFilters): string {
  const params = new URLSearchParams();
  if (filters.status)   params.set('status', filters.status);
  if (filters.q)        params.set('q', filters.q);
  if (filters.page)     params.set('page', String(filters.page));
  if (filters.per_page) params.set('per_page', String(filters.per_page));
  const qs = params.toString();
  return qs ? `?${qs}` : '';
}

export const adminApi = {
  // JORD-11: dashboard endpoint returns stats + by_status + recent so
  // the admin page can render more than counter tiles. `unknown` for
  // recent — the shape is validated inside the consumer.
  dashboard:       () => request<{
    stats: DashboardStats;
    by_status?: Record<string, number>;
    recent?: Array<Record<string, unknown>>;
  }>('GET', '/admin/dashboard'),
  listUsers:       () => request<{ users: User[] }>('GET', '/admin/users'),
  createUser:      (data: unknown) => request<{ user: User }>('POST', '/admin/users', data),
  updateUser:      (id: number, data: unknown) => request<{ user: User }>('PUT', `/admin/users/${id}`, data),

  /**
   * Legacy status-only listing. Kept for the AdminDashboard "recent
   * applications" widget until it migrates to the paginated variant.
   */
  allApplications: (status?: string) =>
    request<{ data: Application[] }>('GET', `/admin/applications${status ? `?status=${status}` : ''}`),

  /**
   * JORD-35: paginated + searchable listing. Backend returns a meta
   * envelope. Use with usePaginatedAdminApplications() in hooks.ts.
   */
  allApplicationsPaginated: (filters: AllApplicationsFilters) =>
    request<Paginated<Application>>('GET', `/admin/applications${toQuery(filters)}`),

  auditLogs: () => request<{ data: unknown[] }>('GET', '/admin/audit-logs'),

  /**
   * List every actual service (excludes the 7 top-level category tiles).
   * `categories` gives the group headers in canonical plan order so the
   * UI can render section headings without hardcoding Arabic names.
   */
  listServices: () =>
    request<{
      services: ServiceDefinition[];
      categories: Array<{ code: string; name_ar: string; name_en: string }>;
    }>('GET', '/admin/services'),

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

  /**
   * JORD-77: per-office boost flags + specialization-head toggles.
   * An "engineering office" is a User with role='applicant'; admins
   * pick an office first, then edit its flags.
   */
  listOffices: () => request<{
    offices: Array<{
      id: number; name: string; email: string;
      is_active: boolean;
      has_excellence_award: boolean;
      is_bit_khibra: boolean;
      has_iso_cert: boolean;
      engineer_count: number;
    }>;
  }>('GET', '/admin/offices'),

  getOfficeSettings: (officeId: number) => request<{
    office: {
      id: number; name: string; email: string;
      has_excellence_award: boolean;
      is_bit_khibra: boolean;
      has_iso_cert: boolean;
    };
    engineers: Array<{
      id: number; name_ar: string; name_en: string | null;
      membership_number: string; specialization: string | null;
      is_specialization_head: boolean;
    }>;
  }>('GET', `/admin/offices/${officeId}`),

  updateOfficeFlags: (officeId: number, flags: {
    has_excellence_award?: boolean;
    is_bit_khibra?: boolean;
    has_iso_cert?: boolean;
  }) => request<{ message: string }>('PATCH', `/admin/offices/${officeId}`, flags),

  updateOfficeEngineerSpecHead: (officeId: number, engineerId: number, is_specialization_head: boolean) =>
    request<{ message: string }>('PATCH', `/admin/offices/${officeId}/engineers/${engineerId}`, { is_specialization_head }),

  /**
   * JORD-79 UI: recurring obligations (F-04 registration + F-05 annual dues).
   * Admin lists an office's dues, seeds registration on-demand, and marks
   * paid with a payment reference. Late surcharge is computed server-side.
   */
  listOfficeDues: (officeId: number) => request<{
    office: {
      id: number; name: string;
      office_classification: string | null;
    };
    obligations: Array<{
      id: number;
      kind: 'registration' | 'annual_dues';
      period_year: number;
      period_label_ar: string | null;
      amount_jod: string;
      due_date: string;
      paid_at: string | null;
      payment_reference: string | null;
      late_surcharge_jod: string;
      total_paid_jod: string | null;
    }>;
    rate_table: Record<string, { registration: number; annual_dues: number }>;
  }>('GET', `/admin/offices/${officeId}/dues`),

  seedOfficeRegistration: (officeId: number) =>
    request<{ message: string }>('POST', `/admin/offices/${officeId}/dues/register`, {}),

  payDue: (obligationId: number, payment_reference: string) =>
    request<{ message: string }>('POST', `/admin/dues/${obligationId}/pay`, { payment_reference }),

  /**
   * JORD-81 UI: disciplinary complaints. Intake (POST /complaints)
   * lives outside the admin scope — any authenticated user can file.
   * These two are admin-only queue + decision endpoints.
   */
  listComplaints: () => request<{
    complaints: Array<{
      id: number;
      kind: 'fee_undercutting' | 'contracting_ban' | 'safety_violation' | 'other';
      description: string;
      status: 'open' | 'investigating' | 'decided' | 'dismissed';
      investigation_deadline: string;
      decided_at: string | null;
      created_at: string;
      target_office: { id: number; name: string } | null;
      reporter: { id: number; name: string } | null;
      reporter_display: string | null;
      sanctions: Array<{ id: number; kind: string; effective_from: string; effective_until: string | null }>;
    }>;
  }>('GET', '/admin/complaints'),

  decideComplaint: (
    id: number,
    payload:
      | { decision: 'sanction'; sanction_kind: 'warning' | 'suspension_1yr' | 'suspension_2yr' | 'deregistration'; reason: string; notes?: string }
      | { decision: 'dismiss'; notes?: string }
  ) => request<{ message: string; transfers_opened?: number }>('POST', `/admin/complaints/${id}/decide`, payload),

  /**
   * JORD-82 UI: legal fines (Art.14 owner fines for unlicensed contractor).
   * Bounds come from the backend (server-side source of truth so a
   * manual amendment doesn't require a frontend release to reflect).
   */
  listLegalFines: () => request<{
    fines: Array<{
      id: number;
      kind: 'unlicensed_contractor_small' | 'unlicensed_contractor_large';
      target_display: string;
      amount_jod: string;
      project_area_m2: number | null;
      reason: string;
      issued_at: string;
      paid_at: string | null;
      payment_reference: string | null;
      issued_by: { id: number; name: string } | null;
      application: { id: number; reference_number: string } | null;
    }>;
    bounds: Record<string, { min: number; max: number; area_threshold_m2: number | null }>;
  }>('GET', '/admin/legal-fines'),

  issueLegalFine: (payload: {
    kind: 'unlicensed_contractor_small' | 'unlicensed_contractor_large';
    amount_jod: number;
    target_display: string;
    project_area_m2?: number;
    application_id?: number;
    reason: string;
  }) => request<{ message: string }>('POST', '/admin/legal-fines', payload),

  payLegalFine: (id: number, payment_reference: string) =>
    request<{ message: string }>('POST', `/admin/legal-fines/${id}/pay`, { payment_reference }),
};
