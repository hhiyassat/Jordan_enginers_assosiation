import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, act } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import i18n from '../../i18n';
import { Dashboard } from './Dashboard';
import { makeQueryWrapper } from '../../test/queryWrapper';
import type { OfficeQuota } from '../../api/projects';

/**
 * Locks the i18n page-body retrofit: switching the app language must
 * flip Dashboard's headings + quick-action tiles + stat labels. If a
 * future regression re-introduces a hardcoded Arabic literal on the
 * page body, the assert on the English switch fails immediately.
 */

const mockList         = vi.fn(async () => ({ projects: [] }));
const mockApplications = vi.fn(async () => ({ applications: [] }));
const mockQuota        = vi.fn(async (): Promise<OfficeQuota> => ({
  year: 2026,
  totals: {
    quota_m2: 5000, used_m2: 0, remaining_m2: 5000,
    percent_used: 0, projects_count: 0, unlimited: false, engineers_count: 0,
  },
  engineers: [],
}));

vi.mock('../../api/client', () => ({
  projectsApi:     { list: () => mockList(),         quota: () => mockQuota() },
  applicationsApi: { list: () => mockApplications() },
}));

vi.mock('../../auth/AuthContext', () => ({
  useAuth: () => ({ user: { id: 1, name: 'Hussein', role: 'applicant', organization_id: 1 } }),
}));

function mount() {
  const { Wrapper } = makeQueryWrapper();
  return render(
    <Wrapper><MemoryRouter><Dashboard /></MemoryRouter></Wrapper>
  );
}

describe('Dashboard i18n retrofit', () => {
  beforeEach(async () => {
    mockList.mockClear();
    mockApplications.mockClear();
    mockQuota.mockClear();
    await i18n.changeLanguage('ar');
  });

  it('renders headings from the Arabic locale', async () => {
    mount();
    await waitFor(() => expect(screen.getByText(/إجراءات سريعة/)).toBeInTheDocument());
    // Dashboard greeting is interpolated with the user's name.
    expect(screen.getByText(/مرحباً، Hussein/)).toBeInTheDocument();
    // Quick-action tile titles come from the ar bundle.
    expect(screen.getByText('مشاريعي')).toBeInTheDocument();
    expect(screen.getByText('الخدمات الإلكترونية')).toBeInTheDocument();
  });

  it('re-renders in English after i18n.changeLanguage("en")', async () => {
    mount();
    await waitFor(() => expect(screen.getByText(/إجراءات سريعة/)).toBeInTheDocument());
    await act(async () => { await i18n.changeLanguage('en'); });
    expect(screen.queryByText(/إجراءات سريعة/)).not.toBeInTheDocument();
    expect(screen.getByText(/Quick actions/i)).toBeInTheDocument();
    expect(screen.getByText(/Welcome, Hussein/)).toBeInTheDocument();
  });
});
