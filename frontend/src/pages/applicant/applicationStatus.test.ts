import { describe, it, expect } from 'vitest';
import { isTerminal, isOngoing, orderForApplicant, TERMINAL_STATUSES } from './applicationStatus';

describe('applicationStatus', () => {
  it('classifies approved / rejected / certificate_issued as terminal', () => {
    expect(isTerminal({ status: 'approved' })).toBe(true);
    expect(isTerminal({ status: 'rejected' })).toBe(true);
    expect(isTerminal({ status: 'certificate_issued' })).toBe(true);
  });

  it('classifies draft / submitted / under_review / modifications_requested as ongoing', () => {
    for (const s of ['draft', 'submitted', 'under_review', 'modifications_requested', 'pending_payment']) {
      expect(isOngoing({ status: s })).toBe(true);
    }
  });

  it('exports TERMINAL_STATUSES as a readonly list', () => {
    // A slip here would silently change which apps appear in the
    // "ongoing" filter.
    expect(TERMINAL_STATUSES).toEqual(['approved', 'rejected', 'certificate_issued']);
  });

  it('floats modifications_requested to the top', () => {
    const apps = [
      { status: 'submitted',                created_at: '2026-07-01' },
      { status: 'modifications_requested',  created_at: '2026-06-01' },
      { status: 'under_review',             created_at: '2026-07-05' },
    ];
    const ordered = orderForApplicant(apps);
    expect(ordered[0].status).toBe('modifications_requested');
  });

  it('breaks ties by newest first', () => {
    const apps = [
      { status: 'submitted', created_at: '2026-07-01' },
      { status: 'submitted', created_at: '2026-07-10' },
      { status: 'submitted', created_at: '2026-07-05' },
    ];
    const ordered = orderForApplicant(apps);
    expect(ordered.map(a => a.created_at)).toEqual(['2026-07-10', '2026-07-05', '2026-07-01']);
  });
});
