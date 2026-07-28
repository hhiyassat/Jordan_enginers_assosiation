import i18n from 'i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import { initReactI18next } from 'react-i18next';
import ar from './locales/ar.json';
import en from './locales/en.json';

/**
 * i18n bootstrap for the ESP portal.
 *
 * JORD-5 / JORD-38 / JORD-2 / JORD-6 / JORD-7 / JORD-13 / JORD-16 / JORD-23:
 * the portal used to render Arabic + English side-by-side ("متلصقتين")
 * throughout the shell — every nav item, header title, and CTA carried
 * both languages at once. This hard-coded translations inside JSX, so
 * changes required touching every page. react-i18next centralises the
 * strings and lets the user switch languages from the header. When the
 * chosen language changes, applyDocumentDirection() flips <html dir> +
 * <html lang> so RTL/LTR rendering follows automatically.
 *
 * Fallback chain: whatever the user last picked (localStorage) → the
 * browser's preferred language → Arabic. Arabic is the default because
 * JEA is the primary audience.
 */

export const SUPPORTED_LANGUAGES = ['ar', 'en'] as const;
export type SupportedLanguage = (typeof SUPPORTED_LANGUAGES)[number];

const STORAGE_KEY = 'esp_lang';

export function applyDocumentDirection(lang: string): void {
  // Guard for SSR / test environments where document may not exist.
  if (typeof document === 'undefined') return;
  const isArabic = lang.startsWith('ar');
  document.documentElement.lang = isArabic ? 'ar' : 'en';
  document.documentElement.dir  = isArabic ? 'rtl' : 'ltr';
}

void i18n
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    resources: {
      ar: { translation: ar },
      en: { translation: en },
    },
    fallbackLng: 'ar',
    supportedLngs: SUPPORTED_LANGUAGES as unknown as string[],
    interpolation: { escapeValue: false }, // React already escapes
    detection: {
      order: ['localStorage', 'navigator'],
      lookupLocalStorage: STORAGE_KEY,
      caches: ['localStorage'],
    },
    // Return the key itself (not undefined) if a translation is missing
    // so a typo is visible instead of an empty span.
    returnNull: false,
  });

// Whenever the language changes, sync <html dir>/<html lang>. Done once at
// bootstrap and on every subsequent switch (LanguageSwitcher fires it).
applyDocumentDirection(i18n.language || 'ar');
i18n.on('languageChanged', applyDocumentDirection);

export default i18n;
