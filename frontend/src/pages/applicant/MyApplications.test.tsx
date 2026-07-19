import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { MyApplications } from './MyApplications';
import type { Application, ServiceDefinition } from '../../types';

const mockList = vi.fn();
vi.mock('../../api/client', () => ({
  applicationsApi: { list: () => mockList() },
}));

function service(): ServiceDefinition {
  return {
    id: 1, code: 'DRW-P-004', name_ar: 'مخططات الهدم', name_en: 'Demolition',
    currency: 'JOD',
    schema: {
      service_code: 'DRW-P-004', name_ar: 'مخططات الهدم', name_en: 'Demolition',
      workflow: {
        stages: [
          { id: 'office_submission', role: 'applicant', label_ar: 'تقديم الطلب',   label_en: 'Submit',  sla_hours: 24 },
          { id: 'review',            role: 'auditor',   label_ar: 'قيد المراجعة',  label_en: 'Review',  sla_hours: 48 },
          { id: 'payment',           role: 'staff',     label_ar: 'الدفع',         label_en: 'Payment', sla_hours: 24 },
          { id: 'issue',             role: 'staff',     label_ar: 'الشهادة',       label_en: 'Certify', sla_hours: 24 },
        ],
      },
      fee: { type: 'fixed', amount: 0, currency: 'JOD' },
      sections: [], fields: [], documents: [],
    },
  } as ServiceDefinition;
}

function app(overrides: Partial<Application>): Application {
  return {
    id: 1, reference_number: 'A-001',
    status: 'submitted', current_stage: 'review',
    fee_amount: 0, payment_status: 'waived',
    service_definition: service(),
    created_at: '2026-07-19T00:00:00Z',
    ...overrides,
  } as Application;
}

beforeEach(() => { mockList.mockReset(); });

describe('MyApplications', () => {
  it('shows only ongoing applications by default (hides approved / rejected / certificate_issued)', async () => {
    mockList.mockResolvedValue({ applications: [
      app({ id: 1, reference_number: 'A-1', status: 'submitted' }),
      app({ id: 2, reference_number: 'A-2', status: 'under_review' }),
      app({ id: 3, reference_number: 'A-3', status: 'certificate_issued' }),
      app({ id: 4, reference_number: 'A-4', status: 'rejected' }),
      app({ id: 5, reference_number: 'A-5', status: 'approved' }),
    ]});
    render(<MemoryRouter><MyApplications /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('A-1')).toBeInTheDocument());

    // Ongoing bucket visible; terminal buckets absent.
    expect(screen.getByText('A-2')).toBeInTheDocument();
    expect(screen.queryByText('A-3')).toBeNull();
    expect(screen.queryByText('A-4')).toBeNull();
    expect(screen.queryByText('A-5')).toBeNull();
  });

  it('reveals every application (including terminal ones) after clicking "الكل"', async () => {
    mockList.mockResolvedValue({ applications: [
      app({ id: 1, reference_number: 'A-1', status: 'submitted' }),
      app({ id: 2, reference_number: 'A-2', status: 'certificate_issued' }),
    ]});
    render(<MemoryRouter><MyApplications /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('A-1')).toBeInTheDocument());
    expect(screen.queryByText('A-2')).toBeNull();

    await userEvent.click(screen.getByRole('tab', { name: /الكل/ }));

    expect(screen.getByText('A-1')).toBeInTheDocument();
    expect(screen.getByText('A-2')).toBeInTheDocument();
  });

  it('renders the workflow stage timeline for each application', async () => {
    mockList.mockResolvedValue({ applications: [
      app({ current_stage: 'review' }),
    ]});
    render(<MemoryRouter><MyApplications /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('A-001')).toBeInTheDocument());

    // The mini timeline is present and highlights the current stage.
    const timeline = screen.getByTestId('mini-stage-timeline');
    expect(timeline).toBeInTheDocument();
    expect(timeline).toHaveTextContent('قيد المراجعة');
    // The stage currently active carries aria-current="step".
    const current = timeline.querySelectorAll('[aria-current="step"]');
    expect(current).toHaveLength(1);
    expect(current[0].getAttribute('data-stage-id')).toBe('review');
  });

  it('floats an application needing modifications above submitted ones', async () => {
    mockList.mockResolvedValue({ applications: [
      app({ id: 1, reference_number: 'A-OLD',  status: 'submitted' }),
      app({ id: 2, reference_number: 'A-BLOCK',status: 'modifications_requested' }),
    ]});
    render(<MemoryRouter><MyApplications /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('A-BLOCK')).toBeInTheDocument());

    // Reading top-to-bottom: A-BLOCK must appear before A-OLD.
    const rows = document.querySelectorAll('.space-y-3 > a');
    const references = Array.from(rows).map(el => el.querySelector('.font-mono')?.textContent);
    expect(references).toEqual(['A-BLOCK', 'A-OLD']);
  });

  it('shows an ongoing-empty helper when everything is terminal', async () => {
    mockList.mockResolvedValue({ applications: [
      app({ id: 1, status: 'certificate_issued' }),
    ]});
    render(<MemoryRouter><MyApplications /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText(/كل الطلبات مكتملة/)).toBeInTheDocument());
  });

  it('renders the "تحميل الشهادة" link when a signed PDF URL is present', async () => {
    // Regression pin — backend sends certificate_pdf_url on rows with
    // an issued certificate. Without this render the applicant has no
    // way to reach the download without opening the detail page.
    mockList.mockResolvedValue({ applications: [
      app({
        id: 1,
        status: 'certificate_issued',
        certificate_pdf_url: 'http://localhost/api/v1/certificates/CERT-123/pdf?token=abc',
      }),
    ]});
    render(<MemoryRouter><MyApplications /></MemoryRouter>);
    // certificate_issued is terminal, so switch to the "all" tab to see it.
    await waitFor(() => expect(screen.getByRole('tab', { name: /الكل/ })).toBeInTheDocument());
    await userEvent.click(screen.getByRole('tab', { name: /الكل/ }));

    const link = await screen.findByTestId('certificate-pdf-link');
    expect(link).toHaveAttribute('href', 'http://localhost/api/v1/certificates/CERT-123/pdf?token=abc');
    expect(link).toHaveAttribute('target', '_blank');
  });

  it('does not render the certificate link when certificate_pdf_url is absent', async () => {
    mockList.mockResolvedValue({ applications: [
      app({ id: 1, status: 'submitted', certificate_pdf_url: null }),
    ]});
    render(<MemoryRouter><MyApplications /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('A-001')).toBeInTheDocument());
    expect(screen.queryByTestId('certificate-pdf-link')).toBeNull();
  });
});
