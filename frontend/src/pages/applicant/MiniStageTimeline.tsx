import React from 'react';
import type { SchemaWorkflowStage } from '../../types';

/**
 * A one-line horizontal stage strip for the MyApplications list. Each
 * stage is a dot + a short Arabic label. Past stages get a check, the
 * current stage is colored, future stages are muted.
 *
 * Designed to fit inside a card row without pushing the layout — labels
 * are truncated so a 6-stage workflow still fits at typical widths.
 * Full detail lives on the application-detail page's WorkflowStepper.
 */
export function MiniStageTimeline({ stages, currentStageId }: {
  stages: SchemaWorkflowStage[];
  currentStageId?: string | null;
}) {
  if (!stages || stages.length === 0) return null;

  const currentIdx = currentStageId
    ? stages.findIndex(s => s.id === currentStageId)
    : -1;

  return (
    <ol
      className="flex items-center gap-1.5 mt-3 overflow-x-auto"
      dir="rtl"
      aria-label="مراحل الطلب"
      data-testid="mini-stage-timeline"
    >
      {stages.map((stage, i) => {
        const pos: 'past' | 'current' | 'future' =
          currentIdx === -1 ? 'future'
          : i < currentIdx ? 'past'
          : i === currentIdx ? 'current'
          : 'future';

        return (
          <React.Fragment key={stage.id}>
            <li
              className="flex items-center gap-1.5 shrink-0"
              data-stage-id={stage.id}
              data-stage-position={pos}
              aria-current={pos === 'current' ? 'step' : undefined}
            >
              <span
                className={`inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold ${
                  pos === 'past'    ? 'bg-emerald-500 text-white'
                  : pos === 'current' ? 'bg-jea-primary text-white ring-2 ring-jea-primary/20'
                  : 'bg-gray-200 text-gray-500'
                }`}
              >
                {pos === 'past' ? '✓' : (i + 1)}
              </span>
              <span
                className={`text-[11px] font-medium truncate max-w-[110px] ${
                  pos === 'current' ? 'text-jea-primary' :
                  pos === 'past'    ? 'text-gray-500' :
                  'text-gray-400'
                }`}
                title={stage.label_ar}
              >
                {stage.label_ar}
              </span>
            </li>
            {i < stages.length - 1 && (
              <span aria-hidden="true" className="text-gray-300 shrink-0 text-xs">←</span>
            )}
          </React.Fragment>
        );
      })}
    </ol>
  );
}
