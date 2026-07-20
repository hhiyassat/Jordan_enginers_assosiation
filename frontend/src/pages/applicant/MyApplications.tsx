import React, { useMemo, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useMyApplications } from '../../api/hooks';
import type { Application } from '../../types';
import { isOngoing, orderForApplicant } from './applicationStatus';
import { MiniStageTimeline } from './MiniStageTimeline';

// Colour + emoji are language-agnostic; the label comes from i18n at
// render time via t('status.<key>').
const STATUS_STYLE: Record<string, { color: string; icon: string }> = {
  draft:                    { color: 'bg-gray-100 text-gray-600',    icon: '📝' },
  submitted:                { color: 'bg-blue-100 text-blue-700',    icon: '📨' },
  under_review:             { color: 'bg-yellow-100 text-yellow-700',icon: '🔍' },
  modifications_requested:  { color: 'bg-orange-100 text-orange-700',icon: '✏️' },
  pending_payment:          { color: 'bg-yellow-100 text-yellow-800',icon: '💳' },
  approved:                 { color: 'bg-green-100 text-green-700',  icon: '✅' },
  rejected:                 { color: 'bg-red-100 text-red-700',      icon: '❌' },
  certificate_issued:       { color: 'bg-teal-100 text-teal-700',    icon: '🏆' },
};

type Filter = 'ongoing' | 'all';

export function MyApplications() {
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  // JORD-33: React Query dedupes concurrent fetches + caches across
  // route changes. Coming back from Apply → MyApplications no longer
  // spins the loader when the list hasn't gone stale.
  const { data, isPending, error } = useMyApplications();
  const apps = data ?? [];
  const loading = isPending;
  const [filter, setFilter]   = useState<Filter>('ongoing');
  const location = useLocation();
  const justSubmitted = (location.state as { submitted?: boolean })?.submitted;

  const { visible, ongoingCount, totalCount } = useMemo(() => {
    const ordered = orderForApplicant(apps);
    const visibleApps = filter === 'ongoing' ? ordered.filter(isOngoing) : ordered;
    return {
      visible: visibleApps,
      ongoingCount: apps.filter(isOngoing).length,
      totalCount: apps.length,
    };
  }, [apps, filter]);

  if (loading) return <div className="flex justify-center py-20"><div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" /></div>;
  if (error)   return <div className="p-8 text-red-600 text-center">{error.message}</div>;

  return (
    <div className="max-w-4xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      {justSubmitted && (
        <div className="mb-6 bg-green-50 border border-green-200 text-green-700 px-5 py-4 rounded-xl text-sm">
          ✅ {t('myApplications.justSubmitted')}
        </div>
      )}

      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{t('myApplications.title')}</h1>
          <p className="text-gray-500 text-sm mt-1">
            {filter === 'ongoing'
              ? `${ongoingCount} / ${totalCount}`
              : totalCount}
          </p>
        </div>
        <Link
          to="/services"
          className="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 font-medium"
        >
          + {t('nav.services')}
        </Link>
      </div>

      {/* Filter tabs — ongoing by default per office ask: the engineer
          office wants THEIR to-do list, not the archive of everything. */}
      <div className="flex items-center gap-2 mb-5" role="tablist" aria-label={t('common.filter', { defaultValue: 'Filter' })}>
        <FilterTab active={filter === 'ongoing'} onClick={() => setFilter('ongoing')} count={ongoingCount}>
          {t('myApplications.tabs.ongoing')}
        </FilterTab>
        <FilterTab active={filter === 'all'} onClick={() => setFilter('all')} count={totalCount}>
          {t('myApplications.tabs.all')}
        </FilterTab>
      </div>

      {visible.length === 0 ? (
        <div className="text-center py-20 text-gray-400">
          <p className="text-5xl mb-4">📋</p>
          <p className="text-lg font-medium text-gray-500">
            {filter === 'ongoing' && totalCount > 0
              ? t('myApplications.emptyOngoing')
              : t('myApplications.empty')}
          </p>
          <Link to="/services" className="mt-4 inline-block text-blue-600 hover:underline text-sm">
            {t('nav.services')}
          </Link>
        </div>
      ) : (
        <div className="space-y-3">
          {visible.map(app => <ApplicationRow key={app.id} app={app} />)}
        </div>
      )}
    </div>
  );
}

