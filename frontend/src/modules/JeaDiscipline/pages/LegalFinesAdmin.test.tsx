import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { LegalFinesAdmin } from './LegalFinesAdmin';

const mockList  = vi.fn();
const mockIssue = vi.fn();
const mockPay   = vi.fn();
vi.mock('../../../api/client', () => ({
  adminApi: {
    listLegalFines: (...a: unknown[]) => mockList(...a),
    issueLegalFine: (...a: unknown[]) => mockIssue(...a),
    payLegalFine:   (...a: unknown[]) => mockPay(...a),
  },
}));

const bounds = {
  unlicensed_contractor_small: { min: 1000, max: 5000, area_threshold_m2: 250 },
  unlicensed_contractor_large: { min: 5000, max: 50000, area_threshold_m2: 250 },
};

function fine(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: 1,
    kind: 'unlicensed_contractor_small' as const,
    target_display: 'محمد الأحمد',
    amount_jod: '2500.00',
    project_area_m2: 200,
    reason: 'استخدام مقاول غير مرخص لمشروع صغير.',
    issued_at: '2026-07-21T10:00:00Z',
    paid_at: null,
    payment_reference: null,
    issued_by: { id: 1, name: 'admin' },
    application: null,
    ...overrides,
  };
}

beforeEach(() => { mockList.mockReset(); mockIssue.mockReset(); mockPay.mockReset(); });

describe('LegalFinesAdmin (JORD-82 UI)', () => {
  it('lists fines from the API with owner + amount + status', async () => {
    mockList.mockResolvedValue({ fines: [fine({ id: 1 }), fine({ id: 2, paid_at: '2026-07-25T00:00:00Z' })], bounds });
    render(<MemoryRouter><LegalFinesAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('fine-row-1')).toBeInTheDocument());
    expect(screen.getByTestId('fine-row-2')).toBeInTheDocument();
    // Paid row (id=2) shows paid badge, no pay button.
    expect(screen.queryByTestId('pay-fine-btn-2')).toBeNull();
    // Unpaid row (id=1) shows pay button.
    expect(screen.getByTestId('pay-fine-btn-1')).toBeInTheDocument();
  });

  it('empty state renders when no fines exist', async () => {
    mockList.mockResolvedValue({ fines: [], bounds });
    render(<MemoryRouter><LegalFinesAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText(/لا توجد غرامات مُصدرة/)).toBeInTheDocument());
  });

  it('toggle button reveals + hides the issue form', async () => {
    mockList.mockResolvedValue({ fines: [], bounds });
    render(<MemoryRouter><LegalFinesAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('toggle-issue-form')).toBeInTheDocument());
    expect(screen.queryByTestId('issue-form')).toBeNull();

    await userEvent.click(screen.getByTestId('toggle-issue-form'));
    expect(screen.getByTestId('issue-form')).toBeInTheDocument();

    await userEvent.click(screen.getByTestId('toggle-issue-form'));
    expect(screen.queryByTestId('issue-form')).toBeNull();
  });

  it('auto-switches tier when area crosses the 250 threshold', async () => {
    mockList.mockResolvedValue({ fines: [], bounds });
    render(<MemoryRouter><LegalFinesAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('toggle-issue-form')).toBeInTheDocument());
    await userEvent.click(screen.getByTestId('toggle-issue-form'));

    // Type an area of 500 → tier auto-flips to large.
    const areaInput = screen.getByTestId('project-area');
    await userEvent.type(areaInput, '500');

    // The large-tier button now has the active styling.
    await waitFor(() => {
      const largeBtn = screen.getByTestId('kind-unlicensed_contractor_large');
      expect(largeBtn.className).toMatch(/red-50/);
    });
  });

  it('client-side validation blocks short reason', async () => {
    mockList.mockResolvedValue({ fines: [], bounds });
    render(<MemoryRouter><LegalFinesAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('toggle-issue-form')).toBeInTheDocument());
    await userEvent.click(screen.getByTestId('toggle-issue-form'));

    await userEvent.type(screen.getByTestId('target-display'), 'محمد');
    await userEvent.type(screen.getByTestId('amount'), '2000');
    await userEvent.type(screen.getByTestId('reason'), 'short'); // < 10
    await userEvent.click(screen.getByTestId('issue-submit'));

    expect(mockIssue).not.toHaveBeenCalled();
    expect(screen.getByText(/10 أحرف/)).toBeInTheDocument();
  });

  it('happy path: full form submits with the expected payload', async () => {
    mockList.mockResolvedValue({ fines: [], bounds });
    mockIssue.mockResolvedValue({ message: 'تم إصدار الغرامة.' });
    render(<MemoryRouter><LegalFinesAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('toggle-issue-form')).toBeInTheDocument());
    await userEvent.click(screen.getByTestId('toggle-issue-form'));

    await userEvent.type(screen.getByTestId('target-display'), 'محمد الأحمد');
    await userEvent.type(screen.getByTestId('project-area'), '200');
    await userEvent.type(screen.getByTestId('amount'), '2500');
    await userEvent.type(screen.getByTestId('reason'), 'استخدام مقاول غير مرخّص لمبنى 200 م².');
    await userEvent.click(screen.getByTestId('issue-submit'));

    await waitFor(() => expect(mockIssue).toHaveBeenCalledTimes(1));
    const args = mockIssue.mock.calls[0][0];
    expect(args.kind).toBe('unlicensed_contractor_small');
    expect(args.amount_jod).toBe(2500);
    expect(args.target_display).toBe('محمد الأحمد');
    expect(args.project_area_m2).toBe(200);
    // Form hides + list reloads.
    await waitFor(() => expect(screen.queryByTestId('issue-form')).toBeNull());
    expect(mockList).toHaveBeenCalledTimes(2);
  });

  it('pay flow submits reference and reloads', async () => {
    mockList.mockResolvedValue({ fines: [fine({ id: 1 })], bounds });
    mockPay.mockResolvedValue({ message: 'ok' });
    render(<MemoryRouter><LegalFinesAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('pay-fine-btn-1')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('pay-fine-btn-1'));
    await userEvent.type(screen.getByTestId('pay-fine-reference-input'), 'COURT-42');
    await userEvent.click(screen.getByTestId('pay-fine-submit'));

    await waitFor(() => expect(mockPay).toHaveBeenCalledWith(1, 'COURT-42'));
    expect(mockList).toHaveBeenCalledTimes(2);
  });

  it('pay modal blocks empty reference', async () => {
    mockList.mockResolvedValue({ fines: [fine({ id: 1 })], bounds });
    render(<MemoryRouter><LegalFinesAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('pay-fine-btn-1')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('pay-fine-btn-1'));
    await userEvent.click(screen.getByTestId('pay-fine-submit'));

    expect(mockPay).not.toHaveBeenCalled();
    expect(screen.getByText(/يرجى إدخال مرجع الدفع/)).toBeInTheDocument();
  });
});
