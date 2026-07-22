import { request } from './http';
import type { Paginated } from './admin';

/**
 * Notifications domain (JORD-9).
 *
 * Backend endpoints:
 *   GET  /notifications                — paginated inbox
 *   GET  /notifications/unread-count   — cheap counter for the bell badge
 *   POST /notifications/{id}/read      — mark one as read
 *   POST /notifications/read-all       — mark every unread as read
 */

export interface NotificationRow {
  id: number;
  type: string;
  title: string;
  body: string;
  link: string | null;
  related_type: string | null;
  related_id: number | null;
  payload: Record<string, unknown> | null;
  read_at: string | null;
  created_at: string;
  updated_at: string;
}

export const notificationsApi = {
  list: (params: { unread_only?: boolean; page?: number; per_page?: number } = {}) => {
    const q = new URLSearchParams();
    if (params.unread_only) q.set('unread_only', '1');
    if (params.page)        q.set('page', String(params.page));
    if (params.per_page)    q.set('per_page', String(params.per_page));
    const qs = q.toString();
    return request<Paginated<NotificationRow>>('GET', `/notifications${qs ? `?${qs}` : ''}`);
  },
  unreadCount: () => request<{ count: number }>('GET', '/notifications/unread-count'),
  markRead:    (id: number) => request<{ notification: NotificationRow }>('POST', `/notifications/${id}/read`),
  markAllRead: () => request<{ updated: number }>('POST', '/notifications/read-all'),
};
