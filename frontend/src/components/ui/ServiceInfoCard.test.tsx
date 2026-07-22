import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { ServiceInfoCard } from './ServiceInfoCard';
import type { ServiceDefinition } from '../../types';

/**
 * JORD-18 pin: Apply page's service info card renders fee, SLA,
 * required-doc count, and workflow stage count from the service +
 * its schema.
 */

function svc(overrides: Partial<ServiceDefinition> = {}): ServiceDefinition {
  return {
    id: 1,
    code: 'DRW-P-004',
    name_ar: 'مخططات الهدم',
    name_en: 'Demolition',
    currency: 'JOD',
    base_fee: 150,
    sla_hours: 48,
    description_ar: 'وصف الخدمة',
    schema: {
      service_code: 'DRW-P-004',
      name_ar: 'مخططات الهدم', name_en: 'Demolition',
      workflow: { stages: [
        { id: 's1', role: 'applicant', label_ar: 'تقديم', label_en: 'Submit', sla_hours: 24, actions: ['submit'] },
        { id: 's2', role: 'staff',     label_ar: 'مراجعة', label_en: 'Review', sla_hours: 48, actions: ['approve', 'reject'] },
      ] },
      fee: { type: 'fixed', amount: 150, currency: 'JOD' },
      sections: [], fields: [],
      documents: [
        { id: 'a', label_ar: 'مستند', label_en: 'Doc', required: true,  accept: ['pdf'], max_size_mb: 5 },
        { id: 'b', label_ar: 'اختياري', label_en: 'Opt', required: false, accept: ['pdf'], max_size_mb: 5 },
      ],
    },
    ...overrides,
  } as unknown as ServiceDefinition;
}

describe('ServiceInfoCard — JORD-18', () => {
  it('shows fee formatted with currency', () => {
    render(<ServiceInfoCard service={svc()} />);
    expect(screen.getByText(/150 JOD/)).toBeInTheDocument();
  });

  it('formats SLA in days when >= 24h', () => {
    render(<ServiceInfoCard service={svc({ sla_hours: 48 })} />);
    // Arabic "أيام" (plural of يوم) is the localized SLA unit for >= 24h.
    expect(screen.getByText(/أيام|d/)).toBeInTheDocument();
  });

  it('counts only required documents', () => {
    render(<ServiceInfoCard service={svc()} />);
    // "1 مستند" — only the required one counts.
    expect(screen.getByText(/1 مستند/)).toBeInTheDocument();
  });

  it('renders the description when present', () => {
    render(<ServiceInfoCard service={svc()} />);
    expect(screen.getByText('وصف الخدمة')).toBeInTheDocument();
  });

  it('shows the service code in the header', () => {
    render(<ServiceInfoCard service={svc()} />);
    expect(screen.getByText('DRW-P-004')).toBeInTheDocument();
  });

  it('shows the workflow stage count', () => {
    render(<ServiceInfoCard service={svc()} />);
    expect(screen.getByText(/2 مراحل/)).toBeInTheDocument();
  });
});
