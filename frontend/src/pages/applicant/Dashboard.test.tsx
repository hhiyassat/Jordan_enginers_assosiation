import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { Dashboard } from './Dashboard';
import type { Application, Project, User } from '../../types';
import type { OfficeQuota } from '../../api/client';

const mockList         = vi.fn();
const mockQuota        = vi.fn();
const mockApplications = vi.fn();

vi.mock('../../api/client', () => ({
  projectsApi:     { list: () => mockList(), quota: () => mockQuota() },
  applicationsApi: { list: () => mockApplications() },
}));

let mockUser: User | null = null;
vi.mock('../../auth/AuthContext', () => ({
  useAuth: () => ({ user: mockUser, logout: vi.fn(), token: 'x', login: vi.fn() }),
}));

function project(overrides: Partial<Project> = {}): Project {
  return {
    id: 1, organization_id: 1, owner_user_id: 4,
    name_ar: 'مشروع', name_en: 'Project',
    status: 'active', city: 'عمّان', area_m2: 100, type: 'سكني',
    created_at: '2026-07-19T00:00:00Z', updated_at: '2026-07-19T00:00:00Z',
    ...overrides,
  } as Project;
}

function app(overrides: Partial<Application>): Application {
  return {
    id: 1, reference_number: 'A-1',
    status: 'submitted', fee_amount: 0, payment_status: 'waived',
    created_at: '2026-07-19T00:00:00Z',
    ...overrides,
  } as Application;
}

const emptyQuota: OfficeQuota = {
  year: 2026,
  totals: {
    quota_m2: 5000, used_m2: 0, remaining_m2: 5000,
    percent_used: 0, projects_count: 0, unlimited: false, engineers_count: 0,
  },
  engineers: [],
};

beforeEach(() => {
  mockList.mockReset();
  mockQuota.mockReset();
  mockApplications.mockReset();
  mockUser = { id: 4, name: 'حسين', email: 'h@t.esp', role: 'applicant', organization_id: 1 };
});

function renderPage() {
  return render(<MemoryRouter><Dashboard /></MemoryRouter>);
}

describe('Dashboard — applicant landing', () => {
  it('greets the signed-in user by name', async () => {
    mockList.mockResolvedValue({ projects: [] });
    mockApplications.mockResolvedValue({ applications: [] });
    mockQuota.mockResolvedValue(emptyQuota);
    renderPage();
    await waitFor(() => expect(screen.getByText(/مرحباً، حسين/)).toBeInTheDocument());
  });

  it('shows counters for projects, active applications, and issued certificates', async () => {
    mockList.mockResolvedValue({ projects: [project({ id: 1 }), project({ id: 2 })] });
    mockApplications.mockResolvedValue({ applications: [
      app({ id: 1, status: 'submitted' }),                // active
      app({ id: 2, status: 'under_review' }),             // active
      app({ id: 3, status: 'certificate_issued' }),       // certificate
      app({ id: 4, status: 'rejected' }),                 // neither
    ]});
    mockQuota.mockResolvedValue(emptyQuota);
    renderPage();

    await waitFor(() => expect(screen.getByText('المشاريع')).toBeInTheDocument());
    // Two projects, two active applications, one certificate.
    const stats = screen.getAllByText(/^\d+$/).map(el => el.textContent);
    expect(stats).toEqual(expect.arrayContaining(['2', '2', '1']));
  });

  it('shows an empty-state helper when the applicant has no projects yet', async () => {
    mockList.mockResolvedValue({ projects: [] });
    mockApplications.mockResolvedValue({ applications: [] });
    mockQuota.mockResolvedValue(emptyQuota);
    renderPage();
    await waitFor(() => expect(screen.getByText('لا توجد مشاريع بعد')).toBeInTheDocument());
  });

  it('renders up to 3 recent projects (top of the list, not the tail)', async () => {
    mockList.mockResolvedValue({ projects: [
      project({ id: 1, name_ar: 'أول' }),
      project({ id: 2, name_ar: 'ثاني' }),
      project({ id: 3, name_ar: 'ثالث' }),
      project({ id: 4, name_ar: 'رابع' }),
    ]});
    mockApplications.mockResolvedValue({ applications: [] });
    mockQuota.mockResolvedValue(emptyQuota);
    renderPage();
    await waitFor(() => expect(screen.getByText('أول')).toBeInTheDocument());
    expect(screen.getByText('ثاني')).toBeInTheDocument();
    expect(screen.getByText('ثالث')).toBeInTheDocument();
    expect(screen.queryByText('رابع')).toBeNull();
  });

  it('shows the per-engineer quota block only when the quota has engineer rows', async () => {
    mockList.mockResolvedValue({ projects: [] });
    mockApplications.mockResolvedValue({ applications: [] });
    mockQuota.mockResolvedValue({
      ...emptyQuota,
      engineers: [
        { engineer_id: 1, engineer_name_ar: 'م. أحمد', year: 2026, quota_m2: 500, used_m2: 100, remaining_m2: 400 },
      ],
    });
    renderPage();
    await waitFor(() => expect(screen.getByText('رصيد كل مهندس')).toBeInTheDocument());
    expect(screen.getByText('م. أحمد')).toBeInTheDocument();
  });

  it('hides the per-engineer block when there are no engineers', async () => {
    mockList.mockResolvedValue({ projects: [] });
    mockApplications.mockResolvedValue({ applications: [] });
    mockQuota.mockResolvedValue(emptyQuota); // engineers: []
    renderPage();
    await waitFor(() => expect(screen.getByText('إجراءات سريعة')).toBeInTheDocument());
    expect(screen.queryByText('رصيد كل مهندس')).toBeNull();
  });

  it('surfaces the quota error inside the QuotaCard when quota fails', async () => {
    mockList.mockResolvedValue({ projects: [] });
    mockApplications.mockResolvedValue({ applications: [] });
    mockQuota.mockRejectedValue(new Error('quota fetch broke'));
    renderPage();
    await waitFor(() => expect(screen.getByText(/quota fetch broke/)).toBeInTheDocument());
  });

  it('renders the three quick-action tiles', async () => {
    mockList.mockResolvedValue({ projects: [] });
    mockApplications.mockResolvedValue({ applications: [] });
    mockQuota.mockResolvedValue(emptyQuota);
    renderPage();
    await waitFor(() => expect(screen.getByText('إجراءات سريعة')).toBeInTheDocument());

    // Three tiles: My Projects, E-Services, My Requests.
    expect(screen.getByRole('link', { name: /مشاريعي/ })).toHaveAttribute('href', '/projects');
    expect(screen.getByRole('link', { name: /الخدمات الإلكترونية/ })).toHaveAttribute('href', '/services');
    expect(screen.getByRole('link', { name: /طلباتي/ })).toHaveAttribute('href', '/my-applications');
  });
});
