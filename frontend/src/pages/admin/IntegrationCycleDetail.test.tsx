import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { IntegrationCycleDetail } from './IntegrationCycleDetail';
import type { IntegrationCycle } from '../../api/client';

const mockCycle          = vi.fn();
const mockNotifyCodeDone = vi.fn();
vi.mock('../../api/client', () => ({
  integrationApi: {
    cycle:          (...a: unknown[]) => mockCycle(...a),
    notifyCodeDone: (...a: unknown[]) => mockNotifyCodeDone(...a),
  },
}));

function cycle(overrides: Partial<IntegrationCycle> = {}): IntegrationCycle {
  return {
    id: 42,
    cycle_ref: 'C-042',
    service_name: 'Business License',
    requirements_source: 'Nashmi requirements pack v1',
    status: 'requirements_received',
    nashmi_project_id: null,
    requirements_meta: { title: 'BL v1' },
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

function renderAt(id: number) {
  return render(
    <MemoryRouter initialEntries={[`/admin/integration/${id}`]}>
      <Routes>
        <Route path="/admin/integration/:id" element={<IntegrationCycleDetail />} />
      </Routes>
    </MemoryRouter>
  );
}

beforeEach(() => {
  mockCycle.mockReset();
  mockNotifyCodeDone.mockReset();
});

describe('IntegrationCycleDetail — lifecycle + notify-done', () => {
  it('shows the cycle header (service name + reference + Arabic status)', async () => {
    mockCycle.mockResolvedValue({ data: cycle() });
    renderAt(42);
    await waitFor(() => expect(screen.getByText('Business License')).toBeInTheDocument());

    expect(screen.getByText('C-042')).toBeInTheDocument();
    expect(screen.getByText('متطلبات واردة')).toBeInTheDocument();
  });

  it('marks completed lifecycle steps with a ✓ and pending ones with ○', async () => {
    // Requirements arrived + code notified; feedback still pending.
    mockCycle.mockResolvedValue({ data: cycle({
      status: 'code_done',
      requirements_received_at: '2026-07-19T00:00:00Z',
      code_done_notified_at:    '2026-07-19T01:00:00Z',
    })});
    renderAt(42);
    await waitFor(() => expect(screen.getByText('مراحل الدورة')).toBeInTheDocument());

    // Two completed markers, two pending markers.
    expect(screen.getAllByText('✓')).toHaveLength(2);
    expect(screen.getAllByText('○')).toHaveLength(2);
  });

  it('shows the notify-code-done form when status permits', async () => {
    mockCycle.mockResolvedValue({ data: cycle({ status: 'requirements_received' }) });
    renderAt(42);
    await waitFor(() => expect(screen.getByText('إعلام بإكمال الكود')).toBeInTheDocument());

    expect(screen.getByRole('button', { name: /إعلام بإكمال الكود/ })).toBeInTheDocument();
  });

  it('hides the notify form and shows a waiting hint when status=code_done', async () => {
    mockCycle.mockResolvedValue({ data: cycle({ status: 'code_done' }) });
    renderAt(42);
    // Old wording "في انتظار ملاحظات Nashmi" collapsed to the shared status
    // label — code_done → "الكود جاهز". The badge at the top ALSO shows this
    // string; the hint hides the notify button so we assert on that state.
    await waitFor(() => expect(screen.getAllByText(/الكود جاهز/).length).toBeGreaterThan(0));
    expect(screen.queryByRole('button', { name: /إعلام بإكمال/ })).toBeNull();
  });

  it('shows a "closed" completion hint when status=closed', async () => {
    mockCycle.mockResolvedValue({ data: cycle({ status: 'closed' }) });
    renderAt(42);
    // Old wording "الدورة مكتملة ومغلقة" collapsed to the shared status
    // label — closed → "مغلق" (also in the top badge; assert presence,
    // not uniqueness).
    await waitFor(() => expect(screen.getAllByText(/مغلق/).length).toBeGreaterThan(0));
  });

  it('parses multi-line inputs and posts the payload when notifying Nashmi', async () => {
    mockCycle.mockResolvedValue({ data: cycle() });
    mockNotifyCodeDone.mockResolvedValue({ message: 'ok' });
    // Second call after notify triggers a refresh — we resolve that too.
    renderAt(42);
    await waitFor(() => expect(screen.getByText('إعلام بإكمال الكود')).toBeInTheDocument());

    await userEvent.type(screen.getByPlaceholderText('abc123f'), 'deadbeef');
    // userEvent.type reads {…} as keyboard modifier syntax; use a raw
    // paste-style helper to keep the literal braces if we ever need them.
    // Here we just avoid braces in the test input.
    await userEvent.type(screen.getByPlaceholderText(/POST \/api\/v1\/services/), 'POST /api/v1/services\nGET /api/v1/services/foo');
    await userEvent.type(screen.getByPlaceholderText(/ServiceList/), 'ServiceList\nApply');
    await userEvent.type(screen.getByPlaceholderText(/applications, documents/), 'applications, reviews');
    await userEvent.click(screen.getByRole('button', { name: /إعلام بإكمال الكود/ }));

    await waitFor(() => expect(mockNotifyCodeDone).toHaveBeenCalledTimes(1));
    const [id, payload] = mockNotifyCodeDone.mock.calls[0];
    expect(id).toBe(42);
    expect(payload).toMatchObject({
      git_branch: 'main',           // default kept
      git_commit: 'deadbeef',
      api_endpoints:  ['POST /api/v1/services', 'GET /api/v1/services/foo'],
      frontend_pages: ['ServiceList', 'Apply'],
      db_tables:      ['applications', 'reviews'],
    });
  });

  it('shows the Arabic error banner when notify fails', async () => {
    mockCycle.mockResolvedValue({ data: cycle() });
    mockNotifyCodeDone.mockRejectedValue(new Error('502 nashmi timeout'));
    renderAt(42);
    await waitFor(() => expect(screen.getByRole('button', { name: /إعلام بإكمال/ })).toBeInTheDocument());

    await userEvent.click(screen.getByRole('button', { name: /إعلام بإكمال/ }));
    await waitFor(() => expect(screen.getByText(/502 nashmi timeout/)).toBeInTheDocument());
  });

  it('renders the requirements meta as pretty JSON', async () => {
    mockCycle.mockResolvedValue({ data: cycle({ requirements_meta: { title: 'BL v1', complexity: 3 } }) });
    renderAt(42);
    // "بيانات المتطلبات" heading was tied to the un-i18n'd panel and
    // still remains hardcoded (deep power-user internal section
    // deliberately deferred to a follow-up).
    await waitFor(() => expect(screen.getByText('بيانات المتطلبات')).toBeInTheDocument());
    // JSON.stringify puts every key on its own line at 2-space indent.
    const pre = document.querySelector('pre')!;
    expect(pre.textContent).toContain('"title": "BL v1"');
    expect(pre.textContent).toContain('"complexity": 3');
  });
});
