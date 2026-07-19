import { applicationsApi } from './applications';

/**
 * Reviewer aliases — thin wrapper over applicationsApi so reviewer pages
 * can import a domain-labelled surface. Split out of client.ts (JORD-22).
 */
export const reviewApi = {
  queue:            () => applicationsApi.reviewQueue(),
  get:              (id: number) => applicationsApi.get(id),
  claim:            (id: number) => applicationsApi.claim(id),
  decide:           (id: number, decision: string, notes?: string, annotations?: unknown) =>
                      applicationsApi.decide(id, decision, notes, annotations),
  confirmPayment:   (id: number, ref: string) => applicationsApi.confirmPayment(id, ref),
  issueCertificate: (id: number) => applicationsApi.issueCertificate(id),
};
