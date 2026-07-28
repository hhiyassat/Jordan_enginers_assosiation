const fs = require('fs');

const schemaContent = `export interface SchemaFieldOption {
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
  options_endpoint?: string;
  conditional?: { field: string; value: string };
  display_order?: number;
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
  category?: 'REPORT' | 'CONTRACT' | string;
  report_type?: string;
  manual_reference_ids?: number[];
  signature_requirements?: {
    signing_role?: string;
    signing_engineer_name_required?: boolean;
    signing_engineer_registration_required?: boolean;
    source?: string;
  };
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

export interface ComplianceNote {
  code: string;
  source?: string;
  page?: number;
  category?: 'retention' | 'fee' | 'eligibility' | 'conduct' | string;
  label_ar: string;
  label_en: string;
  body_ar: string;
  body_en: string;
  severity: 'info' | 'warning' | 'blocker';
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
  compliance_notes?: ComplianceNote[];
}
`;

const serviceContent = `import { ServiceSchema } from './schema';

export interface ServiceDefinition {
  id: number;
  code: string;
  parent_code?: string | null;
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
  phase?: 1 | 2 | 3 | 4 | 5 | null;
  variant_keys?: string[];
  is_locked?: boolean;
}
`;

const userContent = `export interface Organization {
  id: number;
  name_ar: string;
  name_en: string;
  slug: string;
}

export interface User {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  role: 'applicant' | 'staff' | 'auditor' | 'admin' | 'superuser';
  organization_id: number;
  must_change_password?: boolean;
  can_manage_users?: boolean;
  is_active?: boolean;
  created_at?: string;
  presence?: 'online' | 'idle' | 'offline';
  last_seen_at?: string | null;
}

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
`;

const applicationContent = `import type { User } from './user';
import type { ServiceDefinition } from './service';

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
  can_claim?: boolean;
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
  contract_no?: string | null;
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
  certificate_pdf_url?: string | null;
  supervision_expiry?: string | null;
  output_validity_expiry?: string | null;
  created_at: string;
  updated_at: string;
}
`;

const dashboardContent = `export interface DashboardStats {
  total_applications: number;
  pending_review: number;
  under_review: number;
  approved_today: number;
  certificates_issued: number;
  active_services: number;
  total_users: number;
}
`;

const indexContent = `export * from './schema';
export * from './service';
export * from './user';
export * from './application';
export * from './dashboard';
`;

fs.writeFileSync('frontend/src/shared/types/schema.ts', schemaContent);
fs.writeFileSync('frontend/src/shared/types/service.ts', serviceContent);
fs.writeFileSync('frontend/src/shared/types/user.ts', userContent);
fs.writeFileSync('frontend/src/shared/types/application.ts', applicationContent);
fs.writeFileSync('frontend/src/shared/types/dashboard.ts', dashboardContent);
fs.writeFileSync('frontend/src/shared/types/index.ts', indexContent);

// Also overwrite src/types/index.ts to just export from shared
const oldIndexContent = `export * from '../shared/types';\n`;
fs.writeFileSync('frontend/src/types/index.ts', oldIndexContent);
