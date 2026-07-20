import React from 'react';
import { useTranslation } from 'react-i18next';
import {
  User as UserIcon, ShieldCheck, Search, CreditCard, Award,
  Cog, GitBranch, RotateCw, CircleDot,
  type LucideIcon,
} from 'lucide-react';
import type { SchemaWorkflowStage } from '../../types';
import { bucketOf, type PathRole } from '../../engine/workflowRolePath';

/**
 * WorkflowStepper — read-only visualisation of an application's
 * lifecycle stages (extracted from schema.workflow.stages).
 *
 * Bilingual (AR + EN) labels per stage, role-based icon in the pill,
 * highlights the currentStageId with a filled marker. Stages before
 * the current one get a completed check; stages after are muted.
 *
 * Accessibility:
 *   - <ol> semantic list with each stage as an <li>
 *   - aria-current="step" on the active stage
 *   - Full bilingual aria-label on each stage marker so screen readers
 *     announce both AR and EN text
 *   - Decorative connector lines have aria-hidden
 */
interface WorkflowStepperProps {
  stages: SchemaWorkflowStage[];
  /** id of the stage the application is currently at. If undefined, no highlight. */
  currentStageId?: string | null;
  /** Optional heading rendered above the list (bilingual). */
  titleAr?: string;
  titleEn?: string;
  /** Layout — default horizontal on wide screens, vertical on narrow. */
  layout?: 'auto' | 'horizontal' | 'vertical';
  /**
   * When set, stages whose role doesn't belong to this actor are dimmed
   * so the reader sees "their" path highlighted against the fuller flow.
   *   'office'   → dims staff/auditor/admin stages
   *   'reviewer' → dims applicant stages
   * The past/current/future coloring still applies on the actor's own
   * stages so nothing else about the stepper changes.
   */
  dimForRole?: PathRole;
  className?: string;
}

/** Role → icon mapping. Reviewer/staff/system get distinctive glyphs. */
const ROLE_ICON: Record<string, LucideIcon> = {
  applicant: UserIcon,
  staff:     ShieldCheck,
  auditor:   Search,
  admin:     Cog,
  system:    GitBranch,
};

/** Stage id → semantic-hint icon override (overrides role icon when matched). */
const STAGE_HINT_ICON: Record<string, LucideIcon> = {
  office_submission:             UserIcon,
  first_auditor_review:          ShieldCheck,
  second_auditor_review:         Search,
  payment:                       CreditCard,
  design_office_payment:         CreditCard,
  supervision_office_payment:    CreditCard,
  issue_documents:               Award,
  supervision_setup:             Cog,
  contract_type_selection:       GitBranch,
  additional_inspection_check:   RotateCw,
};

function iconFor(stage: SchemaWorkflowStage): LucideIcon {
  return STAGE_HINT_ICON[stage.id] ?? ROLE_ICON[stage.role] ?? CircleDot;
}

type Position = 'past' | 'current' | 'future';

function positionOf(idx: number, currentIdx: number | null): Position {
  if (currentIdx === null) return 'future';
  if (idx < currentIdx)  return 'past';
  if (idx === currentIdx) return 'current';
  return 'future';
}

const MARKER_STYLES: Record<Position, string> = {
  past:    'bg-emerald-500 text-white ring-2 ring-emerald-100',
  current: 'bg-jea-primary text-white ring-4 ring-jea-primary/20 shadow-md',
  future:  'bg-white text-jea-muted ring-2 ring-jea-border',
};

const LABEL_STYLES: Record<Position, string> = {
  past:    'text-jea-text',
  current: 'text-jea-primary font-black',
  future:  'text-jea-muted',
};

const CONNECTOR_STYLES: Record<Position, string> = {
  past:    'bg-emerald-500',
  current: 'bg-gradient-to-l from-jea-border to-jea-primary',
  future:  'bg-jea-border',
};

