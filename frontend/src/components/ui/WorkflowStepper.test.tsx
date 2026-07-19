import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { WorkflowStepper } from './WorkflowStepper';
import type { SchemaWorkflowStage } from '../../types';

const STAGES: SchemaWorkflowStage[] = [
  { id: 'office_submission',      label_ar: 'تقديم الطلب',     label_en: 'Office Submission',      role: 'applicant', sla_hours: 24, actions: ['submit'] },
  { id: 'second_auditor_review',  label_ar: 'مراجعة المدقق',   label_en: 'Second Auditor Review',  role: 'auditor',   sla_hours: 72, actions: ['approve', 'reject'] },
  { id: 'payment',                label_ar: 'الدفع',           label_en: 'Payment',                role: 'staff',     sla_hours: 24, actions: ['confirm_payment'] },
  { id: 'issue_documents',        label_ar: 'إصدار الوثائق',   label_en: 'Issue Documents',        role: 'staff',     sla_hours: 24, actions: ['issue_certificate'] },
];

describe('WorkflowStepper', () => {
  it('renders nothing when stages is empty', () => {
    const { container } = render(<WorkflowStepper stages={[]} />);
    expect(container.firstChild).toBeNull();
  });

  it('renders each stage as an <li> inside a single <ol>', () => {
    const { container } = render(<WorkflowStepper stages={STAGES} />);
    const ols = container.querySelectorAll('ol');
    expect(ols).toHaveLength(1);
    expect(container.querySelectorAll('ol > li')).toHaveLength(STAGES.length);
  });

  it('renders both AR and EN labels per stage', () => {
    render(<WorkflowStepper stages={STAGES} />);
    for (const s of STAGES) {
      expect(screen.getByText(s.label_ar)).toBeInTheDocument();
      expect(screen.getByText(s.label_en)).toBeInTheDocument();
    }
  });

  it('marks the current stage with aria-current="step" when currentStageId matches', () => {
    render(<WorkflowStepper stages={STAGES} currentStageId="second_auditor_review" />);
    const current = document.querySelector('li[aria-current="step"]');
    expect(current).not.toBeNull();
    expect(current!.textContent).toContain('مراجعة المدقق');
  });

  it('sets no aria-current when currentStageId is missing or unknown', () => {
    render(<WorkflowStepper stages={STAGES} currentStageId="nonexistent-stage" />);
    expect(document.querySelector('li[aria-current="step"]')).toBeNull();
  });

  it('exposes a bilingual aria-label on each stage marker', () => {
    render(<WorkflowStepper stages={STAGES} />);
    // Each stage's marker is a role=img with aria-label containing "AR · EN".
    const markers = screen.getAllByRole('img');
    // At least one marker per stage (there may be extra role=img elsewhere,
    // so we filter to those whose aria-label matches our stages).
    const stageLabels = STAGES.map(s => `${s.label_ar} · ${s.label_en}`);
    const found = stageLabels.filter(label =>
      markers.some(m => m.getAttribute('aria-label') === label)
    );
    expect(found).toEqual(stageLabels);
  });

  it('shows the header title in AR + EN', () => {
    render(<WorkflowStepper stages={STAGES} titleAr="مسار مخصص" titleEn="Custom Path" />);
    expect(screen.getByText('مسار مخصص')).toBeInTheDocument();
    expect(screen.getByText(/Custom Path/)).toBeInTheDocument();
  });

  it('renders SLA hours when the stage declares them', () => {
    render(<WorkflowStepper stages={STAGES} />);
    // "24س" for a 24h SLA. Not asserting a specific one — just presence.
    const slaLabels = document.body.textContent ?? '';
    expect(slaLabels).toMatch(/\d+س/);
  });

  describe('dimForRole', () => {
    it('marks office stages as owned + others as not-owned when dimForRole=office', () => {
      render(<WorkflowStepper stages={STAGES} dimForRole="office" />);
      const ownership = screen.getAllByTestId('workflow-stage')
        .map(el => el.getAttribute('data-owned-by-actor'));
      // 1 applicant + 1 auditor + 2 staff → office owns the first one only.
      expect(ownership).toEqual(['true', 'false', 'false', 'false']);
    });

    it('inverts the ownership map when dimForRole=reviewer', () => {
      render(<WorkflowStepper stages={STAGES} dimForRole="reviewer" />);
      const ownership = screen.getAllByTestId('workflow-stage')
        .map(el => el.getAttribute('data-owned-by-actor'));
      expect(ownership).toEqual(['false', 'true', 'true', 'true']);
    });

    it('marks every stage as owned when dimForRole is omitted (backward compat)', () => {
      render(<WorkflowStepper stages={STAGES} />);
      for (const el of screen.getAllByTestId('workflow-stage')) {
        expect(el.getAttribute('data-owned-by-actor')).toBe('true');
      }
    });
  });
});
