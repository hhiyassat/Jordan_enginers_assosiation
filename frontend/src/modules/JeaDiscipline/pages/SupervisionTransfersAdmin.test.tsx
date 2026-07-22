import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { SupervisionTransfersAdmin } from './SupervisionTransfersAdmin';

const mockList        = vi.fn();
const mockAssign      = vi.fn();
const mockAcceptDecl  = vi.fn();
const mockListOffices = vi.fn();
vi.mock('../../../api/client', () => ({
  adminApi: {
    listSupervisionTransfers:          (...a: unknown[]) => mockList(...a),
    assignSupervisionTransfer:         (...a: unknown[]) => mockAssign(...a),
    acceptOrDeclineSupervisionTransfer:(...a: unknown[]) => mockAcceptDecl(...a),
    listOffices:                       (...a: unknown[]) => mockListOffices(...a),
  },
}));

function transfer(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: 1,
    status: 'pending' as const,
    fee_waived: true,
    notes: null,
    assigned_at: null,
    accepted_at: null,
    created_at: '2026-07-21T00:00:00Z',
    application: {
      id: 100,
      reference_number: '2626000100',
      status: 'approved',
      service_definition: { id: 5, code: 'DRW-P-001', name_ar: 'مخططات', name_en: 'Drawings' },
    },
    source_office: { id: 4, name: 'مكتب الأمثلة', email: 'src@t.esp' },
    target_office: null,
    ...overrides,
  };
}

beforeEach(() => {
  mockList.mockReset();
  mockAssign.mockReset();
  mockAcceptDecl.mockReset();
  mockListOffices.mockReset();
});

