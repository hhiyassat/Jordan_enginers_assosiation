import type { User } from './user';
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
