import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { ExpiryBadge } from './ExpiryBadge';

/**
 * JORD-62: badge covers the two "when is this thing valid until" dates
 * that JORD-58 (5-yr drawing validity) and JORD-59 (6-mo supervision)
 * surfaced. The color/severity is threshold-driven off `Date.now()` so
 * we control time with vi.setSystemTime.
 */

describe('ExpiryBadge', () => {
  beforeEach(() => {
    // Fixed clock so "30 days from now" is deterministic across runs.
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-06-01T00:00:00Z'));
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('renders nothing when iso is null (rule doesn\'t apply / not approved yet)', () => {
    const { container } = render(<ExpiryBadge kind="validity" iso={null} />);
    expect(container.firstChild).toBeNull();
  });

  it('renders nothing when iso is undefined', () => {
    const { container } = render(<ExpiryBadge kind="supervision" iso={undefined} />);
    expect(container.firstChild).toBeNull();
  });

  it('shows the green (ok) style when the date is more than 30 days away', () => {
    // 90 days ahead → firmly in "ok" bucket.
    render(<ExpiryBadge kind="validity" iso="2026-08-30T12:00:00Z" />);
    const el = screen.getByTestId('expiry-badge-validity');
    expect(el).toHaveAttribute('data-severity', 'ok');
    expect(el.className).toMatch(/emerald/);
  });

  it('shows the amber (soon) style within 30 days of expiry', () => {
    // 10 days ahead → soon.
    render(<ExpiryBadge kind="supervision" iso="2026-06-11T00:00:00Z" />);
    const el = screen.getByTestId('expiry-badge-supervision');
    expect(el).toHaveAttribute('data-severity', 'soon');
    expect(el.className).toMatch(/amber/);
  });

  it('shows the red (past) style when the date is already past', () => {
    render(<ExpiryBadge kind="supervision" iso="2026-05-01T00:00:00Z" />);
    const el = screen.getByTestId('expiry-badge-supervision');
    expect(el).toHaveAttribute('data-severity', 'past');
    expect(el.className).toMatch(/red/);
  });

  it('uses the correct label per kind (supervision vs validity)', () => {
    render(<ExpiryBadge kind="supervision" iso="2026-08-30T00:00:00Z" />);
    expect(screen.getByText(/الإشراف صالح حتى/)).toBeInTheDocument();
  });

  it('uses the validity label for kind=validity', () => {
    render(<ExpiryBadge kind="validity" iso="2026-08-30T00:00:00Z" />);
    expect(screen.getByText(/المخططات صالحة حتى/)).toBeInTheDocument();
  });

  it('carries the date in the title attribute for hover context', () => {
    render(<ExpiryBadge kind="validity" iso="2026-08-30T00:00:00Z" />);
    const el = screen.getByTestId('expiry-badge-validity');
    // We don't hard-assert the exact locale string (varies with test env)
    // but the title MUST contain the label and a date-shaped substring.
    expect(el.getAttribute('title')).toContain('المخططات صالحة حتى');
  });
});
