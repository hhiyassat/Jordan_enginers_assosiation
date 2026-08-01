import { request } from './http';

/**
 * Office-registration API.
 *
 * PUBLIC endpoint (submit) — no auth. Rate-limited server-side (5/min per IP).
 * ADMIN endpoints (index/get/approve/reject) — require admin or superuser role.
 *
 * See docs/architecture/office-registration-flow.md for the full contract.
 */

export interface OfficeRegistrationEngineerInput {
  name_ar: string;
  name_en?: string;
  membership_number: string;
  /** 'structural' | 'architectural' | 'electrical' | 'mechanical' | 'environmental' | 'civil' (alias) */
  specialization: string;
  phone?: string;
  email?: string;
}

export interface OfficeRegistrationSubmitInput {
  office_name_ar:    string;
  office_name_en:    string;
  office_license_no: string;
  office_city:       string;
  office_address_ar: string;
  office_phone:      string;
  office_email:      string;
  engineers:         OfficeRegistrationEngineerInput[];
}

export interface OfficeRegistrationSubmitResponse {
  message:          string;
  reference_number: string;
  status:           'pending';
  id:               number;
}

export interface OfficeRegistrationRow {
  id:                       number;
  reference_number:         string;
  office_name_ar:           string;
  office_name_en:           string;
  office_license_no:        string;
  office_city:              string;
  office_address_ar:        string;
  office_phone:             string;
  office_email:             string;
  engineers:                OfficeRegistrationEngineerInput[];
  status:                   'pending' | 'approved' | 'rejected';
  review_notes:             string | null;
  reviewed_by_user_id:      number | null;
  reviewed_at:              string | null;
  approved_organization_id: number | null;
  created_at:               string;
  updated_at:               string;
  reviewer?: { id: number; name: string; email: string; role: string } | null;
}

export interface OfficeRegistrationsPaginated {
  registrations: {
    data: OfficeRegistrationRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export const officeRegistrationsApi = {
  /** Public — no auth required. */
  submit: (payload: OfficeRegistrationSubmitInput) =>
    request<OfficeRegistrationSubmitResponse>('POST', '/office-registrations', payload),

  /** Admin — list by status ('pending' default; 'all' for everything). */
  index: (status: 'pending' | 'approved' | 'rejected' | 'all' = 'pending') =>
    request<OfficeRegistrationsPaginated>('GET', `/admin/office-registrations?status=${status}`),

  /** Admin — single registration by id. */
  get: (id: number) =>
    request<{ registration: OfficeRegistrationRow }>('GET', `/admin/office-registrations/${id}`),

  /** Admin — approve + provision Org/User/Engineers. */
  approve: (id: number, review_notes?: string) =>
    request<{ message: string; registration_id: number; organization_id: number }>(
      'POST', `/admin/office-registrations/${id}/approve`, { review_notes },
    ),

  /** Admin — reject (notes required). */
  reject: (id: number, review_notes: string) =>
    request<{ message: string; registration_id: number }>(
      'POST', `/admin/office-registrations/${id}/reject`, { review_notes },
    ),
};
