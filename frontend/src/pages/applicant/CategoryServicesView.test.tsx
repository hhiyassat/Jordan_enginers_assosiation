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
    // Parent name is DIFFERENT from every subcategory so no header is suppressed.
    mockList.mockResolvedValue({
      services: [
        svc({ id: 100, code: 'PARENT', parent_code: null, name_ar: 'الفئة الأم' }),
        svc({ id: 1, code: 'S-001', parent_code: 'PARENT', subcategory_ar: 'المجموعة الأولى',  subcategory_en: 'Group One',   name_ar: 'A' }),
        svc({ id: 2, code: 'S-002', parent_code: 'PARENT', subcategory_ar: 'المجموعة الأولى',  subcategory_en: 'Group One',   name_ar: 'B' }),
        svc({ id: 3, code: 'S-003', parent_code: 'PARENT', subcategory_ar: 'المجموعة الثانية', subcategory_en: 'Group Two',   name_ar: 'C' }),
        svc({ id: 4, code: 'S-004', parent_code: 'PARENT', subcategory_ar: 'المجموعة الثالثة', subcategory_en: 'Group Three', name_ar: 'D' }),
      ],
    });

    renderAt('PARENT');
    await waitFor(() => expect(screen.getByText('المجموعة الأولى', { selector: 'h3' })).toBeInTheDocument());

    expect(screen.getByText('المجموعة الأولى',  { selector: 'h3' })).toBeInTheDocument();
    expect(screen.getByText('المجموعة الثانية', { selector: 'h3' })).toBeInTheDocument();
    expect(screen.getByText('المجموعة الثالثة', { selector: 'h3' })).toBeInTheDocument();

    expect(screen.getByText('Group One')).toBeInTheDocument();
    expect(screen.getByText('Group Two')).toBeInTheDocument();

    const sections = document.querySelectorAll('section[aria-labelledby]');
    expect(sections.length).toBe(3);
  });

  it('groups cards into the correct section', async () => {
    mockList.mockResolvedValue({
      services: [
        svc({ id: 100, code: 'PARENT', parent_code: null, name_ar: 'الفئة الأم' }),
        svc({ id: 1, code: 'S-001', parent_code: 'PARENT', subcategory_ar: 'المجموعة الأولى', subcategory_en: 'Group One',    name_ar: 'AAA' }),
        svc({ id: 3, code: 'S-002', parent_code: 'PARENT', subcategory_ar: 'المجموعة الثانية', subcategory_en: 'Group Two',    name_ar: 'BBB' }),
      ],
    });

    renderAt('PARENT');
    await waitFor(() => expect(screen.getByText('AAA')).toBeInTheDocument());

    const sections = document.querySelectorAll('section[aria-labelledby]');
    const first = sections[0] as HTMLElement;
    expect(within(first).getByText('AAA')).toBeInTheDocument();
    expect(within(first).queryByText('BBB')).toBeNull();
  });

  it('suppresses the header of a subcategory that duplicates the parent name', async () => {
    mockList.mockResolvedValue({
      services: [
        // Parent tile whose name_ar is استطلاع الموقع.
        svc({ id: 100, code: 'JEA-SURV', parent_code: null, name_ar: 'استطلاع الموقع' }),
        // Main group shares the parent's name — its header must NOT render.
        svc({ id: 1, code: 'SRV-A', parent_code: 'JEA-SURV', subcategory_ar: 'استطلاع الموقع', subcategory_en: 'Site Survey', name_ar: 'AAA' }),
        // Distinct group — its header must render.
        svc({ id: 2, code: 'SRV-B', parent_code: 'JEA-SURV', subcategory_ar: 'فحص المواد للأبنية', subcategory_en: 'Material Testing', name_ar: 'BBB' }),
      ],
    });

    renderAt('JEA-SURV');
    await waitFor(() => expect(screen.getByText('AAA')).toBeInTheDocument());

    // Only the non-duplicate subcategory should have a visible <h3> header.
    const h3s = Array.from(document.querySelectorAll('h3')).map(el => el.textContent);
    expect(h3s).toContain('فحص المواد للأبنية');
    // Parent-duplicate header must NOT appear as an <h3>.
    expect(h3s.filter(t => t === 'استطلاع الموقع')).toHaveLength(0);

    // Both sections still exist as landmarks (aria-label OR aria-labelledby).
    const sections = document.querySelectorAll('section[aria-labelledby], section[aria-label]');
    expect(sections.length).toBe(2);
  });

  it('shows the parent name once — not in the crumb AND the heading', async () => {
    // Regression: earlier the breadcrumb crumb and the info-block <h2> both
    // rendered category.name_ar, so استطلاع الموقع appeared twice at the top
    // of the page. The breadcrumb now stops at the parent link.
    mockList.mockResolvedValue({
      services: [
        svc({ id: 100, code: 'JEA-SURV', parent_code: null, name_ar: 'استطلاع الموقع', name_en: 'Site Survey' }),
        svc({ id: 1, code: 'SRV-001', parent_code: 'JEA-SURV', name_ar: 'خدمة أولى' }),
      ],
    });

    renderAt('JEA-SURV');
    await waitFor(() => expect(screen.getByText('خدمة أولى')).toBeInTheDocument());

    // "استطلاع الموقع" appears only in the info-block <h2>, not the breadcrumb.
    const matches = screen.getAllByText('استطلاع الموقع');
    expect(matches).toHaveLength(1);
    expect(matches[0].tagName).toBe('H2');

    // Breadcrumb only carries the ancestor link.
    expect(screen.getByText('الخدمات الإلكترونية')).toBeInTheDocument();
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
