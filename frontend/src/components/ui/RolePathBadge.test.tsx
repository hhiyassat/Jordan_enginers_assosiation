import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { RolePathBadge } from './RolePathBadge';
import type { SchemaWorkflowStage } from '../../types';

function s(role: string): SchemaWorkflowStage {
  return { id: role + '-x', role, label_ar: role, sla_hours: 24 } as SchemaWorkflowStage;
}

const demolitionWorkflow: SchemaWorkflowStage[] = [
  s('applicant'), s('auditor'), s('auditor'), s('staff'), s('staff'),
];

describe('RolePathBadge', () => {
  it('shows 1 office and 4 reviewer for the demolition workflow', () => {
    const { container } = render(<RolePathBadge stages={demolitionWorkflow} />);
    expect(screen.getByTestId('office-path-badge')).toHaveTextContent(/مسار المكتب:.*1/);
    expect(screen.getByTestId('reviewer-path-badge')).toHaveTextContent(/مسار المدقق:.*4/);
    expect(container.querySelector('[aria-label]')).toBeInTheDocument();
  });

  it('hides the office badge when there are no office stages', () => {
    render(<RolePathBadge stages={[s('staff')]} />);
    expect(screen.queryByTestId('office-path-badge')).toBeNull();
    expect(screen.getByTestId('reviewer-path-badge')).toBeInTheDocument();
  });

  it('renders nothing when both buckets are empty', () => {
    const { container } = render(<RolePathBadge stages={[]} />);
    expect(container.textContent).toBe('');
  });

  it('ignores unknown roles (superuser is not counted)', () => {
    // Sanity check that the pinned "superuser has no workflow" boundary
    // holds all the way through to the UI.
    render(<RolePathBadge stages={[s('applicant'), s('superuser')]} />);
    expect(screen.getByTestId('office-path-badge')).toHaveTextContent(/1/);
    expect(screen.queryByTestId('reviewer-path-badge')).toBeNull();
  });
});
