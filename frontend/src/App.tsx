import React, { createContext, Suspense, useContext, useEffect, useState } from 'react';
import { BrowserRouter, Navigate, Route, Routes, useNavigate, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  LogOut, Home, LayoutDashboard, FileText, Settings, ClipboardList, PlusCircle, ShieldCheck, Zap,
  Bell, Menu, Building2, User as UserIcon, Eye, EyeOff, AlertTriangle, LogIn,
} from 'lucide-react';
import { authApi, setUnauthorizedHandler } from './api/client';
import type { User } from './types';
import { JEALogo } from './components/JEALogo';
import { SkipToContent } from './components/ui/SkipToContent';
import { Button } from './components/ui/Button';
import { TextField } from './components/ui/FormField';
import { Captcha } from './components/ui/Captcha';
import { ErrorBoundary } from './components/ErrorBoundary';
import { LanguageSwitcher } from './components/LanguageSwitcher';

// ── Pages (code-split via React.lazy) ────────────────────────────────────────
//
// JORD-32: every route used to live in the same JS chunk — an applicant
// downloaded the entire admin/reviewer surface on first load. Splitting on
// route boundaries means each user role fetches only what it can reach.
// Pages are named exports (not defaults) — the tiny shim below re-shapes
// each module as { default } so React.lazy is happy.
const ServiceList             = React.lazy(() => import('./pages/applicant/ServiceList').then(m => ({ default: m.ServiceList })));
const CategoryServicesView    = React.lazy(() => import('./pages/applicant/CategoryServicesView').then(m => ({ default: m.CategoryServicesView })));
const ProjectsList            = React.lazy(() => import('./pages/applicant/ProjectsList').then(m => ({ default: m.ProjectsList })));
const ProjectDetail           = React.lazy(() => import('./pages/applicant/ProjectDetail').then(m => ({ default: m.ProjectDetail })));
const Dashboard               = React.lazy(() => import('./pages/applicant/Dashboard').then(m => ({ default: m.Dashboard })));
const Apply                   = React.lazy(() => import('./pages/applicant/Apply').then(m => ({ default: m.Apply })));
const MyApplications          = React.lazy(() => import('./pages/applicant/MyApplications').then(m => ({ default: m.MyApplications })));
const ReviewQueue             = React.lazy(() => import('./pages/reviewer/ReviewQueue').then(m => ({ default: m.ReviewQueue })));
const ReviewPanel             = React.lazy(() => import('./pages/reviewer/ReviewPanel').then(m => ({ default: m.ReviewPanel })));
const AdminDashboard          = React.lazy(() => import('./pages/admin/AdminDashboard').then(m => ({ default: m.AdminDashboard })));
const IntegrationCycles       = React.lazy(() => import('./pages/admin/IntegrationCycles').then(m => ({ default: m.IntegrationCycles })));
const IntegrationCycleDetail  = React.lazy(() => import('./pages/admin/IntegrationCycleDetail').then(m => ({ default: m.IntegrationCycleDetail })));
const NewService              = React.lazy(() => import('./pages/admin/NewService').then(m => ({ default: m.NewService })));
const ServicesList            = React.lazy(() => import('./pages/admin/ServicesList').then(m => ({ default: m.ServicesList })));
const EditService             = React.lazy(() => import('./pages/admin/EditService').then(m => ({ default: m.EditService })));
const UserManagement          = React.lazy(() => import('./pages/admin/UserManagement').then(m => ({ default: m.UserManagement })));
const ChangeCredentials       = React.lazy(() => import('./pages/auth/ChangeCredentials').then(m => ({ default: m.ChangeCredentials })));

// ── Auth Context ──────────────────────────────────────────────────────────────

interface AuthContextType {
  user: User | null;
  token: string | null;
  login: (token: string, user: User) => void;
  logout: () => void;
}

// Exported so tests can wrap components in a synthetic provider without
// spinning up the full AuthProvider (which fires network calls on mount).
export const AuthContext = createContext<AuthContextType>({
  user: null, token: null,
  login: () => {}, logout: () => {},
});

