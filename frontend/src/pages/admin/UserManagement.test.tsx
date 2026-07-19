import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { UserManagement } from './UserManagement';
import type { User } from '../../types';

const mockList    = vi.fn();
const mockDestroy = vi.fn();
vi.mock('../../api/client', () => ({
  userManagementApi: {
    list:    (...a: unknown[]) => mockList(...a),
    create:  vi.fn(),
    update:  vi.fn(),
    destroy: (...a: unknown[]) => mockDestroy(...a),
  },
}));

// Names are deliberately different from role labels — otherwise
// getByText('مستخدم أعلى') would ambiguously match both the name cell
// and the role-label cell.
const sampleUsers: User[] = [
  { id: 1, name: 'أحمد',    email: 'admin@t.esp', role: 'admin',     organization_id: 1, is_active: true },
  { id: 2, name: 'محمد',    email: 'staff@t.esp', role: 'staff',     organization_id: 1, is_active: true, must_change_password: true },
  { id: 3, name: 'حسين',    email: 'su@t.esp',    role: 'superuser', organization_id: 1, is_active: true },
];

beforeEach(() => {
  mockList.mockReset();
  mockDestroy.mockReset();
  mockList.mockResolvedValue({ users: sampleUsers });
});

function renderPage() {
  return render(<MemoryRouter><UserManagement /></MemoryRouter>);
}

describe('UserManagement', () => {
  it('lists users with Arabic role labels', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText('admin@t.esp')).toBeInTheDocument());

    expect(screen.getByText('staff@t.esp')).toBeInTheDocument();
    expect(screen.getByText('su@t.esp')).toBeInTheDocument();
    expect(screen.getByText('مستخدم أعلى')).toBeInTheDocument(); // superuser role label
  });

  it('flags accounts still on the must-change-password bootstrap state', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText('staff@t.esp')).toBeInTheDocument());
    expect(screen.getByText(/بحاجة لتغيير كلمة المرور/)).toBeInTheDocument();
  });

  it('calls the delete API after the confirm prompt', async () => {
    mockDestroy.mockResolvedValue({ message: 'ok' });
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true);
    renderPage();
    await waitFor(() => expect(screen.getByText('admin@t.esp')).toBeInTheDocument());

    await userEvent.click(screen.getByLabelText('حذف admin@t.esp'));
    await waitFor(() => expect(mockDestroy).toHaveBeenCalledWith(1));

    confirmSpy.mockRestore();
  });

  it('does not call delete when the user cancels the confirm', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false);
    renderPage();
    await waitFor(() => expect(screen.getByText('admin@t.esp')).toBeInTheDocument());
    await userEvent.click(screen.getByLabelText('حذف admin@t.esp'));
    expect(mockDestroy).not.toHaveBeenCalled();
  });
});
