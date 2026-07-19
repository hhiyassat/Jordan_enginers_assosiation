import { useTranslation } from 'react-i18next';
import { LogOut } from 'lucide-react';
import { JEALogo } from '../components/JEALogo';
import { isActivePath, type NavItem } from './navItems';

/**
 * Sidebar contents (logo strap, nav list, sign-out) — extracted from
 * App.tsx (JORD-25). Border-side flips with the writing direction so
 * RTL and LTR layouts both look right.
 */
export function SidebarContent({
  items,
  pathname,
  onNavigate,
  onLogout,
}: {
  items: NavItem[];
  pathname: string;
  onNavigate: (to: string) => void;
  onLogout: () => void;
}): JSX.Element {
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  return (
    <>
      <div className="h-14 flex items-center px-4 border-b border-[#E0E0E0] gap-3">
        <div className="w-11 h-9 rounded-lg flex items-center justify-center shrink-0 px-1 bg-jea-topbar">
          <JEALogo size={36} dark />
        </div>
        <div>
          <p className="text-xs font-bold text-jea-text">{t('org.name')}</p>
        </div>
      </div>
      <nav className="flex-1 py-3 overflow-y-auto" aria-label={t('layout.mainMenu')}>
        {items.map(({ to, labelKey, Icon }) => {
          const active = isActivePath(pathname, to);
          return (
            <button
              key={to}
              onClick={() => onNavigate(to)}
              aria-current={active ? 'page' : undefined}
              className={`w-full flex items-center gap-3 px-4 py-2.5 text-sm transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-jea-primary/40 focus-visible:ring-inset ${
                active
                  ? `bg-jea-accent text-jea-primary font-semibold ${isRtl ? 'border-r-4' : 'border-l-4'} border-jea-primary`
                  : 'text-[#444] hover:bg-gray-50 hover:text-jea-primary'
              }`}
            >
              <Icon size={16} className={active ? 'text-jea-primary' : 'text-[#999]'} />
              <div className={`flex-1 ${isRtl ? 'text-right' : 'text-left'}`}>
                <div>{t(labelKey)}</div>
              </div>
            </button>
          );
        })}
      </nav>
      <div className="border-t border-[#E0E0E0] p-3">
        <button
          onClick={onLogout}
          className="w-full flex items-center gap-3 px-3 py-2 text-sm text-red-500 hover:bg-red-50 rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400"
        >
          <LogOut size={15} aria-hidden="true" />
          <span>{t('auth.signOut')}</span>
        </button>
      </div>
    </>
  );
}
