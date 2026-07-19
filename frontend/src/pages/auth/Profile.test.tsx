import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { Profile } from './Profile';
import type { User } from '../../types';

/**
 * JORD-10: pins the Profile page behaviour.
 *   • name + phone are editable
 *   • email is rendered but disabled
 *   • save button stays disabled until fields diverge from user props
 *   • after save the AuthContext is refreshed with the new payload
 */

const mockUpdateProfile = vi.fn();
const mockLogin = vi.fn();
let mockUser: User | null = null;

vi.mock('../../api/client', () => ({
  authApi: {
    updateProfile: (...a: unknown[]) => mockUpdateProfile(...a),
  },
}));

vi.mock('../../auth/AuthContext', () => ({
  useAuth: () => ({
    user: mockUser, token: 'x',
    login: mockLogin, logout: vi.fn(),
  }),
}));

function renderPage() {
  return render(<MemoryRouter><Profile /></MemoryRouter>);
}

beforeEach(() => {
  mockUpdateProfile.mockReset();
  mockLogin.mockReset();
  mockUser = {
    id: 1, name: 'حسين', email: 'h@t.esp',
    phone: '0790000000', role: 'applicant', organization_id: 1,
  };
});

describe('Profile — JORD-10', () => {
  it('renders name, email (disabled), phone, and role', () => {
    renderPage();
    expect(screen.getByRole('textbox', { name: /الاسم/ })).toHaveValue('حسين');
    expect(screen.getByRole('textbox', { name: /البريد/ })).toBeDisabled();
    expect(screen.getByRole('textbox', { name: /رقم الهاتف/ })).toHaveValue('0790000000');
  });

  it('keeps the save button disabled until a field diverges from user props', async () => {
    renderPage();
    const btn = screen.getByRole('button', { name: /حفظ التغييرات/ });
    expect(btn).toBeDisabled();
    await userEvent.type(screen.getByRole('textbox', { name: /الاسم/ }), 'ي');
    expect(btn).not.toBeDisabled();
  });

  it('submits the trimmed name + phone and refreshes AuthContext on success', async () => {
    mockUpdateProfile.mockResolvedValue({ user: { ...mockUser!, name: 'حسين ي' } });
    renderPage();
    const nameInput = screen.getByRole('textbox', { name: /الاسم/ });
    await userEvent.clear(nameInput);
    await userEvent.type(nameInput, '  حسين ي  ');
    await userEvent.click(screen.getByRole('button', { name: /حفظ التغييرات/ }));
    await waitFor(() => expect(mockUpdateProfile).toHaveBeenCalledTimes(1));
    expect(mockUpdateProfile).toHaveBeenCalledWith({ name: 'حسين ي', phone: '0790000000' });
    // AuthContext.login gets called with the token + fresh user payload
    // so the header avatar picks up the updated name immediately.
    expect(mockLogin).toHaveBeenCalledWith('x', expect.objectContaining({ name: 'حسين ي' }));
  });

  it('sends phone: null when the user clears the phone field', async () => {
    mockUpdateProfile.mockResolvedValue({ user: mockUser! });
    renderPage();
    await userEvent.clear(screen.getByRole('textbox', { name: /رقم الهاتف/ }));
    await userEvent.click(screen.getByRole('button', { name: /حفظ التغييرات/ }));
    await waitFor(() => expect(mockUpdateProfile).toHaveBeenCalledTimes(1));
    expect(mockUpdateProfile).toHaveBeenCalledWith(expect.objectContaining({ phone: null }));
  });

  it('shows a success banner after saving', async () => {
    mockUpdateProfile.mockResolvedValue({ user: mockUser! });
    renderPage();
    await userEvent.type(screen.getByRole('textbox', { name: /الاسم/ }), 'x');
    await userEvent.click(screen.getByRole('button', { name: /حفظ التغييرات/ }));
    await waitFor(() => expect(screen.getByText(/تم حفظ بياناتك/)).toBeInTheDocument());
  });

  it('surfaces a validation error inline when the API rejects', async () => {
    mockUpdateProfile.mockRejectedValue(Object.assign(new Error('bad'), {
      errors: { name: ['الاسم قصير جدًا'] },
    }));
    renderPage();
    await userEvent.type(screen.getByRole('textbox', { name: /الاسم/ }), 'x');
    await userEvent.click(screen.getByRole('button', { name: /حفظ التغييرات/ }));
    await waitFor(() => expect(screen.getByText(/الاسم قصير جدًا/)).toBeInTheDocument());
  });

  it('renders a link to the change-credentials page in the security section', () => {
    renderPage();
    const link = screen.getByRole('link', { name: /تغيير كلمة المرور/ });
    expect(link).toHaveAttribute('href', '/auth/change-credentials');
  });
});
