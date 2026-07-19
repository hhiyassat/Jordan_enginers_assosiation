import { describe, it, expect } from 'vitest';
import { normalizeApplyError, labelForOtherKey, type ApiError } from './applyErrorHelpers';
import type { ServiceSchema } from '../../types';

function apiError(errors: Record<string, string | string[]> | undefined, message = ''): ApiError {
  const e = new Error(message) as ApiError;
  if (errors) e.errors = errors;
  return e;
}

const sampleSchema: ServiceSchema = {
  service_code: 'DRW-P-004',
  name_ar: 'مخططات الهدم',
  name_en: 'Demolition Drawings',
  workflow: { stages: [] },
  fee: { type: 'fixed', amount: 0, currency: 'JOD' },
  sections: [],
  fields: [
    { id: 'owner_name', label_ar: 'اسم المالك', label_en: 'Owner', type: 'text', required: true },
    { id: 'area_m2',    label_ar: 'المساحة',    label_en: 'Area',  type: 'number', required: true },
  ],
  documents: [
    { id: 'permit_pdf', label_ar: 'الترخيص', label_en: 'Permit', required: true, accept: ['pdf'], max_size_mb: 5 },
  ],
};

describe('normalizeApplyError', () => {
  it('buckets SchemaValidator errors (keyed directly by field id) as fieldErrors', () => {
    const { fieldErrors, otherErrors, summary } = normalizeApplyError(
      apiError({ owner_name: 'اسم المالك مطلوب.', area_m2: 'المساحة مطلوبة.' }, 'Validation failed'),
      sampleSchema,
    );
    expect(fieldErrors).toEqual({
      owner_name: 'اسم المالك مطلوب.',
      area_m2:    'المساحة مطلوبة.',
    });
    expect(otherErrors).toEqual({});
    expect(summary).toBe('Validation failed');
  });

  it('buckets document-validator errors (keyed by document id) as fieldErrors', () => {
    const { fieldErrors } = normalizeApplyError(
      apiError({ permit_pdf: 'الترخيص مطلوب.' }),
      sampleSchema,
    );
    expect(fieldErrors).toEqual({ permit_pdf: 'الترخيص مطلوب.' });
  });

  it('strips the `data.` prefix from Laravel FormRequest nested errors', () => {
    // Regression: previously 'data.owner_name' fell into otherErrors and
    // the applicant saw a banner with no inline highlight next to owner_name.
    const { fieldErrors, otherErrors } = normalizeApplyError(
      apiError({ 'data.owner_name': ['اسم المالك مطلوب.'] }),
      sampleSchema,
    );
    expect(fieldErrors).toEqual({ owner_name: 'اسم المالك مطلوب.' });
    expect(otherErrors).toEqual({});
  });

  it('keeps unknown keys in otherErrors so the banner can list them', () => {
    const { fieldErrors, otherErrors } = normalizeApplyError(
      apiError({
        project_id:   ['المشروع غير مرتبط بحسابك.'],
        service_code: ['رمز الخدمة مطلوب.'],
      }),
      sampleSchema,
    );
    expect(fieldErrors).toEqual({});
    expect(otherErrors).toEqual({
      project_id:   'المشروع غير مرتبط بحسابك.',
      service_code: 'رمز الخدمة مطلوب.',
    });
  });

  it('mixes field errors and other errors correctly in one payload', () => {
    const { fieldErrors, otherErrors } = normalizeApplyError(
      apiError({
        owner_name: 'اسم المالك مطلوب.',
        project_id: 'المشروع غير مرتبط بحسابك.',
      }),
      sampleSchema,
    );
    expect(fieldErrors).toEqual({ owner_name: 'اسم المالك مطلوب.' });
    expect(otherErrors).toEqual({ project_id: 'المشروع غير مرتبط بحسابك.' });
  });

  it('falls back to a generic summary when the backend sent no message', () => {
    const { summary } = normalizeApplyError(
      apiError({ owner_name: 'اسم المالك مطلوب.' }),
      sampleSchema,
    );
    // Empty message but field errors exist → prompt the applicant to review inline.
    expect(summary).toContain('الحقول المحددة');
  });

  it('treats every key as otherErrors when the schema is missing', () => {
    // Edge case: the Apply page might not have the schema loaded yet.
    // Better to show the raw errors than to silently drop them.
    const { fieldErrors, otherErrors } = normalizeApplyError(
      apiError({ owner_name: 'اسم المالك مطلوب.' }),
      undefined,
    );
    expect(fieldErrors).toEqual({});
    expect(otherErrors).toEqual({ owner_name: 'اسم المالك مطلوب.' });
  });
});

describe('labelForOtherKey', () => {
  it('returns Arabic labels for known meta keys', () => {
    expect(labelForOtherKey('project_id')).toBe('المشروع');
    expect(labelForOtherKey('service_code')).toBe('رمز الخدمة');
    expect(labelForOtherKey('data')).toBe('بيانات الطلب');
  });

  it('falls back to the raw key for unknown ones', () => {
    expect(labelForOtherKey('made_up_field')).toBe('made_up_field');
  });
});
