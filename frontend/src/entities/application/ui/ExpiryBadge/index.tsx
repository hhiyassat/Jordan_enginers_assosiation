import React from 'react';
import { useTranslation } from 'react-i18next';
import { Clock, ShieldCheck, AlertTriangle } from 'lucide-react';

/**
 * ExpiryBadge — JORD-62
 *
 * Renders one of two dates that the applicant needs to see on approved
 * applications:
 *
 *   • `supervision`: 6-month supervision-contract window from the JEA
 *     2025 manual p. 27 (JORD-59). Set only for DRW-P-* apps.
 *   • `validity`:    n-month approved-output window from schema's
 *     certificate.validity_months (JORD-58 seeded 60 for drawings).
 *
 * Renders nothing when `iso` is null/undefined (no approval yet, or
 * the rule doesn't apply). Colors:
 *   • Green if the date is more than 30 days away.
 *   • Amber if within 30 days.
 *   • Red   if already past.
 *
 * The 30-day threshold matches the manual's stated grace intent — a
 * project office should have time to re-tender / re-sign the contract
 * before the window lapses.
 */
type Kind = 'supervision' | 'validity';

interface Props {
  kind: Kind;
  iso: string | null | undefined;
}

const KIND_LABEL: Record<Kind, { ar: string; en: string; icon: React.ReactNode }> = {
  supervision: {
    ar: 'الإشراف صالح حتى',
    en: 'Supervision valid until',
    icon: <ShieldCheck size={11} aria-hidden="true" />,
  },
  validity: {
    ar: 'المخططات صالحة حتى',
    en: 'Approval valid until',
    icon: <Clock size={11} aria-hidden="true" />,
  },
};

function severityFor(iso: string): 'ok' | 'soon' | 'past' {
  const target = new Date(iso).getTime();
  const now    = Date.now();
  const diffDays = (target - now) / (1000 * 60 * 60 * 24);
  if (diffDays < 0) return 'past';
  if (diffDays < 30) return 'soon';
  return 'ok';
}

const SEVERITY_CLASS: Record<'ok' | 'soon' | 'past', string> = {
  ok:   'bg-emerald-50 text-emerald-800 border-emerald-200',
  soon: 'bg-amber-50   text-amber-800   border-amber-200',
  past: 'bg-red-50     text-red-700     border-red-200',
};

export function ExpiryBadge({ kind, iso }: Props) {
  const { i18n } = useTranslation();
  const isArabic = i18n.language.startsWith('ar');
  if (!iso) return null;

  const sev  = severityFor(iso);
  const cfg  = KIND_LABEL[kind];
  const label = isArabic ? cfg.ar : cfg.en;
  const locale = isArabic ? 'ar-EG' : 'en-JO';
  const formatted = new Date(iso).toLocaleDateString(locale);

  return (
    <span
      className={`inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full border ${SEVERITY_CLASS[sev]}`}
      data-testid={`expiry-badge-${kind}`}
      data-severity={sev}
      title={`${label}: ${formatted}`}
    >
      {sev === 'past' ? <AlertTriangle size={11} aria-hidden="true" /> : cfg.icon}
      <span>{label}: {formatted}</span>
    </span>
  );
}
