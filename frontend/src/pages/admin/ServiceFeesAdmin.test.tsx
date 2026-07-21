import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ServiceFeesAdmin } from './ServiceFeesAdmin';

const mockList   = vi.fn();
const mockUpdate = vi.fn();
vi.mock('../../api/client', () => ({
  adminApi: {
    listServiceFees:  (...a: unknown[]) => mockList(...a),
    updateServiceFee: (...a: unknown[]) => mockUpdate(...a),
  },
}));

function baseRow(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: 1,
    code: 'MSC-001',
    parent_code: 'JEA-MISC',
    name_ar: 'كشف كوته',
    name_en: 'Office Quota',
    status: 'draft' as const,
    is_locked: false,
    fee: { type: 'fixed', amount: 50000, currency: 'JOD' },
    ...overrides,
  };
}

beforeEach(() => {
  mockList.mockReset();
  mockUpdate.mockReset();
});

describe('ServiceFeesAdmin (JORD-85)', () => {
  it('renders one row per service and flags placeholders', async () => {
    mockList.mockResolvedValue({ fees: [
      baseRow({ id: 1 }),
      baseRow({ id: 2, code: 'MSC-002', fee: { type: 'fixed', amount: 30, currency: 'JOD' } }),
    ]});
    render(<MemoryRouter><ServiceFeesAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('fee-row-1')).toBeInTheDocument());
    expect(screen.getByTestId('fee-row-2')).toBeInTheDocument();
    // id=1 is placeholder (50000), id=2 is real (30).
    expect(screen.getByTestId('placeholder-badge-1')).toBeInTheDocument();
    expect(screen.queryByTestId('placeholder-badge-2')).toBeNull();
  });

  it('sends the fixed payload to the API on save', async () => {
    mockList.mockResolvedValue({ fees: [baseRow({ id: 5 })] });
    mockUpdate.mockResolvedValue({ service: {} });
    render(<MemoryRouter><ServiceFeesAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('fee-row-5')).toBeInTheDocument());

    await userEvent.clear(screen.getByTestId('fee-amount-5'));
    await userEvent.type(screen.getByTestId('fee-amount-5'), '250');
    await userEvent.click(screen.getByTestId('fee-save-5'));

    await waitFor(() => expect(mockUpdate).toHaveBeenCalledWith(5, {
      type: 'fixed', amount: 250, currency: 'JOD',
    }));
  });

  it('switches to per_unit and sends basis + rate', async () => {
    mockList.mockResolvedValue({ fees: [baseRow({ id: 7 })] });
    mockUpdate.mockResolvedValue({ service: {} });
    render(<MemoryRouter><ServiceFeesAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('fee-row-7')).toBeInTheDocument());

    await userEvent.selectOptions(screen.getByTestId('fee-type-7'), 'per_unit');
    await userEvent.type(screen.getByTestId('fee-basis-7'), 'area_m2');
    await userEvent.type(screen.getByTestId('fee-rate-7'), '2.5');
    await userEvent.click(screen.getByTestId('fee-save-7'));

    await waitFor(() => expect(mockUpdate).toHaveBeenCalledWith(7, {
      type: 'per_unit', basis: 'area_m2', rate: 2.5, currency: 'JOD',
    }));
  });

  it('sends {type:free} for a free service (no amount)', async () => {
    mockList.mockResolvedValue({ fees: [baseRow({ id: 9 })] });
    mockUpdate.mockResolvedValue({ service: {} });
    render(<MemoryRouter><ServiceFeesAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('fee-row-9')).toBeInTheDocument());

    await userEvent.selectOptions(screen.getByTestId('fee-type-9'), 'free');
    await userEvent.click(screen.getByTestId('fee-save-9'));

    await waitFor(() => expect(mockUpdate).toHaveBeenCalledWith(9, { type: 'free' }));
  });

  it('refuses per_unit without a basis (client-side check)', async () => {
    mockList.mockResolvedValue({ fees: [baseRow({ id: 3 })] });
    render(<MemoryRouter><ServiceFeesAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('fee-row-3')).toBeInTheDocument());

    await userEvent.selectOptions(screen.getByTestId('fee-type-3'), 'per_unit');
    // Leave basis and rate empty.
    await userEvent.click(screen.getByTestId('fee-save-3'));

    expect(mockUpdate).not.toHaveBeenCalled();
    // The error banner appears (role=alert) with the guidance message.
    expect(screen.getByRole('alert')).toHaveTextContent(/per_unit|الأساس/);
  });

  it('shows "locked" badge and hides save button for locked services', async () => {
    mockList.mockResolvedValue({ fees: [baseRow({ id: 4, is_locked: true })] });
    render(<MemoryRouter><ServiceFeesAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('fee-locked-4')).toBeInTheDocument());
    expect(screen.queryByTestId('fee-save-4')).toBeNull();
  });

  it('filter tab "placeholder" narrows the grid to placeholder rows only', async () => {
    mockList.mockResolvedValue({ fees: [
      baseRow({ id: 1 }), // placeholder
      baseRow({ id: 2, fee: { type: 'fixed', amount: 30, currency: 'JOD' } }), // real
      baseRow({ id: 3, fee: { type: 'per_unit', basis: 'length_lm', rate: 0.15, currency: 'JOD' } }), // real
    ]});
    render(<MemoryRouter><ServiceFeesAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('fee-row-1')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('fee-tab-placeholder'));
    expect(screen.getByTestId('fee-row-1')).toBeInTheDocument();
    expect(screen.queryByTestId('fee-row-2')).toBeNull();
    expect(screen.queryByTestId('fee-row-3')).toBeNull();
  });

  it('search filters by code', async () => {
    mockList.mockResolvedValue({ fees: [
      baseRow({ id: 1, code: 'MSC-001' }),
      baseRow({ id: 2, code: 'SRV-008' }),
    ]});
    render(<MemoryRouter><ServiceFeesAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('fee-row-1')).toBeInTheDocument());

    await userEvent.type(screen.getByTestId('fee-search'), 'SRV');
    expect(screen.queryByTestId('fee-row-1')).toBeNull();
    expect(screen.getByTestId('fee-row-2')).toBeInTheDocument();
  });

  it('sorts by clicking the Code column header (asc → desc → off)', async () => {
    mockList.mockResolvedValue({ fees: [
      baseRow({ id: 1, code: 'MSC-003' }),
      baseRow({ id: 2, code: 'MSC-001' }),
      baseRow({ id: 3, code: 'MSC-002' }),
    ]});
    render(<MemoryRouter><ServiceFeesAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('fee-row-1')).toBeInTheDocument());

    // Default initial sort is 'code' asc (per hook init).
    const rowsAsc = screen.getAllByTestId(/^fee-row-/).map(el => el.getAttribute('data-testid'));
    expect(rowsAsc).toEqual(['fee-row-2', 'fee-row-3', 'fee-row-1']);

    // Click flips to desc.
    await userEvent.click(screen.getByTestId('sort-header-code').querySelector('button')!);
    const rowsDesc = screen.getAllByTestId(/^fee-row-/).map(el => el.getAttribute('data-testid'));
    expect(rowsDesc).toEqual(['fee-row-1', 'fee-row-3', 'fee-row-2']);
  });

  it('CSV export button is present when there are rows and hidden when empty', async () => {
    mockList.mockResolvedValue({ fees: [baseRow({ id: 1 })] });
    const { unmount } = render(<MemoryRouter><ServiceFeesAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('fees-export-csv')).toBeInTheDocument());
    unmount();

    mockList.mockResolvedValue({ fees: [] });
    render(<MemoryRouter><ServiceFeesAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('fees-empty')).toBeInTheDocument());
    expect(screen.queryByTestId('fees-export-csv')).toBeNull();
  });
});
