import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  FolderOpen, FileText, Award, Plus, ArrowLeft, MapPin, Building2,
  type LucideIcon,
} from 'lucide-react';
import { applicationsApi, projectsApi, type OfficeQuota } from '../../api/client';
import type { Application, Project } from '../../types';
import { useAuth } from '../../auth/AuthContext';
import { PageHero } from '../../components/ui/PageHero';
import { QuotaCard } from '../../components/ui/QuotaCard';

/**
 * Dashboard — landing page for the engineering-office (applicant) role.
 *
 * Combines the annual m² quota widget, high-level counts (projects,
 * applications, issued certificates), quick-action tiles, and a
 * recent-projects list. Data is aggregated client-side from the
 * existing endpoints (no new backend surface).
 */
export function Dashboard() {
  const { user } = useAuth();
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');

  const [projects, setProjects]         = useState<Project[]>([]);
  const [applications, setApplications] = useState<Application[]>([]);
  const [quota, setQuota]               = useState<OfficeQuota | null>(null);

  const [loading, setLoading]           = useState(true);
  const [quotaLoading, setQuotaLoading] = useState(true);
  const [quotaError, setQuotaError]     = useState('');
  // JORD-48b: previously a fetch failure was silently swallowed via
  // `.catch(() => [])`, which left the tiles showing "0 projects" while
  // the real reason was a 500. Surface the failure so the user knows
  // to reload — the counter tiles fall back to their skeleton state.
  const [feedError, setFeedError]       = useState('');

  const loadQuota = () => {
    setQuotaLoading(true);
    setQuotaError('');
    projectsApi.quota()
      .then(setQuota)
      .catch(e => setQuotaError((e as Error).message))
      .finally(() => setQuotaLoading(false));
  };

  useEffect(() => {
    setLoading(true);
    setFeedError('');
    Promise.all([
      projectsApi.list().then(r => r.projects),
      applicationsApi.list().then(r => r.applications),
    ])
      .then(([p, a]) => {
        setProjects(p);
        setApplications(a);
      })
      .catch(e => setFeedError((e as Error).message))
      .finally(() => setLoading(false));
    loadQuota();
  }, []);

  // JORD-49: these were previously wrapped in useMemo but the filter
  // + length runs on typically <20 rows. Inlining is faster than the
  // useMemo bookkeeping and keeps the code straightforward.
  const certificatesCount = applications.filter(a => a.status === 'certificate_issued').length;
  const activeAppsCount   = applications.filter(a => !['rejected', 'certificate_issued'].includes(a.status)).length;

  const recentProjects = projects.slice(0, 3);

  return (
    <div className="flex flex-col h-full" dir="rtl">
      <PageHero
        titleAr={t('pageTitle.dashboard')}
        titleEn={t('pageTitle.dashboard')}
        subtitleAr={user?.name ? t('dashboard.greeting', { name: user.name }) : undefined}
      />

      <div className="flex-1 overflow-y-auto bg-jea-bg p-6 flex flex-col gap-6">
        {feedError && (
          <div role="alert" className="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
            {feedError}
          </div>
        )}
        {/* Row 1 — aggregate office quota + counter tiles */}
        <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_auto] gap-4 items-stretch">
          <QuotaCard
            facet={quota?.totals ?? null}
            year={quota?.year}
            titleAr={t('dashboard.officeQuotaTotal')}
            titleEn={t('dashboard.officeQuotaTotal')}
            loading={quotaLoading}
            error={quotaError}
            onRetry={loadQuota}
          />

          <div className="grid grid-cols-3 gap-3 lg:grid-cols-1 lg:w-56">
            <StatTile
              label={t('dashboard.stat.projects')}
              value={projects.length}
              Icon={FolderOpen}
              loading={loading}
            />
            <StatTile
              label={t('dashboard.stat.activeRequests')}
              value={activeAppsCount}
              Icon={FileText}
              loading={loading}
            />
            <StatTile
              label={t('dashboard.stat.certificates')}
              value={certificatesCount}
              Icon={Award}
              loading={loading}
            />
          </div>
        </div>

        {/* Per-engineer breakdown */}
        {quota && quota.engineers.length > 0 && (
          <section aria-labelledby="engineer-quotas">
            <h2 id="engineer-quotas" className="text-sm font-black text-jea-text mb-3">
              {t('dashboard.perEngineerHeading')}
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
              {quota.engineers.map(eng => (
                <QuotaCard
                  key={eng.engineer_id}
                  facet={eng}
                  year={eng.year}
                  titleAr={eng.engineer_name_ar}
                  titleEn={t('dashboard.engineerFallback', { id: eng.engineer_id })}
                  compact
                />
              ))}
            </div>
          </section>
        )}

        {/* Row 2 — quick actions */}
        <section aria-labelledby="quick-actions">
          <h2 id="quick-actions" className="text-sm font-black text-jea-text mb-3">
            {t('common.quickActions')}
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <QuickAction
              to="/projects"
              title={t('dashboard.quickAction.myProjects.title')}
              desc={t('dashboard.quickAction.myProjects.desc')}
              Icon={FolderOpen}
            />
            <QuickAction
              to="/services"
              title={t('dashboard.quickAction.eServices.title')}
              desc={t('dashboard.quickAction.eServices.desc')}
              Icon={Plus}
            />
            <QuickAction
              to="/my-applications"
              title={t('dashboard.quickAction.myRequests.title')}
              desc={t('dashboard.quickAction.myRequests.desc')}
              Icon={FileText}
            />
          </div>
        </section>

        {/* Row 3 — recent projects */}
        <section aria-labelledby="recent-projects" className="max-w-4xl">
          <div className="flex items-center justify-between mb-3">
            <h2 id="recent-projects" className="text-sm font-black text-jea-text">
              {t('dashboard.recentProjects')}
            </h2>
            <Link
              to="/projects"
              className="text-xs font-bold text-jea-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-jea-primary/40 rounded px-1"
            >
              {t('common.viewAll')}
            </Link>
          </div>

          {loading && (
            <div className="flex flex-col gap-3" aria-busy="true">
              {[1, 2, 3].map(i => (
                <div key={i} className="h-20 bg-white rounded-2xl border border-jea-border animate-pulse" />
              ))}
            </div>
          )}

          {!loading && recentProjects.length === 0 && (
            <div className="rounded-xl border border-jea-border bg-white p-8 text-center text-jea-muted">
              <p className="text-sm font-bold text-jea-text">{t('dashboard.noProjectsYet')}</p>
              <p className="text-xs mt-1">{t('dashboard.noProjectsCta')}</p>
            </div>
          )}

          {!loading && recentProjects.length > 0 && (
            <ul className="flex flex-col gap-3">
              {recentProjects.map(p => (
                <li key={p.id}>
                  <RecentProjectRow project={p} />
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>
    </div>
  );
}

/* ── Support components ─────────────────────────────────────────────── */

function StatTile({ label, value, Icon, loading }: {
  label: string; value: number;
  Icon: LucideIcon;
  loading?: boolean;
}) {
  return (
    <div className="bg-white rounded-2xl border border-jea-border shadow-sm p-4 flex items-center gap-3">
      <div className="w-10 h-10 rounded-xl bg-jea-bg flex items-center justify-center shrink-0" aria-hidden="true">
        <Icon size={20} className="text-jea-primary" />
      </div>
      <div className="flex-1 min-w-0">
        <div className="text-xl font-black text-jea-text">
          {loading ? <span className="inline-block h-5 w-8 bg-jea-bg rounded animate-pulse" /> : value}
        </div>
        <p className="text-[11px] text-jea-muted leading-tight">{label}</p>
      </div>
    </div>
  );
}

function QuickAction({ to, title, desc, Icon }: {
  to: string; title: string; desc: string;
  Icon: LucideIcon;
}) {
  return (
    <Link
      to={to}
      className="text-right rounded-2xl p-4 flex flex-col gap-2 shadow-sm border border-white/10 transition-all duration-200 bg-jea-primary hover:shadow-lg hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
    >
      <div className="flex items-start justify-between">
        <div className="w-10 h-10 rounded-xl bg-white/15 flex items-center justify-center shrink-0" aria-hidden="true">
          <Icon size={20} className="text-white" />
        </div>
        <ArrowLeft size={14} className="text-white/70 mt-1" aria-hidden="true" />
      </div>
      <div>
        <h3 className="text-sm font-black text-white leading-snug">{title}</h3>
      </div>
      <p className="text-white/70 text-xs leading-relaxed">{desc}</p>
    </Link>
  );
}

function RecentProjectRow({ project }: { project: Project }) {
  const { t, i18n } = useTranslation();
  // JORD-57: prefer the localised project name so the "Latest
  // projects" widget doesn't leak Arabic-only rows into the
  // English view. Falls back to whichever side has data.
  const isArabic = i18n.language.startsWith('ar');
  const projectName = isArabic
    ? (project.name_ar || project.name_en)
    : (project.name_en || project.name_ar);
  const statusPill =
    project.status === 'active'
      ? { label: t('projectStatus.active'),   cls: 'bg-emerald-100 text-emerald-700 border-emerald-200' }
      : project.status === 'pending'
      ? { label: t('projectStatus.pending'),  cls: 'bg-jea-accent text-jea-primary border-jea-border' }
      : { label: t('projectStatus.archived'), cls: 'bg-gray-100 text-gray-500 border-gray-200' };

  return (
    <Link
      to={`/projects/${project.id}`}
      className="block bg-white rounded-2xl border border-jea-border shadow-sm hover:shadow-md hover:border-jea-primary/40 hover:-translate-y-0.5 transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-jea-primary/40 overflow-hidden"
    >
      <div className="h-1 bg-jea-primary" aria-hidden="true" />
      <div className="p-4 flex items-center gap-3">
        <div className="w-10 h-10 rounded-xl bg-jea-bg flex items-center justify-center shrink-0" aria-hidden="true">
          <Building2 size={20} className="text-jea-primary" />
        </div>
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap">
            <h3 className="text-sm font-black text-jea-text">{projectName}</h3>
            <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full border ${statusPill.cls}`}>
              {statusPill.label}
            </span>
          </div>
          <div className="flex items-center gap-3 mt-1 flex-wrap text-[11px] text-jea-muted">
            {project.city && (
              <span className="flex items-center gap-1">
                <MapPin size={11} aria-hidden="true" />
                <span>{project.city}</span>
              </span>
            )}
            {project.area_m2 != null && <span>{project.area_m2} m²</span>}
            {project.type && (
              <span className="bg-jea-accent text-jea-primary px-2 py-0.5 rounded-full font-semibold">
                {project.type}
              </span>
            )}
          </div>
        </div>
        <ArrowLeft size={16} className="text-jea-muted shrink-0" aria-hidden="true" />
      </div>
    </Link>
  );
}
