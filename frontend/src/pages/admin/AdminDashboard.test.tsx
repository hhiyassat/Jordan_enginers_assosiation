import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import type { User } from '../../types';

import { makeQueryWrapper } from '../../test/queryWrapper';

const mockDashboard = vi.fn();
// useAdminDashboardStats() imports adminApi from ./admin post JORD-22.
vi.mock('../../api/admin', () => ({
  adminApi: { dashboard: () => mockDashboard() },
}));

let mockUser: User | null = null;
vi.mock('../../auth/AuthContext', () => ({
  useAuth: () => ({ user: mockUser, logout: vi.fn(), token: 'x', login: vi.fn() }),
}));

import { AdminDashboard } from './AdminDashboard';

function renderPage() {
  const { Wrapper } = makeQueryWrapper();
  return render(<Wrapper><MemoryRouter><AdminDashboard /></MemoryRouter></Wrapper>);
}

beforeEach(() => {
  mockDashboard.mockReset();
  mockDashboard.mockResolvedValue({
    stats: {
      total_applications: 3, pending_review: 1, approved_today: 0,
      certificates_issued: 2, active_services: 56, total_users: 5,
    },
  });
  mockUser = null;
});

describe('AdminDashboard — user management affordances', () => {
  it('shows the "إدارة المستخدمين" quick-action for admins', async () => {
    mockUser = { id: 1, name: 'admin', email: 'admin@t.esp', role: 'admin', organization_id: 1, can_manage_users: true };
    renderPage();
    await waitFor(() => expect(screen.getByText('إجراءات سريعة')).toBeInTheDocument());
    expect(screen.getByText('إدارة المستخدمين')).toBeInTheDocument();
  });

  it('shows the "المستخدمون" stat card for admins', async () => {
    mockUser = { id: 1, name: 'admin', email: 'admin@t.esp', role: 'admin', organization_id: 1, can_manage_users: true };
    renderPage();
    await waitFor(() => expect(screen.getByText('المستخدمون')).toBeInTheDocument());
  });

  it('hides the "إدارة المستخدمين" quick-action when the user cannot manage users', async () => {
    // Belt-and-braces — the /admin route now blocks staff/auditor anyway.
    // But if that gate ever slips, this ensures user-management affordances
    // still stay hidden from a role that can't act on them.
    mockUser = { id: 2, name: 'staff', email: 'staff@t.esp', role: 'staff', organization_id: 1, can_manage_users: false };
    renderPage();
    await waitFor(() => expect(screen.getByText('إجراءات سريعة')).toBeInTheDocument());
    expect(screen.queryByText('إدارة المستخدمين')).toBeNull();
    expect(screen.queryByText('المستخدمون')).toBeNull();
  });

  // ── Coverage extension: stat cards + always-visible quick actions ─

  it('renders every stat card with values wired to the API response', async () => {
    mockUser = { id: 1, name: 'admin', email: 'admin@t.esp', role: 'admin', organization_id: 1, can_manage_users: true };
    renderPage();

    // Wait for a stat card (React Query settles asynchronously — the
    // "إجراءات سريعة" heading is always rendered so it isn't a signal
    // that the async query has resolved).
    await waitFor(() => expect(screen.getByText('إجمالي الطلبات')).toBeInTheDocument());

    // Every label + count from the mocked dashboard response should be
    // rendered — regression if a stat gets accidentally dropped.
    for (const label of ['إجمالي الطلبات', 'في انتظار المراجعة', 'موافق عليها اليوم', 'الشهادات الصادرة', 'الخدمات النشطة']) {
      expect(screen.getByText(label)).toBeInTheDocument();
    }
    expect(screen.getByText(new Intl.NumberFormat('ar').format(56))).toBeInTheDocument();
  });

  it('always shows the review-queue and services quick actions regardless of role', async () => {
    // These two are role-agnostic. Only user-management is gated.
    mockUser = { id: 2, name: 'staff', email: 'staff@t.esp', role: 'staff', organization_id: 1, can_manage_users: false };
    renderPage();
    await waitFor(() => expect(screen.getByText('إجراءات سريعة')).toBeInTheDocument());

    expect(screen.getByRole('link', { name: /قائمة المراجعة/ })).toHaveAttribute('href', '/review/queue');
    expect(screen.getByRole('link', { name: /إدارة الخدمات/ })).toHaveAttribute('href', '/admin/services');
  });

  it('links the audit-log quick action to /admin/audit-logs', async () => {
    mockUser = { id: 1, name: 'admin', email: 'admin@t.esp', role: 'admin', organization_id: 1, can_manage_users: true };
    renderPage();
    await waitFor(() => expect(screen.getByText('سجل العمليات')).toBeInTheDocument());
    expect(screen.getByRole('link', { name: /سجل العمليات/ })).toHaveAttribute('href', '/admin/audit-logs');
  });

  it('surfaces the API error when the dashboard endpoint fails', async () => {
    mockDashboard.mockReset();
    mockDashboard.mockRejectedValue(new Error('dashboard down'));
    mockUser = { id: 1, name: 'admin', email: 'admin@t.esp', role: 'admin', organization_id: 1, can_manage_users: true };
    renderPage();
    await waitFor(() => expect(screen.getByText(/dashboard down/)).toBeInTheDocument());
  });
});
