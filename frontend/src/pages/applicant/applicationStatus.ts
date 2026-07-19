import type { Application } from '../../types';

/**
 * Terminal statuses — no further stage advance can happen. MyApplications
 * hides these by default so the applicant sees a shorter list focused on
 * "what still needs my attention", with an explicit toggle to include
 * the archive.
 */
export const TERMINAL_STATUSES: ReadonlyArray<string> = [
  'approved',
  'rejected',
  'certificate_issued',
];

export function isTerminal(app: Pick<Application, 'status'>): boolean {
  return TERMINAL_STATUSES.includes(app.status);
}

export function isOngoing(app: Pick<Application, 'status'>): boolean {
  return !isTerminal(app);
}

/**
 * Sort so the applicant's most urgent work floats up: applications with
 * "modifications_requested" first (they're blocked on the applicant),
 * then everything else by newest first.
 */
export function orderForApplicant<T extends { status: string; created_at?: string }>(apps: T[]): T[] {
  return [...apps].sort((a, b) => {
    const aBlocked = a.status === 'modifications_requested' ? 0 : 1;
    const bBlocked = b.status === 'modifications_requested' ? 0 : 1;
    if (aBlocked !== bBlocked) return aBlocked - bBlocked;
    // Fall back to created_at desc when both are equally urgent.
    return (b.created_at ?? '').localeCompare(a.created_at ?? '');
  });
}
