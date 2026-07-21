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
    // has_iso_cert is true in the payload → checkbox pre-checked.
    const isoRow = screen.getByTestId('org-flag-has_iso_cert');
    expect(isoRow.querySelector('input[type=checkbox]')).toBeChecked();
    // has_excellence_award is false → unchecked.
    const awardRow = screen.getByTestId('org-flag-has_excellence_award');
    expect(awardRow.querySelector('input[type=checkbox]')).not.toBeChecked();
  });

  it('does not render the save bar when the page is clean', async () => {
    mockGet.mockResolvedValue(orgPayload);
    render(<MemoryRouter><OrganizationSettings /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText(/جائزة الملك عبد الله للتميز/)).toBeInTheDocument());
    expect(screen.queryByTestId('save-bar')).toBeNull();
  });

  it('toggling a flag surfaces the save bar and marks the row as unsaved', async () => {
    mockGet.mockResolvedValue(orgPayload);
    render(<MemoryRouter><OrganizationSettings /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('org-flag-is_bit_khibra')).toBeInTheDocument());

    const bitKhibraRow = screen.getByTestId('org-flag-is_bit_khibra');
    await userEvent.click(bitKhibraRow.querySelector('input[type=checkbox]')!);

    // Save bar appears with a "1 unsaved change" counter.
    const saveBar = screen.getByTestId('save-bar');
    expect(saveBar).toBeInTheDocument();
    expect(saveBar.textContent).toMatch(/1/);
    // The row itself gets an "unsaved" badge.
    expect(bitKhibraRow.textContent).toMatch(/تغيير غير محفوظ|unsaved/);
    // API has NOT been called yet — save is deferred to the button.
    expect(mockUpdateOrgFlags).not.toHaveBeenCalled();
  });

  it('save button PATCHes only the changed subset of org flags', async () => {
    mockGet.mockResolvedValue(orgPayload);
    mockUpdateOrgFlags.mockResolvedValue({ message: 'ok' });
    render(<MemoryRouter><OrganizationSettings /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('org-flag-has_excellence_award')).toBeInTheDocument());

    // Toggle two of the three flags, leave the third alone.
    await userEvent.click(screen.getByTestId('org-flag-has_excellence_award').querySelector('input[type=checkbox]')!);
    await userEvent.click(screen.getByTestId('org-flag-is_bit_khibra').querySelector('input[type=checkbox]')!);
    await userEvent.click(screen.getByTestId('save-btn'));

    await waitFor(() => expect(mockUpdateOrgFlags).toHaveBeenCalledTimes(1));
    // The PATCH payload carries ONLY the two flipped flags — has_iso_cert
    // was untouched and MUST NOT be resent (idempotent-safe but noisy).
    expect(mockUpdateOrgFlags).toHaveBeenCalledWith({
      has_excellence_award: true,
      is_bit_khibra: true,
    });
    // Success banner shows and save bar disappears (page re-clean).
    expect(screen.getByText(/تم حفظ|Changes saved/)).toBeInTheDocument();
    expect(screen.queryByTestId('save-bar')).toBeNull();
  });

  it('save button also PATCHes each changed engineer in parallel', async () => {
    mockGet.mockResolvedValue(orgPayload);
    mockUpdateOrgFlags.mockResolvedValue({ message: 'ok' });
    mockUpdateEngineer.mockResolvedValue({ message: 'ok' });
    render(<MemoryRouter><OrganizationSettings /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('م. أحمد')).toBeInTheDocument());

    // Toggle Ahmad ON (was false) — Sara stays ON (unchanged).
    await userEvent.click(screen.getByTestId('engineer-flag-10').querySelector('input[type=checkbox]')!);
    await userEvent.click(screen.getByTestId('save-btn'));

    await waitFor(() => expect(mockUpdateEngineer).toHaveBeenCalledTimes(1));
    expect(mockUpdateEngineer).toHaveBeenCalledWith(10, true);
    // Sara (id=11) MUST NOT be in the calls — she wasn't toggled.
    expect(mockUpdateEngineer).not.toHaveBeenCalledWith(11, expect.anything());
    // Org flags weren't touched — that PATCH must not fire either.
    expect(mockUpdateOrgFlags).not.toHaveBeenCalled();
  });

  it('reverting drops all edits and hides the save bar', async () => {
    mockGet.mockResolvedValue(orgPayload);
    render(<MemoryRouter><OrganizationSettings /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('org-flag-is_bit_khibra')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('org-flag-is_bit_khibra').querySelector('input[type=checkbox]')!);
    expect(screen.getByTestId('save-bar')).toBeInTheDocument();

    await userEvent.click(screen.getByTestId('reset-btn'));

    expect(screen.queryByTestId('save-bar')).toBeNull();
    expect(screen.getByTestId('org-flag-is_bit_khibra').querySelector('input[type=checkbox]')).not.toBeChecked();
  });

  it('keeps unsaved edits + shows the error when save fails', async () => {
    mockGet.mockResolvedValue(orgPayload);
    mockUpdateOrgFlags.mockRejectedValue(new Error('server error'));
    render(<MemoryRouter><OrganizationSettings /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('org-flag-has_excellence_award')).toBeInTheDocument());

    const awardRow = screen.getByTestId('org-flag-has_excellence_award');
    await userEvent.click(awardRow.querySelector('input[type=checkbox]')!);
    await userEvent.click(screen.getByTestId('save-btn'));

    await waitFor(() => expect(screen.getByText(/server error/)).toBeInTheDocument());
    // Draft state NOT reverted — user can retry without redoing the click.
    expect(awardRow.querySelector('input[type=checkbox]')).toBeChecked();
    expect(screen.getByTestId('save-bar')).toBeInTheDocument();
  });

  it('renders empty state when the office has no engineers', async () => {
    mockGet.mockResolvedValue({ ...orgPayload, engineers: [] });
    render(<MemoryRouter><OrganizationSettings /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText(/لا يوجد مهندسون مسجّلون/)).toBeInTheDocument());
  });
});
