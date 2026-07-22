import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { OfficeDues } from './OfficeDues';

const mockList  = vi.fn();
const mockSeed  = vi.fn();
const mockPay   = vi.fn();
vi.mock('../../../api/client', () => ({
  adminApi: {
    listOfficeDues:         (...a: unknown[]) => mockList(...a),
    seedOfficeRegistration: (...a: unknown[]) => mockSeed(...a),
    payDue:                 (...a: unknown[]) => mockPay(...a),
  },
}));

const currentYear = new Date().getFullYear();

const basePayload = {
  office: { id: 4, name: 'مكتب أحمد', office_classification: 'engineering' as const },
  obligations: [
    // Registration for currentYear — paid
    { id: 1, kind: 'registration' as const, period_year: currentYear,
      period_label_ar: `رسوم تسجيل ${currentYear}`,
      amount_jod: '80.00', due_date: `${currentYear}-01-15`,
      paid_at: '2026-01-20T10:00:00Z', payment_reference: 'BANK-001',
      late_surcharge_jod: '0.00', total_paid_jod: '80.00' },
    // Annual dues for currentYear — unpaid
    { id: 2, kind: 'annual_dues' as const, period_year: currentYear,
      period_label_ar: `الرسوم السنوية ${currentYear}`,
      amount_jod: '60.00', due_date: `${currentYear}-02-28`,
      paid_at: null, payment_reference: null,
      late_surcharge_jod: '0.00', total_paid_jod: null },
  ],
  rate_table: {
    individual_engineer: { registration: 60,   annual_dues: 30 },
    engineering:         { registration: 80,   annual_dues: 60 },
    consultant:          { registration: 100,  annual_dues: 80 },
    foreign:             { registration: 3500, annual_dues: 2000 },
  },
};

function mount(officeId = 4) {
  return render(
    <MemoryRouter initialEntries={[`/admin/offices/${officeId}/dues`]}>
      <Routes>
        <Route path="/admin/offices/:id/dues" element={<OfficeDues />} />
      </Routes>
    </MemoryRouter>
  );
}

beforeEach(() => { mockList.mockReset(); mockSeed.mockReset(); mockPay.mockReset(); });

describe('OfficeDues (JORD-79 UI)', () => {
  it('renders the tier-specific rate summary from rate_table', async () => {
    mockList.mockResolvedValue(basePayload);
    mount(4);
    // engineering tier → 80 registration + 60 annual
    await waitFor(() => expect(screen.getByText(/80 JOD/)).toBeInTheDocument());
    expect(screen.getByText(/60 JOD/)).toBeInTheDocument();
  });

  it('shows a paid row with the paid badge and no pay button', async () => {
    mockList.mockResolvedValue(basePayload);
    mount();
    await waitFor(() => expect(screen.getByTestId('obligation-row-1')).toBeInTheDocument());
    const row = screen.getByTestId('obligation-row-1');
    expect(row.textContent).toMatch(/مدفوع|Paid/);
    // Pay button must NOT render on a paid row.
    expect(screen.queryByTestId('pay-btn-1')).toBeNull();
  });

  it('shows a pay button on unpaid rows and opens the modal on click', async () => {
    mockList.mockResolvedValue(basePayload);
    mount();
    await waitFor(() => expect(screen.getByTestId('pay-btn-2')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('pay-btn-2'));
    expect(screen.getByTestId('pay-modal')).toBeInTheDocument();
    expect(screen.getByTestId('pay-reference-input')).toBeInTheDocument();
  });

  it('submits the pay flow with the entered reference and refreshes', async () => {
    mockList.mockResolvedValue(basePayload);
    mockPay.mockResolvedValue({ message: 'ok' });
    mount();
    await waitFor(() => expect(screen.getByTestId('pay-btn-2')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('pay-btn-2'));
    await userEvent.type(screen.getByTestId('pay-reference-input'), 'CHECK-2026-77');
    await userEvent.click(screen.getByTestId('pay-submit'));

    await waitFor(() => expect(mockPay).toHaveBeenCalledWith(2, 'CHECK-2026-77'));
    // After success: modal closes and list reloads (listOfficeDues called twice — mount + post-pay refresh).
    await waitFor(() => expect(screen.queryByTestId('pay-modal')).toBeNull());
    expect(mockList).toHaveBeenCalledTimes(2);
  });

  it('refuses submit with an empty reference (client-side)', async () => {
    mockList.mockResolvedValue(basePayload);
    mount();
    await waitFor(() => expect(screen.getByTestId('pay-btn-2')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('pay-btn-2'));
    await userEvent.click(screen.getByTestId('pay-submit'));
    // API not called, error surfaces.
    expect(mockPay).not.toHaveBeenCalled();
    expect(screen.getByText(/يرجى إدخال مرجع الدفع|Payment reference is required/)).toBeInTheDocument();
  });

  it('hides the seed-registration CTA when a current-year registration already exists', async () => {
    // basePayload already has a currentYear registration → CTA hidden.
    mockList.mockResolvedValue(basePayload);
    mount();
    await waitFor(() => expect(screen.getByText(/مكتب أحمد/)).toBeInTheDocument());
    expect(screen.queryByTestId('seed-registration-btn')).toBeNull();
  });

  it('shows the seed-registration CTA when no registration exists for this year', async () => {
    mockList.mockResolvedValue({ ...basePayload, obligations: [basePayload.obligations[1]] }); // annual only
    mount();
    await waitFor(() => expect(screen.getByTestId('seed-registration-btn')).toBeInTheDocument());
  });

  it('seed-registration button POSTs and refreshes on success', async () => {
    mockList.mockResolvedValue({ ...basePayload, obligations: [] });
    mockSeed.mockResolvedValue({ message: 'created' });
    mount(4);
    await waitFor(() => expect(screen.getByTestId('seed-registration-btn')).toBeInTheDocument());
    await userEvent.click(screen.getByTestId('seed-registration-btn'));

    await waitFor(() => expect(mockSeed).toHaveBeenCalledWith(4));
    expect(mockList).toHaveBeenCalledTimes(2);
  });

  it('shows late-surcharge line under a row when it exists', async () => {
    mockList.mockResolvedValue({
      ...basePayload,
      obligations: [{
        id: 3, kind: 'annual_dues' as const, period_year: currentYear - 1,
        period_label_ar: null, amount_jod: '60.00', due_date: `${currentYear - 1}-02-28`,
        paid_at: `${currentYear - 1}-04-15T00:00:00Z`, payment_reference: 'LATE',
        late_surcharge_jod: '9.00', total_paid_jod: '69.00',
      }],
    });
    mount();
    await waitFor(() => expect(screen.getByText(/9\.00/)).toBeInTheDocument());
    expect(screen.getByText(/رسم تأخير|late surcharge/)).toBeInTheDocument();
  });
});