export const useAuth = () => useContext(AuthContext);

function AuthProvider({ children }: { children: React.ReactNode }) {
  const [token, setToken] = useState<string | null>(sessionStorage.getItem('esp_token'));
  const [user, setUser]   = useState<User | null>(null);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    if (token) {
      authApi.me()
        .then(r => setUser(r.user))
        .catch(() => { sessionStorage.removeItem('esp_token'); setToken(null); })
        .finally(() => setReady(true));
    } else {
      setReady(true);
    }
  }, [token]);

  // Re-verify the session whenever the tab regains focus. Fixes the
  // stale-role bug: if the user logged in as a different role in another
  // tab (single-session policy revoked this tab's token), calling /auth/me
  // will either update the cached user or clear the session cleanly.
  useEffect(() => {
    if (!token) return;
    const onFocus = () => {
      authApi.me()
        .then(r => setUser(r.user))
        .catch(() => { sessionStorage.removeItem('esp_token'); setToken(null); });
    };
    window.addEventListener('focus', onFocus);
    return () => window.removeEventListener('focus', onFocus);
  }, [token]);

  const login = (t: string, u: User) => {
    sessionStorage.setItem('esp_token', t);
    setToken(t);
    setUser(u);
  };

  const logout = () => {
    authApi.logout().catch(() => {});
    sessionStorage.removeItem('esp_token');
    setToken(null);
    setUser(null);
  };

  // JORD-29: give the api client a way to invalidate the session when it
  // sees a 401, so callers don't have to check status codes themselves.
  // The api client fires this once; RequireAuth then bounces to /login on
  // the next render because user becomes null.
  useEffect(() => {
    setUnauthorizedHandler(() => {
      sessionStorage.removeItem('esp_token');
      setToken(null);
      setUser(null);
    });
    return () => setUnauthorizedHandler(null);
  }, []);

  if (!ready) return <div className="flex items-center justify-center h-screen"><div className="animate-spin w-10 h-10 border-4 border-blue-500 border-t-transparent rounded-full" /></div>;

  return (
    <AuthContext.Provider value={{ user, token, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

// ── Login Page ────────────────────────────────────────────────────────────────

function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const [email, setEmail]         = useState('');
  const [password, setPassword]   = useState('');
  const [showPass, setShowPass]   = useState(false);
  const [captcha, setCaptcha]     = useState({ id: '', answer: '' });
  const [captchaKey, setCaptchaKey] = useState(0);
  const [captchaError, setCaptchaError] = useState('');
  const [error, setError]         = useState('');
  const [loading, setLoading]     = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setCaptchaError('');
    if (!email.trim() || !password.trim()) {
      setError(t('auth.credentialsRequired'));
      return;
    }
    if (!captcha.answer || captcha.answer.length < 6) {
      setCaptchaError(t('auth.captchaRequired'));
      return;
    }
    setLoading(true);
    try {
      const r = await authApi.login(email, password, captcha);
      login(r.token, r.user);
      navigate('/');
    } catch (err: unknown) {
      const e = err as Error & { errors?: Record<string, string[]>; status?: number };
      // Captcha is single-use — any failure invalidates the challenge on the
      // server. Bump the key so the widget fetches a fresh SVG and clears
      // the user's stale entry regardless of which validation failed.
      setCaptchaKey(k => k + 1);
      setCaptcha({ id: '', answer: '' });
      const captchaMsg = e.errors?.captcha_answer?.[0];
      if (captchaMsg) {
        setCaptchaError(captchaMsg);
      } else {
        setError(e.message || t('auth.loginError'));
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div
      className="min-h-screen flex flex-col items-center justify-center relative overflow-hidden"
      dir={isRtl ? 'rtl' : 'ltr'}
      style={{ background: 'linear-gradient(145deg, #0F5A99 0%, #1A77BC 55%, #3D90C8 100%)' }}
    >
      <div className="absolute inset-0 pointer-events-none overflow-hidden">
        <div className="absolute -top-32 -left-32 w-96 h-96 rounded-full bg-white/5" />
        <div className="absolute -top-16 -left-16 w-64 h-64 rounded-full bg-white/5" />
        <div className="absolute -bottom-40 -right-40 w-[32rem] h-[32rem] rounded-full bg-white/5" />
        <div className="absolute top-8 right-8 flex items-end gap-0.5 opacity-10">
          {[40,36,32,28,24,20,16,12,9,6].map((h,i) => (
            <div key={i} style={{ height: h, width: 3 }} className="bg-white rounded-sm" />
          ))}
        </div>
        <div className="absolute bottom-8 left-8 flex items-end gap-0.5 opacity-10">
          {[6,9,12,16,20,24,28,32,36,40].map((h,i) => (
            <div key={i} style={{ height: h, width: 3 }} className="bg-white rounded-sm" />
          ))}
        </div>
      </div>

      {/* Language switcher pinned to the top corner so the user can
          flip the whole login page before they've authenticated. */}
      <div className={`absolute top-4 ${isRtl ? 'left-4' : 'right-4'} z-20`}>
        <LanguageSwitcher compact />
      </div>

      <div className="relative z-10 w-full max-w-md mx-4">
        <div className="flex flex-col items-center mb-8">
          <div className="bg-white rounded-2xl px-5 py-4 shadow-xl mb-4 inline-flex items-center gap-4">
            <JEALogo size={48} />
            <div className={isRtl ? 'text-right' : 'text-left'}>
              <p className="text-sm font-black text-jea-text leading-tight">{t('org.name')}</p>
            </div>
          </div>
          <div className="mt-1 px-3 py-1 bg-white/15 rounded-full text-white/80 text-xs font-semibold">
            {t('org.portal')}
          </div>
        </div>

        <div className="bg-white rounded-2xl shadow-2xl overflow-hidden">
          <div className="bg-jea-bg px-6 py-4 border-b border-jea-border">
            <h2 className="text-base font-black text-jea-text">{t('auth.signIn')}</h2>
          </div>
          <form onSubmit={handleSubmit} className="px-6 py-6 flex flex-col gap-5" noValidate>
            <TextField
              label={t('auth.email')}
              labelEn={t('auth.email')}
              value={email}
              onChange={setEmail}
              type="email"
              autoComplete="email"
              placeholder="admin@demo.esp"
              required
              startIcon={<Building2 size={16} aria-hidden="true" />}
            />

            <TextField
              label={t('auth.password')}
              labelEn={t('auth.password')}
              value={password}
              onChange={setPassword}
              type={showPass ? 'text' : 'password'}
              autoComplete="current-password"
              placeholder="••••••••"
              required
              startIcon={<UserIcon size={16} aria-hidden="true" />}
              endAdornment={
                <button
                  type="button"
                  onClick={() => setShowPass(s => !s)}
                  aria-label={showPass ? t('auth.hidePassword') : t('auth.showPassword')}
                  aria-pressed={showPass}
                  className="text-jea-muted hover:text-jea-primary transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-jea-primary/40 rounded"
                >
                  {showPass ? <EyeOff size={15} aria-hidden="true" /> : <Eye size={15} aria-hidden="true" />}
                </button>
              }
            />

            <Captcha
              onChange={setCaptcha}
              resetKey={captchaKey}
              error={captchaError}
            />

            {error && (
              <div role="alert" className="flex items-center gap-2 bg-red-50 border border-red-200 rounded-lg px-3 py-2.5 text-sm text-red-700">
                <AlertTriangle size={14} className="shrink-0" aria-hidden="true" />
                {error}
              </div>
            )}

            <Button
              type="submit"
              loading={loading}
              size="lg"
              className="w-full"
              icon={<LogIn size={16} />}
            >
              {loading ? t('auth.signingIn') : t('auth.signIn')}
            </Button>

            {/* JORD-40: demo credentials only render in dev builds so they
                don't leak on the deployed portal. import.meta.env.DEV is
                statically replaced by Vite at build time — the block is
                dead-code-eliminated from the production bundle. */}
            {import.meta.env.DEV && (
              <div className="text-[10px] text-jea-muted bg-jea-bg rounded-lg px-3 py-2 leading-relaxed text-center">
                <p className="font-bold text-jea-text mb-0.5">{t('auth.demoAccountsTitle')}</p>
                <p dir="ltr">admin@demo.esp · staff@demo.esp · auditor@demo.esp · ahmed@demo.esp</p>
              </div>
            )}
          </form>
        </div>

        <p className="text-center text-white/40 text-[11px] mt-6">
          {t('copyright', { year: new Date().getFullYear() })}
        </p>
      </div>
    </div>
  );
}

// ── Layout + Nav ──────────────────────────────────────────────────────────────

interface NavItem {
  to: string;
  /** i18n key resolved at render time; nav items are language-agnostic now. */
  labelKey: string;
  Icon: React.ComponentType<{ size?: number; className?: string }>;
}

export function navItemsForRole(role: User['role'] | undefined): NavItem[] {
  const items: NavItem[] = [];
  if (!role) return items;

  if (role === 'applicant') {
    items.push({ to: '/dashboard',       labelKey: 'nav.dashboard',    Icon: LayoutDashboard });
    items.push({ to: '/services',        labelKey: 'nav.services',     Icon: Home });
    items.push({ to: '/my-applications', labelKey: 'nav.myRequests',   Icon: FileText });
  }
  if (role === 'staff' || role === 'auditor' || role === 'admin') {
    items.push({ to: '/review/queue',    labelKey: 'nav.review',       Icon: ShieldCheck });
  }
  if (role === 'admin' || role === 'superuser') {
    items.push({ to: '/admin',                 labelKey: 'nav.admin',           Icon: Settings });
    items.push({ to: '/admin/services',        labelKey: 'nav.servicesAdmin',   Icon: ClipboardList });
    items.push({ to: '/admin/services/new',    labelKey: 'nav.newService',      Icon: PlusCircle });
    items.push({ to: '/admin/integration',     labelKey: 'nav.integration',     Icon: Zap });
    // Both admin and superuser get the user-management lane. The backend
    // decides which roles the actor can act on inside the page.
    items.push({ to: '/admin/users',           labelKey: 'nav.users',           Icon: UserIcon });
  }
  return items;
}

/**
 * Route-to-title mapping. Returns an i18n key rather than the resolved
 * string so <Header /> can rerender the title when the language flips
 * without any extra plumbing.
 */
export function pageTitleKeyFor(pathname: string): string {
  if (pathname === '/dashboard') return 'pageTitle.dashboard';
  if (pathname === '/services' || pathname.startsWith('/services/') || pathname.startsWith('/apply/')) return 'pageTitle.services';
  if (pathname.startsWith('/projects')) return 'pageTitle.projects';
  if (pathname.startsWith('/my-applications')) return 'pageTitle.myRequests';
  if (pathname.startsWith('/review')) return 'pageTitle.review';
  if (pathname === '/admin') return 'pageTitle.admin';
  if (pathname.startsWith('/admin/services/new')) return 'pageTitle.newService';
  if (pathname.startsWith('/admin/services')) return 'pageTitle.servicesAdmin';
  if (pathname.startsWith('/admin/integration')) return 'pageTitle.integration';
  return 'pageTitle.home';
}

function isActivePath(pathname: string, to: string): boolean {
  if (to === '/services') return pathname === '/services' || pathname.startsWith('/services/') || pathname.startsWith('/apply/') || pathname.startsWith('/projects');
  if (to === '/admin')    return pathname === '/admin';
  return pathname.startsWith(to);
}

function Header({ user, onMenuToggle }: { user: User | null; onMenuToggle: () => void }) {
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
        <span className="text-white/40 text-xs" aria-hidden="true">›</span>
        <span className="text-white/90 text-sm font-medium">{t(titleKey)}</span>
      </div>
      <div className="flex items-center gap-2">
        <LanguageSwitcher compact />
        <button
          aria-label={t('layout.notifications')}
          className="p-1.5 rounded hover:bg-white/10 transition-colors relative focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
        >
          <Bell size={17} aria-hidden="true" />
          <span className="absolute top-0.5 right-0.5 w-2 h-2 bg-orange-400 rounded-full" aria-hidden="true" />
        </button>
        <div
          className="w-7 h-7 rounded-full bg-jea-primary flex items-center justify-center text-xs font-bold"
          aria-label={user?.name ? t('layout.userAvatar', { name: user.name }) : undefined}
          title={user?.name}
        >
          {initial}
        </div>
      </div>
    </header>
  );
}

function SidebarContent({
  items,
  pathname,
  onNavigate,
  onLogout,
}: {
  items: NavItem[];
  pathname: string;
  onNavigate: (to: string) => void;
  onLogout: () => void;
}) {
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

function Layout({ children }: { children: React.ReactNode }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const [sidebarOpen, setSidebarOpen] = useState(false);

  const items = navItemsForRole(user?.role);
  const handleLogout = () => { logout(); navigate('/login'); };
  const handleNavigate = (to: string) => { navigate(to); setSidebarOpen(false); };

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

// ── Route guard ───────────────────────────────────────────────────────────────

function RequireAuth({ children }: { children: React.ReactNode }) {
  const { user } = useAuth();
  const location = useLocation();
  if (!user) return <Navigate to="/login" replace />;
  // First-login gate — the bootstrap password is one-time-use. Redirect
  // to /auth/change-credentials before rendering anything else, unless the
  // user is already on that page.
  if (user.must_change_password && location.pathname !== '/auth/change-credentials') {
    return <Navigate to="/auth/change-credentials" replace />;
  }
  return <>{children}</>;
}

/**
 * JORD-42: the inverse guard — sends an already-authenticated user off
 * the /login page. Without this, hitting the browser Back button after
 * signing in would drop the user on the login form even though they
 * still had a valid session, which was confusing UX. Exported for tests.
 */
export function RequireGuest({ children }: { children: React.ReactNode }) {
  const { user } = useAuth();
  if (user) return <Navigate to="/" replace />;
  return <>{children}</>;
}

/**
 * Blocks users who can't manage the roster. Admin AND superuser both
 * pass — the page itself filters actions by the actor's tier.
 */
function RequireUserManager({ children }: { children: React.ReactNode }) {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  if (!user.can_manage_users) return <Navigate to="/" replace />;
  return <>{children}</>;
}

/**
 * Which roles are allowed on /admin/*. Pure so the boundary is testable
 * without mounting the router — the RequireAdmin guard just wraps it.
 */
export function canReachAdmin(role: User['role'] | undefined): boolean {
  return role === 'admin' || role === 'superuser';
}

/**
 * Which roles are allowed on /review/*. Note that superuser is
 * deliberately excluded — the superuser role is user-management only,
 * not a god-mode. Backend route middleware matches (role:staff,auditor,admin).
 */
export function canReachReviewer(role: User['role'] | undefined): boolean {
  return role === 'staff' || role === 'auditor' || role === 'admin';
}

/** Blocks non-admins from admin-only routes (admin AND superuser pass). */
function RequireAdmin({ children }: { children: React.ReactNode }) {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  if (!canReachAdmin(user.role)) return <Navigate to="/" replace />;
  return <>{children}</>;
}

/** Blocks non-reviewers from /review/* — same UX story as RequireAdmin. */
function RequireReviewer({ children }: { children: React.ReactNode }) {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  if (!canReachReviewer(user.role)) return <Navigate to="/" replace />;
  return <>{children}</>;
}

/** Blocks non-applicants from applicant-only routes */
function RequireApplicant({ children }: { children: React.ReactNode }) {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  if (user.role !== 'applicant') return <Navigate to="/" replace />;
  return <>{children}</>;
}

function HomeRedirect() {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  if (user.role === 'superuser')                        return <Navigate to="/admin/users" replace />;
  if (user.role === 'admin')                            return <Navigate to="/admin" replace />;
  if (user.role === 'staff' || user.role === 'auditor') return <Navigate to="/review/queue" replace />;
  return <Navigate to="/dashboard" replace />;
}

// ── Root ──────────────────────────────────────────────────────────────────────

/**
 * Route-wide Suspense fallback for the code-split page chunks. Kept
 * intentionally spartan — the chunks are small; a full skeleton would
 * flicker on cached loads. RTL because the whole shell is RTL.
 */
function RouteSuspense({ children }: { children: React.ReactNode }) {
  return (
    <Suspense
      fallback={
        <div
          className="flex items-center justify-center h-full min-h-[240px]"
          role="status"
          aria-live="polite"
          aria-label="جارٍ التحميل"
        >
          <div className="animate-spin w-8 h-8 border-4 border-jea-primary border-t-transparent rounded-full" />
        </div>
      }
    >
      {children}
    </Suspense>
  );
}

export default function App() {
  return (
    <BrowserRouter>
      <ErrorBoundary>
        <AuthProvider>
          <RouteSuspense>
        <Routes>
          <Route path="/login" element={<RequireGuest><LoginPage /></RequireGuest>} />

          {/* First-login credential change — reachable by an authenticated user
              carrying the must_change_password flag. Rendered without Layout so
              the sidebar/nav don't leak features they can't use yet. */}
          <Route path="/auth/change-credentials" element={<RequireAuth><ChangeCredentials /></RequireAuth>} />

          <Route path="/" element={<RequireAuth><Layout><HomeRedirect /></Layout></RequireAuth>} />

          {/* Applicant-only */}
          <Route path="/dashboard"               element={<RequireApplicant><Layout><Dashboard /></Layout></RequireApplicant>} />
          <Route path="/services"                element={<RequireApplicant><Layout><ServiceList /></Layout></RequireApplicant>} />
          <Route path="/services/:categoryCode"  element={<RequireApplicant><Layout><CategoryServicesView /></Layout></RequireApplicant>} />
          <Route path="/projects"                element={<RequireApplicant><Layout><ProjectsList /></Layout></RequireApplicant>} />
          <Route path="/projects/:projectId"     element={<RequireApplicant><Layout><ProjectDetail /></Layout></RequireApplicant>} />
          <Route path="/apply/:serviceCode"      element={<RequireApplicant><Layout><Apply /></Layout></RequireApplicant>} />
          <Route path="/my-applications"   element={<RequireApplicant><Layout><MyApplications /></Layout></RequireApplicant>} />

          {/* Reviewer — staff / auditor / admin. Applicants navigating here
              used to see the page render followed by a backend 403; now the
              SPA redirects them to / before the page mounts. */}
          <Route path="/review/queue"      element={<RequireReviewer><Layout><ReviewQueue /></Layout></RequireReviewer>} />
          <Route path="/review/:id"        element={<RequireReviewer><Layout><ReviewPanel /></Layout></RequireReviewer>} />

          {/* Admin — every /admin/* route requires admin or superuser.
              RequireAuth alone let staff/auditor into the Admin Dashboard
              because the route wasn't role-gated at the SPA layer even
              though the backend was 403'ing every API call. Fix belongs
              here so the page never renders for a role that can't use it. */}
          <Route path="/admin"                      element={<RequireAdmin><Layout><AdminDashboard /></Layout></RequireAdmin>} />
          <Route path="/admin/services"             element={<RequireAdmin><Layout><ServicesList /></Layout></RequireAdmin>} />
          <Route path="/admin/services/new"         element={<RequireAdmin><Layout><NewService /></Layout></RequireAdmin>} />
          <Route path="/admin/services/:id/edit"    element={<RequireAdmin><Layout><EditService /></Layout></RequireAdmin>} />
          <Route path="/admin/integration"          element={<RequireAdmin><Layout><IntegrationCycles /></Layout></RequireAdmin>} />
          <Route path="/admin/integration/:id"      element={<RequireAdmin><Layout><IntegrationCycleDetail /></Layout></RequireAdmin>} />

          {/* User management — admin + superuser */}
          <Route path="/admin/users" element={<RequireUserManager><Layout><UserManagement /></Layout></RequireUserManager>} />

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
          </RouteSuspense>
        </AuthProvider>
      </ErrorBoundary>
    </BrowserRouter>
  );
}
