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
