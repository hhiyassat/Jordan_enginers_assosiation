import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { AdminApplications } from './AdminApplications';
import { makeQueryWrapper } from '../../../test/queryWrapper';
import type { Paginated } from '../../../api/admin';
import type { Application } from '../../../types';

/**
 * JORD-33 + JORD-35: pins the paginated + searchable admin listing.
 *
 * The hook lands on ../../api/admin (post JORD-22 split), and every
 * filter change is a new query key so React Query re-fetches. These
 * tests exercise: initial render, search debounce, status filter,
 * per-page selector, and prev/next paging.
 */

const mockPaginated = vi.fn();
// Workstream 6: api/admin.ts split into api/platform/admin.ts +
// api/jea/admin.ts. usePaginatedAdminApplications hits
// `platformAdminApi.allApplicationsPaginated` directly, so we mock
// the platform-side source (not the legacy barrel).
vi.mock('../../../api/platform/admin', async () => {
  const actual = await vi.importActual<typeof import('../../../api/platform/admin')>('../../../api/platform/admin');
  return {
    ...actual,
    platformAdminApi: {
      ...actual.platformAdminApi,
      allApplicationsPaginated: (...a: unknown[]) => mockPaginated(...a),
    },
  };
});

function makeRow(overrides: Partial<Application>): Application {
  return {
    id: 1, reference_number: 'A-100',
    status: 'submitted', current_stage: 's',
    fee_amount: 0, payment_status: 'waived',
    created_at: '2026-07-19T00:00:00Z',
    applicant: { id: 1, name: 'حسين', email: 'h@t.esp', role: 'applicant', organization_id: 1 },
    service_definition: { id: 1, code: 'DRW-P-004', name_ar: 'مخططات الهدم', name_en: 'Demolition', currency: 'JOD' },
    ...overrides,
  } as unknown as Application;
}

function page(rows: Application[], overrides: Partial<Paginated<Application>> = {}): Paginated<Application> {
  return {
    data: rows,
    current_page: 1,
    per_page: 20,
    total: rows.length,
    last_page: 1,
    from: rows.length ? 1 : null,
    to: rows.length ? rows.length : null,
    ...overrides,
  };
}

function renderPage() {
  const { Wrapper } = makeQueryWrapper();
  return render(<Wrapper><MemoryRouter><AdminApplications /></MemoryRouter></Wrapper>);
}

beforeEach(() => { mockPaginated.mockReset(); });

describe('AdminApplications — JORD-35', () => {
  it('renders the initial page and asks for page=1, per_page=20', async () => {
    mockPaginated.mockResolvedValue(page([makeRow({ id: 1, reference_number: 'A-1' })]));
    renderPage();
    await waitFor(() => expect(screen.getByText('A-1')).toBeInTheDocument());
    expect(mockPaginated).toHaveBeenLastCalledWith(
      expect.objectContaining({ page: 1, per_page: 20 })
    );
  });

  it('debounces the search input and fires with the trimmed needle', async () => {
    mockPaginated.mockResolvedValue(page([]));
    renderPage();
    // Wait for initial call
    await waitFor(() => expect(mockPaginated).toHaveBeenCalled());
    mockPaginated.mockClear();

    const input = screen.getByRole('searchbox', { name: /بحث في الطلبات/ });
    await userEvent.type(input, 'حسين');

    // With 300ms debounce, wait past that threshold.
    await act(() => new Promise(r => setTimeout(r, 400)));
    // At least one call should carry q === 'حسين'
    const calls = mockPaginated.mock.calls.map(c => c[0]);
    expect(calls.some((f: { q?: string }) => f.q === 'حسين')).toBe(true);
  });

  it('applies the status filter and resets page to 1', async () => {
    mockPaginated.mockResolvedValue(page([makeRow({ status: 'approved' })]));
    renderPage();
    await waitFor(() => expect(mockPaginated).toHaveBeenCalled());
    mockPaginated.mockClear();

    await userEvent.selectOptions(
      screen.getByRole('combobox', { name: /فلترة حسب الحالة/ }),
      'approved'
    );
    await waitFor(() => expect(mockPaginated).toHaveBeenCalledWith(
      expect.objectContaining({ status: 'approved', page: 1 })
    ));
  });

  it('advances pages via the next-page button', async () => {
    mockPaginated.mockResolvedValue(page(
      [makeRow({ id: 1, reference_number: 'P1' })],
      { current_page: 1, last_page: 3, total: 45, per_page: 20, from: 1, to: 20 }
    ));
    renderPage();
    await waitFor(() => expect(screen.getByText('P1')).toBeInTheDocument());

    // Reset so we can assert the next call
    mockPaginated.mockClear();
    mockPaginated.mockResolvedValue(page(
      [makeRow({ id: 2, reference_number: 'P2' })],
      { current_page: 2, last_page: 3, total: 45, per_page: 20, from: 21, to: 40 }
    ));

    await userEvent.click(screen.getByRole('button', { name: /الصفحة التالية/ }));
    await waitFor(() => expect(mockPaginated).toHaveBeenCalledWith(
      expect.objectContaining({ page: 2 })
    ));
  });

  it('shows an empty-state message when the API returns zero rows', async () => {
    mockPaginated.mockResolvedValue(page([]));
    renderPage();
    await waitFor(() => expect(screen.getByText(/لا توجد نتائج/)).toBeInTheDocument());
  });

  it('surfaces the API error message when the query rejects', async () => {
    mockPaginated.mockRejectedValue(new Error('boom'));
    renderPage();
    await waitFor(() => expect(screen.getByText(/boom/)).toBeInTheDocument());
  });
});
