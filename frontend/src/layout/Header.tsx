import { Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Menu } from 'lucide-react';
import { JEALogo } from '../components/JEALogo';
import { LanguageSwitcher } from '../components/LanguageSwitcher';
import { NotificationBell } from '../components/NotificationBell';
import type { User } from '../types';
import { pageTitleKeyFor } from './pageTitle';

/**
 * App header (JEA strap, breadcrumb, notifications, avatar) — extracted
 * from App.tsx (JORD-25). Direction follows the current language so
 * icons + hamburger appear on the correct edge for both AR and EN.
 */
export function Header({ user, onMenuToggle }: {
  user: User | null;
  onMenuToggle: () => void;
}): JSX.Element {
  const location = useLocation();
  const { t, i18n } = useTranslation();
  const titleKey = pageTitleKeyFor(location.pathname);
  const initial = (user?.name ?? '').trim().charAt(0) || '?';
  const isRtl = i18n.language.startsWith('ar');

  return (
    <header className="bg-jea-topbar text-white h-14 flex items-center px-4 gap-4 shrink-0" dir={isRtl ? 'rtl' : 'ltr'}>
      <button
        onClick={onMenuToggle}
        aria-label={t('layout.openSidebar')}
        aria-expanded={undefined}
        className="p-1 rounded hover:bg-white/10 transition-colors lg:hidden focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
      >
        <Menu size={20} aria-hidden="true" />
      </button>
      <div className="flex items-center gap-2.5">
        <JEALogo size={38} dark />
        <div className="hidden sm:block">
          <div className="font-bold text-sm leading-tight">{t('org.name')}</div>
        </div>
      </div>
      <div className="flex-1 flex items-center gap-2 mx-4" aria-label="breadcrumb">
        <span className="text-white/70 text-xs" aria-hidden="true">›</span>
        <span className="text-white/90 text-sm font-medium">{t(titleKey)}</span>
      </div>
      <div className="flex items-center gap-2">
        <LanguageSwitcher compact />
        <NotificationBell />
        <Link
          to="/profile"
          className="w-7 h-7 rounded-full bg-jea-primary flex items-center justify-center text-xs font-bold text-white hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
          aria-label={user?.name ? t('layout.userAvatar', { name: user.name }) : t('profile.title')}
          title={user?.name ?? t('profile.title')}
        >
          {initial}
        </Link>
      </div>
    </header>
  );
}
