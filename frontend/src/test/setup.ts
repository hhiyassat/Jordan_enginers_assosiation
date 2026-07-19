import '@testing-library/jest-dom/vitest';
import { afterEach, beforeEach } from 'vitest';
import { cleanup } from '@testing-library/react';
// Preload the i18n bootstrap so useTranslation() has real resources in
// every test. Without this a component under test rendering t('foo.bar')
// would return the raw key instead of the translation and break every
// snapshot / getByText check that looked for the resolved string.
import i18n from '../i18n';

// Reset i18n to Arabic before every test — the LanguageSwitcher spec
// flips to English mid-run, and without this reset any downstream page
// test that looks for an Arabic label would find the English one instead.
beforeEach(() => {
  if (i18n.language !== 'ar') void i18n.changeLanguage('ar');
});

// Auto-unmount + reset DOM after each test.
afterEach(() => {
  cleanup();
});
