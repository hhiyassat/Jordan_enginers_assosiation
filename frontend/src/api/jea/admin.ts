import type { ServiceDefinition, ServiceSchema } from '../../types';
import { request } from '../http';

/**
 * JEA admin API (Workstream 6 split from api/admin.ts).
 *
 * Every endpoint here encodes a JEA-specific concept: the service
 * catalog, the fee editor, the AI schema generator (a plugin
 * candidate), office boost flags, dues, complaints, legal fines,
 * supervision transfers.
 *
 * The legacy `adminApi` barrel in api/admin.ts re-exports
 * `{ ...platformAdminApi, ...jeaAdminApi }` so nothing importing
 * `adminApi.foo` needs to move today. Workstream 8+ splits this
 * file into per-module api clients (`jea-services`, `jea-discipline`,
 * `jea-dues`, `jea-fees`).
 */

export const jeaAdminApi = {
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

  /** JORD-85: compact fee-only listing for the admin fee editor grid.
   *  Returns one row per service with just the fee sub-block. */
  listServiceFees: () => request<{
    fees: Array<{
      id: number;
      code: string;
      parent_code: string | null;
      name_ar: string;
      name_en: string;
      status: 'active' | 'inactive' | 'draft';
      is_locked: boolean;
      fee: {
        type?: 'fixed' | 'per_unit' | 'free';
        amount?: number;
        currency?: string;
        basis?: string;
        rate?: number;
        source?: string;
      } | null;
    }>;
  }>('GET', '/admin/service-fees'),

  /** JORD-85: focused fee editor. Sends only the fee payload — no need
   *  to round-trip the whole schema for a rate change. */
  updateServiceFee: (
    id: number,
    payload:
      | { type: 'fixed'; amount: number; currency?: string; notes?: string }
      | { type: 'per_unit'; basis: string; rate: number; currency?: string; notes?: string }
      | { type: 'free'; notes?: string }
  ) => request<{ service: ServiceDefinition }>('PATCH', `/admin/services/${id}/fee`, payload),

  /** Update service schema/metadata. JORD-75/76: `schema` is now typed
   *  as ServiceSchema so callers stop double-casting parsedSchema. */
  updateService: (id: number, data: Partial<{
    name_ar: string; name_en: string; description_ar: string;
    description_en: string; schema: ServiceSchema; status: string;
  }>) => request<{ service: ServiceDefinition }>('PUT', `/services/${id}`, data),

  /** Lock / unlock a service. Every content mutation is refused with 423
   *  while the service is locked — call unlock before editing. */
  lockService:   (id: number) =>
    request<{ service: ServiceDefinition; message: string }>('POST', `/admin/services/${id}/lock`),
  unlockService: (id: number) =>
    request<{ service: ServiceDefinition; message: string }>('POST', `/admin/services/${id}/unlock`),

  /** FR-019: Apply a natural-language change to an existing schema via Claude.
   *  JORD-75/76: `current_schema` accepts ServiceSchema so callers don't cast. */
  chatUpdateSchema: (current_schema: ServiceSchema, message: string) =>
    request<{ updated_schema: Record<string, unknown>; explanation: string; changes: string[]; tokens_used: number }>(
      'POST', '/admin/services/chat-schema', { current_schema, message }
    ),

  /** FR-018: Generate ESP v2 schema from SRS text via Claude API (server-side).
   *  Applies full Hukm Governance Layer — returns verdict, validation_report,
   *  blockers, hukm_ir, and generation_audit alongside the schema. */
  generateSchema: (srs_text: string, service_code?: string, mode: 'azimah' | 'rukhsa' = 'azimah', cycle_id?: number) =>
    request<{
      // JORD-76: schema is a ServiceSchema; the two Hukm payloads are
      // typed as unknown so the caller narrows to its own view type
      // instead of double-casting through unknown.
      schema:             ServiceSchema;
      verdict:            'sahih' | 'fasid' | 'batil';
      validation_report:  unknown;
      blockers:           Array<{ type: string; severity: string; decision: string; message: string; resolution: string }>;
      hukm_ir:            unknown[] | null;
      generation_audit:   unknown;
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
      // JORD-76: match generateSchema's tightened shape so callers
      // consume both endpoints with the same typing.
      schema:             ServiceSchema;
      verdict:            'sahih' | 'fasid' | 'batil';
      validation_report:  unknown;
      blockers:           Array<{ type: string; severity: string; decision: string; message: string; resolution: string }>;
      hukm_ir:            unknown[] | null;
      generation_audit:   unknown;
      mode:               'azimah' | 'rukhsa';
      tokens_used:        number;
      model:              string;
    }>('POST', '/admin/services/generate-schema-from-file', fd, true);
  },

  /** Save a generated schema as a new ServiceDefinition.
   *  JORD-76: `schema` typed as ServiceSchema so callers don't cast. */
  saveService: (data: {
    code: string; name_ar: string; name_en: string;
    description_ar?: string; description_en?: string;
    currency?: string; schema: ServiceSchema;
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

  /**
   * JORD-83 UI: supervision transfer queue. Auto-populated by
   * ComplaintController on suspension_2yr / deregistration; admin
   * assigns receiving office; target accepts or declines.
   */
  listSupervisionTransfers: (status?: 'pending' | 'assigned' | 'accepted' | 'declined') =>
    request<{
      transfers: Array<{
        id: number;
        status: 'pending' | 'assigned' | 'accepted' | 'declined';
        fee_waived: boolean;
        notes: string | null;
        assigned_at: string | null;
        accepted_at: string | null;
        created_at: string;
        application: {
          id: number;
          reference_number: string;
          status: string;
          service_definition: { id: number; code: string; name_ar: string; name_en: string } | null;
        } | null;
        source_office: { id: number; name: string; email: string } | null;
        target_office: { id: number; name: string; email: string } | null;
      }>;
    }>('GET', status ? `/admin/supervision-transfers?status=${status}` : '/admin/supervision-transfers'),

  assignSupervisionTransfer: (id: number, target_office_user_id: number, notes?: string) =>
    request<{ message: string }>('POST', `/admin/supervision-transfers/${id}/assign`, { target_office_user_id, notes }),

  acceptOrDeclineSupervisionTransfer: (id: number, outcome: 'accept' | 'decline', notes?: string) =>
    request<{ message: string }>('POST', `/admin/supervision-transfers/${id}/accept-decline`, { outcome, notes }),
};
