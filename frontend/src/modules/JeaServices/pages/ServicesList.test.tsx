import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ServicesList } from './ServicesList';
import type { ServiceDefinition } from '../../../types';

const mockList          = vi.fn();
const mockLockService   = vi.fn();
const mockUnlockService = vi.fn();
vi.mock('../../../api/client', () => ({
  adminApi: {
    listServices:         (...a: unknown[]) => mockList(...a),
    updateServiceStatus:  vi.fn(),
    lockService:          (...a: unknown[]) => mockLockService(...a),
    unlockService:        (...a: unknown[]) => mockUnlockService(...a),
  },
}));

function svc(overrides: Partial<ServiceDefinition>): ServiceDefinition {
  return {
    id: 1, code: 'TST-001', name_ar: 'خدمة اختبار', name_en: 'Test Service',
    currency: 'JOD', status: 'active', is_locked: true, ...overrides,
  } as ServiceDefinition;
}

beforeEach(() => {
  mockList.mockReset();
  mockLockService.mockReset();
  mockUnlockService.mockReset();
});

describe('ServicesList — lock state', () => {
  it('renders a "مقفلة" badge for locked services', async () => {
    mockList.mockResolvedValue({ services: [svc({ is_locked: true, parent_code: 'JEA-CERT' })], categories: [{ code: 'JEA-CERT', name_ar: 'الشهادات', name_en: 'Certificates' }] });
    render(<MemoryRouter><ServicesList /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('خدمة اختبار')).toBeInTheDocument());
    expect(screen.getByText('مقفلة')).toBeInTheDocument();
  });

  it('does not render "مقفلة" badge for unlocked services', async () => {
    mockList.mockResolvedValue({ services: [svc({ is_locked: false, parent_code: 'JEA-CERT' })], categories: [{ code: 'JEA-CERT', name_ar: 'الشهادات', name_en: 'Certificates' }] });
    render(<MemoryRouter><ServicesList /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('خدمة اختبار')).toBeInTheDocument());
    expect(screen.queryByText('مقفلة')).toBeNull();
  });

  it('calls unlockService when the "فتح القفل" button is clicked on a locked row', async () => {
    mockList.mockResolvedValue({ services: [svc({ is_locked: true, parent_code: 'JEA-CERT' })], categories: [{ code: 'JEA-CERT', name_ar: 'الشهادات', name_en: 'Certificates' }] });
    mockUnlockService.mockResolvedValue({ service: svc({ is_locked: false }), message: 'ok' });
    render(<MemoryRouter><ServicesList /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('خدمة اختبار')).toBeInTheDocument());

    await userEvent.click(screen.getByLabelText('فتح قفل TST-001'));
    await waitFor(() => expect(mockUnlockService).toHaveBeenCalledWith(1));
    expect(mockLockService).not.toHaveBeenCalled();
  });

  it('calls lockService when the "إقفال" button is clicked on an unlocked row', async () => {
    mockList.mockResolvedValue({ services: [svc({ is_locked: false, parent_code: 'JEA-CERT' })], categories: [{ code: 'JEA-CERT', name_ar: 'الشهادات', name_en: 'Certificates' }] });
    mockLockService.mockResolvedValue({ service: svc({ is_locked: true }), message: 'ok' });
    render(<MemoryRouter><ServicesList /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('خدمة اختبار')).toBeInTheDocument());

    await userEvent.click(screen.getByLabelText('إقفال TST-001'));
    await waitFor(() => expect(mockLockService).toHaveBeenCalledWith(1));
    expect(mockUnlockService).not.toHaveBeenCalled();
  });

  it('disables the edit link on locked rows', async () => {
    // Prevents the user from landing on the edit page just to discover
    // the API refuses their save with 423.
    mockList.mockResolvedValue({ services: [svc({ is_locked: true, parent_code: 'JEA-CERT' })], categories: [{ code: 'JEA-CERT', name_ar: 'الشهادات', name_en: 'Certificates' }] });
    render(<MemoryRouter><ServicesList /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('خدمة اختبار')).toBeInTheDocument());

    const editLink = screen.getByRole('link', { name: 'تعديل' });
    expect(editLink).toHaveAttribute('aria-disabled', 'true');
  });

  it('leaves the edit link active on unlocked rows', async () => {
    mockList.mockResolvedValue({ services: [svc({ is_locked: false, parent_code: 'JEA-CERT' })], categories: [{ code: 'JEA-CERT', name_ar: 'الشهادات', name_en: 'Certificates' }] });
    render(<MemoryRouter><ServicesList /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('خدمة اختبار')).toBeInTheDocument());

    const editLink = screen.getByRole('link', { name: 'تعديل' });
    expect(editLink).toHaveAttribute('aria-disabled', 'false');
  });
});