describe('SupervisionTransfersAdmin (JORD-83 UI)', () => {
  it('renders every transfer with status + source office', async () => {
    mockList.mockResolvedValue({ transfers: [
      transfer({ id: 1, status: 'pending' }),
      transfer({ id: 2, status: 'assigned', target_office: { id: 5, name: 'مكتب المستلم', email: 'rcv@t.esp' } }),
      transfer({ id: 3, status: 'accepted', target_office: { id: 5, name: 'مكتب المستلم', email: 'rcv@t.esp' } }),
    ]});
    render(<MemoryRouter><SupervisionTransfersAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('transfer-row-1')).toBeInTheDocument());
    expect(screen.getByTestId('transfer-row-2')).toBeInTheDocument();
    expect(screen.getByTestId('transfer-row-3')).toBeInTheDocument();
    // fee_waived chip visible.
    expect(screen.getAllByText(/مُعفى من الرسوم|Fee waived/).length).toBeGreaterThan(0);
  });

  it('filter tabs narrow the list', async () => {
    mockList.mockResolvedValue({ transfers: [
      transfer({ id: 1, status: 'pending' }),
      transfer({ id: 2, status: 'accepted' }),
    ]});
    render(<MemoryRouter><SupervisionTransfersAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('filter-tabs')).toBeInTheDocument());
    await userEvent.click(screen.getByTestId('filter-accepted'));
    expect(screen.getByTestId('transfer-row-2')).toBeInTheDocument();
    expect(screen.queryByTestId('transfer-row-1')).toBeNull();
  });

  it('pending row shows Assign button; assigned shows Accept + Decline; accepted shows Completed', async () => {
    mockList.mockResolvedValue({ transfers: [
      transfer({ id: 1, status: 'pending' }),
      transfer({ id: 2, status: 'assigned', target_office: { id: 5, name: 'x', email: 'x@t.esp' } }),
      transfer({ id: 3, status: 'accepted' }),
    ]});
    render(<MemoryRouter><SupervisionTransfersAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('transfer-row-1')).toBeInTheDocument());

    expect(screen.getByTestId('assign-btn-1')).toBeInTheDocument();
    expect(screen.queryByTestId('accept-btn-1')).toBeNull();

    expect(screen.getByTestId('accept-btn-2')).toBeInTheDocument();
    expect(screen.getByTestId('decline-btn-2')).toBeInTheDocument();

    expect(screen.queryByTestId('assign-btn-3')).toBeNull();
    expect(screen.queryByTestId('accept-btn-3')).toBeNull();
    expect(screen.getByText(/مكتمل|Completed/)).toBeInTheDocument();
  });

  it('opens the assign modal and posts the picked target office', async () => {
    mockList.mockResolvedValue({ transfers: [transfer({ id: 1 })] });
    mockListOffices.mockResolvedValue({ offices: [
      // Source office (4) should be filtered out by the modal.
      { id: 4, name: 'المصدر', email: 'src@t.esp', is_active: true,
        has_excellence_award: false, is_bit_khibra: false, has_iso_cert: false, engineer_count: 2 },
      { id: 5, name: 'المستلم أ', email: 'a@t.esp', is_active: true,
        has_excellence_award: false, is_bit_khibra: false, has_iso_cert: false, engineer_count: 3 },
      { id: 6, name: 'المستلم ب', email: 'b@t.esp', is_active: true,
        has_excellence_award: false, is_bit_khibra: false, has_iso_cert: false, engineer_count: 1 },
    ]});
    mockAssign.mockResolvedValue({ message: 'ok' });
    render(<MemoryRouter><SupervisionTransfersAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('assign-btn-1')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('assign-btn-1'));
    await waitFor(() => expect(screen.getByTestId('assign-modal')).toBeInTheDocument());
    // Source office (id=4) must NOT be in the picker.
    expect(screen.queryByTestId('target-office-4')).toBeNull();
    expect(screen.getByTestId('target-office-5')).toBeInTheDocument();
    expect(screen.getByTestId('target-office-6')).toBeInTheDocument();

    await userEvent.click(screen.getByTestId('target-office-6').querySelector('input[type=radio]')!);
    await userEvent.click(screen.getByTestId('assign-submit'));

    await waitFor(() => expect(mockAssign).toHaveBeenCalledWith(1, 6, undefined));
    // Reload fires + modal closes.
    await waitFor(() => expect(screen.queryByTestId('assign-modal')).toBeNull());
    expect(mockList).toHaveBeenCalledTimes(2);
  });

  it('assign submit blocked when no target picked', async () => {
    mockList.mockResolvedValue({ transfers: [transfer({ id: 1 })] });
    mockListOffices.mockResolvedValue({ offices: [
      { id: 5, name: 'x', email: 'x@t.esp', is_active: true,
        has_excellence_award: false, is_bit_khibra: false, has_iso_cert: false, engineer_count: 1 },
    ]});
    render(<MemoryRouter><SupervisionTransfersAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('assign-btn-1')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('assign-btn-1'));
    await waitFor(() => expect(screen.getByTestId('assign-submit')).toBeInTheDocument());
    // Submit button disabled when nothing picked.
    expect(screen.getByTestId('assign-submit')).toBeDisabled();
  });

  it('accept flow: opens confirm bar, submits accept outcome', async () => {
    mockList.mockResolvedValue({ transfers: [
      transfer({ id: 2, status: 'assigned', target_office: { id: 5, name: 'x', email: 'x@t.esp' } }),
    ]});
    mockAcceptDecl.mockResolvedValue({ message: 'ok' });
    render(<MemoryRouter><SupervisionTransfersAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('accept-btn-2')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('accept-btn-2'));
    await userEvent.type(screen.getByTestId('confirm-notes-2'), 'موافقة رسمية');
    await userEvent.click(screen.getByTestId('confirm-submit-2'));

    await waitFor(() => expect(mockAcceptDecl).toHaveBeenCalledWith(2, 'accept', 'موافقة رسمية'));
  });

  it('decline flow submits decline outcome', async () => {
    mockList.mockResolvedValue({ transfers: [
      transfer({ id: 2, status: 'assigned', target_office: { id: 5, name: 'x', email: 'x@t.esp' } }),
    ]});
    mockAcceptDecl.mockResolvedValue({ message: 'ok' });
    render(<MemoryRouter><SupervisionTransfersAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('decline-btn-2')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('decline-btn-2'));
    await userEvent.click(screen.getByTestId('confirm-submit-2'));

    await waitFor(() => expect(mockAcceptDecl).toHaveBeenCalledWith(2, 'decline', undefined));
  });

  it('empty state renders when filter yields nothing', async () => {
    mockList.mockResolvedValue({ transfers: [transfer({ id: 1, status: 'pending' })] });
    render(<MemoryRouter><SupervisionTransfersAdmin /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('transfer-row-1')).toBeInTheDocument());
    await userEvent.click(screen.getByTestId('filter-accepted'));
    expect(screen.getByText(/لا توجد طلبات نقل/)).toBeInTheDocument();
  });
});
