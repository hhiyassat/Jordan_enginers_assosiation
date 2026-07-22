import { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Bell, Check } from 'lucide-react';
import {
  useNotifications,
  useUnreadNotificationCount,
  useMarkAllNotificationsRead,
  useMarkNotificationRead,
} from '../../api/hooks';
import type { NotificationRow } from '../../api/notifications';

/**
 * Header bell + dropdown (JORD-9).
 *
 * • Red-dot badge when unreadCount > 0.
 * • Click opens a dropdown of unread + recent-read entries (first
 *   page, capped at 10 to keep the popover compact).
 * • Clicking an entry marks it as read and follows its `link` if set.
 * • "Mark all as read" clears everything in one round trip.
 * • Closes on outside click / Escape.
 */
export function NotificationBell(): JSX.Element {
  const { t, i18n } = useTranslation();
  const navigate = useNavigate();
  const isRtl = i18n.language.startsWith('ar');

  const [open, setOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  const { data: countRes } = useUnreadNotificationCount();
  const unreadCount = countRes?.count ?? 0;
  const { data: page, isPending, error } = useNotifications({ per_page: 10 });
  const markRead = useMarkNotificationRead();
  const markAll  = useMarkAllNotificationsRead();

  // Close on outside click + Escape.
  useEffect(() => {
    if (!open) return;
    const onClick = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') setOpen(false); };
    document.addEventListener('mousedown', onClick);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onClick);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  const handleOpen = (n: NotificationRow): void => {
    if (n.read_at === null) markRead.mutate(n.id);
    if (n.link) navigate(n.link);
    setOpen(false);
  };

  // JORD-81: memoised so the identity is stable across renders. The
  // function only depends on `t`, and `t` is stable per language, so
  // subcomponents that receive `timeAgo` as a prop no longer thrash
  // memoisation. Also cheaper for a bell that re-renders on every
  // notification poll.
  const timeAgo = useCallback((iso: string): string => {
    const ms = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(ms / 60_000);
    if (mins < 1)  return t('notifications.just_now');
    if (mins < 60) return t('notifications.minutes_ago', { count: mins });
    const hours = Math.floor(mins / 60);
    if (hours < 24) return t('notifications.hours_ago', { count: hours });
    const days = Math.floor(hours / 24);
    return t('notifications.days_ago', { count: days });
  }, [t]);

  return (
    <div className="relative" ref={containerRef}>
      <button
        type="button"
        onClick={() => setOpen(o => !o)}
        aria-label={
          unreadCount > 0
            ? `${t('layout.notifications')} — ${unreadCount} ${t('notifications.unread')}`
            : t('layout.notifications')
        }
        aria-expanded={open}
        aria-haspopup="dialog"
        className="p-1.5 rounded hover:bg-white/10 transition-colors relative focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
      >
        <Bell size={17} aria-hidden="true" />
        {unreadCount > 0 && (
          <span
            className="absolute top-0 right-0 min-w-[16px] h-4 px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center"
            aria-hidden="true"
          >
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </button>

      {open && (
        <div
          role="dialog"
          aria-label={t('notifications.title')}
          dir={isRtl ? 'rtl' : 'ltr'}
          className={`absolute top-full mt-2 ${isRtl ? 'left-0' : 'right-0'} w-80 sm:w-96 bg-white text-jea-text rounded-xl shadow-xl border border-jea-border z-50 max-h-[70vh] overflow-hidden flex flex-col`}
        >
          <div className="flex items-center justify-between px-4 py-3 border-b border-jea-border">
            <h3 className="text-sm font-bold">{t('notifications.title')}</h3>
            {unreadCount > 0 && (
              <button
                type="button"
                onClick={() => markAll.mutate()}
                disabled={markAll.isPending}
                className="text-xs text-jea-primary hover:underline disabled:opacity-60"
              >
                <Check size={11} className="inline mx-1" aria-hidden="true" />
                {t('notifications.markAllRead')}
              </button>
            )}
          </div>

          <div className="overflow-y-auto flex-1 divide-y divide-jea-border">
            {isPending && (
              [1, 2, 3].map(i => (
                <div key={i} className="px-4 py-3">
                  <div className="h-3 bg-jea-bg rounded animate-pulse mb-2" />
                  <div className="h-2 bg-jea-bg rounded w-2/3 animate-pulse" />
                </div>
              ))
            )}

            {error && (
              <div className="px-4 py-6 text-center text-xs text-red-600">
                {t('notifications.loadFailed')}
              </div>
            )}

            {!isPending && !error && (page?.data ?? []).length === 0 && (
              <div className="px-4 py-10 text-center text-xs text-jea-muted">
                {t('notifications.empty')}
              </div>
            )}

            {(page?.data ?? []).map(n => (
              <button
                key={n.id}
                type="button"
                onClick={() => handleOpen(n)}
                className={`w-full text-start px-4 py-3 hover:bg-jea-bg transition-colors ${
                  n.read_at === null ? 'bg-blue-50/60' : ''
                }`}
              >
                <div className="flex items-start gap-2">
                  {n.read_at === null && (
                    <span className="mt-1.5 shrink-0 w-2 h-2 rounded-full bg-jea-primary" aria-hidden="true" />
                  )}
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-jea-text truncate">{n.title}</p>
                    <p className="text-xs text-jea-muted mt-0.5 line-clamp-2">{n.body}</p>
                    <p className="text-[10px] text-jea-muted/70 mt-1">{timeAgo(n.created_at)}</p>
                  </div>
                </div>
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
