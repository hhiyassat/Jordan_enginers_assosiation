import { describe, it, expect } from 'vitest';
import { bucketOf, partitionByRole, countByRole } from './workflowRolePath';
import type { SchemaWorkflowStage } from '../types';

function stage(id: string, role: string): SchemaWorkflowStage {
  return { id, role, label_ar: id, sla_hours: 24 } as SchemaWorkflowStage;
}

// Model the actual DRW-P-004 مخططات الهدم workflow so tests double as a
// contract check — 1 office stage, 4 reviewer stages, 5 total.
const demolitionWorkflow: SchemaWorkflowStage[] = [
  stage('office_submission',      'applicant'),
  stage('public_safety_review',   'auditor'),
  stage('final_technical_review', 'auditor'),
  stage('pay_fees_tax',           'staff'),
  stage('issue_certificate',      'staff'),
];

describe('bucketOf', () => {
  it('routes applicant → office', () => {
    expect(bucketOf('applicant')).toBe('office');
  });

  it('routes staff, auditor, admin → reviewer', () => {
    expect(bucketOf('staff')).toBe('reviewer');
    expect(bucketOf('auditor')).toBe('reviewer');
    expect(bucketOf('admin')).toBe('reviewer');
  });

  it('does NOT bucket superuser — superuser has no workflow presence', () => {
    // Pinned in project_superuser_scope memory: superuser is
    // user-management only and must not surface in workflow paths.
    expect(bucketOf('superuser')).toBeNull();
  });

  it('returns null for unknown roles instead of guessing', () => {
    expect(bucketOf('inspector')).toBeNull();
    expect(bucketOf(undefined)).toBeNull();
  });
});

describe('partitionByRole', () => {
  it('splits demolition workflow into 1 office / 4 reviewer', () => {
    const { mine: officeStages, other: reviewerStages } = partitionByRole(demolitionWorkflow, 'office');
    expect(officeStages.map(s => s.id)).toEqual(['office_submission']);
    expect(reviewerStages.map(s => s.id)).toEqual([
      'public_safety_review', 'final_technical_review', 'pay_fees_tax', 'issue_certificate',
    ]);
  });

  it('splits the same workflow into 4 mine / 1 other for a reviewer actor', () => {
    const { mine, other } = partitionByRole(demolitionWorkflow, 'reviewer');
    expect(mine).toHaveLength(4);
    expect(other).toHaveLength(1);
    expect(other[0].id).toBe('office_submission');
  });

  it('preserves order within each bucket', () => {
    // The stepper renders stages in workflow order regardless of which
    // bucket owns each — dimming happens later, not reordering.
    const { mine } = partitionByRole(demolitionWorkflow, 'reviewer');
    expect(mine.map(s => s.id)).toEqual([
      'public_safety_review', 'final_technical_review', 'pay_fees_tax', 'issue_certificate',
    ]);
  });

  it('sends unknown-role stages to `other` rather than dropping them', () => {
    const stages = [stage('a', 'applicant'), stage('b', 'inspector'), stage('c', 'staff')];
    const { mine, other } = partitionByRole(stages, 'office');
    expect(mine.map(s => s.id)).toEqual(['a']);
    expect(other.map(s => s.id)).toEqual(['b', 'c']);
  });
});

describe('countByRole', () => {
  it('counts DRW-P-004 as 1 office / 4 reviewer', () => {
    expect(countByRole(demolitionWorkflow)).toEqual({ office: 1, reviewer: 4 });
  });

  it('ignores unknown-role stages so the badge stays truthful', () => {
    expect(countByRole([stage('x', 'inspector'), stage('y', 'applicant')]))
      .toEqual({ office: 1, reviewer: 0 });
  });

  it('returns zeros for an empty workflow', () => {
    expect(countByRole([])).toEqual({ office: 0, reviewer: 0 });
  });
});
