import React, { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowRight, Building2, Plus, Clock } from 'lucide-react';
import { projectsApi, servicesApi } from '../../api/client';
import type { Project, ServiceDefinition } from '../../types';
import { PhaseBadge } from '../../components/ui/PhaseBadge';

function formatSla(t: (key: string, opts?: Record<string, unknown>) => string, hours?: number | null): string {
  if (hours == null) return '—';
  if (hours >= 24) return t('category.slaDays', { count: Math.round(hours / 24) });
  return t('category.slaHours', { count: hours });
}

function formatFee(fee: ServiceDefinition['base_fee'], currency: string): string {
  if (fee == null) return '—';
  const num = typeof fee === 'string' ? parseFloat(fee) : fee;
  if (Number.isNaN(num)) return '—';
  return `${num} ${currency}`;
}

export function ProjectDetail() {
  const { projectId } = useParams<{ projectId: string }>();
  const navigate = useNavigate();
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const isArabic = isRtl;

  const [project, setProject]   = useState<Project | null>(null);
  const [services, setServices] = useState<ServiceDefinition[]>([]);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState('');

  useEffect(() => {
    if (!projectId) return;
    setLoading(true);

    Promise.all([
      projectsApi.get(Number(projectId)),
      servicesApi.list(),
    ])
      .then(([p, s]) => {
        setProject(p.project);
        // Show only services under the مشاريعي (JEA-PROJ) folder — the 7 drawings.
        setServices(s.services.filter(sv => sv.parent_code === 'JEA-PROJ'));
      })
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, [projectId]);

  const projectName = project ? (isArabic ? (project.name_ar || project.name_en) : (project.name_en || project.name_ar)) : '';

  return (
    <div className="flex flex-col h-full" dir={isRtl ? 'rtl' : 'ltr'}>
      <div className="bg-jea-topbar px-6 py-4 shrink-0">
        <div className="flex items-center gap-2 text-xs text-white/50 mb-2">
          <Link to="/services" className="hover:text-white transition-colors">{t('category.backToServices')}</Link>
          <span aria-hidden="true">/</span>
          <Link to="/projects" className="hover:text-white transition-colors">{t('projects.title')}</Link>
        </div>
        {project && (
          <div className="flex items-center justify-between flex-wrap gap-2">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-white/15 flex items-center justify-center shrink-0">
                <Building2 size={20} className="text-white" />
              </div>
              <div>
                <h1 className="text-lg font-black text-white">{projectName}</h1>
                <p className="text-white/50 text-[10px]">
                  {[project.city, project.area_m2 ? `${project.area_m2} m²` : null].filter(Boolean).join(' · ')}
                </p>
              </div>
            </div>
          </div>
        )}
      </div>

      <div className="flex-1 overflow-y-auto bg-jea-bg p-6">
        {loading && (
          <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 max-w-5xl">
            {[1, 2, 3, 4, 5, 6].map(i => (
              <div key={i} className="bg-white rounded-xl border border-jea-border h-40 animate-pulse" />
            ))}
          </div>
        )}

        {!loading && error && (
          <div className="rounded-xl border border-jea-danger/30 bg-white p-6 text-jea-danger max-w-5xl">
            {error}
          </div>
        )}

        {!loading && !error && (
          <>
            {project && (
              <div className="bg-white rounded-xl border border-jea-border px-5 py-3 mb-5 flex items-center gap-6 flex-wrap text-xs text-jea-muted max-w-5xl">
                {project.contract_no && (
                  <span>{t('projectDetail.contract')}: <span className="font-semibold text-jea-primary">{project.contract_no}</span></span>
                )}
                {project.request_no && (
                  <span>{t('projectDetail.request')}: <span className="font-semibold text-jea-primary">{project.request_no}</span></span>
                )}
                {project.type && (
                  <span>{t('projectDetail.type')}: <span className="font-semibold text-jea-text">{project.type}</span></span>
                )}
              </div>
            )}

            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 max-w-5xl">
              {services.map(svc => (
                <DrawingCard
                  key={svc.id}
                  service={svc}
                  onOpen={() => navigate(`/apply/${svc.code}?project_id=${projectId}`)}
                />
              ))}
            </div>
          </>
        )}
      </div>
    </div>
  );
}

function DrawingCard({ service, onOpen }: { service: ServiceDefinition; onOpen: () => void }) {
  const { t, i18n } = useTranslation();
  const isArabic = i18n.language.startsWith('ar');
  const name = isArabic ? (service.name_ar || service.name_en) : (service.name_en || service.name_ar);
  const active = true;
  return (
    <div className="bg-white rounded-xl border border-jea-border shadow-sm overflow-hidden flex flex-col transition-all duration-200 hover:shadow-md hover:border-jea-primary/40 hover:-translate-y-0.5">
      <div className="h-1 bg-jea-primary" />
      <div className="p-4 flex-1 flex flex-col gap-3">
        <div className="flex items-start justify-between gap-2">
          <div className="flex-1 min-w-0">
            <h3 className="text-sm font-bold text-jea-text leading-snug">{name}</h3>
          </div>
          <div className="flex items-center gap-1.5 shrink-0">
            <PhaseBadge phase={service.phase} variant="pill" />
            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-jea-accent text-jea-primary">
              {t('category.available')}
            </span>
          </div>
        </div>

        <div className="grid grid-cols-3 gap-2 text-center">
          {[
            { label: t('category.fieldCode'), val: service.code },
            { label: t('category.fieldFee'),  val: formatFee(service.base_fee, service.currency) },
            { label: t('category.fieldSla'),  val: formatSla(t, service.sla_hours) },
          ].map(item => (
            <div key={item.label} className="bg-jea-bg rounded-lg px-2 py-1.5">
              <div className="text-[9px] text-jea-muted">{item.label}</div>
              <div className="text-[11px] font-bold text-jea-primary mt-0.5 leading-tight">{item.val}</div>
            </div>
          ))}
        </div>

        <button
          onClick={onOpen}
          disabled={!active}
          className="w-full py-2 rounded-lg text-xs font-bold flex items-center justify-center gap-1.5 transition-all duration-150 bg-jea-primary text-white hover:bg-jea-hover active:bg-jea-topbarDeep"
        >
          {active ? (<><Plus size={11} />{t('category.cta')}</>) : (<><Clock size={11} />{t('category.ctaSoon')}</>)}
        </button>
      </div>
    </div>
  );
}
