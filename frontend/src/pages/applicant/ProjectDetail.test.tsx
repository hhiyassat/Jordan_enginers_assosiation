import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ProjectDetail } from './ProjectDetail';

const mockProjectGet = vi.fn();
const mockServicesList = vi.fn();
vi.mock('../../api/client', () => ({
  projectsApi: { get: (...a: unknown[]) => mockProjectGet(...a) },
  servicesApi: { list: (...a: unknown[]) => mockServicesList(...a) },
}));

function renderAt(projectId: string) {
  return render(
    <MemoryRouter initialEntries={[`/projects/${projectId}`]}>
      <Routes>
        <Route path="/projects/:projectId" element={<ProjectDetail />} />
      </Routes>
    </MemoryRouter>
  );
}

beforeEach(() => {
  mockProjectGet.mockReset();
  mockServicesList.mockReset();
});

describe('ProjectDetail — hero', () => {
  it('shows the project name once — heading, not breadcrumb', async () => {
    // Regression: breadcrumb used to include the project name AND the <h1>
    // showed it again. Breadcrumb now stops at "مشاريعي".
    mockProjectGet.mockResolvedValue({
      project: { id: 42, name_ar: 'إسكان حسين', name_en: 'Hussein Housing', city: 'عمّان' },
    });
    mockServicesList.mockResolvedValue({ services: [] });

    renderAt('42');
    await waitFor(() => expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument());

    const matches = screen.getAllByText('إسكان حسين');
    expect(matches).toHaveLength(1);
    expect(matches[0].tagName).toBe('H1');

    expect(screen.getByText('الخدمات الإلكترونية')).toBeInTheDocument();
    expect(screen.getByText('مشاريعي')).toBeInTheDocument();
  });
});
