import React from 'react';
import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom/vitest';
import { ReportsPanel } from './ReportsPanel';
import type { Application, SchemaDocument } from '../../shared/types';

// Deterministic Arabic locale — the panel filters + labels off i18n.
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (k: string) => k,
    i18n: { language: 'ar' },
  }),
}));

// Stub the (?) icon — the ReportsPanel test doesn't care about its
// internal fetch behavior, only that the ids reach it.
vi.mock('./ManualReferenceIcon', () => ({
  ManualReferenceIcon: ({ referenceId }: { referenceId: number }) => (
    <span data-testid={`manual-ref-icon-${referenceId}`}>?</span>
  ),
}));

const contract: SchemaDocument = {
  id: 'survey_contract',
  label_ar: 'عقد استطلاع الموقع',
  label_en: 'Site-Investigation Contract',
  required: true,
  accept: ['pdf'],
  max_size_mb: 10,
  category: 'CONTRACT',
  manual_reference_ids: [999],
};

const report: SchemaDocument = {
  id: 'site_investigation_report',
  label_ar: 'تقرير استطلاع الموقع',
  label_en: 'Site-Investigation Report',
  required: true,
  accept: ['pdf'],
  max_size_mb: 25,
  category: 'REPORT',
  report_type: 'SITE_INVESTIGATION_REPORT',
  manual_reference_ids: [11, 22, 33],
  signature_requirements: {
    signing_role: 'HEAD_OF_SPECIALIZATION',
    signing_engineer_name_required: true,
    signing_engineer_registration_required: true,
    source: 'كتاب التعليمات الفنية 2025 ص.37',
  },
};

// Contract test: the shape rendered here MUST match what the backend
// seeder emits. If Srv001PilotSeeder ever adds a canonical key, extend
// SchemaDocument in types/jea.ts and this fixture in the same PR.
describe('SchemaDocument contract', () => {
  it('report + contract use the canonical file-type keys only', () => {
    for (const doc of [report, contract]) {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const anyDoc = doc as any;
      expect(anyDoc.accept).toEqual(['pdf']);
      expect(typeof anyDoc.max_size_mb).toBe('number');
      // Aliases would silently disagree — the frontend reads doc.accept
      // and doc.max_size_mb; no other MIME/size key is consumed anywhere.
      expect(anyDoc.accepted_mime_types).toBeUndefined();
      expect(anyDoc.max_size_bytes).toBeUndefined();
    }
  });
});

describe('ReportsPanel', () => {
  it('renders nothing when the service has no REPORT-category documents', () => {
    const { container } = render(<ReportsPanel documents={[contract]} />);
    expect(container).toBeEmptyDOMElement();
  });

  it('renders REPORT documents only — CONTRACT stays elsewhere', () => {
    render(<ReportsPanel documents={[contract, report]} />);
    expect(screen.getByTestId('report-site_investigation_report')).toBeInTheDocument();
    expect(screen.queryByTestId('report-survey_contract')).not.toBeInTheDocument();
  });

  it('renders one ManualReferenceIcon per manual_reference_ids entry', () => {
    render(<ReportsPanel documents={[report]} />);
    expect(screen.getByTestId('manual-ref-icon-11')).toBeInTheDocument();
    expect(screen.getByTestId('manual-ref-icon-22')).toBeInTheDocument();
    expect(screen.getByTestId('manual-ref-icon-33')).toBeInTheDocument();
    // Contract's ref (999) must NOT render inside the panel.
    expect(screen.queryByTestId('manual-ref-icon-999')).not.toBeInTheDocument();
  });

  it('exposes the signature role for REPORT documents', () => {
    render(<ReportsPanel documents={[report]} />);
    expect(screen.getByText('HEAD_OF_SPECIALIZATION')).toBeInTheDocument();
  });

  it('marks the report as uploaded when the application has a matching document', () => {
    const application = {
      id: 1,
      documents: [
        {
          id: 100,
          document_id: 'site_investigation_report',
          original_filename: 'report.pdf',
          status: 'uploaded',
        },
      ],
    } as unknown as Application;
    render(<ReportsPanel documents={[report]} application={application} />);
    expect(screen.getByText(/مرفوع/)).toBeInTheDocument();
  });

  it('exposes the report_type badge when the document declares one', () => {
    render(<ReportsPanel documents={[report]} />);
    expect(screen.getByText('SITE_INVESTIGATION_REPORT')).toBeInTheDocument();
  });
});
