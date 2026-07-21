import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { MyOffice } from './MyOffice';

const mockDues       = vi.fn();
const mockComplaints = vi.fn();
const mockSanctions  = vi.fn();
vi.mock('../../api/client', () => ({
  myOfficeApi: {
    dues:       (...a: unknown[]) => mockDues(...a),
    complaints: (...a: unknown[]) => mockComplaints(...a),
    sanctions:  (...a: unknown[]) => mockSanctions(...a),
  },
}));

function baseDues(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    me: { id: 1, name: 'مكتب الاختبار', office_classification: 'engineering' },
    obligations: [],
    rate_table: {
      engineering:         { registration: 300, annual_dues: 150 },
      individual_engineer: { registration: 150, annual_dues: 100 },
      consultant:          { registration: 500, annual_dues: 250 },
      foreign:             { registration: 1000, annual_dues: 500 },
    },
    ...overrides,
  };
}

beforeEach(() => {
  mockDues.mockReset();
  mockComplaints.mockReset();
  mockSanctions.mockReset();
  // Default: everything empty.
  mockDues.mockResolvedValue(baseDues());
  mockComplaints.mockResolvedValue({ complaints: [] });
  mockSanctions.mockResolvedValue({ sanctions: [] });
});

describe('MyOffice (JORD-84)', () => {
  it('renders the office identity and tier label', async () => {
    render(<MemoryRouter><MyOffice /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText(/مكتب الاختبار/)).toBeInTheDocument());
    expect(screen.getByText(/مصنف هندسي/)).toBeInTheDocument();
  });

  it('shows the tier rate reference (registration + annual)', async () => {
    render(<MemoryRouter><MyOffice /></MemoryRouter>);
    await waitFor(() => expect(screen.getByText(/300 JOD/)).toBeInTheDocument());
    expect(screen.getByText(/150 JOD/)).toBeInTheDocument();
  });

  it('renders summary counts for outstanding, complaints, sanctions', async () => {
    mockDues.mockResolvedValue(baseDues({
      obligations: [
        { id: 1, kind: 'registration', period_year: 2026, period_label_ar: null,
          amount_jod: '300.00', due_date: '2026-01-31', paid_at: null,
          payment_reference: null, late_surcharge_jod: '0.00', total_paid_jod: null },
        { id: 2, kind: 'annual_dues', period_year: 2025, period_label_ar: null,
          amount_jod: '150.00', due_date: '2025-01-31', paid_at: '2025-02-01',
          payment_reference: 'REF-1', late_surcharge_jod: '0.00', total_paid_jod: '150.00' },
      ],
    }));
    mockComplaints.mockResolvedValue({ complaints: [
      { id: 1, kind: 'safety_violation', description: 'x', status: 'open',
        investigation_deadline: '2027-01-15', decided_at: null, created_at: '2026-12-15',
        reporter: null, sanctions: [] },
    ]});
    mockSanctions.mockResolvedValue({ sanctions: [
      { id: 1, kind: 'warning', effective_from: '2026-01-01',
        effective_until: '2026-01-01', reason: 'past' },
      { id: 2, kind: 'suspension_1yr', effective_from: '2026-06-01',
        effective_until: '2027-06-01', reason: 'active' },
    ]});
    render(<MemoryRouter><MyOffice /></MemoryRouter>);

    await waitFor(() => expect(screen.getByTestId('summary-outstanding')).toHaveTextContent('1'));
    expect(screen.getByTestId('summary-complaints')).toHaveTextContent('1');
    expect(screen.getByTestId('summary-sanctions')).toHaveTextContent('1'); // one active
  });

  it('shows a "no obligations" empty state when none', async () => {
    render(<MemoryRouter><MyOffice /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('obligations-empty')).toBeInTheDocument());
    expect(screen.getByTestId('complaints-empty')).toBeInTheDocument();
    expect(screen.getByTestId('sanctions-empty')).toBeInTheDocument();
  });

  it('does NOT expose any pay / decide affordance (read-only per policy)', async () => {
    mockDues.mockResolvedValue(baseDues({
      obligations: [
        { id: 42, kind: 'registration', period_year: 2026, period_label_ar: null,
          amount_jod: '300.00', due_date: '2026-01-31', paid_at: null,
          payment_reference: null, late_surcharge_jod: '0.00', total_paid_jod: null },
      ],
    }));
    render(<MemoryRouter><MyOffice /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('my-obligation-row-42')).toBeInTheDocument());
    // No pay button.
    expect(screen.queryByTestId('pay-btn-42')).toBeNull();
    // No decide-form / sanction-picker for complaints either.
    expect(screen.queryByTestId(/decide-form/)).toBeNull();
    // The "visit JEA counter" hint appears.
    expect(screen.getByText(/نقابة المهندسين|JEA/)).toBeInTheDocument();
  });

  it('surfaces attached sanctions inside a complaint row', async () => {
    mockComplaints.mockResolvedValue({ complaints: [
      { id: 9, kind: 'safety_violation', description: 'خطر سلامة موثق.', status: 'decided',
        investigation_deadline: '2026-12-01', decided_at: '2026-11-01',
        created_at: '2026-10-01', reporter: null,
        sanctions: [{ id: 5, kind: 'suspension_1yr', effective_from: '2026-11-01', effective_until: '2027-11-01' }] },
    ]});
    render(<MemoryRouter><MyOffice /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('my-complaint-9')).toBeInTheDocument());
    expect(screen.getByText(/إيقاف سنة/)).toBeInTheDocument();
    expect(screen.getByTestId('my-complaint-status-9')).toHaveTextContent('decided');
  });

  it('marks an active vs an expired sanction correctly', async () => {
    mockSanctions.mockResolvedValue({ sanctions: [
      { id: 1, kind: 'warning', effective_from: '2020-01-01',
        effective_until: '2020-01-01', reason: 'old' },
      { id: 2, kind: 'suspension_1yr', effective_from: '2026-06-01',
        effective_until: '2100-01-01', reason: 'current' },
    ]});
    render(<MemoryRouter><MyOffice /></MemoryRouter>);
    await waitFor(() => expect(screen.getByTestId('my-sanction-1')).toBeInTheDocument());
    // 2 sanctions rendered, only 1 counted active in the summary.
    expect(screen.getByTestId('summary-sanctions')).toHaveTextContent('1');
  });
});
