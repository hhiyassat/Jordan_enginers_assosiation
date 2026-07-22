import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  AlertCircle, AlertTriangle, CheckCircle2, ChevronRight, ClipboardCheck,
  Clock, Gavel, Inbox, TrendingUp,
  type LucideIcon,
} from 'lucide-react';
import { reviewApi } from '../../api/client';
import type { ReviewDashboardResponse } from '../../api/review';
import { errorMessage } from '../../platform/utils/errorMessage';

/**
 * ReviewDashboard — JORD-88 (PM)
 *
 * Reviewer landing page. Before this shipped, staff/auditor logged in
 * and landed straight on the queue table with no signal of workload
 * or SLA pressure. This page aggregates the four numbers that matter
 * per shift (claimed by me, queue backlog my role can act on, overdue
 * cases, decisions closed this week), plus a couple of lists to seed
 * quick jumps back into the review flow.
 *
 * Non-goals:
 *   • This page has no mutation controls — no claim / decide / assign.
 *     Those all live on ReviewPanel where the full context is loaded.
 *   • No filter tabs. This is a summary; the queue page itself owns
 *     filtering by service / role / status.
 */

const DECISION_STYLE: Record<string, string> = {
  approved:                'bg-emerald-100 text-emerald-800',
  rejected:                'bg-red-100 text-red-700',
  modifications_requested: 'bg-orange-100 text-orange-800',
};

