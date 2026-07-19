import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { NotificationBell } from './NotificationBell';
import { makeQueryWrapper } from '../test/queryWrapper';
import type { Paginated } from '../api/admin';
import type { NotificationRow } from '../api/notifications';

/**
 * JORD-9: pin the bell dropdown behaviour.
 */

const mockList        = vi.fn();
const mockUnreadCount = vi.fn();
const mockMarkRead    = vi.fn();
const mockMarkAllRead = vi.fn();

vi.mock('../api/notifications', () => ({
  notificationsApi: {
    list:        (...a: unknown[]) => mockList(...a),
    unreadCount: () => mockUnreadCount(),
    markRead:    (...a: unknown[]) => mockMarkRead(...a),
    markAllRead: () => mockMarkAllRead(),
  },
}));

function makeRow(overrides: Partial<NotificationRow>): NotificationRow {
  return {
    id: 1, type: 'application.submitted',
    title: 'تم تقديم طلبك', body: 'تم استلام طلبك',
    link: '/my-applications',
    related_type: null, related_id: null, payload: null,
    read_at: null,
    created_at: new Date(Date.now() - 5 * 60_000).toISOString(),
    updated_at: new Date().toISOString(),
    ...overrides,
  };
}

function page(rows: NotificationRow[]): Paginated<NotificationRow> {
  return {
    data: rows,
    current_page: 1, per_page: 10, total: rows.length, last_page: 1,
    from: rows.length ? 1 : null, to: rows.length ? rows.length : null,
  };
}

function renderBell() {
  const { Wrapper } = makeQueryWrapper();
  return render(
    <Wrapper><MemoryRouter><NotificationBell /></MemoryRouter></Wrapper>
  );
}

beforeEach(() => {
  mockList.mockReset();
  mockUnreadCount.mockReset();
  mockMarkRead.mockReset();
  mockMarkAllRead.mockReset();
  mockList.mockResolvedValue(page([]));
  mockUnreadCount.mockResolvedValue({ count: 0 });
});

describe('NotificationBell — JORD-9', () => {
  it('renders the bell with no badge when there are no unread notifications', async () => {
    renderBell();
    // Bell button exists; no numeric badge.
    await waitFor(() => expect(screen.getByRole('button', { name: /الإشعارات/ })).toBeInTheDocument());
    // Numeric badge (1..99+) should NOT be present.
    expect(screen.queryByText(/^\d+$/)).toBeNull();
  });

  it('shows the unread count on the badge', async () => {
    mockUnreadCount.mockResolvedValue({ count: 3 });
    renderBell();
    await waitFor(() => expect(screen.getByText('3')).toBeInTheDocument());
  });

  it('caps the badge at "99+" for large unread counts', async () => {
    mockUnreadCount.mockResolvedValue({ count: 250 });
    renderBell();
    await waitFor(() => expect(screen.getByText('99+')).toBeInTheDocument());
  });

  it('opens the dropdown on click and shows the empty-state', async () => {
    renderBell();
    await userEvent.click(screen.getByRole('button', { name: /الإشعارات/ }));
    await waitFor(() => expect(screen.getByRole('dialog', { name: 'الإشعارات' })).toBeInTheDocument());
    expect(screen.getByText(/لا توجد إشعارات/)).toBeInTheDocument();
  });

  it('lists notifications and marks one as read on click', async () => {
    mockUnreadCount.mockResolvedValue({ count: 1 });
    mockList.mockResolvedValue(page([makeRow({ id: 42, title: 'موافقة' })]));
    mockMarkRead.mockResolvedValue({ notification: makeRow({ id: 42, read_at: new Date().toISOString() }) });

    renderBell();
    await userEvent.click(screen.getByRole('button', { name: /الإشعارات/ }));
    const row = await screen.findByText('موافقة');
    await userEvent.click(row);
    await waitFor(() => expect(mockMarkRead).toHaveBeenCalledWith(42));
  });

  it('fires markAllRead when "mark all" is clicked', async () => {
    mockUnreadCount.mockResolvedValue({ count: 2 });
    mockList.mockResolvedValue(page([makeRow({ id: 1 }), makeRow({ id: 2 })]));
    mockMarkAllRead.mockResolvedValue({ updated: 2 });

    renderBell();
    await userEvent.click(screen.getByRole('button', { name: /الإشعارات/ }));
    const markAllBtn = await screen.findByRole('button', { name: /تعليم الكل كمقروء/ });
    await userEvent.click(markAllBtn);
    await waitFor(() => expect(mockMarkAllRead).toHaveBeenCalledTimes(1));
  });

  it('closes on Escape', async () => {
    renderBell();
    await userEvent.click(screen.getByRole('button', { name: /الإشعارات/ }));
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    await userEvent.keyboard('{Escape}');
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
  });
});
