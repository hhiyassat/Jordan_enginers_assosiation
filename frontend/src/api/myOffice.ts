import { request } from './http';

/**
 * JORD-84: applicant self-service — office user's read-only view of
 * their own dues, complaints filed against them, and sanctions.
 *
 * Pay + decide flows stay admin-only. The office CAN see what's owed
 * and what's pending against them, but CANNOT record payments or
 * reverse sanctions from this surface.
 */

export interface MyDuesResponse {
  me: {
    id: number;
    name: string;
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
}

export interface MyComplaint {
  id: number;
  kind: 'fee_undercutting' | 'contracting_ban' | 'safety_violation' | 'other';
  description: string;
  status: 'open' | 'investigating' | 'decided' | 'dismissed';
  investigation_deadline: string;
  decided_at: string | null;
  created_at: string;
  // reporter_display is stripped server-side per manual p.278.
  reporter?: { id: number; name: string } | null;
  sanctions: Array<{ id: number; kind: string; effective_from: string; effective_until: string | null }>;
}

export interface MySanction {
  id: number;
  kind: 'warning' | 'suspension_1yr' | 'suspension_2yr' | 'deregistration';
  effective_from: string;
  effective_until: string | null;
  reason: string;
}

export const myOfficeApi = {
  dues:       () => request<MyDuesResponse>('GET', '/my/dues'),
  complaints: () => request<{ complaints: MyComplaint[] }>('GET', '/my/complaints'),
  sanctions:  () => request<{ sanctions: MySanction[] }>('GET', '/my/sanctions'),
};
