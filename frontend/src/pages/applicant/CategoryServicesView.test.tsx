import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import type { ServiceDefinition } from '../../types';
import { CategoryServicesView } from './CategoryServicesView';

/* ── API mock ──────────────────────────────────────────────────────── */
const mockList = vi.fn();
vi.mock('../../api/client', () => ({
  servicesApi: { list: (...a: unknown[]) => mockList(...a) },
}));

/* Helpers ───────────────────────────────────────────────────────────── */
function svc(overrides: Partial<ServiceDefinition> & Pick<ServiceDefinition, 'id' | 'code' | 'parent_code' | 'name_ar'>): ServiceDefinition {
  return {
    // Use code as English name so name_en never collides with name_ar and
    // getByText(name_ar) resolves to exactly one <h3>.
    name_en: overrides.code,
    currency: 'JOD',
    ...overrides,
  } as ServiceDefinition;
}

function renderAt(categoryCode: string) {
  return render(
    <MemoryRouter initialEntries={[`/services/${categoryCode}`]}>
      <Routes>
        <Route path="/services/:categoryCode" element={<CategoryServicesView />} />
      </Routes>
    </MemoryRouter>
  );
}

beforeEach(() => {
  mockList.mockReset();
});

describe('CategoryServicesView — subcategory grouping', () => {
  it('renders section headers when at least one child has a subcategory', async () => {
    mockList.mockResolvedValue({
      services: [
        svc({ id: 100, code: 'JEA-SURV', parent_code: null, name_ar: 'استطلاع الموقع' }),
        svc({ id: 1, code: 'SRV-001', parent_code: 'JEA-SURV', subcategory_ar: 'استطلاع الموقع', subcategory_en: 'Site Survey', name_ar: 'A' }),
        svc({ id: 2, code: 'SRV-002', parent_code: 'JEA-SURV', subcategory_ar: 'استطلاع الموقع', subcategory_en: 'Site Survey', name_ar: 'B' }),
        svc({ id: 3, code: 'SRV-008', parent_code: 'JEA-SURV', subcategory_ar: 'فحص المواد للأبنية', subcategory_en: 'Material Testing', name_ar: 'C' }),
        svc({ id: 4, code: 'SRV-007', parent_code: 'JEA-SURV', subcategory_ar: 'الحفريات', subcategory_en: 'Excavations', name_ar: 'D' }),
      ],
    });

    renderAt('JEA-SURV');
    await waitFor(() => expect(screen.getByText('استطلاع الموقع', { selector: 'h3' })).toBeInTheDocument());

    // Each subcategory gets its own <h3> header.
    expect(screen.getByText('استطلاع الموقع', { selector: 'h3' })).toBeInTheDocument();
    expect(screen.getByText('فحص المواد للأبنية', { selector: 'h3' })).toBeInTheDocument();
    expect(screen.getByText('الحفريات', { selector: 'h3' })).toBeInTheDocument();

    // Header shows English label + AR count.
    expect(screen.getByText('Site Survey')).toBeInTheDocument();
    expect(screen.getByText('Material Testing')).toBeInTheDocument();

    // Each section is a landmark with aria-labelledby pointing at its h3.
    const sections = document.querySelectorAll('section[aria-labelledby]');
    expect(sections.length).toBe(3);
  });

  it('groups cards into the correct section', async () => {
    mockList.mockResolvedValue({
      services: [
        svc({ id: 100, code: 'JEA-SURV', parent_code: null, name_ar: 'استطلاع الموقع' }),
        svc({ id: 1, code: 'SRV-001', parent_code: 'JEA-SURV', subcategory_ar: 'استطلاع الموقع', subcategory_en: 'Site Survey', name_ar: 'AAA' }),
        svc({ id: 3, code: 'SRV-008', parent_code: 'JEA-SURV', subcategory_ar: 'فحص المواد للأبنية', subcategory_en: 'Material Testing', name_ar: 'BBB' }),
      ],
    });

    renderAt('JEA-SURV');
    await waitFor(() => expect(screen.getByText('AAA')).toBeInTheDocument());

    const sections = document.querySelectorAll('section[aria-labelledby]');
    // First section (استطلاع الموقع) should contain AAA, not BBB.
    const first = sections[0] as HTMLElement;
    expect(within(first).getByText('AAA')).toBeInTheDocument();
    expect(within(first).queryByText('BBB')).toBeNull();
  });

  it('falls back to a flat grid when no subcategories are present', async () => {
    mockList.mockResolvedValue({
      services: [
        svc({ id: 100, code: 'JEA-CERT', parent_code: null, name_ar: 'الشهادات' }),
        svc({ id: 1, code: 'CERT-001', parent_code: 'JEA-CERT', name_ar: 'شهادة المطابقة' }),
        svc({ id: 2, code: 'CERT-002', parent_code: 'JEA-CERT', name_ar: 'سلامة المنشأ' }),
      ],
    });

    renderAt('JEA-CERT');
    await waitFor(() => expect(screen.getByText('شهادة المطابقة')).toBeInTheDocument());

    // No <section> landmarks in the flat variant.
    expect(document.querySelectorAll('section[aria-labelledby]').length).toBe(0);
  });
});