export function WorkflowStepper({
  stages,
  currentStageId,
  titleAr = 'مسار الطلب',
  titleEn = 'Application workflow',
  layout = 'auto',
  dimForRole,
  className = '',
}: WorkflowStepperProps) {
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const isArabic = isRtl;
  if (!stages || stages.length === 0) return null;

  const currentIdx = currentStageId
    ? stages.findIndex(s => s.id === currentStageId)
    : -1;
  const activeIdx = currentIdx === -1 ? null : currentIdx;

  const listCls =
    layout === 'vertical' ? 'flex flex-col gap-4' :
    layout === 'horizontal' ? 'flex items-start gap-2 overflow-x-auto' :
    'flex flex-col gap-4 md:flex-row md:items-start md:gap-2 md:overflow-x-auto';

  return (
    <section
      aria-labelledby="workflow-stepper-title"
      className={`bg-white rounded-2xl border border-jea-border shadow-sm p-5 ${className}`}
      dir={isRtl ? 'rtl' : 'ltr'}
    >
      <header className="mb-4 flex items-baseline justify-between gap-3">
        <h2 id="workflow-stepper-title" className="text-sm font-black text-jea-text">
          {titleAr}
        </h2>
        <span className="text-[11px] text-jea-muted">
          {t('workflowStepper.totalStages', { count: stages.length })}
        </span>
      </header>

      <ol className={listCls} aria-label={titleAr}>
        {stages.map((stage, idx) => {
          const pos = positionOf(idx, activeIdx);
          const Icon = iconFor(stage);
          const isLast = idx === stages.length - 1;
          const stageLabel = isArabic
            ? (stage.label_ar || stage.label_en)
            : (stage.label_en || stage.label_ar);
          const isMine = dimForRole ? bucketOf(stage.role) === dimForRole : true;
          const dimCls = isMine ? '' : 'opacity-40';
          const actions = stage.actions ?? [];
          return (
            <li
              key={stage.id}
              aria-current={pos === 'current' ? 'step' : undefined}
              data-testid="workflow-stage"
              data-stage-role={stage.role}
              data-owned-by-actor={isMine ? 'true' : 'false'}
              className={`flex items-start gap-3 md:flex-col md:items-center md:flex-1 min-w-0 transition-opacity ${dimCls}`}
            >
              {/* Marker + connector */}
              <div className="flex items-center md:flex-col md:items-center gap-2 md:gap-0 md:w-full">
                <div className="flex-1 md:hidden" aria-hidden="true" />
                <div
                  className={`w-10 h-10 rounded-full flex items-center justify-center shrink-0 transition-all duration-200 ${MARKER_STYLES[pos]}`}
                  aria-label={stageLabel}
                  role="img"
                >
                  {pos === 'past' ? (
                    <span aria-hidden="true">✓</span>
                  ) : (
                    <Icon size={18} aria-hidden="true" />
                  )}
                </div>
                {!isLast && (
                  <div
                    className={`h-8 w-0.5 md:h-0.5 md:w-full mt-1 md:mt-4 ${CONNECTOR_STYLES[pos]}`}
                    aria-hidden="true"
                  />
                )}
              </div>

              {/* Label block */}
              <div className={`flex-1 md:text-center md:mt-2 ${LABEL_STYLES[pos]}`}>
                <div className="text-xs font-bold leading-tight">
                  {stageLabel}
                </div>
                <div className="text-[10px] mt-1 text-jea-muted">
                  {t('workflowStepper.role')}: {stage.role}
                  {stage.sla_hours ? (
                    <>
                      <span className="mx-1" aria-hidden="true">·</span>
                      <span>{stage.sla_hours}{t('workflowStepper.hoursShort')}</span>
                    </>
                  ) : null}
                </div>
                {/* JORD-19: surface the actions available at this stage
                    so the applicant / reviewer sees WHAT can happen at
                    each step, not just the label. */}
                {actions.length > 0 && (
                  <div className="mt-1.5 flex flex-wrap gap-1 md:justify-center" aria-label={t('workflowStepper.actions')}>
                    {actions.map(a => (
                      <span
                        key={a}
                        className="text-[9px] font-semibold px-1.5 py-0.5 rounded-full bg-jea-accent text-jea-primary border border-jea-border"
                      >
                        {t(`stageAction.${a}`, { defaultValue: a })}
                      </span>
                    ))}
                  </div>
                )}
              </div>
            </li>
          );
        })}
      </ol>
    </section>
  );
}
