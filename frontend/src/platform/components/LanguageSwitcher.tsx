import { useTranslation } from 'react-i18next';
import { Languages } from 'lucide-react';
import { SUPPORTED_LANGUAGES, type SupportedLanguage } from '../../i18n';

/**
 * Small header control that flips between ar/en. Two-language toggle
 * for now — if a third locale is ever added the button becomes a
 * dropdown, but a single button is nicer for a binary switch.
 *
 * Wired to i18n.changeLanguage() which triggers our languageChanged
 * listener, so <html dir> + persistence to localStorage happen for free.
 */
export function LanguageSwitcher({ compact = false }: { compact?: boolean }): JSX.Element {
  const { i18n, t } = useTranslation();
  const current = (i18n.language.startsWith('ar') ? 'ar' : 'en') as SupportedLanguage;
  const next: SupportedLanguage = current === 'ar' ? 'en' : 'ar';

  const nextLabel = next === 'ar' ? t('language.arabic') : t('language.english');
  // "EN"/"ع" is the compact visible chip — the aria-label carries the
  // full "Switch to X" so screen readers announce the action.
  const chip = next === 'en' ? 'EN' : 'ع';

  return (
    <button
      type="button"
      onClick={() => void i18n.changeLanguage(next)}
      aria-label={t('language.switchTo', { lang: nextLabel })}
      title={t('language.switchTo', { lang: nextLabel })}
      className={
        compact
          ? 'inline-flex items-center gap-1 text-xs font-semibold text-white/80 hover:text-white transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60 rounded px-1.5 py-0.5'
          : 'inline-flex items-center gap-1.5 text-xs font-semibold text-jea-text hover:text-jea-primary transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-jea-primary/40 rounded px-2 py-1'
      }
      {...(SUPPORTED_LANGUAGES.length > 0 ? {} : {})}
    >
      <Languages size={14} aria-hidden="true" />
      <span>{chip}</span>
    </button>
  );
}
