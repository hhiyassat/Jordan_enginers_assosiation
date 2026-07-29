import React from 'react';

/**
 * PhaseBadge — coloured indicator for the JEA services delivery phase.
 *
 *   Phase 1 → green   (immediate rollout)
 *   Phase 2 → orange  (near-term)
 *   Phase 3 → red     (financial / heavy backend)
 *   Phase 4 → blue    (board decisions layer)
 *   Phase 5 → purple  (self-service / catalogue)
 *
 * Renders as a colored dot on dark surfaces (top-level tiles) and as a
 * pill with the phase number on light surfaces (detail cards). Nothing
 * is rendered when phase is null/undefined so old services stay clean.
 */
export type Phase = 1 | 2 | 3 | 4 | 5;

interface PhaseBadgeProps {
  phase: Phase | number | null | undefined;
  /** dot = 8px coloured circle (for dark tiles); pill = badge with number (for light cards) */
  variant?: 'dot' | 'pill';
  className?: string;
}

const PHASE_COLOR: Record<Phase, { bg: string; text: string; label_ar: string }> = {
  1: { bg: 'bg-emerald-500', text: 'text-white',       label_ar: 'المرحلة الأولى' },
  2: { bg: 'bg-orange-500',  text: 'text-white',       label_ar: 'المرحلة الثانية' },
  3: { bg: 'bg-red-500',     text: 'text-white',       label_ar: 'المرحلة الثالثة' },
  4: { bg: 'bg-blue-500',    text: 'text-white',       label_ar: 'المرحلة الرابعة' },
  5: { bg: 'bg-purple-500',  text: 'text-white',       label_ar: 'المرحلة الخامسة' },
};

function isValidPhase(v: unknown): v is Phase {
  return v === 1 || v === 2 || v === 3 || v === 4 || v === 5;
}

export function PhaseBadge({ phase, variant = 'dot', className = '' }: PhaseBadgeProps) {
  if (!isValidPhase(phase)) return null;
  const cfg = PHASE_COLOR[phase];
  const label = `${cfg.label_ar} · Phase ${phase}`;

  if (variant === 'pill') {
    return (
      <span
        className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${cfg.bg} ${cfg.text} ${className}`}
        title={label}
        aria-label={label}
      >
        <span className="w-1.5 h-1.5 rounded-full bg-white/80" aria-hidden="true" />
        <span lang="ar">م{phase}</span>
        <span className="opacity-70" lang="en" dir="ltr">P{phase}</span>
      </span>
    );
  }

  return (
    <span
      className={`inline-block w-2.5 h-2.5 rounded-full ring-2 ring-white/70 ${cfg.bg} ${className}`}
      title={label}
      aria-label={label}
      role="img"
    />
  );
}
