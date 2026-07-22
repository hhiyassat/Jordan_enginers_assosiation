import { describe, it, expect } from 'vitest';
import { normalizeSaveError, type ApiError } from './saveErrorHelpers';

function apiError(errors: Record<string, string | string[]> | undefined, message = 'validation failed'): ApiError {
  const e = new Error(message) as ApiError;
  if (errors) e.errors = errors;
  return e;
}

describe('normalizeSaveError', () => {
  it('turns a "code taken" Laravel error into a friendly Arabic summary', () => {
    const { summary, fieldErrors } = normalizeSaveError(
      apiError({ code: ['The code has already been taken.'] }),
      'SVC-DUP'
    );
    expect(summary).toContain('SVC-DUP');
    expect(summary).toContain('مستخدم مسبقاً');
    expect(fieldErrors).toEqual({});
  });

  it('surfaces every per-field schema error under the summary', () => {
    // Regression: SchemaStructureValidator emits string values (not string[])
    // keyed by dotted paths. UI previously hid them behind the top message.
    const err = apiError(
      {
        'schema.workflow.stages[0].actions':
          "المرحلة [0]: قيم actions غير مسموح بها: initiate_transaction.",
        'schema.workflow.stages[1].sla_hours':
          'المرحلة [1]: sla_hours مطلوب ويجب أن يكون رقماً.',
      },
      'المخطط لا يتوافق مع بنية ESP v2. يرجى مراجعة الأخطاء أدناه.'
    );

    const { summary, fieldErrors } = normalizeSaveError(err, 'SVC-WF-017');

    expect(summary).toBe('المخطط لا يتوافق مع بنية ESP v2. يرجى مراجعة الأخطاء أدناه.');
    expect(Object.keys(fieldErrors)).toHaveLength(2);
    expect(fieldErrors['schema.workflow.stages[0].actions']).toContain('initiate_transaction');
    expect(fieldErrors['schema.workflow.stages[1].sla_hours']).toContain('sla_hours');
  });

  it('handles Laravel-style string[] values and backend string values in one payload', () => {
    const { fieldErrors } = normalizeSaveError(
      apiError({
        'schema.workflow.stages[0].id': "المرحلة [0] يجب أن تحتوي على حقل id.",
        'name_ar':                        ['اسم الخدمة مطلوب'],
      }),
      'SVC-X'
    );
    expect(fieldErrors['schema.workflow.stages[0].id']).toContain('id');
    expect(fieldErrors['name_ar']).toBe('اسم الخدمة مطلوب');
  });

  it('falls back to the top-level message when the backend sends none', () => {
    const { summary, fieldErrors } = normalizeSaveError(
      apiError(undefined, 'network timeout'),
      'SVC-X'
    );
    expect(summary).toBe('network timeout');
    expect(fieldErrors).toEqual({});
  });
});
