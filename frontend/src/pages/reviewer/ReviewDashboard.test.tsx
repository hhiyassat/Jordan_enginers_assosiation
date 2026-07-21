import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { ReviewDashboard } from './ReviewDashboard';

const mockDashboard = vi.fn();
vi.mock('../../api/client', () => ({
  reviewApi: {
    dashboard: (...a: unknown[]) => mockDashboard(...a),
  },
}));

function baseData(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    stats: {
      my_in_progress: 0,
      queue_available: 0,
      overdue: 0,
      decided_this_week: 0,
      decided_this_month: 0,
    },
    by_decision_this_month: { approved: 0, rejected: 0, modifications_requested: 0 },
    recent_decisions: [],
    my_in_progress: [],
    ...overrides,
  };
}

beforeEach(() => { mockDashboard.mockReset(); });

function renderPage() {
  return render(<MemoryRouter><ReviewDashboard /></MemoryRouter>);
}

describe('ReviewDashboard (JORD-88)', () => {
  it('renders every headline tile from the API stats', async () => {
    mockDashboard.mockResolvedValue(baseData({
      stats: {
        my_in_progress: 4, queue_available: 12, overdue: 2,
        decided_this_week: 7, decided_this_month: 30,
      },
    }));
    renderPage();

    await waitFor(() => expect(screen.getByTestId('tile-my-in-progress')).toHaveTextContent('4'));
    expect(screen.getByTestId('tile-queue-available')).toHaveTextContent('12');
    expect(screen.getByTestId('tile-overdue')).toHaveTextContent('2');
    expect(screen.getByTestId('tile-decided-this-week')).toHaveTextContent('7');
  });

  it('highlights the overdue tile when > 0 (visual red state)', async () => {
    mockDashboard.mockResolvedValue(baseData({
      stats: {
        my_in_progress: 0, queue_available: 0, overdue: 3,
        decided_this_week: 0, decided_this_month: 0,
      },
    }));
    renderPage();
    await waitFor(() => expect(screen.getByTestId('tile-overdue')).toBeInTheDocument());
    expect(screen.getByTestId('tile-overdue').className).toMatch(/red/);
  });

  it('renders each decision-breakdown row zero-filled when the month is empty', async () => {
    mockDashboard.mockResolvedValue(baseData());
    renderPage();
    await waitFor(() => expect(screen.getByTestId('by-decision-approved')).toHaveTextContent('0'));
    expect(screen.getByTestId('by-decision-rejected')).toHaveTextContent('0');
    expect(screen.getByTestId('by-decision-modifications')).toHaveTextContent('0');
  });

  it('shows the "no cases claimed" empty state when my_in_progress is empty', async () => {
    mockDashboard.mockResolvedValue(baseData());
    renderPage();
    await waitFor(() => expect(screen.getByTestId('my-in-progress-empty')).toBeInTheDocument());
    expect(screen.getByTestId('recent-decisions-empty')).toBeInTheDocument();
  });

  it('lists my in-progress rows with SLA badges and links to /review/:id', async () => {
    mockDashboard.mockResolvedValue(baseData({
      stats: { ...baseData().stats, my_in_progress: 2 },
      my_in_progress: [
        { id: 11, reference: 'JEA-26-1234-0001', service_name_ar: 'مخططات', service_name_en: 'Drawings',
          sla_deadline: '2027-01-01T00:00:00Z', sla_breached: false },
        { id: 12, reference: 'JEA-26-1234-0002', service_name_ar: 'شهادة', service_name_en: 'Cert',
          sla_deadline: '2020-01-01T00:00:00Z', sla_breached: true },
      ],
    }));
    renderPage();

    await waitFor(() => expect(screen.getByTestId('in-progress-11')).toBeInTheDocument());
    expect(screen.getByTestId('in-progress-12')).toBeInTheDocument();
    // Overdue row shows the "متأخرة/overdue" chip.
    expect(screen.getByTestId('in-progress-12').textContent).toMatch(/متأخرة|overdue/);
    // Link goes to /review/{id}.
    expect(screen.getByTestId('in-progress-11').querySelector('a')?.getAttribute('href'))
      .toBe('/review/11');
  });

  it('renders recent decisions with localised decision badges', async () => {
    mockDashboard.mockResolvedValue(baseData({
      recent_decisions: [
        { id: 1, application_id: 5, reference: 'JEA-1', service_name_ar: 'خ', service_name_en: 'S',
          decision: 'approved', created_at: '2026-07-01T10:00:00Z' },
        { id: 2, application_id: 6, reference: 'JEA-2', service_name_ar: 'خ', service_name_en: 'S',
          decision: 'modifications_requested', created_at: '2026-06-30T10:00:00Z' },
      ],
    }));
    renderPage();

    await waitFor(() => expect(screen.getByTestId('recent-1')).toBeInTheDocument());
    expect(screen.getByTestId('recent-2')).toBeInTheDocument();
    expect(screen.getByTestId('recent-1-decision')).toBeInTheDocument();
    // The decision chip contains the localised status.
    expect(screen.getByTestId('recent-2-decision').textContent?.toLowerCase())
      .toMatch(/modifications|تعديل/);
  });

  it('renders the error banner if the API rejects', async () => {
    mockDashboard.mockRejectedValue(new Error('boom'));
    renderPage();
    await waitFor(() => expect(screen.getByTestId('review-dashboard-error')).toBeInTheDocument());
    expect(screen.getByTestId('review-dashboard-error')).toHaveTextContent('boom');
  });

  it('exposes a prominent "Open queue" link', async () => {
    mockDashboard.mockResolvedValue(baseData());
    renderPage();
    await waitFor(() => expect(screen.getByTestId('open-queue-link')).toBeInTheDocument());
    expect(screen.getByTestId('open-queue-link')).toHaveAttribute('href', '/review/queue');
  });
});
