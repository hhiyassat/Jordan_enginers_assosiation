import { applicationsApi } from './applications';
import { request } from './http';

/**
 * Reviewer aliases — thin wrapper over applicationsApi so reviewer pages
 * can import a domain-labelled surface. Split out of client.ts (JORD-22).
 */

export interface ReviewDashboardResponse {
  stats: {
    my_in_progress:     number;
    queue_available:    number;
    overdue:            number;
    decided_this_week:  number;
    decided_this_month: number;
  };
  by_decision_this_month: {
    approved:                number;
    rejected:                number;
    modifications_requested: number;
  };
  recent_decisions: Array<{
    id:              number;
    application_id:  number;
    reference:       string | null;
    service_name_ar: string | null;
    service_name_en: string | null;
    decision:        string;
    created_at:      string | null;
  }>;
  my_in_progress: Array<{
    id:              number;
    reference:       string;
    service_name_ar: string | null;
    service_name_en: string | null;
    sla_deadline:    string | null;
    sla_breached:    boolean;
  }>;
}

export const reviewApi = {
  // JORD-88 (PM): reviewer summary widget data.
  dashboard:        () => request<ReviewDashboardResponse>('GET', '/review/dashboard'),
  queue:            () => applicationsApi.reviewQueue(),
  get:              (id: number) => applicationsApi.get(id),
  claim:            (id: number) => applicationsApi.claim(id),
  // PR#1: unclaim / return to queue.
  release:          (id: number) => applicationsApi.release(id),
  decide:           (id: number, decision: string, notes?: string, annotations?: unknown) =>
                      applicationsApi.decide(id, decision, notes, annotations),
  confirmPayment:   (id: number, ref: string) => applicationsApi.confirmPayment(id, ref),
  issueCertificate: (id: number) => applicationsApi.issueCertificate(id),
};
