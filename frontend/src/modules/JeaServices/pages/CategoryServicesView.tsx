import React, { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowRight, Plus, Clock, Edit3 } from 'lucide-react';
import { servicesApi } from '../../../api/client';
import type { ServiceDefinition } from '../../../types';
import { PhaseBadge } from '../../../components/ui/PhaseBadge';
import { RolePathBadge } from '../../../components/ui/RolePathBadge';

/**
 * Groups an array of services by subcategory_ar. Services with no
 * subcategory land in an "" (empty-string) bucket that the renderer
 * treats as "ungrouped" (no header shown when it's the only bucket).
 * Insertion order is preserved so the seeder's ordering drives the UI.
 */
function groupBySubcategory(services: ServiceDefinition[]): Array<{
  ar: string;
  en: string;
  services: ServiceDefinition[];
}> {
  const groups = new Map<string, { ar: string; en: string; services: ServiceDefinition[] }>();
  for (const svc of services) {
    const ar = svc.subcategory_ar ?? '';
    const en = svc.subcategory_en ?? '';
    if (!groups.has(ar)) groups.set(ar, { ar, en, services: [] });
    groups.get(ar)!.services.push(svc);
  }
  return Array.from(groups.values());
}

// Turn sla_hours into a language-aware human string. Rendered via i18n
// so plural / unit follow the active language automatically.
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

