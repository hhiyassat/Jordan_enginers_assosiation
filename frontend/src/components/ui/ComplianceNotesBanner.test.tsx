import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { ComplianceNotesBanner } from './ComplianceNotesBanner';
import type { ComplianceNote } from '../../types';

/**
 * JORD-61: renders schema.compliance_notes above the applicant form.
 * The tests below pin the invariants a future edit could quietly
 * break — silence on empty input, per-severity styling, and the
 * source citation that makes a note auditable back to the manual.
 */

function note(overrides: Partial<ComplianceNote> = {}): ComplianceNote {
  return {
    code: 'S-03',
    source: 'كتاب التعليمات الفنية 2025',
    page: 36,
    category: 'retention',
    label_ar: 'المحافظة على العينات لمدة 10 أيام',
    label_en: 'Retain samples for 10 days',
    body_ar: 'المحافظة على الآبار السبرية والعينات لمدة عشرة أيام.',
    body_en: 'Preserve boreholes and samples for 10 days.',
    severity: 'warning',
    ...overrides,
  };
}

describe('ComplianceNotesBanner', () => {
  it('renders nothing when notes are undefined', () => {
    const { container } = render(<ComplianceNotesBanner notes={undefined} />);
    expect(container.firstChild).toBeNull();
  });

  it('renders nothing when notes array is empty', () => {
    const { container } = render(<ComplianceNotesBanner notes={[]} />);
    expect(container.firstChild).toBeNull();
  });

  it('renders one callout per note with label + body + source citation', () => {
    render(<ComplianceNotesBanner notes={[note()]} />);
    expect(screen.getByText('المحافظة على العينات لمدة 10 أيام')).toBeInTheDocument();
    expect(screen.getByText(/المحافظة على الآبار السبرية/)).toBeInTheDocument();
    // Citation must contain rule code + page number so an auditor can
    // trace it back to the source manual without opening dev tools.
    expect(screen.getByText(/كتاب التعليمات الفنية 2025/)).toBeInTheDocument();
    expect(screen.getByText(/S-03/)).toBeInTheDocument();
    expect(screen.getByText(/ص 36/)).toBeInTheDocument();
  });

  it('carries data-severity attribute for styling / e2e targeting', () => {
    render(<ComplianceNotesBanner notes={[note({ severity: 'warning' })]} />);
    const el = screen.getByRole('note');
    expect(el).toHaveAttribute('data-severity', 'warning');
    expect(el).toHaveAttribute('data-note-code', 'S-03');
  });

  it('uses distinct styling per severity (info / warning / blocker)', () => {
    render(<ComplianceNotesBanner notes={[
      note({ code: 'INFO-1', severity: 'info' }),
      note({ code: 'WARN-1', severity: 'warning' }),
      note({ code: 'BLOCK-1', severity: 'blocker' }),
    ]} />);
    const info    = screen.getByText(/INFO-1/).closest('[role="note"]');
    const warn    = screen.getByText(/WARN-1/).closest('[role="note"]');
    const blocker = screen.getByText(/BLOCK-1/).closest('[role="note"]');
    // Each severity gets its own background token — future refactors
    // (e.g. theming) can move the tokens but must keep them distinct.
    expect(info?.className).toMatch(/blue/);
    expect(warn?.className).toMatch(/amber/);
    expect(blocker?.className).toMatch(/red/);
  });

  it('falls back to info styling for an unknown severity', () => {
    // Defensive: a future backend might send a severity string this
    // frontend doesn't know yet. Better to render as info than crash.
    render(<ComplianceNotesBanner notes={[
      note({ severity: 'unknown-future' as unknown as ComplianceNote['severity'] }),
    ]} />);
    const el = screen.getByRole('note');
    expect(el.className).toMatch(/blue/);
  });
});
