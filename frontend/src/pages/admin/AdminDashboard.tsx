import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAdminDashboardStats } from '../../api/hooks';
import { useAuth } from '../../auth/AuthContext';
import type { DashboardStats } from '../../types';

// Concrete row shape from /admin/dashboard. Fields the backend returns
// are optional at the type level (DashboardStats) — narrow to non-null
// once the query resolves.
type Stats = DashboardStats & Partial<{
  total_applications: number;
  pending_review: number;
  approved_today: number;
  certificates_issued: number;
  active_services: number;
  total_users: number;
}>;

// JORD-74: RecentApp is now typed at the API-client layer so this
// page consumes `data.recent` directly without a cast. Kept as an
// alias so subcomponent props still read clearly.
type RecentApp = NonNullable<
  NonNullable<ReturnType<typeof useAdminDashboardStats>['data']>['recent']
>[number];

export function AdminDashboard() {
  const { user } = useAuth();
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const numLocale = isRtl ? 'ar' : 'en';
  const { data, isPending, error } = useAdminDashboardStats();
  const stats = data?.stats;
  const byStatus = data?.by_status ?? {};
  const recent: RecentApp[] = data?.recent ?? [];
  const loading = isPending;
  // Defense in depth — the /admin route is gated at the SPA layer, but if
  // that gate ever slips this still hides user-mgmt affordances from an
  // actor who can't act on them.
  const canManageUsers = user?.can_manage_users ?? false;

  const cards = stats ? [
    { label: t('adminDashboard.stat.totalApplications'), value: stats.total_applications ?? 0, icon: '📋', link: '/admin/applications', color: 'bg-blue-50 border-blue-200' },
    { label: t('adminDashboard.stat.pendingReview'),     value: stats.pending_review ?? 0,     icon: '🔍', link: '/review/queue',      color: 'bg-yellow-50 border-yellow-200' },
    { label: t('adminDashboard.stat.approvedToday'),     value: stats.approved_today ?? 0,     icon: '✅', link: '/admin/applications', color: 'bg-green-50 border-green-200' },
    { label: t('adminDashboard.stat.certificates'),      value: stats.certificates_issued ?? 0, icon: '🏆', link: '/admin/certificates', color: 'bg-teal-50 border-teal-200' },
    { label: t('adminDashboard.stat.activeServices'),    value: stats.active_services ?? 0,    icon: '⚙️', link: '/admin/services',     color: 'bg-purple-50 border-purple-200' },
    ...(canManageUsers ? [{ label: t('adminDashboard.stat.users'), value: stats.total_users ?? 0, icon: '👥', link: '/admin/users', color: 'bg-gray-50 border-gray-200' }] : []),
  ] : [];

  return (
    <div className="max-w-5xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">{t('adminDashboard.title')}</h1>
        <p className="text-gray-500 text-sm mt-1">{t('adminDashboard.subtitle')}</p>
      </div>
      {error && (
        <div className="mb-6 bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm">{error.message}</div>
      )}

      {loading ? (
        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
          {[1,2,3,4,5,6].map(i => (
            <div key={i} className="h-28 bg-gray-100 rounded-xl animate-pulse" />
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
          {cards.map(card => (
            <Link
              key={card.label}
              to={card.link}
              className={`rounded-xl border-2 p-5 hover:shadow-md transition-all ${card.color}`}
            >
              <div className="text-3xl mb-2">{card.icon}</div>
              <div className="text-3xl font-bold text-gray-900">{card.value.toLocaleString(numLocale)}</div>
              <div className="text-sm text-gray-600 mt-1">{card.label}</div>
            </Link>
          ))}
        </div>
      )}

      {/* JORD-11: Recent applications + by-status breakdown */}
      {!loading && (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
          <RecentApplicationsCard
            recent={recent}
            isArabic={isRtl}
            viewAllLabel={t('adminDashboard.viewAllApps')}
            heading={t('adminDashboard.recentApplications')}
            emptyLabel={t('adminDashboard.recentEmpty')}
          />
          <ByStatusCard
            byStatus={byStatus}
            heading={t('adminDashboard.byStatus')}
            emptyLabel={t('adminDashboard.byStatusEmpty')}
          />
        </div>
      )}

      {/* Quick links */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div className="bg-white rounded-xl border border-gray-200 p-6">
          <h3 className="font-semibold text-gray-800 mb-4">{t('adminDashboard.quickActions')}</h3>
          <div className="space-y-2">
            <Link to="/review/queue" className="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-sm text-gray-700 transition-colors">
              <span>🔍</span> {t('adminDashboard.reviewQueue')}
            </Link>
            {canManageUsers && (
              <Link to="/admin/users" className="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-sm text-gray-700 transition-colors">
                <span>👥</span> {t('adminDashboard.manageUsers')}
              </Link>
            )}
            <Link to="/admin/services" className="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-sm text-gray-700 transition-colors">
              <span>⚙️</span> {t('adminDashboard.manageServices')}
            </Link>
            <Link to="/admin/audit-logs" className="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-sm text-gray-700 transition-colors">
              <span>📜</span> {t('adminDashboard.auditLogs')}
            </Link>
          </div>
        </div>

        <div className="bg-navy rounded-xl p-6 text-white">
          <h3 className="font-semibold mb-2">{t('adminDashboard.aboutTitle')}</h3>
          <p className="text-blue-200 text-sm leading-relaxed">{t('adminDashboard.aboutBody')}</p>
          <div className="mt-4 text-xs text-blue-300 space-y-1">
            <p>✓ {t('adminDashboard.aboutFeatures.dynamicForm')}</p>
            <p>✓ {t('adminDashboard.aboutFeatures.workflowEngine')}</p>
            <p>✓ {t('adminDashboard.aboutFeatures.certIssue')}</p>
            <p>✓ {t('adminDashboard.aboutFeatures.multiTenant')}</p>
          </div>
        </div>
      </div>
    </div>
  );
}

/**
 * JORD-11: 5 most recent applications with a link to the full list.
 * Takes up two grid columns to give the rows breathing room.
 */
function RecentApplicationsCard({ recent, isArabic, heading, emptyLabel, viewAllLabel }: {
  recent: RecentApp[];
  isArabic: boolean;
  heading: string;
  emptyLabel: string;
  viewAllLabel: string;
}) {
  const { t } = useTranslation();
  return (
    <div className="md:col-span-2 bg-white rounded-xl border border-gray-200 p-6">
      <div className="flex items-center justify-between mb-4">
        <h3 className="font-semibold text-gray-800">{heading}</h3>
        <Link to="/admin/applications" className="text-xs text-blue-600 hover:underline">
          {viewAllLabel}
        </Link>
      </div>
      {recent.length === 0 ? (
        <p className="text-sm text-gray-400 text-center py-6">{emptyLabel}</p>
      ) : (
        <ul className="divide-y divide-gray-100">
          {recent.map(app => (
            <li key={app.id} className="py-2 flex items-center justify-between gap-3">
              <div className="min-w-0">
                <p className="font-mono text-xs text-blue-700">{app.reference_number}</p>
                <p className="text-sm text-gray-800 truncate">
                  {(isArabic
                    ? (app.service_definition?.name_ar || app.service_definition?.name_en)
                    : (app.service_definition?.name_en || app.service_definition?.name_ar)) ?? '—'}
                </p>
                <p className="text-xs text-gray-500">{app.applicant?.name ?? '—'}</p>
              </div>
              <span className="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600 shrink-0">
                {/* JORD-87: raw enum was leaking through untranslated. */}
                {t(`status.${app.status}`, { defaultValue: app.status })}
              </span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

/**
 * JORD-11: status breakdown as a compact horizontal bar list.
 * Each row is a labelled bar whose width is proportional to the max.
 */
function ByStatusCard({ byStatus, heading, emptyLabel }: {
  byStatus: Record<string, number>;
  heading: string;
  emptyLabel: string;
}) {
  const entries = Object.entries(byStatus).sort((a, b) => b[1] - a[1]);
  const max = entries.reduce((m, [, v]) => Math.max(m, v), 0);

  return (
    <div className="bg-white rounded-xl border border-gray-200 p-6">
      <h3 className="font-semibold text-gray-800 mb-4">{heading}</h3>
      {entries.length === 0 ? (
        <p className="text-sm text-gray-400 text-center py-6">{emptyLabel}</p>
      ) : (
        <ul className="space-y-2">
          {entries.map(([status, count]) => (
            <li key={status}>
              <div className="flex items-center justify-between text-xs text-gray-600 mb-1">
                <span>{status}</span>
                <span className="font-mono">{count}</span>
              </div>
              <div className="h-2 rounded-full bg-gray-100 overflow-hidden" aria-hidden="true">
                <div
                  className="h-full bg-jea-primary rounded-full"
                  style={{ width: `${max > 0 ? (count / max) * 100 : 0}%` }}
                />
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
