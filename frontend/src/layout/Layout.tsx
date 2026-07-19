import React, { useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../auth/AuthContext';
import { SkipToContent } from '../components/ui/SkipToContent';
import { Header } from './Header';
import { SidebarContent } from './SidebarContent';
import { navItemsForRole } from './navItems';

/**
 * Signed-in shell wrapper — extracted from App.tsx (JORD-25).
 *
 * Owns the responsive sidebar (fixed drawer under lg, permanent aside
 * from lg up), the header, and the <main> scroll region. Every
 * page-body chunk renders inside {children}.
 */
export function Layout({ children }: { children: React.ReactNode }): JSX.Element {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const [sidebarOpen, setSidebarOpen] = useState(false);

  const items = navItemsForRole(user?.role);
  const handleLogout = (): void => { logout(); navigate('/login'); };
  const handleNavigate = (to: string): void => { navigate(to); setSidebarOpen(false); };

  return (
    <div className="h-screen flex flex-col overflow-hidden bg-jea-bg" dir={isRtl ? 'rtl' : 'ltr'}>
      <SkipToContent />
      <Header user={user} onMenuToggle={() => setSidebarOpen(o => !o)} />

      <div className="flex flex-1 overflow-hidden">
        <aside
          className={`hidden lg:flex w-60 shrink-0 bg-white ${isRtl ? 'border-l' : 'border-r'} border-[#E0E0E0] flex-col h-full`}
          aria-label={t('layout.sidebarLabel')}
        >
          <SidebarContent
            items={items}
            pathname={location.pathname}
            onNavigate={handleNavigate}
            onLogout={handleLogout}
          />
        </aside>

        {sidebarOpen && (
          <div
            className="fixed inset-0 bg-black/40 z-20 lg:hidden"
            onClick={() => setSidebarOpen(false)}
            aria-hidden="true"
          />
        )}
        <aside
          className={`fixed top-0 ${isRtl ? 'right-0 border-l' : 'left-0 border-r'} h-full w-60 bg-white border-[#E0E0E0] z-30 flex flex-col transform transition-transform duration-300 lg:hidden ${
            sidebarOpen
              ? 'translate-x-0'
              : (isRtl ? 'translate-x-full' : '-translate-x-full')
          }`}
          aria-label={t('layout.sidebarLabel')}
          aria-hidden={!sidebarOpen}
        >
          <SidebarContent
            items={items}
            pathname={location.pathname}
            onNavigate={handleNavigate}
            onLogout={handleLogout}
          />
        </aside>

        <main
          id="main-content"
          tabIndex={-1}
          className="flex-1 overflow-y-auto focus:outline-none"
        >
          {children}
        </main>
      </div>
    </div>
  );
}
