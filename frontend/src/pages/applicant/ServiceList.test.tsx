import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import type { ServiceDefinition } from '../../types';
import { ServiceList } from './ServiceList';
import { makeQueryWrapper } from '../../test/queryWrapper';

const mockList = vi.fn();
// After JORD-22, useServices() imports servicesApi from ./services
// directly, not from the client barrel. Mock the domain module.
vi.mock('../../api/services', () => ({
  servicesApi: { list: (...a: unknown[]) => mockList(...a) },
}));

function tile(overrides: Partial<ServiceDefinition> & Pick<ServiceDefinition, 'id' | 'code' | 'name_ar' | 'name_en'>): ServiceDefinition {
  return {
    parent_code: null,
    currency: 'JOD',
    ...overrides,
  } as ServiceDefinition;
}

beforeEach(() => {
  mockList.mockReset();
});

describe('ServiceList — tile ordering', () => {
  it('pins مشاريعي (JEA-PROJ) first and استطلاع الموقع (JEA-SURV) second', async () => {
    // Deliberately reverse the natural order to prove the priority sort works.
    mockList.mockResolvedValue({
      services: [
        tile({ id: 1, code: 'JEA-CERT',  name_ar: 'الشهادات',                   name_en: 'Certificates' }),
        tile({ id: 2, code: 'JEA-MISC',  name_ar: 'خدمات أخرى',                 name_en: 'Miscellaneous' }),
        tile({ id: 3, code: 'JEA-SURV',  name_ar: 'استطلاع الموقع',              name_en: 'Site Survey' }),
        tile({ id: 4, code: 'JEA-DEC',   name_ar: 'قرارات هيئة المكاتب',         name_en: 'Board Decisions' }),
        tile({ id: 5, code: 'JEA-PROJ',  name_ar: 'مشاريعي',                     name_en: 'My Projects' }),
        tile({ id: 6, code: 'JEA-FIN',   name_ar: 'الخدمات المالية',             name_en: 'Financial' }),
        tile({ id: 7, code: 'JEA-ENG',   name_ar: 'المهندسون في المكاتب',        name_en: 'Engineers' }),
      ],
    });

    const { Wrapper } = makeQueryWrapper();
    render(
      <Wrapper>
        <MemoryRouter initialEntries={['/services']}>
          <ServiceList />
        </MemoryRouter>
      </Wrapper>
    );

    // Wait for the tiles to be visible before measuring order.
    await waitFor(() => expect(screen.getByText('مشاريعي')).toBeInTheDocument());

    // Pick a heading present on every tile to derive the DOM render order.
    const headingsInOrder = Array.from(document.querySelectorAll('h3'))
      .map(h => h.textContent?.trim() ?? '');

    // First heading should be مشاريعي, second should be استطلاع الموقع.
    expect(headingsInOrder[0]).toBe('مشاريعي');
    expect(headingsInOrder[1]).toBe('استطلاع الموقع');
  });

  it('preserves incoming order for non-priority tiles', async () => {
    mockList.mockResolvedValue({
      services: [
        tile({ id: 10, code: 'JEA-CERT', name_ar: 'الشهادات',       name_en: 'C' }),
        tile({ id: 11, code: 'JEA-MISC', name_ar: 'خدمات أخرى',      name_en: 'M' }),
        tile({ id: 12, code: 'JEA-DEC',  name_ar: 'قرارات الهيئة',   name_en: 'D' }),
      ],
    });

    const { Wrapper } = makeQueryWrapper();
    render(
      <Wrapper>
        <MemoryRouter initialEntries={['/services']}>
          <ServiceList />
        </MemoryRouter>
      </Wrapper>
    );
    await waitFor(() => expect(screen.getByText('الشهادات')).toBeInTheDocument());

    const headingsInOrder = Array.from(document.querySelectorAll('h3'))
      .map(h => h.textContent?.trim() ?? '');

    expect(headingsInOrder).toEqual(['الشهادات', 'خدمات أخرى', 'قرارات الهيئة']);
  });
});
