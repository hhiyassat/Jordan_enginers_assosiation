import React from 'react';
import { User as UserIcon, ShieldCheck } from 'lucide-react';
import type { SchemaWorkflowStage } from '../../types';
import { countByRole } from '../../engine/workflowRolePath';

/**
 * Compact "N office · M reviewer" summary rendered on service cards
 * so applicants scanning the catalog know how many workflow stages
 * belong to their side vs the reviewer's before they even click in.
 *
 * Zero-count buckets are hidden — no need to show "0 خطوات للمكتب" on a
 * pure-reviewer service. If both buckets are zero (e.g. tile-level
 * placeholder schemas), the whole component renders nothing.
 */
export function RolePathBadge({ stages, className = '' }: {
  stages: SchemaWorkflowStage[];
  className?: string;
}) {
  const { office, reviewer } = countByRole(stages);
  if (office === 0 && reviewer === 0) return null;

  return (
    <div
      className={`flex items-center gap-2 flex-wrap text-[10px] ${className}`}
      role="group"
      aria-label="عدد خطوات كل جهة"
    >
      {office > 0 && (
        <span
          className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-50 border border-blue-100 text-blue-700"
          data-testid="office-path-badge"
        >
          <UserIcon size={10} aria-hidden="true" />
          <span lang="ar">مسار المكتب:</span>
          <span dir="ltr">{office}</span>
        </span>
      )}
      {reviewer > 0 && (
        <span
          className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-100 text-emerald-700"
          data-testid="reviewer-path-badge"
        >
          <ShieldCheck size={10} aria-hidden="true" />
          <span lang="ar">مسار المدقق:</span>
          <span dir="ltr">{reviewer}</span>
        </span>
      )}
    </div>
  );
}
