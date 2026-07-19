import { describe, expect, it, beforeEach } from 'vitest';
import i18n, { applyDocumentDirection, SUPPORTED_LANGUAGES } from './index';

/**
 * JORD-5 / JORD-38 regression: locking the bootstrap contract so a
 * future refactor can't break the two things every screen depends on:
 *   1. Both locales resolve every key we ship — no bare-key leaks.
 *   2. Switching language flips <html dir> + <html lang>.
 */

describe('i18n bootstrap', () => {
  beforeEach(async () => {
    // Reset to Arabic between tests so ordering doesn't leak state.
    await i18n.changeLanguage('ar');
  });

  it('exposes both supported languages', () => {
    expect(SUPPORTED_LANGUAGES).toEqual(['ar', 'en']);
  });

  it('resolves core header keys in Arabic', () => {
    expect(i18n.t('org.name')).toBe('نقابة المهندسين الأردنيين');
    expect(i18n.t('nav.dashboard')).toBe('الرئيسية');
    expect(i18n.t('auth.signIn')).toBe('تسجيل الدخول');
  });

  it('resolves core header keys in English after switch', async () => {
    await i18n.changeLanguage('en');
    expect(i18n.t('org.name')).toBe('Jordan Engineers Association');
    expect(i18n.t('nav.dashboard')).toBe('Dashboard');
    expect(i18n.t('auth.signIn')).toBe('Sign In');
  });

  it('mirrors every top-level key between ar and en (no orphan translations)', () => {
    const arKeys = Object.keys(i18n.getResourceBundle('ar', 'translation') as Record<string, unknown>);
    const enKeys = Object.keys(i18n.getResourceBundle('en', 'translation') as Record<string, unknown>);
    expect(arKeys.sort()).toEqual(enKeys.sort());
  });

  it('interpolates {{year}} in copyright', () => {
    expect(i18n.t('copyright', { year: 2026 })).toContain('2026');
  });

  it('interpolates {{name}} in the avatar label', () => {
    expect(i18n.t('layout.userAvatar', { name: 'Hussein' })).toContain('Hussein');
  });
});

describe('applyDocumentDirection', () => {
  it('sets rtl + ar for Arabic', () => {
    applyDocumentDirection('ar');
    expect(document.documentElement.dir).toBe('rtl');
    expect(document.documentElement.lang).toBe('ar');
  });

  it('sets ltr + en for English', () => {
    applyDocumentDirection('en');
    expect(document.documentElement.dir).toBe('ltr');
    expect(document.documentElement.lang).toBe('en');
  });

  it('treats ar-JO (BCP47) as Arabic', () => {
    applyDocumentDirection('ar-JO');
    expect(document.documentElement.dir).toBe('rtl');
  });
});