/**
 * JORD-51 regression: the admin page used to dump every row in the
 * services table, which included the 7 top-level category tiles as
 * junk cards. It also ordered by created_at DESC — semantically
 * meaningless for a plan-driven catalog. Now the backend groups &
 * orders; the frontend just renders the sections. These tests pin
 * that the sections appear with headers, in server order, and that
 * a service with no parent_code is not silently swallowed.
 */
describe('ServicesList — JORD-51 category grouping', () => {
  it('renders one section per non-empty category, in the order the API returned', async () => {
    mockList.mockResolvedValue({
      services: [
        svc({ id: 1, code: 'DRW-P-001', name_ar: 'مخططات المقترحة', parent_code: 'JEA-PROJ', is_locked: false }),
        svc({ id: 2, code: 'DRW-P-002', name_ar: 'مخططات القائمة',   parent_code: 'JEA-PROJ', is_locked: false }),
        svc({ id: 3, code: 'CERT-001',  name_ar: 'شهادة المطابقة',    parent_code: 'JEA-CERT', is_locked: false }),
      ],
      categories: [
        { code: 'JEA-PROJ', name_ar: 'خدمات تصديق المخططات الهندسية', name_en: 'Drawings Approval' },
        { code: 'JEA-CERT', name_ar: 'الشهادات', name_en: 'Certificates' },
      ],
    });
    render(<MemoryRouter><ServicesList /></MemoryRouter>);

    // Both category headers appear.
    await waitFor(() => expect(screen.getByText('خدمات تصديق المخططات الهندسية')).toBeInTheDocument());
    expect(screen.getByText('الشهادات')).toBeInTheDocument();

    // JEA-PROJ header comes BEFORE JEA-CERT in the DOM (API order).
    const projHeader = screen.getByText('خدمات تصديق المخططات الهندسية');
    const certHeader = screen.getByText('الشهادات');
    expect(projHeader.compareDocumentPosition(certHeader) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
  });

  it('hides a category header whose services array is empty', async () => {
    mockList.mockResolvedValue({
      services: [
        svc({ id: 1, code: 'CERT-001', name_ar: 'شهادة المطابقة', parent_code: 'JEA-CERT', is_locked: false }),
      ],
      categories: [
        { code: 'JEA-PROJ', name_ar: 'خدمات تصديق المخططات الهندسية', name_en: 'Drawings' },
        { code: 'JEA-CERT', name_ar: 'الشهادات', name_en: 'Certificates' },
      ],
    });
    render(<MemoryRouter><ServicesList /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('الشهادات')).toBeInTheDocument());
    // The empty PROJ category is filtered out — no orphaned header.
    expect(screen.queryByText('خدمات تصديق المخططات الهندسية')).toBeNull();
  });

  it('falls back to a flat list if the backend returns an empty categories array', async () => {
    // Guard against an older backend that hasn't shipped the categories
    // field yet — the page should still list every service, not blank out.
    mockList.mockResolvedValue({
      services: [
        svc({ id: 1, code: 'CERT-001', name_ar: 'شهادة المطابقة', parent_code: 'JEA-CERT', is_locked: false }),
      ],
      categories: [],
    });
    render(<MemoryRouter><ServicesList /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('شهادة المطابقة')).toBeInTheDocument());
    // No section headers rendered when the backend didn't ship any.
    expect(screen.queryByRole('heading', { level: 2 })).toBeNull();
  });
});
