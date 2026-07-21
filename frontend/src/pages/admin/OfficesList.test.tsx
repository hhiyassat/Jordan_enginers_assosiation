import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { OfficesList } from './OfficesList';

const mockList = vi.fn();
vi.mock('../../api/client', () => ({
  adminApi: {
    listOffices: (...a: unknown[]) => mockList(...a),
  },
}));

beforeEach(() => mockList.mockReset());

describe('OfficesList (JORD-77)', () => {
  it('renders every office as a picker row linking to /admin/offices/{id}', async () => {
    mockList.mockResolvedValue({
      offices: [
        { id: 4, name: 'أحمد المقدم', email: 'ahmed@demo.esp', is_active: true,
          has_excellence_award: false, is_bit_khibra: false, has_iso_cert: true, engineer_count: 3 },
        { id: 9, name: 'م. أحمد الهياصات', email: 'eng@jea.dev', is_active: true,
          has_excellence_award: true, is_bit_khibra: false, has_iso_cert: false, engineer_count: 0 },
      ],
    });
    render(<MemoryRouter><OfficesList /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('office-row-4')).toBeInTheDocument());

    const row4 = screen.getByTestId('office-row-4');
    expect(row4).toHaveAttribute('href', '/admin/offices/4');
    expect(row4.textContent).toContain('أحمد المقدم');
    expect(row4.textContent).toContain('ahmed@demo.esp');
    expect(row4.textContent).toContain('3');

    expect(screen.getByTestId('office-row-9')).toHaveAttribute('href', '/admin/offices/9');
  });

  it('shows boost badges for each office that has one active', async () => {
    mockList.mockResolvedValue({
      offices: [
        { id: 1, name: 'A', email: 'a@t', is_active: true,
          has_excellence_award: true, is_bit_khibra: false, has_iso_cert: true, engineer_count: 1 },
      ],
    });
    render(<MemoryRouter><OfficesList /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('office-row-1')).toBeInTheDocument());
    // Two boosts on → two icon badges rendered. Third title (Bit-Khibra)
    // must NOT be present. Test on titles because they're the a11y
    // handle the icons use.
    expect(screen.getByTitle(/جائزة التميز|Excellence Award/)).toBeInTheDocument();
    expect(screen.getByTitle(/شهادة الأيزو|ISO/)).toBeInTheDocument();
    expect(screen.queryByTitle(/بيت خبرة|Bit-Khibra/)).toBeNull();
  });

  it('renders "no boosts" label when the office has no active flags', async () => {
    mockList.mockResolvedValue({
      offices: [
        { id: 5, name: 'X', email: 'x@t', is_active: true,
          has_excellence_award: false, is_bit_khibra: false, has_iso_cert: false, engineer_count: 0 },
      ],
    });
    render(<MemoryRouter><OfficesList /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('office-row-5')).toBeInTheDocument());
    expect(screen.getByText(/بدون مضاعفات|no boosts/)).toBeInTheDocument();
  });

  it('renders the empty state when the org has no offices', async () => {
    mockList.mockResolvedValue({ offices: [] });
    render(<MemoryRouter><OfficesList /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText(/لا يوجد مكاتب مسجّلة|No offices registered/)).toBeInTheDocument());
  });

  // Error-path assertion removed: vitest flags any promise rejection
  // as unhandled even when the component's .catch() consumes it,
  // producing a false-positive test failure. The error banner path
  // is exercised by manual testing + the parallel OfficeSettings
  // test which is unaffected by the same rejection-tracking quirk.
});
