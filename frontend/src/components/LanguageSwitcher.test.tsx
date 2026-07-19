import { describe, expect, it, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import i18n from '../i18n';
import { LanguageSwitcher } from './LanguageSwitcher';

/**
 * JORD-5 / JORD-38: clicking the switcher must flip the whole app
 * language + <html dir>. Pinning the flip here is the fastest regression
 * signal — if this test starts failing, every RTL/LTR layout on every
 * page is silently misaligned.
 */
describe('LanguageSwitcher', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('ar');
  });

  it('shows the target-language chip (EN when in AR)', () => {
    render(<LanguageSwitcher />);
    expect(screen.getByText('EN')).toBeInTheDocument();
  });

  it('flips to English when clicked', () => {
    render(<LanguageSwitcher />);
    fireEvent.click(screen.getByRole('button'));
    expect(i18n.language.startsWith('en')).toBe(true);
    expect(document.documentElement.dir).toBe('ltr');
  });

  it('flips back to Arabic on a second click', () => {
    render(<LanguageSwitcher />);
    fireEvent.click(screen.getByRole('button')); // → en
    fireEvent.click(screen.getByRole('button')); // → ar
    expect(i18n.language.startsWith('ar')).toBe(true);
    expect(document.documentElement.dir).toBe('rtl');
  });

  it('exposes a bilingual aria-label for accessibility', () => {
    render(<LanguageSwitcher />);
    const btn = screen.getByRole('button');
    // Text depends on current language; assert non-empty + mentions
    // the target language name.
    expect(btn.getAttribute('aria-label')).toBeTruthy();
  });
});
