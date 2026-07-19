import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { ReviewQueue } from './ReviewQueue';
import type { Application, ServiceDefinition } from '../../types';

const mockQueue = vi.fn();
vi.mock('../../api/client', () => ({
  reviewApi: { queue: () => mockQueue() },
}));

function service(): ServiceDefinition {
  return {
    id: 1, code: 'DRW-P-004', name_ar: 'مخططات الهدم', name_en: 'Demolition', currency: 'JOD',
    schema: {
      service_code: 'DRW-P-004', name_ar: 'مخططات الهدم', name_en: 'Demolition',
      workflow: {
        stages: [
          { id: 'office_submission',    role: 'applicant', label_ar: 'تقديم الطلب',  label_en: 'Submit',  sla_hours: 24 },
          { id: 'public_safety_review', role: 'auditor',   label_ar: 'مراجعة السلامة', label_en: 'Safety',  sla_hours: 48 },
          { id: 'payment',              role: 'staff',     label_ar: 'الدفع',          label_en: 'Payment', sla_hours: 24 },
        ],
      },
      fee: { type: 'fixed', amount: 0, currency: 'JOD' },
      sections: [], fields: [], documents: [],
    },
  } as ServiceDefinition;
}

function app(overrides: Partial<Application>): Application {
  return {
    id: 1, reference_number: 'A-100',
    status: 'submitted', current_stage: 'public_safety_review',
    fee_amount: 0, payment_status: 'waived',
    service_definition: service(),
    applicant: { id: 4, name: 'أحمد', email: 'ahmed@t.esp', role: 'applicant', organization_id: 1 },
    can_claim: true, current_stage_role: 'auditor',
    ...overrides,
  } as Application;
}

beforeEach(() => { mockQueue.mockReset(); });

describe('ReviewQueue', () => {
  it('renders the current-stage Arabic label as a badge on each row', async () => {
    // Regression pin — before the queue filter fix, staff saw rows whose
    // current stage was auditor-owned and clicked into a broken claim.
    // The label lets the reviewer verify their role matches at a glance.
    mockQueue.mockResolvedValue({ applications: [app({})] });
    render(<MemoryRouter><ReviewQueue /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('A-100')).toBeInTheDocument());

    expect(screen.getByText('مراجعة السلامة')).toBeInTheDocument();
  });

  it('empty state shows the celebratory 🎉 message when nothing is queued', async () => {
    mockQueue.mockResolvedValue({ applications: [] });
    render(<MemoryRouter><ReviewQueue /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('لا توجد طلبات معلقة')).toBeInTheDocument());
  });

  it('shows the "قيد مراجعتك" chip on applications the actor has already claimed', async () => {
    mockQueue.mockResolvedValue({ applications: [app({ assigned_reviewer_id: 42, status: 'under_review' })] });
    render(<MemoryRouter><ReviewQueue /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText(/قيد مراجعتك/)).toBeInTheDocument());
  });
});