function FilterTab({ active, onClick, count, children }: {
  active: boolean;
  onClick: () => void;
  count: number;
  children: React.ReactNode;
}) {
  return (
    <button
      role="tab"
      aria-selected={active}
      onClick={onClick}
      className={`px-4 py-1.5 text-sm rounded-full font-medium transition-colors ${
        active
          ? 'bg-jea-primary text-white'
          : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
      }`}
    >
      {children}
      <span className={`mr-1.5 inline-flex items-center justify-center px-1.5 rounded-full text-[10px] ${
        active ? 'bg-white/20' : 'bg-gray-300 text-gray-600'
      }`}>{count}</span>
    </button>
  );
}

function ApplicationRow({ app }: { app: Application }) {
  const { t, i18n } = useTranslation();
  const style = STATUS_STYLE[app.status] ?? { color: 'bg-gray-100 text-gray-600', icon: '❓' };
  const statusLabel = t(`status.${app.status}`, { defaultValue: app.status });
  const needsAction = app.status === 'modifications_requested';
  const stages = app.service_definition?.schema?.workflow?.stages ?? [];
  const dateLocale = i18n.language.startsWith('ar') ? 'ar-EG' : 'en-JO';

  return (
    <Link
      to={`/applications/${app.id}`}
      className={`block bg-white rounded-xl border-2 p-5 hover:shadow-sm transition-all ${
        needsAction
          ? 'border-orange-400 hover:border-orange-500'
          : 'border-gray-200 hover:border-blue-300'
      }`}
    >
      {needsAction && (
        <div className="mb-3 bg-orange-100 text-orange-800 text-xs font-medium px-3 py-2 rounded-lg flex items-center gap-2">
          <span>⚠️</span>
          <span>{t('status.modifications_requested')}</span>
        </div>
      )}

      <div className="flex items-start justify-between gap-4">
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-3 flex-wrap">
            <span className="font-mono text-xs text-gray-400">{app.reference_number}</span>
            {/* JORD-14: contract identifier inherited from the project. */}
            {app.contract_no && (
              <span className="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-jea-accent text-jea-primary">
                {t('myApplications.contractNo')} · {app.contract_no}
              </span>
            )}
            <span className={`text-xs px-2.5 py-0.5 rounded-full font-medium ${style.color}`}>
              {style.icon} {statusLabel}
            </span>
          </div>

          <p className="font-semibold text-gray-900 mt-2">
            {app.service_definition?.name_ar || '—'}
          </p>

          <div className="flex items-center gap-4 mt-2 text-xs text-gray-400 flex-wrap">
            {app.submitted_at && (
              <span>📅 {new Date(app.submitted_at).toLocaleDateString(dateLocale)}</span>
            )}
            {(app.fee_amount ?? 0) > 0 && (
              <span>💰 {app.fee_amount} {app.service_definition?.currency ?? 'JOD'}</span>
            )}
            {/* Certificate download — only when the case actually issued
                a cert. Signed URL carries the qr_token so no session
                needed. stopPropagation keeps the outer <Link to
                /applications/{id}> from firing when clicked. */}
            {app.certificate_pdf_url && (
              <a
                href={app.certificate_pdf_url}
                target="_blank"
                rel="noreferrer"
                onClick={e => e.stopPropagation()}
                className="text-teal-700 bg-teal-50 border border-teal-200 rounded-full px-2 py-0.5 font-semibold hover:bg-teal-100"
                data-testid="certificate-pdf-link"
              >
                📄 {t('myApplications.downloadCertificate')}
              </a>
            )}
          </div>

          {/* Compact stage timeline — the engineer office sees where the
              application is inside the JEA workflow without opening it. */}
          <MiniStageTimeline stages={stages} currentStageId={app.current_stage ?? null} />
        </div>

        <div className="flex-shrink-0 text-gray-300 text-xl">›</div>
      </div>
    </Link>
  );
}
