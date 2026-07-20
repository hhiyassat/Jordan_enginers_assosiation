import { describe, it, expect } from 'vitest';
import { validateAll } from './DynamicForm';
import type { ServiceSchema } from '../types';

/**
 * JORD-16: pin the pre-submit client validation surface.
 * validateAll must:
 *   • flag required fields that are empty
 *   • flag malformed email / number / date
 *   • honour pattern / min_length / max_length
 *   • skip fields hidden by a `conditional` guard
 *   • return valid:true when every visible field passes
 */

function schema(fields: ServiceSchema['fields']): ServiceSchema {
  return {
    service_code: 'X', name_ar: 'X', name_en: 'X',
    fields,
    documents: [],
    workflow: { stages: [] },
    fee: { type: 'fixed', amount: 0, currency: 'JOD' },
  } as unknown as ServiceSchema;
}

describe('validateAll — JORD-16 pre-submit sweep', () => {
  it('flags every empty required field at once', () => {
    const s = schema([
      { id: 'a', label_ar: 'a', label_en: 'a', type: 'text',   required: true },
      { id: 'b', label_ar: 'b', label_en: 'b', type: 'number', required: true },
      { id: 'c', label_ar: 'c', label_en: 'c', type: 'email',  required: false }, // optional
    ]);
    const { valid, errors } = validateAll(s, {});
    expect(valid).toBe(false);
    expect(Object.keys(errors).sort()).toEqual(['a', 'b']);
  });

  it('flags a malformed email even when optional', () => {
    const s = schema([
      { id: 'e', label_ar: 'e', label_en: 'e', type: 'email', required: false },
    ]);
    const { valid, errors } = validateAll(s, { e: 'not-an-email' });
    expect(valid).toBe(false);
    expect(errors.e).toMatch(/البريد|Invalid/i);
  });

  it('enforces number min/max bounds', () => {
    const s = schema([
      { id: 'age', label_ar: 'age', label_en: 'age', type: 'number', required: true, min: 18, max: 65 },
    ]);
    expect(validateAll(s, { age: 12 }).errors.age).toMatch(/18/);
    expect(validateAll(s, { age: 99 }).errors.age).toMatch(/65/);
    expect(validateAll(s, { age: 30 }).valid).toBe(true);
  });

  it('honours a regex pattern', () => {
    // validateField wraps the pattern in ^(?:...)$, so provide the full
    // shape here. IBAN JO format: JO + 2 check digits + 4 bank letters + 22 digits.
    const s = schema([
      { id: 'iban', label_ar: 'iban', label_en: 'iban', type: 'text', required: true,
        pattern: 'JO\\d{2}[A-Z]{4}\\d{22}' },
    ]);
    expect(validateAll(s, { iban: 'garbage' }).errors.iban).toBeTruthy();
    expect(validateAll(s, { iban: 'JO94CBJO0010000000000131000302' }).valid).toBe(true);
  });

  it('skips fields hidden by a conditional guard', () => {
    // spouse_name required ONLY when marital_status == 'married'
    const s = schema([
      { id: 'marital_status', label_ar: 'm', label_en: 'm', type: 'select', required: true },
      {
        id: 'spouse_name', label_ar: 's', label_en: 's', type: 'text', required: true,
        conditional: { field: 'marital_status', value: 'married' },
      },
    ]);
    // Single applicant — spouse_name is hidden and must not be flagged.
    const { valid, errors } = validateAll(s, { marital_status: 'single' });
    expect(valid).toBe(true);
    expect(errors.spouse_name).toBeUndefined();
  });

  it('flags a conditionally-visible required field when its guard matches', () => {
    const s = schema([
      { id: 'marital_status', label_ar: 'm', label_en: 'm', type: 'select', required: true },
      {
        id: 'spouse_name', label_ar: 's', label_en: 's', type: 'text', required: true,
        conditional: { field: 'marital_status', value: 'married' },
      },
    ]);
    const { valid, errors } = validateAll(s, { marital_status: 'married' });
    expect(valid).toBe(false);
    expect(errors.spouse_name).toBeTruthy();
  });

  it('returns valid:true + empty errors when every visible field passes', () => {
    const s = schema([
      { id: 'name', label_ar: 'n', label_en: 'n', type: 'text',  required: true },
      { id: 'age',  label_ar: 'a', label_en: 'a', type: 'number', required: true, min: 0 },
    ]);
    const { valid, errors } = validateAll(s, { name: 'Hussein', age: 30 });
    expect(valid).toBe(true);
    expect(errors).toEqual({});
  });
});
