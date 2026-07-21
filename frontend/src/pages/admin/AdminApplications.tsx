import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Search, ChevronRight, ChevronLeft } from 'lucide-react';
import { usePaginatedAdminApplications } from '../../api/hooks';
import type { AllApplicationsFilters } from '../../api/admin';

/**
 * AdminApplications — organisation-wide applications table with
 * server-side pagination + free-text search (JORD-35).
 *
 * • Search input is debounced at 300ms so a fast typist doesn't
 *   spawn a request per keystroke.
 * • Per-page selector clamped to 5/20/50 (matching the backend cap).
 * • `placeholderData: previous` on the React Query hook keeps the
 *   current page's rows visible during a page transition — no empty-
 *   table flicker while the next page loads.
 */

const STATUS_KEYS = [
  'draft', 'submitted', 'under_review', 'modifications_requested',
  'approved', 'rejected', 'certificate_issued',
] as const;

export function AdminApplications(): JSX.Element {
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const dateLocale = isRtl ? 'ar-JO' : 'en-JO';
  // JORD-61/66 (PM): landing here with ?status=<x> pre-selects the
  // filter. Fixes the "Certificates" tile on AdminDashboard, which
  // used to point at /admin/certificates (a nonexistent route → the
  // wildcard bounced back to /). Now it deep-links here with the
  // right status pre-picked and the table shows just certificates.
  const [searchParams] = useSearchParams();
  const urlStatus = searchParams.get('status') ?? '';

  const [q, setQ] = useState('');
  const [debouncedQ, setDebouncedQ] = useState('');
  const [status, setStatus] = useState<string>(urlStatus);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);

  // When the URL param changes (client-side navigation), reflect it.
  useEffect(() => { setStatus(urlStatus); setPage(1); }, [urlStatus]);

  // Debounce the search input. Also resets to page 1 whenever the
  // needle changes so the applicant list stays coherent.
  useEffect(() => {
    const id = setTimeout(() => {
      setDebouncedQ(q.trim());
      setPage(1);
    }, 300);
    return () => clearTimeout(id);
  }, [q]);

  const filters: AllApplicationsFilters = {
    page,
    per_page: perPage,
    status: status || undefined,
    q:      debouncedQ || undefined,
  };
  const { data, isPending, isFetching, error } = usePaginatedAdminApplications(filters);

  const rows = data?.data ?? [];
  const total = data?.total ?? 0;
  const lastPage = data?.last_page ?? 1;
  const from = data?.from ?? 0;
  const to = data?.to ?? 0;

  return (
    <div className="max-w-6xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">{t('adminApps.title')}</h1>
        <p className="text-gray-500 text-sm mt-1">{t('adminApps.subtitle')}</p>
      </div>

      {/* Filter bar */}
      <div className="bg-white rounded-xl border border-gray-200 p-4 mb-4 flex flex-wrap gap-3 items-center">
        <div className="relative flex-1 min-w-[220px]">
          <Search size={16} className={`absolute ${isRtl ? 'right-3' : 'left-3'} top-1/2 -translate-y-1/2 text-gray-400`} aria-hidden="true" />
          <input
            type="search"
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder={t('adminApps.searchPlaceholder')}
            aria-label={t('adminApps.searchLabel')}
            className={`w-full border border-gray-300 rounded-lg ${isRtl ? 'pr-9 pl-3' : 'pl-9 pr-3'} py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200`}
          />
        </div>

        <select
          value={status}
          onChange={(e) => { setStatus(e.target.value); setPage(1); }}
          aria-label={t('adminApps.filterLabel')}
          className="border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500"
        >
          <option value="">{t('adminApps.allStatuses')}</option>
          {STATUS_KEYS.map(key => (
            <option key={key} value={key}>{t(`status.${key}`)}</option>
          ))}
        </select>

        <select
          value={perPage}
          onChange={(e) => { setPerPage(Number(e.target.value)); setPage(1); }}
          aria-label={t('adminApps.perPageLabel')}
          className="border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500"
        >
          {[5, 10, 20, 50].map(n => (
            <option key={n} value={n}>{n} {t('adminApps.perPageSuffix')}</option>
          ))}
        </select>
      </div>

      {error && (
        <div className="mb-4 bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm">{error.message}</div>
      )}

      {/* Table */}
      <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table className="w-full text-sm">
          <thead className={`bg-gray-50 text-gray-500 ${isRtl ? 'text-right' : 'text-left'}`}>
            <tr>
              <th className="px-4 py-3 font-semibold">{t('adminApps.columns.reference')}</th>
              <th className="px-4 py-3 font-semibold">{t('adminApps.columns.applicant')}</th>
              <th className="px-4 py-3 font-semibold">{t('adminApps.columns.service')}</th>
              <th className="px-4 py-3 font-semibold">{t('adminApps.columns.status')}</th>
              <th className="px-4 py-3 font-semibold">{t('adminApps.columns.createdAt')}</th>
              <th className="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody className={isFetching ? 'opacity-60 transition-opacity' : ''}>
            {isPending && rows.length === 0 && (
              [1,2,3,4,5].map(i => (
                <tr key={i}><td colSpan={6} className="px-4 py-3">
                  <div className="h-4 bg-gray-100 rounded animate-pulse" />
                </td></tr>
              ))
            )}

            {!isPending && rows.length === 0 && (
              <tr><td colSpan={6} className="px-4 py-10 text-center text-gray-400">
                {t('adminApps.empty')}
              </td></tr>
            )}

            {rows.map(app => (
              <tr key={app.id} className="border-t border-gray-100 hover:bg-gray-50">
                <td className="px-4 py-3 font-mono text-blue-700">{app.reference_number}</td>
                <td className="px-4 py-3">{app.applicant?.name ?? '—'}</td>
                <td className="px-4 py-3">
                  {isRtl
                    ? (app.service_definition?.name_ar ?? app.service_definition?.name_en ?? '—')
                    : (app.service_definition?.name_en ?? app.service_definition?.name_ar ?? '—')}
                </td>
                <td className="px-4 py-3">
                  <span className="inline-block px-2 py-0.5 rounded text-xs bg-gray-100">
                    {t(`status.${app.status}`, { defaultValue: app.status })}
                  </span>
                </td>
                <td className="px-4 py-3 text-gray-500 text-xs">
                  {new Date(app.created_at).toLocaleDateString(dateLocale)}
                </td>
                <td className="px-4 py-3">
                  <Link to={`/review/${app.id}`} className="text-blue-600 hover:underline text-xs">
                    {t('adminApps.view')}
                  </Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Pagination footer */}
      <div className="mt-4 flex items-center justify-between text-sm text-gray-600">
        <div>
          {total > 0 ? t('adminApps.showing', { from, to, total }) : '—'}
        </div>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => setPage(p => Math.max(1, p - 1))}
            disabled={page <= 1 || isFetching}
            aria-label={t('common.previousPage')}
            className="px-2 py-1 rounded border border-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <ChevronRight size={14} />
          </button>
          <span aria-live="polite" aria-atomic="true">
            {page} / {lastPage}
          </span>
          <button
            type="button"
            onClick={() => setPage(p => Math.min(lastPage, p + 1))}
            disabled={page >= lastPage || isFetching}
            aria-label={t('common.nextPage')}
            className="px-2 py-1 rounded border border-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <ChevronLeft size={14} />
          </button>
        </div>
      </div>
    </div>
  );
}
