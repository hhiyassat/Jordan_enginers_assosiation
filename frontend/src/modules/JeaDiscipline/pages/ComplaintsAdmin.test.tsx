import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ComplaintsAdmin } from './ComplaintsAdmin';

const mockList   = vi.fn();
const mockDecide = vi.fn();
vi.mock('../../../api/client', () => ({
  adminApi: {
    listComplaints:   (...a: unknown[]) => mockList(...a),
    decideComplaint:  (...a: unknown[]) => mockDecide(...a),
  },
}));

function baseComplaint(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: 1,
    kind: 'safety_violation' as const,
    description: 'Unsafe scaffolding at the site of the office\'s current project.',
    status: 'open' as const,
    investigation_deadline: '2027-01-15',
    decided_at: null,
    created_at: '2026-12-15T10:00:00Z',
    target_office: { id: 4, name: 'مكتب المستهدف' },
    reporter: { id: 2, name: 'المشتكي' },
    reporter_display: null,
    sanctions: [] as Array<{ id: number; kind: string; effective_from: string; effective_until: string | null }>,
    ...overrides,
  };
}

beforeEach(() => { mockList.mockReset(); mockDecide.mockReset(); });

describe('ComplaintsAdmin (JORD-81 UI)', () => {
  it('renders every complaint returned by the API', async () => {
    mockList.mockResolvedValue({ complaints: [
      baseComplaint({ id: 1 }),
      baseComplaint({ id: 2, kind: 'fee_undercutting', status: 'decided' }),
    ]});
    render(<MemoryRouter><ComplaintsAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('complaint-row-1')).toBeInTheDocument());
    expect(screen.getByTestId('complaint-row-2')).toBeInTheDocument();
  });

  it('filter tabs narrow the list by status', async () => {
    mockList.mockResolvedValue({ complaints: [
      baseComplaint({ id: 1, status: 'open' }),
      baseComplaint({ id: 2, status: 'decided' }),
      baseComplaint({ id: 3, status: 'dismissed' }),
    ]});
    render(<MemoryRouter><ComplaintsAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('filter-tabs')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('filter-decided'));
    // Only id=2 remains visible.
    expect(screen.getByTestId('complaint-row-2')).toBeInTheDocument();
    expect(screen.queryByTestId('complaint-row-1')).toBeNull();
    expect(screen.queryByTestId('complaint-row-3')).toBeNull();
  });

  it('expands a row on click and shows the decide form for open complaints', async () => {
    mockList.mockResolvedValue({ complaints: [baseComplaint({ id: 5 })] });
    render(<MemoryRouter><ComplaintsAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('complaint-row-5')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('complaint-row-5').querySelector('button')!);
    expect(screen.getByTestId('decide-form-5')).toBeInTheDocument();
  });

  it('submits a sanction with the picked ladder + reason', async () => {
    mockList.mockResolvedValue({ complaints: [baseComplaint({ id: 5 })] });
    mockDecide.mockResolvedValue({ message: 'تم إصدار العقوبة.', transfers_opened: 2 });
    render(<MemoryRouter><ComplaintsAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('complaint-row-5')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('complaint-row-5').querySelector('button')!);
    await userEvent.click(screen.getByTestId('sanction-kind-suspension_2yr'));
    await userEvent.type(screen.getByTestId('sanction-reason'), 'مخالفة سلامة موثقة بتقرير النقابة.');
    await userEvent.click(screen.getByTestId('decide-submit'));

    await waitFor(() => expect(mockDecide).toHaveBeenCalledWith(5, {
      decision: 'sanction',
      sanction_kind: 'suspension_2yr',
      reason: 'مخالفة سلامة موثقة بتقرير النقابة.',
      notes: undefined,
    }));
    // Success flash includes the transfers-opened count.
    await waitFor(() => expect(screen.getByText(/2 طلب نقل إشراف/)).toBeInTheDocument());
  });

  it('refuses sanction submit with a short reason (client-side)', async () => {
    mockList.mockResolvedValue({ complaints: [baseComplaint({ id: 5 })] });
    render(<MemoryRouter><ComplaintsAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('complaint-row-5')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('complaint-row-5').querySelector('button')!);
    await userEvent.type(screen.getByTestId('sanction-reason'), 'short'); // < 10 chars
    await userEvent.click(screen.getByTestId('decide-submit'));

    expect(mockDecide).not.toHaveBeenCalled();
    expect(screen.getByText(/10 أحرف/)).toBeInTheDocument();
  });

  it('dismiss flow does not require a reason', async () => {
    mockList.mockResolvedValue({ complaints: [baseComplaint({ id: 5 })] });
    mockDecide.mockResolvedValue({ message: 'تم رفض الشكوى.' });
    render(<MemoryRouter><ComplaintsAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('complaint-row-5')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('complaint-row-5').querySelector('button')!);
    await userEvent.click(screen.getByTestId('decision-dismiss'));
    await userEvent.click(screen.getByTestId('decide-submit'));

    await waitFor(() => expect(mockDecide).toHaveBeenCalledWith(5, {
      decision: 'dismiss',
      notes: undefined,
    }));
  });

  it('collapses a decided complaint (no decide form shown)', async () => {
    mockList.mockResolvedValue({ complaints: [baseComplaint({
      id: 7, status: 'decided',
      sanctions: [{ id: 99, kind: 'suspension_1yr', effective_from: '2026-01-01', effective_until: '2027-01-01' }],
    })]});
    render(<MemoryRouter><ComplaintsAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('complaint-row-7')).toBeInTheDocument());

    // Expand the row — decide form must NOT appear (already decided).
    await userEvent.click(screen.getByTestId('complaint-row-7').querySelector('button')!);
    expect(screen.queryByTestId('decide-form-7')).toBeNull();
    // Sanction badge is visible in the summary.
    expect(screen.getByText(/suspension_1yr/)).toBeInTheDocument();
  });

  it('empty state renders when the filter yields no complaints', async () => {
    mockList.mockResolvedValue({ complaints: [baseComplaint({ id: 1, status: 'open' })] });
    render(<MemoryRouter><ComplaintsAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('complaint-row-1')).toBeInTheDocument());
    await userEvent.click(screen.getByTestId('filter-decided'));
    expect(screen.getByText(/لا توجد شكاوى/)).toBeInTheDocument();
  });
});
