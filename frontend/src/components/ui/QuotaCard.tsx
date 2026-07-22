import React from 'react';
import { Gauge, AlertTriangle } from 'lucide-react';

/**
 * QuotaCard — annual m² quota widget
 *
 * Renders one quota facet: either the aggregate office totals or a
 * single-engineer breakdown. Accepts any object with the required
 * numeric fields plus optional title/subtitle overrides.
 *
 * Severity thresholds:
 *   < 60%  neutral (primary blue)
 *   60–90% caution (amber)
 *   > 90%  danger  (red)
 */
export interface QuotaFacet {
  quota_m2: number | null;
  used_m2: number;
  remaining_m2: number | null;
  percent_used: number | null;
  projects_count: number;
  unlimited: boolean;
}

interface QuotaCardProps {
  facet: QuotaFacet | null;
  /** Optional year label (shown in the subtitle) */
  year?: number;
  /** Title override (default: "رصيد الأمتار السنوي" · "Annual m² quota") */
  titleAr?: string;
  titleEn?: string;
  /** Compact variant (used inline in per-engineer lists) */
  compact?: boolean;
  loading?: boolean;
  error?: string;
  onRetry?: () => void;
}

function formatM2(n: number): string {
  return n.toLocaleString('en-US');
}

function severity(pct: number | null): 'ok' | 'warn' | 'danger' {
  if (pct === null) return 'ok';
  if (pct >= 90)    return 'danger';
  if (pct >= 60)    return 'warn';
  return 'ok';
}

const BAR_CLASS: Record<'ok' | 'warn' | 'danger', string> = {
  ok:     'bg-jea-primary',
  warn:   'bg-amber-500',
  danger: 'bg-jea-danger',
};

const TEXT_CLASS: Record<'ok' | 'warn' | 'danger', string> = {
  ok:     'text-jea-primary',
  warn:   'text-amber-700',
  danger: 'text-jea-danger',
};

