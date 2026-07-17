import React from 'react';
import {
  User as UserIcon, ShieldCheck, Search, CreditCard, Award,
  Cog, GitBranch, RotateCw, CircleDot,
} from 'lucide-react';
import type { SchemaWorkflowStage } from '../../types';

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
  className?: string;
}

/** Role → icon mapping. Reviewer/staff/system get distinctive glyphs. */
const ROLE_ICON: Record<string, React.ComponentType<{ size?: number; className?: string }>> = {
  applicant: UserIcon,
  staff:     ShieldCheck,
  auditor:   Search,
  admin:     Cog,
  system:    GitBranch,
};

/** Stage id → semantic-hint icon override (overrides role icon when matched). */
const STAGE_HINT_ICON: Record<string, React.ComponentType<{ size?: number; className?: string }>> = {
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

function iconFor(stage: SchemaWorkflowStage): React.ComponentType<{ size?: number; className?: string }> {
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
  className = '',
}: WorkflowStepperProps) {
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
      dir="rtl"
    >
      <header className="mb-4 flex items-baseline justify-between gap-3">
        <h2 id="workflow-stepper-title" className="text-sm font-black text-jea-text">
          <span lang="ar">{titleAr}</span>
          <span className="text-jea-muted font-normal text-xs mx-1" lang="en" dir="ltr">· {titleEn}</span>
        </h2>
        <span className="text-[11px] text-jea-muted">
          <span lang="ar">{stages.length} مراحل</span>
        </span>
      </header>

      <ol className={listCls} aria-label={`${titleAr} · ${titleEn}`}>
        {stages.map((stage, idx) => {
          const pos = positionOf(idx, activeIdx);
          const Icon = iconFor(stage);
          const isLast = idx === stages.length - 1;
          const stageAria = `${stage.label_ar} · ${stage.label_en}`;
          return (
            <li
              key={stage.id}
              aria-current={pos === 'current' ? 'step' : undefined}
              className="flex items-start gap-3 md:flex-col md:items-center md:flex-1 min-w-0"
            >
              {/* Marker + connector */}
              <div className="flex items-center md:flex-col md:items-center gap-2 md:gap-0 md:w-full">
                <div className="flex-1 md:hidden" aria-hidden="true" />
                <div
                  className={`w-10 h-10 rounded-full flex items-center justify-center shrink-0 transition-all duration-200 ${MARKER_STYLES[pos]}`}
                  aria-label={stageAria}
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
                <div className="text-xs font-bold leading-tight" lang="ar">
                  {stage.label_ar}
                </div>
                <div className="text-[10px] mt-0.5 opacity-80" lang="en" dir="ltr">
                  {stage.label_en}
                </div>
                <div className="text-[10px] mt-1 text-jea-muted">
                  <span lang="ar">دور: </span>
                  <span>{stage.role}</span>
                  {stage.sla_hours ? (
                    <>
                      <span className="mx-1" aria-hidden="true">·</span>
                      <span lang="ar">{stage.sla_hours}س</span>
                    </>
                  ) : null}
                </div>
              </div>
            </li>
          );
        })}
      </ol>
    </section>
  );
}
