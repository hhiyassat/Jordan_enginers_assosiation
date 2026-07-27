import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom/vitest';
import { AdminOfficeRegistrations } from './AdminOfficeRegistrations';
import type { OfficeRegistrationRow } from '../../api/officeRegistrations';

const indexMock   = vi.fn();
const approveMock = vi.fn();
const rejectMock  = vi.fn();

vi.mock('../../api/officeRegistrations', () => ({
  officeRegistrationsApi: {
    index:   (...a: unknown[]) => indexMock(...a),
    approve: (...a: unknown[]) => approveMock(...a),
    reject:  (...a: unknown[]) => rejectMock(...a),
  },
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (k: string) => k, i18n: { language: 'ar' } }),
}));

function rowFixture(overrides: Partial<OfficeRegistrationRow> = {}): OfficeRegistrationRow {
  return {
    id: 1,
    reference_number: 'OFR-26-A3F92C',
    office_name_ar: 'مكتب الاختبار',
    office_name_en: 'Test Office',
    office_license_no: 'LIC-42',
    office_city: 'عمان',
    office_address_ar: 'شارع الاختبار',
    office_phone: '+962-79-0000000',
    office_email: 'office@example.com',
    engineers: [{
      name_ar: 'م. أحمد', name_en: 'Ahmad',
      membership_number: 'M1', specialization: 'architectural',
    }],
    status: 'pending',
    review_notes: null,
    reviewed_by_user_id: null,
    reviewed_at: null,
    approved_organization_id: null,
    created_at: '2026-07-27T10:00:00Z',
    updated_at: '2026-07-27T10:00:00Z',
    reviewer: null,
    ...overrides,
  };
}

beforeEach(() => {
  indexMock.mockReset();
  approveMock.mockReset();
  rejectMock.mockReset();
});

describe('AdminOfficeRegistrations', () => {
  it('shows the empty state when no registrations exist for the filter', async () => {
    indexMock.mockResolvedValueOnce({ registrations: { data: [] } });
    render(<AdminOfficeRegistrations />);
    await waitFor(() => {
      expect(screen.getByTestId('office-registrations-empty')).toBeInTheDocument();
    });
  });

  it('lists rows and expands details when the header is clicked', async () => {
    indexMock.mockResolvedValueOnce({ registrations: { data: [rowFixture()] } });
    render(<AdminOfficeRegistrations />);
    await waitFor(() => {
      expect(screen.getByTestId('office-registration-row-1')).toBeInTheDocument();
    });
    // Details are collapsed initially
    expect(screen.queryByText('LIC-42')).not.toBeInTheDocument();
    await userEvent.click(screen.getByText('مكتب الاختبار'));
    // Now the license number field is visible
    expect(screen.getByText('LIC-42')).toBeInTheDocument();
    // And the engineers table shows the row
    expect(screen.getByText(/م. أحمد/)).toBeInTheDocument();
  });

  it('changing the status filter re-fetches with the new value', async () => {
    indexMock.mockResolvedValue({ registrations: { data: [] } });
    render(<AdminOfficeRegistrations />);
    await waitFor(() => expect(indexMock).toHaveBeenCalledWith('pending'));

    await userEvent.selectOptions(screen.getByTestId('admin-office-reg-status-filter'), 'approved');
    await waitFor(() => expect(indexMock).toHaveBeenCalledWith('approved'));
  });

  it('approve calls the API and refetches on success', async () => {
    indexMock.mockResolvedValueOnce({ registrations: { data: [rowFixture()] } });
    approveMock.mockResolvedValueOnce({ message: 'ok', registration_id: 1, organization_id: 42 });
    indexMock.mockResolvedValueOnce({ registrations: { data: [] } });

    render(<AdminOfficeRegistrations />);
    await waitFor(() => expect(screen.getByTestId('office-registration-row-1')).toBeInTheDocument());
    await userEvent.click(screen.getByText('مكتب الاختبار'));
    await userEvent.click(screen.getByTestId('office-registration-approve-1'));

    await waitFor(() => expect(approveMock).toHaveBeenCalledWith(1, undefined));
    await waitFor(() => expect(indexMock).toHaveBeenCalledTimes(2));
  });

  it('reject requires notes before calling the API', async () => {
    indexMock.mockResolvedValueOnce({ registrations: { data: [rowFixture()] } });
    render(<AdminOfficeRegistrations />);
    await waitFor(() => expect(screen.getByTestId('office-registration-row-1')).toBeInTheDocument());
    await userEvent.click(screen.getByText('مكتب الاختبار'));
    // Click reject without notes → API is NOT called, inline error appears
    await userEvent.click(screen.getByTestId('office-registration-reject-1'));
    expect(rejectMock).not.toHaveBeenCalled();
    expect(screen.getByText(/سبب الرفض مطلوب/)).toBeInTheDocument();
  });

  it('reject with notes calls the API', async () => {
    indexMock.mockResolvedValueOnce({ registrations: { data: [rowFixture()] } });
    rejectMock.mockResolvedValueOnce({ message: 'ok', registration_id: 1 });
    indexMock.mockResolvedValueOnce({ registrations: { data: [] } });

    render(<AdminOfficeRegistrations />);
    await waitFor(() => expect(screen.getByTestId('office-registration-row-1')).toBeInTheDocument());
    await userEvent.click(screen.getByText('مكتب الاختبار'));
    await userEvent.type(screen.getByTestId('office-registration-notes-1'), 'رخصة غير صالحة');
    await userEvent.click(screen.getByTestId('office-registration-reject-1'));

    await waitFor(() => expect(rejectMock).toHaveBeenCalledWith(1, 'رخصة غير صالحة'));
  });

  it('non-pending rows show reviewer info instead of action buttons', async () => {
    indexMock.mockResolvedValueOnce({
      registrations: {
        data: [rowFixture({
          status: 'approved',
          reviewed_at: '2026-07-27T11:00:00Z',
          review_notes: 'ok',
          reviewer: { id: 9, name: 'مدير النقابة', email: 'admin@jea.test', role: 'admin' },
        })],
      },
    });
    render(<AdminOfficeRegistrations />);
    await waitFor(() => expect(screen.getByTestId('office-registration-row-1')).toBeInTheDocument());
    await userEvent.click(screen.getByText('مكتب الاختبار'));

    expect(screen.queryByTestId('office-registration-approve-1')).not.toBeInTheDocument();
    expect(screen.queryByTestId('office-registration-reject-1')).not.toBeInTheDocument();
    expect(screen.getByText(/مدير النقابة/)).toBeInTheDocument();
    expect(screen.getByText(/ok/)).toBeInTheDocument();
  });
});
