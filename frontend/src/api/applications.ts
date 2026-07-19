import type { Application, ApplicationDocument, Certificate } from '../types';
import { request } from './http';

/**
 * Applications domain — draft / submit / claim / decide / pay / issue.
 * Split out of client.ts (JORD-22).
 */

export interface StageAction {
  id: string;
  label_ar: string;
  label_en: string;
  variant: 'primary' | 'success' | 'warn' | 'danger' | 'neutral';
  requires_notes: boolean;
  decision: string | null;
  annotation: Record<string, unknown>;
  allowed_roles: string[];
}

export const applicationsApi = {
  list:   () => request<{ applications: Application[] }>('GET', '/applications'),
  get:    (id: number) => request<{ application: Application; available_actions?: StageAction[] }>('GET', `/applications/${id}`),
  create: (service_code: string, data: Record<string, unknown>, project_id?: number) =>
    request<{ application: Application }>('POST', '/applications', {
      service_code, data,
      ...(project_id ? { project_id } : {}),
    }),
  update: (id: number, data: Record<string, unknown>) =>
    request<{ application: Application }>('PUT', `/applications/${id}`, { data }),
  submit: (id: number) => request<{ application: Application }>('POST', `/applications/${id}/submit`),
  uploadDocument: (id: number, document_id: string, file: File) => {
    const fd = new FormData();
    fd.append('document_id', document_id);
    fd.append('file', file);
    return request<{ document: ApplicationDocument }>('POST', `/applications/${id}/documents`, fd, true);
  },
  reviewQueue: () => request<{ applications: Application[] }>('GET', '/review/queue'),
  claim:  (id: number) => request<{ application: Application }>('POST', `/applications/${id}/claim`),
  decide: (id: number, decision: string, notes?: string, annotations?: unknown) =>
    request<{ review: unknown; application: Application }>('POST', `/applications/${id}/decide`, { decision, notes, annotations }),
  confirmPayment: (id: number, payment_reference: string) =>
    request<{ application: Application }>('POST', `/applications/${id}/confirm-payment`, { payment_reference }),
  issueCertificate: (id: number) =>
    request<{ certificate: Certificate; application: Application }>('POST', `/applications/${id}/issue-certificate`),
};
