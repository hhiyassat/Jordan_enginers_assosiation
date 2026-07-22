import React from 'react';
import { useTranslation } from 'react-i18next';
import { AlertTriangle, Info, ShieldAlert } from 'lucide-react';
import type { ComplianceNote } from '../../types';

/**
 * ComplianceNotesBanner — JORD-61
 *
 * Surfaces schema.compliance_notes entries above the applicant's Apply
 * form so a policy obligation the platform can't gate (e.g. JORD-60's
 * "keep samples on site for 10 days after report submission") is
 * visible before submit. Each note is a self-contained callout with
 * severity-driven color + icon and a small citation to the source
 * manual + page for auditability.
 *
 * Renders nothing when the schema carries no notes (the common case
 * — most services don't need one), so it's safe to always mount.
 *
 * Blocker-severity notes do NOT gate submit today; the backend
 * SchemaValidator has no matching enforcement path yet. When we add
 * one in Phase 2 the banner's severity contract already models it.
 */
const SEVERITY_CFG: Record<ComplianceNote['severity'], {
  wrapper: string; icon: React.ReactNode;
}> = {
  info: {
    wrapper: 'bg-blue-50 border-blue-200 text-blue-900',
    icon: <Info size={16} className="text-blue-600 shrink-0 mt-0.5" aria-hidden="true" />,
  },
  warning: {
    wrapper: 'bg-amber-50 border-amber-200 text-amber-900',
    icon: <AlertTriangle size={16} className="text-amber-600 shrink-0 mt-0.5" aria-hidden="true" />,
  },
  blocker: {
    wrapper: 'bg-red-50 border-red-200 text-red-900',
    icon: <ShieldAlert size={16} className="text-red-600 shrink-0 mt-0.5" aria-hidden="true" />,
  },
};

export function ComplianceNotesBanner({ notes }: { notes: ComplianceNote[] | undefined }) {
  const { i18n } = useTranslation();
  const isArabic = i18n.language.startsWith('ar');
  if (!notes || notes.length === 0) return null;

  return (
    <div className="space-y-2" data-testid="compliance-notes-banner">
      {notes.map(note => {
        const cfg = SEVERITY_CFG[note.severity] ?? SEVERITY_CFG.info;
        const label = isArabic ? note.label_ar : note.label_en;
        const body  = isArabic ? note.body_ar  : note.body_en;
        return (
          <div
            key={note.code}
            role="note"
            className={`flex items-start gap-2 border rounded-lg px-3 py-2.5 text-sm ${cfg.wrapper}`}
            data-note-code={note.code}
            data-severity={note.severity}
          >
            {cfg.icon}
            <div className="flex-1 min-w-0">
              <p className="font-semibold leading-tight">{label}</p>
              <p className="text-xs mt-1 leading-relaxed">{body}</p>
              {(note.source || note.page) && (
                <p className="text-[10px] mt-1.5 opacity-70">
                  {note.source ?? ''}
                  {note.page ? ` · ${isArabic ? 'ص ' : 'p. '}${note.page}` : ''}
                  {` · ${note.code}`}
                </p>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}
