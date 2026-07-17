import '@testing-library/jest-dom/vitest';
import { afterEach } from 'vitest';
import { cleanup } from '@testing-library/react';

// Auto-unmount + reset DOM after each test.
afterEach(() => {
  cleanup();
});
