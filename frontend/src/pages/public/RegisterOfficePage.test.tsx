import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom/vitest';
import { MemoryRouter } from 'react-router-dom';
import { RegisterOfficePage } from './RegisterOfficePage';

// Mock the API — RegisterOfficePage submits via officeRegistrationsApi.submit
const submitMock = vi.fn();
vi.mock('../../api/officeRegistrations', () => ({
  officeRegistrationsApi: {
    submit: (...args: unknown[]) => submitMock(...args),
  },
}));

// i18n deterministic Arabic
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (k: string) => k, i18n: { language: 'ar' } }),
}));

// LanguageSwitcher pulls translation infra; render as no-op
vi.mock('../../platform/components/LanguageSwitcher', () => ({
  LanguageSwitcher: () => null,
}));

function render_(): void {
  render(
    <MemoryRouter>
      <RegisterOfficePage />
    </MemoryRouter>,
  );
}

async function fillOfficeSection(): Promise<void> {
  const inputs = screen.getAllByRole('textbox');
  // Order in the form:
  // 0: office_name_ar, 1: office_name_en, 2: license_no, 3: city,
  // 4: address_ar, 5: phone
  // Email is type=email (not 'textbox' role in some browsers) — fetch by placeholder/label
  await userEvent.type(inputs[0], 'مكتب الاختبار');
  await userEvent.type(inputs[1], 'Test Office');
  await userEvent.type(inputs[2], 'LIC-99999');
  await userEvent.type(inputs[3], 'عمان');
  await userEvent.type(inputs[4], 'شارع الاختبار');
  await userEvent.type(inputs[5], '+962-79-0000000');
  const email = screen.getByLabelText(/البريد الإلكتروني/);
  await userEvent.type(email, 'office@example.com');
}

async function fillEngineerRow(rowIndex: number, name: string, membershipNo: string): Promise<void> {
  const row = screen.getByTestId(`engineer-row-${rowIndex}`);
  const rowInputs = row.querySelectorAll('input[type="text"]');
  // 0: name_ar, 1: name_en, 2: membership_number
  await userEvent.type(rowInputs[0] as HTMLInputElement, name);
  await userEvent.type(rowInputs[2] as HTMLInputElement, membershipNo);
}

beforeEach(() => {
  submitMock.mockReset();
});

describe('RegisterOfficePage', () => {
  it('renders the office section + one engineer row by default', () => {
    render_();
    expect(screen.getByText('بيانات المكتب')).toBeInTheDocument();
    expect(screen.getByTestId('engineer-row-0')).toBeInTheDocument();
    expect(screen.queryByTestId('engineer-row-1')).not.toBeInTheDocument();
  });

  it('add engineer button appends a new row; remove takes it away again', async () => {
    render_();
    await userEvent.click(screen.getByTestId('add-engineer-btn'));
    expect(screen.getByTestId('engineer-row-1')).toBeInTheDocument();
    await userEvent.click(screen.getByTestId('remove-engineer-1'));
    expect(screen.queryByTestId('engineer-row-1')).not.toBeInTheDocument();
  });

  it('the sole engineer row does not offer a remove button (min-1 UX)', () => {
    render_();
    expect(screen.queryByTestId('remove-engineer-0')).not.toBeInTheDocument();
  });

  it('successful submit renders the reference-number success view', async () => {
    submitMock.mockResolvedValueOnce({
      reference_number: 'OFR-26-A3F92C',
      status: 'pending',
      id: 42,
      message: 'ok',
    });

    render_();
    await fillOfficeSection();
    await fillEngineerRow(0, 'م. أحمد', 'JEA-12345');
    await userEvent.click(screen.getByTestId('office-registration-submit'));

    await waitFor(() => {
      expect(screen.getByTestId('office-registration-ref')).toHaveTextContent('OFR-26-A3F92C');
    });
    expect(submitMock).toHaveBeenCalledOnce();
    // The submit payload includes the engineer array with the chosen discipline default
    const payload = submitMock.mock.calls[0][0];
    expect(payload.office_name_ar).toBe('مكتب الاختبار');
    expect(payload.engineers).toHaveLength(1);
    expect(payload.engineers[0].name_ar).toBe('م. أحمد');
    expect(payload.engineers[0].membership_number).toBe('JEA-12345');
    expect(payload.engineers[0].specialization).toBe('architectural');
  });

  it('surfaces server 422 field errors next to the correct inputs', async () => {
    const error = new Error('422') as Error & { body?: unknown };
    error.body = {
      message: 'يوجد أخطاء في البيانات',
      errors: {
        office_email: ['البريد الإلكتروني مسجَّل لحساب مستخدم آخر.'],
        'engineers.0.specialization': ['إذا كان المكتب يضم مهندساً واحداً فقط...'],
      },
    };
    submitMock.mockRejectedValueOnce(error);

    render_();
    await fillOfficeSection();
    await fillEngineerRow(0, 'م. أحمد', '1');
    await userEvent.click(screen.getByTestId('office-registration-submit'));

    await waitFor(() => {
      expect(screen.getByTestId('office-registration-top-error')).toHaveTextContent('يوجد أخطاء');
    });
    expect(screen.getByText('البريد الإلكتروني مسجَّل لحساب مستخدم آخر.')).toBeInTheDocument();
    // Per-engineer discipline error surfaces near the discipline select
    expect(screen.getByText(/إذا كان المكتب يضم مهندساً واحداً فقط/)).toBeInTheDocument();
  });

  it('changes discipline via the select', async () => {
    submitMock.mockResolvedValueOnce({
      reference_number: 'OFR-26-XYZ123',
      status: 'pending',
      id: 1,
      message: 'ok',
    });

    render_();
    await fillOfficeSection();
    await fillEngineerRow(0, 'م. سارة', '2');
    await userEvent.selectOptions(screen.getByTestId('engineer-discipline-0'), 'structural');
    await userEvent.click(screen.getByTestId('office-registration-submit'));

    await waitFor(() => expect(submitMock).toHaveBeenCalledOnce());
    expect(submitMock.mock.calls[0][0].engineers[0].specialization).toBe('structural');
  });
});
