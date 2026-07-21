import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApplicationDetail } from './ApplicationDetail';

const mockGet = vi.fn();
vi.mock('../../api/client', () => ({
  applicationsApi: {
    get: (...a: unknown[]) => mockGet(...a),
  },
}));

function renderAt(id: number) {
  return render(
    <MemoryRouter initialEntries={[`/applications/${id}`]}>
      <Routes>
        <Route path="/applications/:id" element={<ApplicationDetail />} />
        <Route path="/apply/:serviceCode" element={<div data-testid="landed-on-apply" />} />
        <Route path="/my-applications" element={<div data-testid="landed-on-my-apps" />} />
      </Routes>
    </MemoryRouter>,
  );
}

function baseApp(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: 42,
    reference_number: 'JEA-26-1234-0001',
    status: 'under_review',
    fee_amount: 30,
    payment_status: 'pending',
    review_round: 1,
    submitted_at: '2026-06-01T10:00:00Z',
    service_definition: { id: 1, code: 'DRW-P-001', name_ar: 'المخططات', name_en: 'Drawings', currency: 'JOD' },
    documents: [],
    reviews: [],
    created_at: '2026-06-01T09:00:00Z',
    updated_at: '2026-06-01T10:00:00Z',
    ...overrides,
  };
}

beforeEach(() => { mockGet.mockReset(); });