export function QuotaCard({
  facet, year, titleAr, titleEn, compact = false,
  loading, error, onRetry,
}: QuotaCardProps) {
  const t_ar = titleAr ?? 'رصيد الأمتار السنوي';
  const t_en = titleEn ?? 'Annual m² quota';

  if (loading) {
    return (
      <div className="bg-white rounded-2xl border border-jea-border shadow-sm p-5 animate-pulse max-w-3xl">
        <div className="h-4 bg-jea-bg rounded w-1/3 mb-3" />
        <div className="h-8 bg-jea-bg rounded w-1/2 mb-3" />
        <div className="h-3 bg-jea-bg rounded w-full" />
      </div>
    );
  }

  if (error) {
    return (
      <div role="alert" className="bg-white rounded-2xl border border-jea-danger/30 shadow-sm p-5 max-w-3xl flex items-center gap-3">
        <AlertTriangle size={18} className="text-jea-danger shrink-0" aria-hidden="true" />
        <div className="flex-1">
          <p className="text-sm font-bold text-jea-text" lang="ar">تعذّر تحميل الرصيد</p>
          <p className="text-xs text-jea-muted mt-0.5">{error}</p>
        </div>
        {onRetry && (
          <button
            type="button"
            onClick={onRetry}
            className="text-xs font-bold text-jea-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-jea-primary/40 rounded px-2 py-1"
          >
            <span lang="ar">إعادة المحاولة</span>
          </button>
        )}
      </div>
    );
  }

  if (!facet) return null;

  // Unlimited variant — no numeric progress
  if (facet.unlimited) {
    return (
      <div className={`bg-white rounded-2xl border border-jea-border shadow-sm ${compact ? 'p-4' : 'p-5'} max-w-3xl`} dir="rtl">
        <div className="flex items-center gap-3">
          <div className="w-11 h-11 rounded-xl bg-jea-bg flex items-center justify-center shrink-0" aria-hidden="true">
            <Gauge size={22} className="text-jea-primary" />
          </div>
          <div className="flex-1">
            <h3 className="text-sm font-black text-jea-text" lang="ar">{t_ar}</h3>
            <p className="text-xs text-jea-muted" lang="en" dir="ltr">{t_en}{year ? ` · ${year}` : ''}</p>
          </div>
          <div className="text-left">
            <span className="text-xs font-bold text-jea-muted" lang="ar">غير محدد</span>
            <p className="text-[10px] text-jea-muted mt-0.5" lang="en" dir="ltr">Unlimited</p>
          </div>
        </div>
        <p className="text-xs text-jea-muted mt-3">
          <span lang="ar">استُخدم {formatM2(facet.used_m2)} م² هذا العام عبر {facet.projects_count} مشروع.</span>
        </p>
      </div>
    );
  }

  const sev = severity(facet.percent_used);
  const barCls = BAR_CLASS[sev];
  const txtCls = TEXT_CLASS[sev];

  return (
    <div className={`bg-white rounded-2xl border border-jea-border shadow-sm ${compact ? 'p-4' : 'p-5'} max-w-3xl`} dir="rtl">
      <div className={`flex items-center gap-3 ${compact ? 'mb-3' : 'mb-4'}`}>
        <div className="w-11 h-11 rounded-xl bg-jea-bg flex items-center justify-center shrink-0" aria-hidden="true">
          <Gauge size={22} className="text-jea-primary" />
        </div>
        <div className="flex-1">
          <h3 className="text-sm font-black text-jea-text" lang="ar">{t_ar}</h3>
          <p className="text-[10px] text-jea-muted">
            <span lang="en" dir="ltr">{t_en}{year ? ` · ${year}` : ''}</span>
            <span className="mx-1" aria-hidden="true">·</span>
            <span lang="ar">{facet.projects_count} مشروع</span>
          </p>
        </div>
        <div className="text-left">
          <div className={`text-2xl font-black leading-none ${txtCls}`}>
            {facet.percent_used}%
          </div>
          <p className="text-[10px] text-jea-muted mt-1" lang="ar">مستخدَم</p>
        </div>
      </div>

      <div
        className="h-3 rounded-full bg-jea-bg overflow-hidden"
        role="progressbar"
        aria-valuenow={facet.percent_used ?? 0}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-label={`${facet.percent_used ?? 0}% used`}
      >
        <div
          className={`h-full ${barCls} transition-all duration-500`}
          style={{ width: `${facet.percent_used ?? 0}%` }}
        />
      </div>

      {!compact && (
        <div className="grid grid-cols-3 gap-3 mt-4 text-center">
          <StatBox label="المستخدَم" value={facet.used_m2} tone="text-jea-text" />
          <StatBox label="المتبقي"   value={facet.remaining_m2 ?? 0} tone={txtCls} />
          <StatBox label="السقف السنوي" value={facet.quota_m2 ?? 0} tone="text-jea-text" />
        </div>
      )}

      {compact && (
        <div className="flex items-center justify-between text-[11px] text-jea-muted mt-2">
          <span>
            <span lang="ar">{formatM2(facet.used_m2)} / {formatM2(facet.quota_m2 ?? 0)}</span>
            <span className="mx-1">م²</span>
          </span>
          <span>
            <span lang="ar">المتبقي</span>:{' '}
            <span className={`font-bold ${txtCls}`}>{formatM2(facet.remaining_m2 ?? 0)}</span>
            <span className="mx-1">م²</span>
          </span>
        </div>
      )}

      {sev === 'danger' && !compact && (
        <div role="alert" className="mt-3 flex items-center gap-2 text-xs text-jea-danger">
          <AlertTriangle size={14} aria-hidden="true" />
          <span lang="ar">وصلت إلى حدود السقف السنوي. لن تتمكن من إضافة مشاريع جديدة بمساحة إضافية.</span>
        </div>
      )}
    </div>
  );
}

function StatBox({ label, value, tone }: { label: string; value: number; tone: string }) {
  return (
    <div className="bg-jea-bg rounded-lg px-2 py-2">
      <div className="text-[10px] text-jea-muted" lang="ar">{label}</div>
      <div className={`text-sm font-black mt-0.5 ${tone}`}>
        {value.toLocaleString('en-US')}
        <span className="text-[10px] font-normal text-jea-muted mr-1">م²</span>
      </div>
    </div>
  );
}
