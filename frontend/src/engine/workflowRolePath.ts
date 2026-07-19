import type { SchemaWorkflowStage } from '../types';

/**
 * The workflow schema tags every stage with a role (applicant, staff,
 * auditor, admin). Callers need to answer two questions repeatedly:
 *   • Which stages does this actor own?
 *   • How many stages does each broad group own for the summary badge?
 *
 * The platform collapses the fine-grained roles into two applicant-
 * facing buckets:
 *   • office   — the engineering office (schema role: 'applicant')
 *   • reviewer — everyone in the reviewing tier (staff / auditor / admin)
 *
 * Anything else (unknown roles, superuser) → not counted in either.
 * We deliberately do NOT bucket superuser into reviewer: superuser is
 * user-management only and has no workflow presence (pinned in
 * project_superuser_scope memory).
 */

export type PathRole = 'office' | 'reviewer';

export function bucketOf(schemaRole: string | undefined): PathRole | null {
  if (schemaRole === 'applicant') return 'office';
  if (schemaRole === 'staff' || schemaRole === 'auditor' || schemaRole === 'admin') return 'reviewer';
  return null;
}

export interface RolePathSplit {
  mine: SchemaWorkflowStage[];
  other: SchemaWorkflowStage[];
}

/**
 * Split stages into "owned by this actor" vs "owned by someone else",
 * preserving order. Stages that fall outside both buckets (unknown roles)
 * are treated as `other` — they're still part of the flow, just not
 * this actor's responsibility.
 */
export function partitionByRole(
  stages: SchemaWorkflowStage[],
  actor: PathRole,
): RolePathSplit {
  const mine: SchemaWorkflowStage[] = [];
  const other: SchemaWorkflowStage[] = [];
  for (const stage of stages) {
    const bucket = bucketOf(stage.role);
    if (bucket === actor) mine.push(stage);
    else other.push(stage);
  }
  return { mine, other };
}

export interface PathCounts {
  office: number;
  reviewer: number;
}

/**
 * Count how many stages fall into each applicant-facing bucket. Used by
 * the service tile / card to render "مسار المكتب: N · مسار المدقق: M".
 */
export function countByRole(stages: SchemaWorkflowStage[]): PathCounts {
  const out: PathCounts = { office: 0, reviewer: 0 };
  for (const stage of stages) {
    const b = bucketOf(stage.role);
    if (b) out[b]++;
  }
  return out;
}
