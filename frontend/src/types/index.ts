// ESP v2 — TypeScript Types
// NFR-003: Bilingual (Arabic/English) throughout

export interface Organization {
  id: number;
  name_ar: string;
  name_en: string;
  slug: string;
}

export interface User {
  id: number;
  name: string;
  email: string;
  role: 'applicant' | 'staff' | 'auditor' | 'admin' | 'superuser';
  organization_id: number;
  // Present on /auth/me and /auth/login payloads. When true the SPA routes
  // the user to /auth/change-credentials before anything else.
  must_change_password?: boolean;
  // True only for the superuser role. Drives the "إدارة المستخدمين" nav link.
  can_manage_users?: boolean;
  is_active?: boolean;
  created_at?: string;
}

// ── Schema types ────────────────────────────────────────────────────

export interface SchemaFieldOption {
  value: string;
  label_ar: string;
  label_en: string;
}

export interface SchemaField {
  id: string;
  label_ar: string;
  label_en: string;
  type: 'text' | 'textarea' | 'select' | 'radio' | 'multiselect' | 'checkbox_group' | 'number' | 'date' | 'email';
  required: boolean;
  section?: string;
  placeholder_ar?: string;
  placeholder_en?: string;
  description_ar?: string;
  description_en?: string;
  pattern?: string;
  min_length?: number;
  max_length?: number;
  min?: number;
  max?: number;
  options?: SchemaFieldOption[];
  conditional?: { field: string; value: string };
}

export interface SchemaSection {
  id: string;
  label_ar: string;
  label_en: string;
}

export interface SchemaDocument {
  id: string;
  label_ar: string;
  label_en: string;
  required: boolean;
  accept: string[];
  max_size_mb: number;
  description_ar?: string;
  description_en?: string;
  conditional?: { field: string; value: string };
}

export interface SchemaWorkflowStage {
  id: string;
  label_ar: string;
  label_en: string;
  role: string;
  sla_hours?: number;
  actions: string[];
}

export interface WorkflowVariant {
  source?: string;
  label_ar: string;
  label_en: string;
  stages: SchemaWorkflowStage[];
}

export interface ServiceSchema {
  service_code: string;
  name_ar: string;
  name_en: string;
  version?: string;
  fields: SchemaField[];
  sections?: SchemaSection[];
  documents: SchemaDocument[];
  workflow: {
    stages: SchemaWorkflowStage[];
    metadata?: Record<string, unknown>;
    variants?: Record<string, WorkflowVariant>;
  };
  flowchart_source?: string;
  fee: {
    type: 'fixed' | 'tiered' | 'formula';
    amount?: number;
    field?: string;
    tiers?: Record<string, number>;
    default?: number;
    currency?: string;
  };
  certificate?: {
    validity_months: number;
    title_ar: string;
    title_en: string;
    fields_on_cert: string[];
  };
}

// ── Service Definition ──────────────────────────────────────────────

export interface ServiceDefinition {
  id: number;
  code: string;
  parent_code?: string | null;
  /** Optional visual grouping within a parent (e.g. استطلاع الموقع vs فحص المواد under JEA-SURV) */
  subcategory_ar?: string | null;
  subcategory_en?: string | null;
  name_ar: string;
  name_en: string;
  description_ar?: string;
  description_en?: string;
  category?: string;
  base_fee?: number | string | null;
  sla_hours?: number | null;
  currency: string;
  schema?: ServiceSchema;
  status?: 'active' | 'inactive' | 'draft';
  /** JEA delivery phase 1..5 (colored dot/pill on the UI); null = unclassified */
  phase?: 1 | 2 | 3 | 4 | 5 | null;
  /** Keys of alternate workflows available (e.g. ['modification']) */
  variant_keys?: string[];
  /**
   * When true, the backend refuses every mutation with 423. Admin +
   * superuser can toggle via POST /admin/services/{id}/lock|unlock.
   */
  is_locked?: boolean;
}

// ── Engineer ────────────────────────────────────────────────────────

export interface Engineer {
  id: number;
  organization_id: number;
  office_user_id: number;
  name_ar: string;
  name_en?: string | null;
  membership_number: string;
  specialization?: string | null;
  phone?: string | null;
  email?: string | null;
  annual_quota_m2?: number | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

// ── Project ─────────────────────────────────────────────────────────

export interface Project {
  id: number;
  organization_id: number;
  owner_user_id: number;
  engineer_id?: number | null;
  engineer?: Pick<Engineer, 'id' | 'name_ar' | 'name_en' | 'membership_number'> | null;
  name_ar: string;
  name_en?: string | null;
  type?: string | null;
  area_m2?: number | null;
  city?: string | null;
  contract_no?: string | null;
  request_no?: string | null;
  status: 'active' | 'pending' | 'archived';
  created_at: string;
  updated_at: string;
}

// ── Application ─────────────────────────────────────────────────────

export type ApplicationStatus =
  | 'draft'
  | 'submitted'
  | 'under_review'
  | 'modifications_requested'
  | 'approved'
  | 'rejected'
  | 'certificate_issued';

export interface ApplicationDocument {
  id: number;
  document_id: string;
  original_filename: string;
  mime_type: string;
  size_bytes: number;
  status: 'pending' | 'accepted' | 'rejected';
  created_at: string;
}

export interface ApplicationReview {
  id: number;
  stage_id: string;
  stage?: string;
  decision: string;
  notes?: string;
  annotations?: Record<string, unknown>;
  review_round: number;
  reviewer?: Pick<User, 'id' | 'name' | 'role'>;
  /**
   * Attached by GET /review/queue: true when the current actor's role
   * matches the application's current stage. The queue is already filtered
   * server-side, so this defaults to true for every row a non-admin sees;
   * admin (who sees everything) uses it to grey out rows they can't act on.
   */
  can_claim?: boolean;
  /** Role required by the current stage — used for "waiting for {role}" hints. */
  current_stage_role?: string | null;
  created_at: string;
}

export interface Certificate {
  id: number;
  certificate_number: string;
  status: 'active' | 'revoked' | 'expired';
  issued_date: string;
  expiry_date: string;
}

export interface Application {
  id: number;
  reference_number: string;
  status: ApplicationStatus;
  current_stage?: string;
  data?: Record<string, unknown>;
  fee_amount: number;
  payment_status: 'pending' | 'paid' | 'waived';
  sla_deadline?: string;
  sla_breached?: boolean;
  review_round: number;
  assigned_reviewer_id?: number | null;
  submitted_at?: string;
  service_definition?: ServiceDefinition;
  applicant?: Pick<User, 'id' | 'name' | 'email'>;
  documents?: ApplicationDocument[];
  reviews?: ApplicationReview[];
  certificate?: Certificate;
  created_at: string;
  updated_at: string;
}

// ── Admin stats ─────────────────────────────────────────────────────

export interface DashboardStats {
  total_applications: number;
  pending_review: number;
  under_review: number;
  approved_today: number;
  certificates_issued: number;
  active_services: number;
  total_users: number;
}
