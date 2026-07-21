import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { OfficeSettings } from './OfficeSettings';

const mockGet             = vi.fn();
const mockUpdateFlags     = vi.fn();
const mockUpdateEngineer  = vi.fn();
vi.mock('../../api/client', () => ({
  adminApi: {
    getOfficeSettings:            (...a: unknown[]) => mockGet(...a),
    updateOfficeFlags:            (...a: unknown[]) => mockUpdateFlags(...a),
    updateOfficeEngineerSpecHead: (...a: unknown[]) => mockUpdateEngineer(...a),
  },
}));

const payload = {
  office: {
    id: 4, name: 'أحمد المقدم', email: 'ahmed@demo.esp',
    has_excellence_award: false, is_bit_khibra: false, has_iso_cert: true,
  },
  engineers: [
    { id: 10, name_ar: 'م. أحمد', name_en: 'Ahmad', membership_number: 'EN-001',
      specialization: 'architectural', is_specialization_head: false },
    { id: 11, name_ar: 'م. سارة', name_en: 'Sara', membership_number: 'EN-002',
      specialization: 'structural', is_specialization_head: true },
  ],
};

function mount(officeId = 4) {
  return render(
    <MemoryRouter initialEntries={[`/admin/offices/${officeId}`]}>
      <Routes>
        <Route path="/admin/offices/:id" element={<OfficeSettings />} />
      </Routes>
    </MemoryRouter>
  );
}

beforeEach(() => {
  mockGet.mockReset();
  mockUpdateFlags.mockReset();
  mockUpdateEngineer.mockReset();
});

describe('OfficeSettings (JORD-77)', () => {
  it('loads the office by URL id and reflects server state', async () => {
    mockGet.mockResolvedValue(payload);
    mount(4);
    await waitFor(() => expect(mockGet).toHaveBeenCalledWith(4));
    // Preloaded flags surface correctly.
    const isoRow = screen.getByTestId('office-flag-has_iso_cert');
    expect(isoRow.querySelector('input[type=checkbox]')).toBeChecked();
    const awardRow = screen.getByTestId('office-flag-has_excellence_award');
    expect(awardRow.querySelector('input[type=checkbox]')).not.toBeChecked();
  });

  it('has no save bar on a clean page', async () => {
    mockGet.mockResolvedValue(payload);
    mount();
    await waitFor(() => expect(screen.getByText('أحمد المقدم')).toBeInTheDocument());
    expect(screen.queryByTestId('save-bar')).toBeNull();
  });

  it('surfaces the save bar + unsaved chip on any toggle without calling the API', async () => {
    mockGet.mockResolvedValue(payload);
    mount();
    await waitFor(() => expect(screen.getByTestId('office-flag-is_bit_khibra')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('office-flag-is_bit_khibra').querySelector('input[type=checkbox]')!);
    expect(screen.getByTestId('save-bar')).toBeInTheDocument();
    // Save deferred until button click.
    expect(mockUpdateFlags).not.toHaveBeenCalled();
  });

  it('save PATCHes only the changed subset of office flags', async () => {
    mockGet.mockResolvedValue(payload);
    mockUpdateFlags.mockResolvedValue({ message: 'ok' });
    mount(4);
    await waitFor(() => expect(screen.getByTestId('office-flag-has_excellence_award')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('office-flag-has_excellence_award').querySelector('input[type=checkbox]')!);
    await userEvent.click(screen.getByTestId('office-flag-is_bit_khibra').querySelector('input[type=checkbox]')!);
    await userEvent.click(screen.getByTestId('save-btn'));

    await waitFor(() => expect(mockUpdateFlags).toHaveBeenCalledTimes(1));
    // Office id (4) is the first arg — diff payload is the second.
    expect(mockUpdateFlags).toHaveBeenCalledWith(4, {
      has_excellence_award: true,
      is_bit_khibra: true,
    });
  });

  it('engineer toggle is scoped to this office via updateOfficeEngineerSpecHead', async () => {
    mockGet.mockResolvedValue(payload);
    mockUpdateEngineer.mockResolvedValue({ message: 'ok' });
    mount(4);
    await waitFor(() => expect(screen.getByText('م. أحمد')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('engineer-flag-10').querySelector('input[type=checkbox]')!);
    await userEvent.click(screen.getByTestId('save-btn'));

    // Must include the officeId (4) so backend scopes to this office.
    await waitFor(() => expect(mockUpdateEngineer).toHaveBeenCalledWith(4, 10, true));
  });

  it('revert clears every draft and hides the save bar', async () => {
    mockGet.mockResolvedValue(payload);
    mount();
    await waitFor(() => expect(screen.getByTestId('office-flag-is_bit_khibra')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('office-flag-is_bit_khibra').querySelector('input[type=checkbox]')!);
    expect(screen.getByTestId('save-bar')).toBeInTheDocument();

    await userEvent.click(screen.getByTestId('reset-btn'));
    expect(screen.queryByTestId('save-bar')).toBeNull();
  });

  it('preserves drafts + shows error when save fails', async () => {
    mockGet.mockResolvedValue(payload);
    mockUpdateFlags.mockRejectedValue(new Error('server error'));
    mount();
    await waitFor(() => expect(screen.getByTestId('office-flag-has_excellence_award')).toBeInTheDocument());

    const awardRow = screen.getByTestId('office-flag-has_excellence_award');
    await userEvent.click(awardRow.querySelector('input[type=checkbox]')!);
    await userEvent.click(screen.getByTestId('save-btn'));

    await waitFor(() => expect(screen.getByText(/server error/)).toBeInTheDocument());
    expect(awardRow.querySelector('input[type=checkbox]')).toBeChecked();
  });

  it('shows engineer empty state when the office has no engineers', async () => {
    mockGet.mockResolvedValue({ ...payload, engineers: [] });
    mount();
    await waitFor(() => expect(screen.getByText(/لا يوجد مهندسون مسجّلون تحت هذا المكتب/)).toBeInTheDocument());
  });
});
