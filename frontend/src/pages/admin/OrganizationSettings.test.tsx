import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { OrganizationSettings } from './OrganizationSettings';

const mockGet             = vi.fn();
const mockUpdateOrgFlags  = vi.fn();
const mockUpdateEngineer  = vi.fn();
vi.mock('../../api/client', () => ({
  adminApi: {
    getOrganizationSettings: (...a: unknown[]) => mockGet(...a),
    updateOrganizationFlags: (...a: unknown[]) => mockUpdateOrgFlags(...a),
    updateEngineerSpecHead:  (...a: unknown[]) => mockUpdateEngineer(...a),
  },
}));

const orgPayload = {
  organization: {
    id: 1, name_ar: 'مكتب اختبار', name_en: 'Test Office',
    has_excellence_award: false,
    is_bit_khibra: false,
    has_iso_cert: true,
  },
  engineers: [
    { id: 10, name_ar: 'م. أحمد', name_en: 'Ahmad', membership_number: 'EN-001',
      specialization: 'architectural', is_specialization_head: false },
    { id: 11, name_ar: 'م. سارة', name_en: 'Sara', membership_number: 'EN-002',
      specialization: 'structural', is_specialization_head: true },
  ],
};

beforeEach(() => {
  mockGet.mockReset();
  mockUpdateOrgFlags.mockReset();
  mockUpdateEngineer.mockReset();
});

describe('OrganizationSettings (JORD-76)', () => {
  it('renders the three org flag toggles reflecting server state', async () => {
    mockGet.mockResolvedValue(orgPayload);
    render(<MemoryRouter><OrganizationSettings /></MemoryRouter>);

    await waitFor(() => expect(screen.getByText(/جائزة الملك عبد الله للتميز/)).toBeInTheDocument());
    // has_iso_cert is true in the payload — its badge (+5%) renders.
    const isoRow = screen.getByTestId('org-flag-has_iso_cert');
    expect(isoRow.querySelector('input[type=checkbox]')).toBeChecked();
    // has_excellence_award is false — unchecked, no +5% badge inside its row.
    const awardRow = screen.getByTestId('org-flag-has_excellence_award');
    expect(awardRow.querySelector('input[type=checkbox]')).not.toBeChecked();
  });

  it('optimistically toggles a flag and calls the API', async () => {
    mockGet.mockResolvedValue(orgPayload);
    mockUpdateOrgFlags.mockResolvedValue({ message: 'ok' });
    render(<MemoryRouter><OrganizationSettings /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('org-flag-is_bit_khibra')).toBeInTheDocument());

    const bitKhibraRow = screen.getByTestId('org-flag-is_bit_khibra');
    await userEvent.click(bitKhibraRow.querySelector('input[type=checkbox]')!);

    expect(mockUpdateOrgFlags).toHaveBeenCalledWith({ is_bit_khibra: true });
    // Optimistic update: badge appears immediately.
    await waitFor(() => expect(bitKhibraRow.querySelector('input[type=checkbox]')).toBeChecked());
  });

  it('reverts the toggle when the save fails', async () => {
    mockGet.mockResolvedValue(orgPayload);
    mockUpdateOrgFlags.mockRejectedValue(new Error('server error'));
    render(<MemoryRouter><OrganizationSettings /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('org-flag-has_excellence_award')).toBeInTheDocument());

    const awardRow = screen.getByTestId('org-flag-has_excellence_award');
    await userEvent.click(awardRow.querySelector('input[type=checkbox]')!);

    // On failure, the checkbox should revert to false and the error surfaces.
    await waitFor(() => expect(awardRow.querySelector('input[type=checkbox]')).not.toBeChecked());
    expect(screen.getByText(/server error/)).toBeInTheDocument();
  });

  it('renders the engineer roster with the correct pre-checked state', async () => {
    mockGet.mockResolvedValue(orgPayload);
    render(<MemoryRouter><OrganizationSettings /></MemoryRouter>);

    await waitFor(() => expect(screen.getByText('م. أحمد')).toBeInTheDocument());
    // Ahmad NOT spec head, Sara IS.
    const ahmadRow = screen.getByTestId('engineer-flag-10');
    const saraRow  = screen.getByTestId('engineer-flag-11');
    expect(ahmadRow.querySelector('input[type=checkbox]')).not.toBeChecked();
    expect(saraRow.querySelector('input[type=checkbox]')).toBeChecked();
  });

  it('toggles specialization-head on an engineer and calls the API', async () => {
    mockGet.mockResolvedValue(orgPayload);
    mockUpdateEngineer.mockResolvedValue({ message: 'ok' });
    render(<MemoryRouter><OrganizationSettings /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('م. أحمد')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('engineer-flag-10').querySelector('input[type=checkbox]')!);
    expect(mockUpdateEngineer).toHaveBeenCalledWith(10, true);
  });

  it('renders empty state when the office has no engineers', async () => {
    mockGet.mockResolvedValue({ ...orgPayload, engineers: [] });
    render(<MemoryRouter><OrganizationSettings /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText(/لا يوجد مهندسون مسجّلون/)).toBeInTheDocument());
  });
});
