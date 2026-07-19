import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ServicesList } from './ServicesList';
import type { ServiceDefinition } from '../../types';

const mockList          = vi.fn();
const mockLockService   = vi.fn();
const mockUnlockService = vi.fn();
vi.mock('../../api/client', () => ({
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
    mockList.mockResolvedValue({ services: [svc({ is_locked: true })] });
    render(<MemoryRouter><ServicesList /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('خدمة اختبار')).toBeInTheDocument());
    expect(screen.getByText('مقفلة')).toBeInTheDocument();
  });

  it('does not render "مقفلة" badge for unlocked services', async () => {
    mockList.mockResolvedValue({ services: [svc({ is_locked: false })] });
    render(<MemoryRouter><ServicesList /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('خدمة اختبار')).toBeInTheDocument());
    expect(screen.queryByText('مقفلة')).toBeNull();
  });

  it('calls unlockService when the "فتح القفل" button is clicked on a locked row', async () => {
    mockList.mockResolvedValue({ services: [svc({ is_locked: true })] });
    mockUnlockService.mockResolvedValue({ service: svc({ is_locked: false }), message: 'ok' });
    render(<MemoryRouter><ServicesList /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('خدمة اختبار')).toBeInTheDocument());

    await userEvent.click(screen.getByLabelText('فتح قفل TST-001'));
    await waitFor(() => expect(mockUnlockService).toHaveBeenCalledWith(1));
    expect(mockLockService).not.toHaveBeenCalled();
  });

  it('calls lockService when the "إقفال" button is clicked on an unlocked row', async () => {
    mockList.mockResolvedValue({ services: [svc({ is_locked: false })] });
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
    mockList.mockResolvedValue({ services: [svc({ is_locked: true })] });
    render(<MemoryRouter><ServicesList /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('خدمة اختبار')).toBeInTheDocument());

    const editLink = screen.getByRole('link', { name: 'تعديل' });
    expect(editLink).toHaveAttribute('aria-disabled', 'true');
  });

  it('leaves the edit link active on unlocked rows', async () => {
    mockList.mockResolvedValue({ services: [svc({ is_locked: false })] });
    render(<MemoryRouter><ServicesList /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText('خدمة اختبار')).toBeInTheDocument());

    const editLink = screen.getByRole('link', { name: 'تعديل' });
    expect(editLink).toHaveAttribute('aria-disabled', 'false');
  });
});