export function ReviewDashboard() {
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const isArabic = isRtl;
  const dateLocale = isArabic ? 'ar-EG' : 'en-JO';

  const [data, setData]       = useState<ReviewDashboardResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError]     = useState('');

  useEffect(() => {
    setLoading(true);
    reviewApi.dashboard()
      .then(setData)
      .catch(e => setError(errorMessage(e)))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return (
    <div className="flex justify-center py-20">
      <div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" />
    </div>
  );

  if (error || !data) return (
    <div className="max-w-3xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      <div role="alert" className="bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm flex items-start gap-2" data-testid="review-dashboard-error">
        <AlertCircle size={16} className="mt-0.5 shrink-0" aria-hidden="true" />
        <span>{error || (isArabic ? 'تعذّر تحميل لوحة التحكم.' : 'Failed to load dashboard.')}</span>
      </div>
    </div>
  );

  const localisedServiceName = (r: { service_name_ar: string | null; service_name_en: string | null }) =>
    isArabic
      ? (r.service_name_ar || r.service_name_en || '—')
      : (r.service_name_en || r.service_name_ar || '—');

  return (
    <div className="max-w-6xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      <header className="mb-6 flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            {isArabic ? 'لوحة تحكم المدقق' : 'Reviewer Dashboard'}
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            {isArabic
              ? 'نظرة سريعة على أعباء المراجعة وطابور الطلبات.'
              : 'At-a-glance review workload and queue backlog.'}
          </p>
        </div>
        <Link
          to="/review/queue"
          data-testid="open-queue-link"
          className="inline-flex items-center gap-1 px-4 py-2 bg-jea-primary text-white text-sm rounded-lg hover:opacity-90 font-semibold"
        >
          <Inbox size={14} aria-hidden="true" />
          {isArabic ? 'فتح طابور الطلبات' : 'Open review queue'}
        </Link>
      </header>

      {/* Headline tiles */}
      <section className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <StatTile
          testId="tile-my-in-progress"
          icon={ClipboardCheck}
          label={isArabic ? 'قيد المراجعة عندي' : 'Claimed by me'}
          value={data.stats.my_in_progress}
        />
        <StatTile
          testId="tile-queue-available"
          icon={Inbox}
          label={isArabic ? 'في الطابور' : 'In the queue'}
          value={data.stats.queue_available}
        />
        <StatTile
          testId="tile-overdue"
          icon={AlertTriangle}
          label={isArabic ? 'متأخرة' : 'Overdue'}
          value={data.stats.overdue}
          highlight={data.stats.overdue > 0}
        />
        <StatTile
          testId="tile-decided-this-week"
          icon={CheckCircle2}
          label={isArabic ? 'قرارات هذا الأسبوع' : 'Decided this week'}
          value={data.stats.decided_this_week}
        />
      </section>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        {/* By-decision-this-month */}
        <section className="bg-white border border-gray-200 rounded-xl p-4">
          <div className="flex items-center justify-between mb-3">
            <h2 className="text-sm font-bold text-gray-800 flex items-center gap-1">
              <TrendingUp size={14} aria-hidden="true" />
              {isArabic ? 'قرارات هذا الشهر' : 'Decisions this month'}
            </h2>
            <span className="text-xs text-gray-500">{data.stats.decided_this_month}</span>
          </div>
          <ul className="space-y-2 text-sm">
            <DecisionRow
              label={isArabic ? 'موافق عليه' : 'Approved'}
              value={data.by_decision_this_month.approved}
              cls="bg-emerald-100 text-emerald-800"
              testId="by-decision-approved"
            />
            <DecisionRow
              label={isArabic ? 'مرفوض' : 'Rejected'}
              value={data.by_decision_this_month.rejected}
              cls="bg-red-100 text-red-700"
              testId="by-decision-rejected"
            />
            <DecisionRow
              label={isArabic ? 'طلب تعديل' : 'Modifications requested'}
              value={data.by_decision_this_month.modifications_requested}
              cls="bg-orange-100 text-orange-800"
              testId="by-decision-modifications"
            />
          </ul>
        </section>

        {/* My currently claimed */}
        <section className="bg-white border border-gray-200 rounded-xl overflow-hidden lg:col-span-2">
          <div className="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 className="text-sm font-bold text-gray-800 flex items-center gap-1">
              <Clock size={14} aria-hidden="true" />
              {isArabic ? 'قيد المراجعة عندي' : 'Currently claimed by me'}
            </h2>
            <span className="text-xs text-gray-500">
              {data.my_in_progress.length}
              {data.stats.my_in_progress > data.my_in_progress.length
                ? ` / ${data.stats.my_in_progress}`
                : ''}
            </span>
          </div>
          {data.my_in_progress.length === 0 ? (
            <div className="text-center py-10 text-gray-400" data-testid="my-in-progress-empty">
              <ClipboardCheck size={36} className="mx-auto mb-2 opacity-40" aria-hidden="true" />
              <p className="text-sm">
                {isArabic ? 'لا توجد طلبات قيد المراجعة عندك.' : 'No cases claimed right now.'}
              </p>
            </div>
          ) : (
            <ul className="divide-y divide-gray-100">
              {data.my_in_progress.map(row => (
                <li key={row.id} data-testid={`in-progress-${row.id}`}>
                  <Link
                    to={`/review/${row.id}`}
                    className="flex items-center gap-3 px-4 py-3 hover:bg-gray-50"
                  >
                    <div className="min-w-0 flex-1">
                      <p className="text-sm font-semibold text-gray-800 truncate">
                        {localisedServiceName(row)}
                      </p>
                      <p className="text-xs text-gray-500 font-mono mt-0.5">{row.reference}</p>
                    </div>
                    <div className="flex flex-col items-end shrink-0">
                      {row.sla_deadline && (
                        <span className={`text-[10px] px-2 py-0.5 rounded ${
                          row.sla_breached
                            ? 'bg-red-100 text-red-700'
                            : 'bg-gray-100 text-gray-600'
                        }`}>
                          {new Date(row.sla_deadline).toLocaleDateString(dateLocale)}
                          {row.sla_breached && (
                            <span className="ms-1">
                              {isArabic ? 'متأخرة' : 'overdue'}
                            </span>
                          )}
                        </span>
                      )}
                    </div>
                    <ChevronRight size={14} className={`text-gray-300 ${isRtl ? 'rotate-180' : ''}`} aria-hidden="true" />
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>

      {/* Recent decisions */}
      <section className="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div className="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
          <h2 className="text-sm font-bold text-gray-800 flex items-center gap-1">
            <Gavel size={14} aria-hidden="true" />
            {isArabic ? 'أحدث القرارات' : 'Recent decisions'}
          </h2>
          <span className="text-xs text-gray-500">{data.recent_decisions.length}</span>
        </div>
        {data.recent_decisions.length === 0 ? (
          <div className="text-center py-10 text-gray-400" data-testid="recent-decisions-empty">
            <Gavel size={36} className="mx-auto mb-2 opacity-40" aria-hidden="true" />
            <p className="text-sm">
              {isArabic ? 'لم تقم بإصدار أي قرار بعد.' : 'No decisions on record yet.'}
            </p>
          </div>
        ) : (
          <ul className="divide-y divide-gray-100">
            {data.recent_decisions.map(row => (
              <li key={row.id} data-testid={`recent-${row.id}`}>
                <Link
                  to={`/review/${row.application_id}`}
                  className="flex items-center gap-3 px-4 py-3 hover:bg-gray-50"
                >
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-gray-800 truncate">
                      {localisedServiceName(row)}
                    </p>
                    <p className="text-xs text-gray-500 font-mono mt-0.5">{row.reference ?? '—'}</p>
                  </div>
                  <span
                    data-testid={`recent-${row.id}-decision`}
                    className={`text-[10px] px-2 py-0.5 rounded shrink-0 ${
                      DECISION_STYLE[row.decision] ?? 'bg-gray-100 text-gray-700'
                    }`}
                  >
                    {t(`status.${row.decision}`, { defaultValue: row.decision })}
                  </span>
                  {row.created_at && (
                    <span className="text-[10px] text-gray-400 shrink-0">
                      {new Date(row.created_at).toLocaleDateString(dateLocale)}
                    </span>
                  )}
                  <ChevronRight size={14} className={`text-gray-300 ${isRtl ? 'rotate-180' : ''}`} aria-hidden="true" />
                </Link>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  );
}

function StatTile({ testId, icon: Icon, label, value, highlight }: {
  testId: string;
  icon: LucideIcon;
  label: string;
  value: number;
  highlight?: boolean;
}) {
  return (
    <div
      className={`bg-white border rounded-xl p-4 ${
        highlight ? 'border-red-300 bg-red-50/50' : 'border-gray-200'
      }`}
      data-testid={testId}
    >
      <div className={`flex items-center gap-2 text-xs mb-1 ${
        highlight ? 'text-red-700' : 'text-gray-500'
      }`}>
        <Icon size={12} aria-hidden={true} />
        {label}
      </div>
      <div className={`text-2xl font-bold ${
        highlight ? 'text-red-800' : 'text-gray-900'
      }`}>
        {value}
      </div>
    </div>
  );
}

function DecisionRow({ label, value, cls, testId }: {
  label: string; value: number; cls: string; testId: string;
}) {
  return (
    <li className="flex items-center justify-between" data-testid={testId}>
      <span className="text-xs text-gray-700">{label}</span>
      <span className={`text-xs px-2 py-0.5 rounded font-semibold ${cls}`}>{value}</span>
    </li>
  );
}
