import '@testing-library/jest-dom/vitest';
import { afterEach } from 'vitest';
import { cleanup } from '@testing-library/react';
// Preload the i18n bootstrap so useTranslation() has real resources in
// every test. Without this a component under test rendering t('foo.bar')
// would return the raw key instead of the translation and break every
// snapshot / getByText check that looked for the resolved string.
import '../i18n';

// Auto-unmount + reset DOM after each test.
afterEach(() => {
  cleanup();
});
