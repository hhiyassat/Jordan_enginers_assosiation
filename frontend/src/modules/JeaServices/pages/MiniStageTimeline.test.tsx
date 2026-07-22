import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MiniStageTimeline } from './MiniStageTimeline';
import type { SchemaWorkflowStage } from '../../../types';

function s(id: string, label_ar: string, role = 'staff'): SchemaWorkflowStage {
  return { id, role, label_ar, label_en: id, sla_hours: 24 } as SchemaWorkflowStage;
}

const stages = [
  s('office_submission', 'تقديم الطلب', 'applicant'),
  s('review',            'قيد المراجعة'),
  s('pending_missing',   'بانتظار نواقص'),
  s('payment',           'الدفع'),
  s('issue',             'الشهادة'),
];

describe('MiniStageTimeline', () => {
  it('renders nothing when stages is empty', () => {
    const { container } = render(<MiniStageTimeline stages={[]} />);
    expect(container.firstChild).toBeNull();
  });

  it('marks stages before currentStageId as past, the target as current, later as future', () => {
    render(<MiniStageTimeline stages={stages} currentStageId="pending_missing" />);
    const items = screen.getAllByRole('listitem');
    // The <li> elements carry a data-stage-position attribute so the
    // renderer's classification is easy to lock in a single assertion.
    expect(items.map(el => el.getAttribute('data-stage-position'))).toEqual([
      'past', 'past', 'current', 'future', 'future',
    ]);
  });

  it('shows Arabic labels for every stage', () => {
    render(<MiniStageTimeline stages={stages} currentStageId="review" />);
    for (const label of ['تقديم الطلب', 'قيد المراجعة', 'بانتظار نواقص', 'الدفع', 'الشهادة']) {
      expect(screen.getByText(label)).toBeInTheDocument();
    }
  });

  it('marks the current stage with aria-current="step" for screen readers', () => {
    render(<MiniStageTimeline stages={stages} currentStageId="payment" />);
    const current = screen.getAllByRole('listitem').find(el => el.getAttribute('aria-current') === 'step');
    expect(current).toBeTruthy();
    expect(current!.getAttribute('data-stage-id')).toBe('payment');
  });

  it('treats every stage as future when currentStageId is unknown', () => {
    render(<MiniStageTimeline stages={stages} currentStageId={null} />);
    const items = screen.getAllByRole('listitem');
    expect(items.every(el => el.getAttribute('data-stage-position') === 'future')).toBe(true);
  });
});
