import { describe, it, expect, beforeEach, afterAll } from 'vitest';
import i18n from '../i18n';
import { validateAll } from './DynamicForm';
import type { ServiceSchema } from '../types';

/**
 * JORD-93: validation messages must follow the current app language
 * without a caller passing an explicit locale.
 *
 * Before: `validateAll(schema, values)` defaulted to Arabic even when
 * the user's UI was in English, so error banners on the Apply page
 * carried mixed-language text.
 *
 * After: absent `locale` → picks the current i18n.language and pulls
 * the message from the same JSON bundle the rest of the UI uses.
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

const emailField = {
  id: 'e', label_ar: 'e', label_en: 'e', type: 'email' as const, required: true,
};

describe('validateAll — JORD-93 i18n', () => {
  beforeEach(async () => { await i18n.changeLanguage('ar'); });
  afterAll(async () => { await i18n.changeLanguage('ar'); });

  it('returns Arabic messages when the app is in Arabic', async () => {
    await i18n.changeLanguage('ar');
    const { errors } = validateAll(schema([emailField]), {});
    expect(errors.e).toContain('مطلوب');
  });

  it('returns English messages when the app is in English', async () => {
    await i18n.changeLanguage('en');
    const { errors } = validateAll(schema([emailField]), {});
    expect(errors.e).toContain('required');
  });

  it('explicit locale argument still wins over the current language', async () => {
    // App language is English, but the caller explicitly asks for Arabic.
    await i18n.changeLanguage('en');
    const { errors } = validateAll(schema([emailField]), {}, 'ar');
    expect(errors.e).toContain('مطلوب');
  });

  it('substitutes number bounds through the i18n interpolation', async () => {
    await i18n.changeLanguage('en');
    const s = schema([{
      id: 'age', label_ar: 'age', label_en: 'age',
      type: 'number', required: true, min: 18, max: 65,
    }]);
    expect(validateAll(s, { age: 12 }).errors.age).toMatch(/Minimum value is 18/);
    expect(validateAll(s, { age: 99 }).errors.age).toMatch(/Maximum value is 65/);
  });

  it('substitutes length bounds through the i18n interpolation', async () => {
    await i18n.changeLanguage('en');
    const s = schema([{
      id: 'code', label_ar: 'c', label_en: 'c',
      type: 'text', required: true, min_length: 4, max_length: 10,
    }]);
    expect(validateAll(s, { code: 'abc' }).errors.code).toMatch(/Minimum 4 characters/);
    expect(validateAll(s, { code: 'a'.repeat(11) }).errors.code).toMatch(/Maximum 10 characters/);
  });
});