describe('ApplicationDetail (JORD-59 / JORD-62)', () => {
  it('renders reference number, status badge, and service name from the API', async () => {
    mockGet.mockResolvedValue({ application: baseApp() });
    renderAt(42);

    await waitFor(() => expect(screen.getByTestId('application-reference')).toHaveTextContent('JEA-26-1234-0001'));
    expect(screen.getByTestId('application-status-badge')).toBeInTheDocument();
    // Arabic default → shows name_ar.
    expect(screen.getByText('المخططات')).toBeInTheDocument();
  });

  it('renders the modifications banner + reviewer notes when status = modifications_requested', async () => {
    mockGet.mockResolvedValue({
      application: baseApp({
        status: 'modifications_requested',
        reviews: [{
          id: 5, stage_id: 'first_review', decision: 'modifications_requested',
          notes: 'يرجى إعادة إرفاق المخطط الإنشائي بعد التعديل.',
          review_round: 1, reviewer: { id: 9, name: 'المدقق الأول', role: 'auditor' },
          created_at: '2026-06-02T12:00:00Z',
        }],
      }),
    });
    renderAt(42);

    await waitFor(() => expect(screen.getByTestId('modifications-banner')).toBeInTheDocument());
    expect(screen.getByTestId('reviewer-notes')).toHaveTextContent(/إعادة إرفاق المخطط/);
    // Edit CTA is present when in modifications_requested.
    expect(screen.getByTestId('edit-application-btn')).toBeInTheDocument();
  });

  it('picks the LATEST review as the banner note (highest round, newest first)', async () => {
    mockGet.mockResolvedValue({
      application: baseApp({
        status: 'modifications_requested',
        reviews: [
          { id: 1, stage_id: 'a', decision: 'modifications_requested', notes: 'OLD note',
            review_round: 1, reviewer: { id: 9, name: 'r', role: 'auditor' },
            created_at: '2026-06-01T10:00:00Z' },
          { id: 2, stage_id: 'a', decision: 'modifications_requested', notes: 'LATEST note',
            review_round: 2, reviewer: { id: 9, name: 'r', role: 'auditor' },
            created_at: '2026-06-05T10:00:00Z' },
        ],
      }),
    });
    renderAt(42);

    await waitFor(() => expect(screen.getByTestId('reviewer-notes')).toHaveTextContent('LATEST note'));
    expect(screen.getByTestId('reviewer-notes')).not.toHaveTextContent('OLD note');
  });

  it('clicking Edit navigates to /apply/{code}?application_id={id}', async () => {
    mockGet.mockResolvedValue({
      application: baseApp({
        status: 'modifications_requested',
        reviews: [{ id: 1, stage_id: 'a', decision: 'modifications_requested',
          notes: 'fix it', review_round: 1,
          reviewer: { id: 9, name: 'r', role: 'auditor' },
          created_at: '2026-06-01T10:00:00Z' }],
      }),
    });
    renderAt(42);

    await waitFor(() => expect(screen.getByTestId('edit-application-btn')).toBeInTheDocument());
    await userEvent.click(screen.getByTestId('edit-application-btn'));
    // MemoryRouter renders the Apply placeholder route we registered above.
    await waitFor(() => expect(screen.getByTestId('landed-on-apply')).toBeInTheDocument());
  });

  it('does NOT render the modifications banner for a terminal status', async () => {
    mockGet.mockResolvedValue({
      application: baseApp({ status: 'certificate_issued', reviews: [
        { id: 1, stage_id: 'a', decision: 'approved', review_round: 1,
          reviewer: { id: 9, name: 'r', role: 'auditor' },
          created_at: '2026-06-01T10:00:00Z' },
      ]}),
    });
    renderAt(42);
    await waitFor(() => expect(screen.getByTestId('application-reference')).toBeInTheDocument());
    expect(screen.queryByTestId('modifications-banner')).toBeNull();
  });

  it('shows the certificate download link when the API returns certificate_pdf_url', async () => {
    mockGet.mockResolvedValue({
      application: baseApp({ status: 'certificate_issued' }),
      certificate_pdf_url: '/api/v1/certificates/JEA-CERT-1/pdf?token=abc',
    });
    renderAt(42);
    await waitFor(() => expect(screen.getByTestId('certificate-download')).toBeInTheDocument());
    expect(screen.getByTestId('certificate-download')).toHaveAttribute('href', expect.stringContaining('token=abc'));
  });

  it('surfaces an API failure via an error banner (no silent bounce)', async () => {
    mockGet.mockRejectedValue(new Error('boom'));
    renderAt(42);
    await waitFor(() => expect(screen.getByTestId('application-error')).toBeInTheDocument());
    expect(screen.getByTestId('application-error')).toHaveTextContent('boom');
  });

  it('renders documents empty state instead of a bare list', async () => {
    mockGet.mockResolvedValue({ application: baseApp({ documents: [] }) });
    renderAt(42);
    await waitFor(() => expect(screen.getByTestId('documents-empty')).toBeInTheDocument());
  });

  /**
   * JORD-64 (PM): after auditor approval the app sits at
   * status=approved + payment_status=pending. Applicant needs a
   * clear "pay this amount at the JEA counter" instruction; the
   * page previously showed nothing about payment.
   */
  it('shows the "payment required" banner when approved but not yet paid', async () => {
    mockGet.mockResolvedValue({
      application: baseApp({
        status: 'approved',
        payment_status: 'pending',
        fee_amount: 150,
        reference_number: 'JEA-26-1234-0042',
      }),
    });
    renderAt(42);
    await waitFor(() => expect(screen.getByTestId('payment-required-banner')).toBeInTheDocument());
    expect(screen.getByTestId('payment-required-banner')).toHaveTextContent(/150 JOD/);
    // Reference number is prominent so the applicant can quote it at the counter.
    expect(screen.getByTestId('payment-reference-hint')).toHaveTextContent('JEA-26-1234-0042');
  });

  it('hides the payment banner once the fee is paid', async () => {
    mockGet.mockResolvedValue({
      application: baseApp({
        status: 'approved',
        payment_status: 'paid',
      }),
    });
    renderAt(42);
    await waitFor(() => expect(screen.getByTestId('application-reference')).toBeInTheDocument());
    expect(screen.queryByTestId('payment-required-banner')).toBeNull();
  });

  it('hides the payment banner for terminal statuses', async () => {
    mockGet.mockResolvedValue({
      application: baseApp({ status: 'certificate_issued', payment_status: 'paid' }),
    });
    renderAt(42);
    await waitFor(() => expect(screen.getByTestId('application-reference')).toBeInTheDocument());
    expect(screen.queryByTestId('payment-required-banner')).toBeNull();
  });
});
