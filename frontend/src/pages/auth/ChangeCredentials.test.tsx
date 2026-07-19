import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import type { User } from '../../types';

// Mock BEFORE the ChangeCredentials import so its `useAuth` from ../App
// resolves to the mocked module. logout()/navigate() get spies we assert on.
const mockChangePassword = vi.fn();
const mockLogout         = vi.fn();
let mockUser: User | null = null;

vi.mock('../../api/client', () => ({
  authApi: {
    changePassword: (...a: unknown[]) => mockChangePassword(...a),
  },
}));

vi.mock('../../auth/AuthContext', () => ({
  useAuth: () => ({ user: mockUser, logout: mockLogout, token: 'x', login: vi.fn() }),
}));

import { ChangeCredentials } from './ChangeCredentials';

function renderPage() {
  return render(<MemoryRouter><ChangeCredentials /></MemoryRouter>);
}

beforeEach(() => {
  mockChangePassword.mockReset();
  mockLogout.mockReset();
  mockUser = null;
});

describe('ChangeCredentials — non-superuser', () => {
  beforeEach(() => {
    mockUser = { id: 1, name: 'admin', email: 'admin@t.esp', role: 'admin', organization_id: 1, must_change_password: true };
  });

  it('does not show an email field for non-superusers', () => {
    renderPage();
    expect(screen.queryByText('البريد الإلكتروني الدائم')).toBeNull();
  });

  it('submits password only and then logs out', async () => {
    mockChangePassword.mockResolvedValue({ message: 'ok' });
    renderPage();

    await userEvent.type(screen.getByLabelText(/كلمة المرور الحالية/), 'OldPass1!');
    await userEvent.type(screen.getByLabelText(/كلمة المرور الجديدة/), 'NewPass1!');
    await userEvent.type(screen.getByLabelText(/تأكيد كلمة المرور/), 'NewPass1!');
    await userEvent.click(screen.getByRole('button', { name: /حفظ ومتابعة/ }));

    await waitFor(() => expect(mockChangePassword).toHaveBeenCalled());
    // Signature: current, new, confirm, email(optional). Email must be undefined for non-superuser.
    expect(mockChangePassword).toHaveBeenCalledWith('OldPass1!', 'NewPass1!', 'NewPass1!', undefined);
    expect(mockLogout).toHaveBeenCalled();
  });
});

describe('ChangeCredentials — superuser', () => {
  beforeEach(() => {
    mockUser = { id: 5, name: 'super', email: 'boot@eqratech.com', role: 'superuser', organization_id: 1, must_change_password: true };
  });

  it('shows the extra email field for superusers', () => {
    renderPage();
    expect(screen.getByText('البريد الإلكتروني الدائم')).toBeInTheDocument();
  });

  it('sends the new email only when it actually changed', async () => {
    mockChangePassword.mockResolvedValue({ message: 'ok' });
    renderPage();

    // Change ONLY the password — email stays the same → api should get undefined
    await userEvent.type(screen.getByLabelText(/كلمة المرور الحالية/), 'Bootstrap1!');
    await userEvent.type(screen.getByLabelText(/كلمة المرور الجديدة/), 'Permanent1!');
    await userEvent.type(screen.getByLabelText(/تأكيد كلمة المرور/), 'Permanent1!');
    await userEvent.click(screen.getByRole('button', { name: /حفظ ومتابعة/ }));

    await waitFor(() => expect(mockChangePassword).toHaveBeenCalled());
    expect(mockChangePassword).toHaveBeenCalledWith('Bootstrap1!', 'Permanent1!', 'Permanent1!', undefined);
  });

  it('sends a new email when the field was modified', async () => {
    mockChangePassword.mockResolvedValue({ message: 'ok' });
    renderPage();

    // Overwrite the email field
    const emailInput = screen.getByLabelText(/البريد الإلكتروني الدائم/);
    await userEvent.clear(emailInput);
    await userEvent.type(emailInput, 'new-super@eqratech.com');
    await userEvent.type(screen.getByLabelText(/كلمة المرور الحالية/), 'Bootstrap1!');
    await userEvent.type(screen.getByLabelText(/كلمة المرور الجديدة/), 'Permanent1!');
    await userEvent.type(screen.getByLabelText(/تأكيد كلمة المرور/), 'Permanent1!');
    await userEvent.click(screen.getByRole('button', { name: /حفظ ومتابعة/ }));

    await waitFor(() => expect(mockChangePassword).toHaveBeenCalled());
    expect(mockChangePassword).toHaveBeenCalledWith('Bootstrap1!', 'Permanent1!', 'Permanent1!', 'new-super@eqratech.com');
  });

  it('blocks submit until confirmation matches', async () => {
    renderPage();
    await userEvent.type(screen.getByLabelText(/كلمة المرور الحالية/), 'Bootstrap1!');
    await userEvent.type(screen.getByLabelText(/كلمة المرور الجديدة/), 'Permanent1!');
    await userEvent.type(screen.getByLabelText(/تأكيد كلمة المرور/), 'WrongMatch!');
    const submit = screen.getByRole('button', { name: /حفظ ومتابعة/ });
    expect(submit).toBeDisabled();
    expect(screen.getByText('كلمتا المرور غير متطابقتين')).toBeInTheDocument();
  });
});

/**
 * JORD-46: the old check only enforced minLength=8, so a plain
 * "password" input passed client-side and got a 422 back from the
 * server (which requires mixedCase + digits). These tests pin the
 * inline rule so the mismatch can't regress.
 */
describe('ChangeCredentials — JORD-46 password rule alignment', () => {
  beforeEach(() => {
    mockUser = { id: 1, name: 'u', email: 'u@t.esp', role: 'applicant', organization_id: 1, must_change_password: true };
  });

  it('rejects an 8-char lowercase password (missing uppercase + digit)', async () => {
    renderPage();
    await userEvent.type(screen.getByLabelText(/كلمة المرور الحالية/), 'Bootstrap1!');
    await userEvent.type(screen.getByLabelText(/كلمة المرور الجديدة/), 'password');
    await userEvent.type(screen.getByLabelText(/تأكيد كلمة المرور/), 'password');
    expect(screen.getByRole('button', { name: /حفظ ومتابعة/ })).toBeDisabled();
    expect(screen.getByText(/لا تلبّي الشروط/)).toBeInTheDocument();
  });

  it('rejects a mixed-case password without a digit', async () => {
    renderPage();
    await userEvent.type(screen.getByLabelText(/كلمة المرور الحالية/), 'Bootstrap1!');
    await userEvent.type(screen.getByLabelText(/كلمة المرور الجديدة/), 'PasswordX');
    await userEvent.type(screen.getByLabelText(/تأكيد كلمة المرور/), 'PasswordX');
    expect(screen.getByRole('button', { name: /حفظ ومتابعة/ })).toBeDisabled();
  });

  it('accepts once mixedCase + digit + 8 chars all present', async () => {
    renderPage();
    await userEvent.type(screen.getByLabelText(/كلمة المرور الحالية/), 'Bootstrap1!');
    await userEvent.type(screen.getByLabelText(/كلمة المرور الجديدة/), 'Secret123');
    await userEvent.type(screen.getByLabelText(/تأكيد كلمة المرور/), 'Secret123');
    expect(screen.getByRole('button', { name: /حفظ ومتابعة/ })).not.toBeDisabled();
  });
});
