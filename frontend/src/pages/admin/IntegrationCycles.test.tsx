import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { IntegrationCycles } from './IntegrationCycles';
import type { IntegrationCycle } from '../../api/client';

const mockCycles = vi.fn();
vi.mock('../../api/client', () => ({
  integrationApi: { cycles: () => mockCycles() },
}));

function cycle(overrides: Partial<IntegrationCycle> = {}): IntegrationCycle {
  return {
    id: 1,
    cycle_ref: 'C-001',
    service_name: 'Business License',
    requirements_source: 'Nashmi requirements',
    status: 'requirements_received',
    nashmi_project_id: null,
    requirements_meta: null,
    code_summary: null,
    feedback: null,
    notes: null,
    requirements_received_at: '2026-07-19T00:00:00Z',
    code_done_notified_at: null,
    feedback_received_at: null,
    created_at: '2026-07-19T00:00:00Z',
    updated_at: '2026-07-19T00:00:00Z',
    ...overrides,
  };
}

beforeEach(() => { mockCycles.mockReset(); });

describe('IntegrationCycles', () => {
  it('renders each cycle with reference + service name + Arabic status badge', async () => {
    mockCycles.mockResolvedValue({ data: [
      cycle({ id: 1, cycle_ref: 'C-001', service_name: 'Service A', status: 'requirements_received' }),
      cycle({ id: 2, cycle_ref: 'C-002', service_name: 'Service B', status: 'closed' }),
    ]});
    render(<MemoryRouter><IntegrationCycles /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('C-001')).toBeInTheDocument());

    expect(screen.getByText('Service A')).toBeInTheDocument();
    expect(screen.getByText('Service B')).toBeInTheDocument();
    // Bilingual status: requirements_received → متطلبات واردة, closed → مغلق
    expect(screen.getByText('متطلبات واردة')).toBeInTheDocument();
    expect(screen.getByText('مغلق')).toBeInTheDocument();
  });

  it('shows the empty state with the integration hint when zero cycles exist', async () => {
    mockCycles.mockResolvedValue({ data: [] });
    render(<MemoryRouter><IntegrationCycles /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('لا توجد دورات تكامل بعد')).toBeInTheDocument());
    // Hint tells the operator which endpoint Nashmi POSTs to. Header
    // integration-points panel also mentions it, so match "at least one".
    expect(screen.getAllByText(/receive-requirements/).length).toBeGreaterThan(0);
  });

  it('surfaces API errors above the list', async () => {
    mockCycles.mockRejectedValue(new Error('502 upstream Nashmi timeout'));
    render(<MemoryRouter><IntegrationCycles /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText(/502 upstream Nashmi timeout/)).toBeInTheDocument());
  });

  it('renders a Nashmi project id badge when the cycle has been pushed', async () => {
    // Regression pin: nashmi_project_id is populated after a push-to-Nashmi
    // step; the purple pill helps operators know a cycle has been
    // upstreamed already.
    mockCycles.mockResolvedValue({ data: [cycle({ nashmi_project_id: 5152 })] });
    render(<MemoryRouter><IntegrationCycles /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText(/Nashmi #5152/)).toBeInTheDocument());
  });

  it('does not render a Nashmi project id badge when null', async () => {
    mockCycles.mockResolvedValue({ data: [cycle({ nashmi_project_id: null })] });
    render(<MemoryRouter><IntegrationCycles /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('C-001')).toBeInTheDocument());
    expect(screen.queryByText(/Nashmi #/)).toBeNull();
  });

  it('shows the count of cycles in the header', async () => {
    mockCycles.mockResolvedValue({ data: [cycle({ id: 1 }), cycle({ id: 2 }), cycle({ id: 3 })] });
    render(<MemoryRouter><IntegrationCycles /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('3 دورة')).toBeInTheDocument());
  });
});
