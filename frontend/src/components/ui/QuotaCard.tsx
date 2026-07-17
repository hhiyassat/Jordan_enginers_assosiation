import React from 'react';
import { Gauge, AlertTriangle } from 'lucide-react';
import type { QuotaStatus } from '../../api/client';

/**
 * QuotaCard — engineering-office annual m² quota widget
 *
 * Shows total quota, used m² (sum of area_m2 for the office's projects
 * created since Jan 1 of current year), remaining, and a color-coded
 * progress bar. Unlimited users get a neutral \"no cap\" variant.
 *
 * Severity thresholds:
 *   < 60%  neutral (primary blue)
 *   60–90% caution (amber)
 *   > 90%  danger  (red)
 */
interface QuotaCardProps {
  status: QuotaStatus | null;
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

export function QuotaCard({ status, loading, error, onRetry }: QuotaCardProps) {
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

  if (!status) return null;

  // Unlimited variant — no numeric progress
  if (status.unlimited) {
    return (
      <div className="bg-white rounded-2xl border border-jea-border shadow-sm p-5 max-w-3xl" dir="rtl">
        <div className="flex items-center gap-3">
          <div className="w-11 h-11 rounded-xl bg-jea-bg flex items-center justify-center shrink-0" aria-hidden="true">
            <Gauge size={22} className="text-jea-primary" />
          </div>
          <div className="flex-1">
            <h2 className="text-sm font-black text-jea-text" lang="ar">رصيد الأمتار السنوي</h2>
            <p className="text-xs text-jea-muted" lang="en" dir="ltr">Annual m² quota · {status.year}</p>
          </div>
          <div className="text-left">
            <span className="text-xs font-bold text-jea-muted" lang="ar">غير محدد</span>
            <p className="text-[10px] text-jea-muted mt-0.5" lang="en" dir="ltr">Unlimited</p>
          </div>
        </div>
        <p className="text-xs text-jea-muted mt-3">
          <span lang="ar">استُخدم {formatM2(status.used_m2)} م² هذا العام عبر {status.projects_count} مشروع.</span>
        </p>
      </div>
    );
  }

  const sev = severity(status.percent_used);
  const barCls = BAR_CLASS[sev];
  const txtCls = TEXT_CLASS[sev];

  return (
    <div className="bg-white rounded-2xl border border-jea-border shadow-sm p-5 max-w-3xl" dir="rtl">
      <div className="flex items-center gap-3 mb-4">
        <div className="w-11 h-11 rounded-xl bg-jea-bg flex items-center justify-center shrink-0" aria-hidden="true">
          <Gauge size={22} className="text-jea-primary" />
        </div>
        <div className="flex-1">
          <h2 className="text-sm font-black text-jea-text" lang="ar">رصيد الأمتار السنوي</h2>
          <p className="text-[10px] text-jea-muted">
            <span lang="en" dir="ltr">Annual m² quota · {status.year}</span>
            <span className="mx-1" aria-hidden="true">·</span>
            <span lang="ar">{status.projects_count} مشروع</span>
          </p>
        </div>
        <div className="text-left">
          <div className={`text-2xl font-black leading-none ${txtCls}`}>
            {status.percent_used}%
          </div>
          <p className="text-[10px] text-jea-muted mt-1" lang="ar">مستخدَم</p>
        </div>
      </div>

      {/* Progress bar */}
      <div
        className="h-3 rounded-full bg-jea-bg overflow-hidden"
        role="progressbar"
        aria-valuenow={status.percent_used ?? 0}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-label={`${status.percent_used ?? 0}% used`}
      >
        <div
          className={`h-full ${barCls} transition-all duration-500`}
          style={{ width: `${status.percent_used ?? 0}%` }}
        />
      </div>

      {/* Numbers row */}
      <div className="grid grid-cols-3 gap-3 mt-4 text-center">
        <div className="bg-jea-bg rounded-lg px-2 py-2">
          <div className="text-[10px] text-jea-muted" lang="ar">المستخدَم</div>
          <div className="text-sm font-black text-jea-text mt-0.5">
            {formatM2(status.used_m2)}
            <span className="text-[10px] font-normal text-jea-muted mr-1">م²</span>
          </div>
        </div>
        <div className="bg-jea-bg rounded-lg px-2 py-2">
          <div className="text-[10px] text-jea-muted" lang="ar">المتبقي</div>
          <div className={`text-sm font-black mt-0.5 ${txtCls}`}>
            {formatM2(status.remaining_m2 ?? 0)}
            <span className="text-[10px] font-normal text-jea-muted mr-1">م²</span>
          </div>
        </div>
        <div className="bg-jea-bg rounded-lg px-2 py-2">
          <div className="text-[10px] text-jea-muted" lang="ar">السقف السنوي</div>
          <div className="text-sm font-black text-jea-text mt-0.5">
            {formatM2(status.quota_m2 ?? 0)}
            <span className="text-[10px] font-normal text-jea-muted mr-1">م²</span>
          </div>
        </div>
      </div>

      {sev === 'danger' && (
        <div role="alert" className="mt-3 flex items-center gap-2 text-xs text-jea-danger">
          <AlertTriangle size={14} aria-hidden="true" />
          <span lang="ar">وصلت إلى حدود السقف السنوي. لن تتمكن من إضافة مشاريع جديدة بمساحة إضافية.</span>
        </div>
      )}
    </div>
  );
}