export function CategoryServicesView() {
  const { categoryCode } = useParams<{ categoryCode: string }>();
  const navigate = useNavigate();
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const isArabic = isRtl;
  const [all, setAll]         = useState<ServiceDefinition[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError]     = useState('');

  useEffect(() => {
    servicesApi.list()
      .then(r => setAll(r.services))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  const category = all.find(s => s.code === categoryCode);
  const children = all.filter(s => s.parent_code === categoryCode);
  // JORD-49: `children` gets a fresh array reference from filter() on
  // every render, so the previous useMemo(groups, [children]) never
  // hit — inlining is honest about the cost. groupBySubcategory runs
  // over typically <10 items.
  const groups = groupBySubcategory(children);
  // Only render subcategory headers when at least one non-empty subcategory
  // exists — otherwise we get a redundant empty header before every card.
  const useGroupedLayout = groups.some(g => g.ar !== '');

  const categoryTitle = category ? (isArabic ? (category.name_ar || category.name_en) : (category.name_en || category.name_ar)) : '';

  return (
    <div className="flex flex-col h-full" dir={isRtl ? 'rtl' : 'ltr'}>
      {/* Compact breadcrumb — only the ancestor link. The current page's
          name is the <h2> below (single source of truth, no duplication). */}
      <div className="bg-jea-topbar px-6 py-4 shrink-0 flex items-center gap-3">
        <Link
          to="/services"
          className="flex items-center gap-2 text-white/70 hover:text-white text-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60 rounded"
          aria-label={t('category.backToServices')}
        >
          <ArrowRight size={16} aria-hidden="true" className={isRtl ? '' : 'rotate-180'} />
          <span>{t('category.backToServices')}</span>
        </Link>
      </div>

      <div className="flex-1 overflow-y-auto bg-jea-bg p-6">
        {category && (
          <header className="mb-6">
            <h2 className="text-base font-black text-jea-text">{categoryTitle}</h2>
            <p className="text-xs text-jea-muted">{t('category.count', { count: children.length })}</p>
          </header>
        )}

        {loading && <DetailGridSkeleton />}

        {!loading && error && (
          <div className="rounded-xl border border-jea-danger/30 bg-white p-6 text-jea-danger max-w-5xl">
            {error}
          </div>
        )}

        {!loading && !error && children.length === 0 && (
          <div className="rounded-xl border border-jea-border bg-white p-16 text-center text-jea-muted max-w-5xl">
            <p className="text-sm font-bold text-jea-text">{t('category.empty')}</p>
          </div>
        )}

        {!loading && !error && children.length > 0 && !useGroupedLayout && (
          <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 max-w-5xl">
            {children.map(svc => (
              <DetailServiceCard
                key={svc.id}
                service={svc}
                onOpen={() => navigate(`/apply/${svc.code}`)}
                onOpenVariant={(v) => navigate(`/apply/${svc.code}?variant=${v}`)}
              />
            ))}
          </div>
        )}

        {!loading && !error && useGroupedLayout && groups.map(g => {
          const groupId = `subcat-${g.ar || 'general'}`;
          const groupLabel = isArabic ? (g.ar || g.en) : (g.en || g.ar);
          const isDuplicateOfParent = !!category && g.ar === category.name_ar;
          const showHeader = !!groupLabel && !isDuplicateOfParent;
          return (
            <section
              key={g.ar || 'general'}
              aria-labelledby={showHeader ? groupId : undefined}
              aria-label={showHeader ? undefined : (groupLabel || undefined)}
              className="mb-8 max-w-5xl"
            >
              {showHeader && (
                <header className="flex items-baseline justify-between gap-3 mb-3 border-b border-jea-border pb-2">
                  <h3 id={groupId} className="text-base font-black text-jea-text">
                    {groupLabel}
                  </h3>
                  <div className="flex items-center gap-2 text-xs text-jea-muted">
                    {t('category.count', { count: g.services.length })}
                  </div>
                </header>
              )}
              <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                {g.services.map(svc => (
                  <DetailServiceCard
                    key={svc.id}
                    service={svc}
                    onOpen={() => navigate(`/apply/${svc.code}`)}
                    onOpenVariant={(v) => navigate(`/apply/${svc.code}?variant=${v}`)}
                  />
                ))}
              </div>
            </section>
          );
        })}
      </div>
    </div>
  );
}

function DetailServiceCard({
  service, onOpen, onOpenVariant,
}: {
  service: ServiceDefinition;
  onOpen: () => void;
  onOpenVariant?: (variantKey: string) => void;
}) {
  const { t, i18n } = useTranslation();
  const isArabic = i18n.language.startsWith('ar');
  const active = true; // API only returns active services
  const hasModificationVariant = (service.variant_keys ?? []).includes('modification');
  const name = isArabic ? (service.name_ar || service.name_en) : (service.name_en || service.name_ar);

  return (
    <div
      className={`bg-white rounded-xl border border-jea-border shadow-sm overflow-hidden flex flex-col transition-all duration-200 ${
        active
          ? 'hover:shadow-md hover:border-jea-primary/40 hover:-translate-y-0.5'
          : 'opacity-60'
      }`}
    >
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

        <RolePathBadge stages={service.schema?.workflow?.stages ?? []} className="mt-1" />

        <div className="flex gap-2">
          <button
            onClick={onOpen}
            disabled={!active}
            className={`flex-1 py-2 rounded-lg text-xs font-bold flex items-center justify-center gap-1.5 transition-all duration-150 ${
              active
                ? 'bg-jea-primary text-white hover:bg-jea-hover active:bg-jea-topbarDeep'
                : 'bg-gray-100 text-gray-400 cursor-not-allowed'
            }`}
          >
            {active ? (<><Plus size={11} />{t('category.cta')}</>) : (<><Clock size={11} />{t('category.ctaSoon')}</>)}
          </button>
          {hasModificationVariant && onOpenVariant && active && (
            <button
              onClick={() => onOpenVariant('modification')}
              aria-label={t('category.modifyAria')}
              title={t('category.modifyAria')}
              className="py-2 px-3 rounded-lg text-xs font-bold flex items-center justify-center gap-1 transition-all duration-150 bg-white border border-jea-border text-jea-primary hover:bg-jea-accent focus:outline-none focus-visible:ring-2 focus-visible:ring-jea-primary/40"
            >
              <Edit3 size={11} aria-hidden="true" />
              <span>{t('category.modifyLabel')}</span>
            </button>
          )}
        </div>
      </div>
    </div>
  );
}

function DetailGridSkeleton() {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 max-w-5xl">
      {[1, 2, 3, 4, 5, 6].map(i => (
        <div key={i} className="bg-white rounded-xl border border-jea-border shadow-sm overflow-hidden">
          <div className="h-1 bg-jea-primary/40" />
          <div className="p-4 h-40 animate-pulse space-y-3">
            <div className="h-3 bg-jea-bg rounded w-3/4" />
            <div className="h-2 bg-jea-bg rounded w-1/2" />
            <div className="grid grid-cols-3 gap-2">
              <div className="h-10 bg-jea-bg rounded" />
              <div className="h-10 bg-jea-bg rounded" />
              <div className="h-10 bg-jea-bg rounded" />
            </div>
            <div className="h-8 bg-jea-bg rounded" />
          </div>
        </div>
      ))}
    </div>
  );
}
