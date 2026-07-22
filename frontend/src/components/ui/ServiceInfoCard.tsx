import { useTranslation } from 'react-i18next';
import { FileText, Clock, Coins, ListTree } from 'lucide-react';
import type { ServiceDefinition } from '../../types';

/**
 * ServiceInfoCard — compact summary of a service (JORD-18).
 *
 * Shown at the top of the Apply page so the applicant sees fee,
 * SLA, required document count, and workflow-stage count before
 * they start filling anything in. Prior to this card, only the
 * service name rendered above the form and the applicant had no
 * signal that the service costs money / needs specific documents.
 */
export function ServiceInfoCard({ service }: { service: ServiceDefinition }): JSX.Element {
  const { t, i18n } = useTranslation();
  const isArabic = i18n.language.startsWith('ar');

  const schema = service.schema;
  const documents = schema?.documents ?? [];
  const requiredDocs = documents.filter(d => d.required).length;
  const stages = schema?.workflow?.stages ?? [];

  const description = isArabic
    ? (service.description_ar || service.description_en || '')
    : (service.description_en || service.description_ar || '');

  const formatFee = (): string => {
    const fee = service.base_fee;
    if (fee == null) return '—';
    const num = typeof fee === 'string' ? parseFloat(fee) : fee;
    if (Number.isNaN(num)) return '—';
    return `${num} ${service.currency}`;
  };

  const formatSla = (): string => {
    const h = service.sla_hours;
    if (h == null) return '—';
    if (h >= 24) return t('category.slaDays', { count: Math.round(h / 24) });
    return t('category.slaHours', { count: h });
  };

  return (
    <section
      aria-labelledby="service-info-heading"
      className="bg-white rounded-2xl border border-jea-border shadow-sm p-5 mb-6"
    >
      <header className="mb-3 flex items-baseline justify-between gap-3">
        <h2 id="service-info-heading" className="text-sm font-black text-jea-text">
          {t('serviceInfo.heading')}
        </h2>
        <span className="font-mono text-xs text-jea-muted">{service.code}</span>
      </header>

      {description && (
        <p className="text-sm text-jea-muted mb-4 leading-relaxed">{description}</p>
      )}

      <dl className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <FactTile
          Icon={Coins}
          label={t('serviceInfo.fee')}
          value={formatFee()}
        />
        <FactTile
          Icon={Clock}
          label={t('serviceInfo.sla')}
          value={formatSla()}
        />
        <FactTile
          Icon={FileText}
          label={t('serviceInfo.requiredDocs')}
          value={t('serviceInfo.docsCount', { count: requiredDocs })}
        />
        <FactTile
          Icon={ListTree}
          label={t('serviceInfo.workflowStages')}
          value={t('serviceInfo.stagesCount', { count: stages.length })}
        />
      </dl>
    </section>
  );
}

function FactTile({ Icon, label, value }: {
  Icon: typeof FileText;
  label: string;
  value: string;
}): JSX.Element {
  return (
    <div className="bg-jea-bg rounded-xl px-3 py-2 flex items-center gap-2">
      <div className="w-8 h-8 rounded-lg bg-white border border-jea-border flex items-center justify-center shrink-0" aria-hidden="true">
        <Icon size={16} className="text-jea-primary" />
      </div>
      <div className="min-w-0">
        <dt className="text-[10px] text-jea-muted">{label}</dt>
        <dd className="text-xs font-bold text-jea-text mt-0.5 truncate">{value}</dd>
      </div>
    </div>
  );
}
