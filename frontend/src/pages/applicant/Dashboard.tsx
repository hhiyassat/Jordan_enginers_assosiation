import React, { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  FolderOpen, FileText, Award, Plus, ArrowLeft, MapPin, Building2,
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

  const [projects, setProjects]         = useState<Project[]>([]);
  const [applications, setApplications] = useState<Application[]>([]);
  const [quota, setQuota]               = useState<OfficeQuota | null>(null);

  const [loading, setLoading]           = useState(true);
  const [quotaLoading, setQuotaLoading] = useState(true);
  const [quotaError, setQuotaError]     = useState('');

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
    Promise.all([
      projectsApi.list().then(r => r.projects).catch(() => [] as Project[]),
      applicationsApi.list().then(r => r.applications).catch(() => [] as Application[]),
    ])
      .then(([p, a]) => {
        setProjects(p);
        setApplications(a);
      })
      .finally(() => setLoading(false));
    loadQuota();
  }, []);

  const certificatesCount = useMemo(
    () => applications.filter(a => a.status === 'certificate_issued').length,
    [applications],
  );

  const activeAppsCount = useMemo(
    () => applications.filter(a => !['rejected', 'certificate_issued'].includes(a.status)).length,
    [applications],
  );

  const recentProjects = projects.slice(0, 3);

  return (
    <div className="flex flex-col h-full" dir="rtl">
      <PageHero
        titleAr="الرئيسية"
        titleEn="Dashboard"
        subtitleAr={user?.name ? `مرحباً، ${user.name}` : undefined}
      />

      <div className="flex-1 overflow-y-auto bg-jea-bg p-6 flex flex-col gap-6">
        {/* Row 1 — aggregate office quota + counter tiles */}
        <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_auto] gap-4 items-stretch">
          <QuotaCard
            facet={quota?.totals ?? null}
            year={quota?.year}
            titleAr="إجمالي رصيد المكتب"
            titleEn="Office annual m² total"
            loading={quotaLoading}
            error={quotaError}
            onRetry={loadQuota}
          />

          <div className="grid grid-cols-3 gap-3 lg:grid-cols-1 lg:w-56">
            <StatTile
              ar="المشاريع"
              en="Projects"
              value={projects.length}
              Icon={FolderOpen}
              loading={loading}
            />
            <StatTile
              ar="طلبات نشطة"
              en="Active requests"
              value={activeAppsCount}
              Icon={FileText}
              loading={loading}
            />
            <StatTile
              ar="الشهادات"
              en="Certificates"
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
              <span lang="ar">رصيد كل مهندس</span>
              <span className="text-jea-muted font-normal text-xs mx-1" lang="en" dir="ltr">· Per-engineer quota</span>
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
              {quota.engineers.map(eng => (
                <QuotaCard
                  key={eng.engineer_id}
                  facet={eng}
                  year={eng.year}
                  titleAr={eng.engineer_name_ar}
                  titleEn={`Engineer #${eng.engineer_id}`}
                  compact
                />
              ))}
            </div>
          </section>
        )}

        {/* Row 2 — quick actions */}
        <section aria-labelledby="quick-actions">
          <h2 id="quick-actions" className="text-sm font-black text-jea-text mb-3">
            <span lang="ar">إجراءات سريعة</span>
            <span className="text-jea-muted font-normal text-xs mx-1" lang="en" dir="ltr">· Quick actions</span>
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <QuickAction
              to="/projects"
              ar="مشاريعي"
              en="My Projects"
              desc="إدارة المشاريع وإضافة مشروع جديد"
              Icon={FolderOpen}
            />
            <QuickAction
              to="/services"
              ar="الخدمات الإلكترونية"
              en="E-Services"
              desc="تصفح جميع الخدمات المتاحة"
              Icon={Plus}
            />
            <QuickAction
              to="/my-applications"
              ar="طلباتي"
              en="My Requests"
              desc="متابعة حالة الطلبات المقدمة"
              Icon={FileText}
            />
          </div>
        </section>

        {/* Row 3 — recent projects */}
        <section aria-labelledby="recent-projects" className="max-w-4xl">
          <div className="flex items-center justify-between mb-3">
            <h2 id="recent-projects" className="text-sm font-black text-jea-text">
              <span lang="ar">أحدث المشاريع</span>
              <span className="text-jea-muted font-normal text-xs mx-1" lang="en" dir="ltr">· Recent projects</span>
            </h2>
            <Link
              to="/projects"
              className="text-xs font-bold text-jea-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-jea-primary/40 rounded px-1"
            >
              <span lang="ar">عرض الكل</span> · <span lang="en" dir="ltr">View all</span>
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
              <p className="text-sm font-bold text-jea-text" lang="ar">لا توجد مشاريع بعد</p>
              <p className="text-xs mt-1" lang="ar">ابدأ بإضافة مشروع من صفحة «مشاريعي»</p>
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

function StatTile({ ar, en, value, Icon, loading }: {
  ar: string; en: string; value: number;
  Icon: React.ComponentType<{ size?: number; className?: string }>;
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
        <p className="text-[11px] text-jea-muted leading-tight">
          <span lang="ar">{ar}</span>
          <span className="mx-1" aria-hidden="true">·</span>
          <span lang="en" dir="ltr">{en}</span>
        </p>
      </div>
    </div>
  );
}

function QuickAction({ to, ar, en, desc, Icon }: {
  to: string; ar: string; en: string; desc: string;
  Icon: React.ComponentType<{ size?: number; className?: string }>;
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
        <ArrowLeft size={14} className="text-white/40 mt-1" aria-hidden="true" />
      </div>
      <div>
        <h3 className="text-sm font-black text-white leading-snug" lang="ar">{ar}</h3>
        <p className="text-white/60 text-[11px] mt-0.5" lang="en" dir="ltr">{en}</p>
      </div>
      <p className="text-white/70 text-xs leading-relaxed" lang="ar">{desc}</p>
    </Link>
  );
}

function RecentProjectRow({ project }: { project: Project }) {
  const statusPill =
    project.status === 'active'
      ? { ar: 'نشط',          cls: 'bg-emerald-100 text-emerald-700 border-emerald-200' }
      : project.status === 'pending'
      ? { ar: 'قيد المراجعة', cls: 'bg-jea-accent text-jea-primary border-jea-border' }
      : { ar: 'مؤرشف',        cls: 'bg-gray-100 text-gray-500 border-gray-200' };

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
            <h3 className="text-sm font-black text-jea-text" lang="ar">{project.name_ar}</h3>
            <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full border ${statusPill.cls}`}>
              <span lang="ar">{statusPill.ar}</span>
            </span>
          </div>
          <div className="flex items-center gap-3 mt-1 flex-wrap text-[11px] text-jea-muted">
            {project.city && (
              <span className="flex items-center gap-1">
                <MapPin size={11} aria-hidden="true" />
                <span lang="ar">{project.city}</span>
              </span>
            )}
            {project.area_m2 != null && <span>{project.area_m2} م²</span>}
            {project.type && (
              <span className="bg-jea-accent text-jea-primary px-2 py-0.5 rounded-full font-semibold" lang="ar">
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
