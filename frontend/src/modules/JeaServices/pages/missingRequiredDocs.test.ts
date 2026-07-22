import { describe, it, expect } from 'vitest';
import type { Application, SchemaDocument } from '../../../types';
import { missingRequiredDocs, missingRequiredDocsFor } from './missingRequiredDocs';

const doc = (o: Partial<SchemaDocument> = {}): SchemaDocument => ({
  id: 'x', label_ar: 'x', label_en: 'x',
  required: true, accept: ['pdf'], max_size_mb: 5,
  ...o,
});

describe('missingRequiredDocs (JORD-58 gate helper)', () => {
  it('returns [] when the schema has no documents', () => {
    expect(missingRequiredDocs(undefined, [], {})).toEqual([]);
    expect(missingRequiredDocs([],        [], {})).toEqual([]);
  });

  it('flags required docs the applicant has NOT uploaded', () => {
    const docs = [
      doc({ id: 'plan', required: true }),
      doc({ id: 'permit', required: true }),
    ];
    expect(missingRequiredDocs(docs, [], {})).toHaveLength(2);
    expect(missingRequiredDocs(docs, ['plan'], {}).map(d => d.id)).toEqual(['permit']);
    expect(missingRequiredDocs(docs, ['plan', 'permit'], {})).toEqual([]);
  });

  it('ignores optional documents', () => {
    const docs = [
      doc({ id: 'plan', required: true }),
      doc({ id: 'notes', required: false }),
    ];
    // Optional missing → still no gate.
    expect(missingRequiredDocs(docs, ['plan'], {})).toEqual([]);
  });

  it('respects conditional documents: only bites when the gate field matches', () => {
    const docs = [
      doc({ id: 'health_cert', required: true, conditional: { field: 'category', value: 'f_and_b' } }),
    ];
    // Gate not matched → conditional doc is invisible → no missing.
    expect(missingRequiredDocs(docs, [], { category: 'retail' })).toEqual([]);
    // Gate matched → conditional doc becomes required-missing.
    expect(missingRequiredDocs(docs, [], { category: 'f_and_b' })).toHaveLength(1);
  });

  it('handles multiple conditional docs on the same page independently', () => {
    const docs = [
      doc({ id: 'health_cert', required: true, conditional: { field: 'category', value: 'f_and_b' } }),
      doc({ id: 'permit',      required: true, conditional: { field: 'category', value: 'retail'  } }),
    ];
    // Only the retail doc's gate matches → only that one is missing.
    const missing = missingRequiredDocs(docs, [], { category: 'retail' });
    expect(missing.map(d => d.id)).toEqual(['permit']);
  });
});

describe('missingRequiredDocsFor (Application overload)', () => {
  const docs = [
    doc({ id: 'plan', required: true }),
    doc({ id: 'permit', required: true }),
  ];

  const app = (uploadedIds: string[] = []): Application => ({
    id: 1,
    reference_number: 'X',
    status: 'draft',
    fee_amount: 0,
    payment_status: 'pending',
    review_round: 0,
    documents: uploadedIds.map((did, i) => ({
      id: i + 1, document_id: did, original_filename: `${did}.pdf`,
      mime_type: 'application/pdf', size_bytes: 100, status: 'pending',
      created_at: '2026-06-01T09:00:00Z',
    })),
    reviews: [],
    created_at: '2026-06-01T09:00:00Z',
    updated_at: '2026-06-01T09:00:00Z',
  });

  it('null application → returns every required doc as missing', () => {
    expect(missingRequiredDocsFor(docs, null, {})).toHaveLength(2);
  });

  it('reads the uploaded document ids off application.documents', () => {
    expect(missingRequiredDocsFor(docs, app(['plan']), {}).map(d => d.id)).toEqual(['permit']);
    expect(missingRequiredDocsFor(docs, app(['plan', 'permit']), {})).toEqual([]);
  });
});
