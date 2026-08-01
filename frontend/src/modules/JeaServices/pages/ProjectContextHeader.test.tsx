import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { ProjectContextHeader } from './ProjectContextHeader';
import type { Project } from '../../../types';

function project(overrides: Partial<Project>): Project {
  return {
    id: 42, organization_id: 1, owner_user_id: 4,
    name_ar: 'إسكان حسين', name_en: 'Hussein Housing',
    status: 'active', created_at: '2026-07-19T00:00:00Z', updated_at: '2026-07-19T00:00:00Z',
    ...overrides,
  } as Project;
}

describe('ProjectContextHeader', () => {
  it('renders every populated project field as a read-only row', () => {
    render(<ProjectContextHeader project={project({
      contract_no: 'C-100', request_no: 'R-200', city: 'عمّان', area_m2: 350, type: 'سكني',
    })} />);

    // JORD-89: name is now localised (one row, not two). Under the
    // default Arabic test locale the name_ar wins; name_en shows
    // only when the app is in English. We assert on the visible
    // side so the test tracks real user experience.
    expect(screen.getByText('إسكان حسين')).toBeInTheDocument();
    expect(screen.queryByText('Hussein Housing')).toBeNull();
    expect(screen.getByText('C-100')).toBeInTheDocument();
    expect(screen.getByText('R-200')).toBeInTheDocument();
    expect(screen.getByText('عمّان')).toBeInTheDocument();
    expect(screen.getByText('350')).toBeInTheDocument();
    expect(screen.getByText('سكني')).toBeInTheDocument();
  });

  it('shows the "للقراءة فقط" badge so the applicant knows they can\'t edit', () => {
    render(<ProjectContextHeader project={project({})} />);
    expect(screen.getByText('للقراءة فقط')).toBeInTheDocument();
  });

  it('hides label rows whose value is null / undefined / empty', () => {
    render(<ProjectContextHeader project={project({
      contract_no: null, request_no: undefined, city: '', area_m2: null,
    })} />);
    // The row LABELS should be absent for empty values — no dangling
    // "رقم العقد: —" rendering.
    expect(screen.queryByText('رقم العقد')).toBeNull();
    expect(screen.queryByText('رقم الطلب')).toBeNull();
    expect(screen.queryByText('المدينة')).toBeNull();
    expect(screen.queryByText('المساحة (م²)')).toBeNull();
    // Populated field still renders.
    expect(screen.getByText('إسكان حسين')).toBeInTheDocument();
  });

  it('exposes a region role with the read-only Arabic label for screen readers', () => {
    render(<ProjectContextHeader project={project({})} />);
    expect(screen.getByRole('region', { name: /معلومات المشروع/ })).toBeInTheDocument();
  });
});
