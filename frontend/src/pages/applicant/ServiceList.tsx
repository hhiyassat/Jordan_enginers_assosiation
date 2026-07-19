import React, { useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  ArrowLeft, FolderOpen, FileText, FileCheck, GraduationCap,
  CreditCard, Users, Scale, Wrench, Building2,
  type LucideIcon,
} from 'lucide-react';
import { useServices } from '../../api/hooks';
import type { ServiceDefinition } from '../../types';

// Icon selection by code prefix — matches the JEA portal design categories.
const ICON_BY_PREFIX: Array<[string, LucideIcon]> = [
  ['JEA-CERT',  GraduationCap],
  ['JEA-FIN',   CreditCard],
  ['JEA-PROJ',  FolderOpen],
  ['JEA-MISC',  Wrench],
  ['JEA-DEC',   Scale],
  ['JEA-ENG',   Users],
  ['CERT',      GraduationCap],
  ['FIN',       CreditCard],
  ['PROJ',      FolderOpen],
  ['MISC',      Wrench],
  ['DEC',       Scale],
  ['ENG',       Users],
  ['SVC-PR',    FileCheck],
  ['SVC-FI',    CreditCard],
  ['SVC-TR',    FileText],
];

function iconFor(code: string) {
  const hit = ICON_BY_PREFIX.find(([prefix]) => code.startsWith(prefix));
  return hit ? hit[1] : Building2;
}

export function ServiceList() {
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  // JORD-33: useServices() dedupes concurrent fetches and caches the
  // result across route changes — the two Dashboard tiles that also
  // depend on the services list now share this single request.
  const { data, isPending, error } = useServices();
  const services = data ?? [];
  const loading = isPending;

  // Top-level = anything without a parent. Categories = top-levels that have children.
  // Display order pins مشاريعي (JEA-PROJ) first and استطلاع الموقع (JEA-SURV)
  // second so both anchor the top row (right side in RTL).
  const { topLevel, childCounts } = useMemo(() => {
    const priority = ['JEA-PROJ', 'JEA-SURV'];
    const top = services
      .filter(s => !s.parent_code)
      .sort((a, b) => {
        const ia = priority.indexOf(a.code);
        const ib = priority.indexOf(b.code);
        if (ia !== -1 || ib !== -1) return (ia === -1 ? 999 : ia) - (ib === -1 ? 999 : ib);
        return 0;
      });
    const counts: Record<string, number> = {};
    for (const svc of services) {
      if (svc.parent_code) counts[svc.parent_code] = (counts[svc.parent_code] ?? 0) + 1;
    }
    return { topLevel: top, childCounts: counts };
  }, [services]);

  return (
    <div className="flex flex-col h-full" dir={isRtl ? 'rtl' : 'ltr'}>
      <div className="bg-jea-topbar px-6 py-5 shrink-0">
        <h1 className="text-xl font-black text-white">{t('pageTitle.services')}</h1>
        <p className="text-white/50 text-xs mt-0.5">{t('org.name')}</p>
      </div>

      <div className="flex-1 overflow-y-auto bg-jea-bg p-6">
        {loading && <TileGridSkeleton />}

        {!loading && error && (
          <div className="rounded-xl border border-jea-danger/30 bg-white p-6 text-jea-danger max-w-4xl">
            {error.message}
          </div>
        )}

        {!loading && !error && topLevel.length === 0 && (
          <div className="rounded-xl border border-jea-border bg-white p-16 text-center text-jea-muted max-w-4xl">
            <p className="text-4xl mb-3">📋</p>
            <p className="text-sm font-bold text-jea-text">{t('services.empty')}</p>
          </div>
        )}

        {!loading && !error && topLevel.length > 0 && (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 max-w-4xl">
            {topLevel.map(svc => (
              <ServiceTile
                key={svc.id}
                service={svc}
                childCount={childCounts[svc.code] ?? 0}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

function ServiceTile({ service, childCount }: { service: ServiceDefinition; childCount: number }) {
  const navigate = useNavigate();
  const { i18n } = useTranslation();
  const isArabic = i18n.language.startsWith('ar');
  const Icon = iconFor(service.code);
  const isCategory = childCount > 0;
  // Show the label in the active language; fall back to the other
  // language if the ServiceDefinition doesn't carry it.
  const title = isArabic
    ? (service.name_ar || service.name_en)
    : (service.name_en || service.name_ar);
  const description = isArabic
    ? (service.description_ar || service.description_en || '')
    : (service.description_en || service.description_ar || '');

  const handleClick = () => {
    if (isCategory) navigate(`/services/${service.code}`);
    else            navigate(`/apply/${service.code}`);
  };

  return (
    <button
      onClick={handleClick}
      className={`${isArabic ? 'text-right' : 'text-left'} rounded-2xl p-5 flex flex-col gap-3 shadow-sm border border-white/10 transition-all duration-200 bg-jea-primary hover:shadow-lg hover:-translate-y-0.5 cursor-pointer`}
    >
      <div className="flex items-start justify-between">
        <div className="w-11 h-11 rounded-xl bg-white/15 flex items-center justify-center shrink-0">
          <Icon size={22} className="text-white" />
        </div>
        <ArrowLeft size={16} className={`text-white/40 mt-1 ${isArabic ? '' : 'rotate-180'}`} />
      </div>
      <div>
        <h3 className="text-base font-black text-white leading-snug">{title}</h3>
      </div>
      <p className="text-white/70 text-xs leading-relaxed line-clamp-3">{description}</p>
    </button>
  );
}

function TileGridSkeleton() {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 max-w-4xl">
      {[1, 2, 3, 4, 5, 6].map(i => (
        <div key={i} className="rounded-2xl p-5 bg-jea-primary/40 h-40 animate-pulse" />
      ))}
    </div>
  );
}
