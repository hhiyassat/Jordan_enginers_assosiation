import React, { createContext, useContext, useEffect, useState } from 'react';
import { BrowserRouter, Navigate, Route, Routes, useNavigate, useLocation } from 'react-router-dom';
import {
  LogOut, Home, LayoutDashboard, FileText, Settings, ClipboardList, PlusCircle, ShieldCheck, Zap,
  Bell, Menu, Building2, User as UserIcon, Eye, EyeOff, AlertTriangle, LogIn,
} from 'lucide-react';
import { authApi } from './api/client';
import type { User } from './types';
import { JEALogo } from './components/JEALogo';
import { SkipToContent } from './components/ui/SkipToContent';
import { Button } from './components/ui/Button';
import { TextField } from './components/ui/FormField';
import { Captcha } from './components/ui/Captcha';

// ── Pages ─────────────────────────────────────────────────────────────────────
import { ServiceList }             from './pages/applicant/ServiceList';
import { CategoryServicesView }    from './pages/applicant/CategoryServicesView';
import { ProjectsList }            from './pages/applicant/ProjectsList';
import { ProjectDetail }           from './pages/applicant/ProjectDetail';
import { Dashboard }               from './pages/applicant/Dashboard';
import { Apply }                   from './pages/applicant/Apply';
import { MyApplications }          from './pages/applicant/MyApplications';
import { ReviewQueue }             from './pages/reviewer/ReviewQueue';
import { ReviewPanel }             from './pages/reviewer/ReviewPanel';
import { AdminDashboard }          from './pages/admin/AdminDashboard';
import { IntegrationCycles }       from './pages/admin/IntegrationCycles';
import { IntegrationCycleDetail }  from './pages/admin/IntegrationCycleDetail';
import { NewService }              from './pages/admin/NewService';
import { ServicesList }            from './pages/admin/ServicesList';
import { EditService }             from './pages/admin/EditService';
import { UserManagement }          from './pages/admin/UserManagement';
import { ChangeCredentials }       from './pages/auth/ChangeCredentials';

// ── Auth Context ──────────────────────────────────────────────────────────────

interface AuthContextType {
  user: User | null;
  token: string | null;
  login: (token: string, user: User) => void;
  logout: () => void;
}

const AuthContext = createContext<AuthContextType>({
  user: null, token: null,
  login: () => {}, logout: () => {},
});

export const useAuth = () => useContext(AuthContext);

function AuthProvider({ children }: { children: React.ReactNode }) {
  const [token, setToken] = useState<string | null>(localStorage.getItem('esp_token'));
  const [user, setUser]   = useState<User | null>(null);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    if (token) {
      authApi.me()
        .then(r => setUser(r.user))
        .catch(() => { localStorage.removeItem('esp_token'); setToken(null); })
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
        .catch(() => { localStorage.removeItem('esp_token'); setToken(null); });
    };
    window.addEventListener('focus', onFocus);
    return () => window.removeEventListener('focus', onFocus);
  }, [token]);

  const login = (t: string, u: User) => {
    localStorage.setItem('esp_token', t);
    setToken(t);
    setUser(u);
  };

  const logout = () => {
    authApi.logout().catch(() => {});
    localStorage.removeItem('esp_token');
    setToken(null);
    setUser(null);
  };

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
      setError('يرجى إدخال البريد الإلكتروني وكلمة المرور');
      return;
    }
    if (!captcha.answer || captcha.answer.length < 6) {
      setCaptchaError('يرجى إدخال رمز التحقق كاملاً');
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
        setError(e.message || 'خطأ في تسجيل الدخول');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div
      className="min-h-screen flex flex-col items-center justify-center relative overflow-hidden"
      dir="rtl"
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

      <div className="relative z-10 w-full max-w-md mx-4">
        <div className="flex flex-col items-center mb-8">
          <div className="bg-white rounded-2xl px-5 py-4 shadow-xl mb-4 inline-flex items-center gap-4">
            <JEALogo size={48} />
            <div className="text-right">
              <p className="text-sm font-black text-jea-text leading-tight">نقابة المهندسين الأردنيين</p>
              <p className="text-[11px] text-jea-muted">Jordan Engineers Association</p>
            </div>
          </div>
          <div className="mt-1 px-3 py-1 bg-white/15 rounded-full text-white/80 text-xs font-semibold">
            بوابة الخدمات الإلكترونية
          </div>
        </div>

        <div className="bg-white rounded-2xl shadow-2xl overflow-hidden">
          <div className="bg-jea-bg px-6 py-4 border-b border-jea-border">
            <h2 className="text-base font-black text-jea-text" lang="ar">تسجيل الدخول</h2>
            <p className="text-xs text-jea-muted mt-0.5" lang="en" dir="ltr">Sign In</p>
          </div>
          <form onSubmit={handleSubmit} className="px-6 py-6 flex flex-col gap-5" noValidate>
            <TextField
              label="البريد الإلكتروني"
              labelEn="Email"
              value={email}
              onChange={setEmail}
              type="email"
              autoComplete="email"
              placeholder="admin@demo.esp"
              required
              startIcon={<Building2 size={16} aria-hidden="true" />}
            />

            <TextField
              label="كلمة المرور"
              labelEn="Password"
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
                  aria-label={showPass ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'}
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
              {loading ? 'جارٍ تسجيل الدخول...' : (<><span lang="ar">تسجيل الدخول</span> · <span lang="en" dir="ltr">Sign in</span></>)}
            </Button>

            <div className="text-[10px] text-jea-muted bg-jea-bg rounded-lg px-3 py-2 leading-relaxed text-center">
              <p className="font-bold text-jea-text mb-0.5" lang="ar">حسابات تجريبية (Demo1234!)</p>
              <p dir="ltr">admin@demo.esp · staff@demo.esp · auditor@demo.esp · ahmed@demo.esp</p>
            </div>
          </form>
        </div>

        <p className="text-center text-white/40 text-[11px] mt-6">
          © {new Date().getFullYear()} نقابة المهندسين الأردنيين · جميع الحقوق محفوظة
        </p>
      </div>
    </div>
  );
}

// ── Layout + Nav ──────────────────────────────────────────────────────────────

interface NavItem {
  to: string;
  ar: string;
  en: string;
  Icon: React.ComponentType<{ size?: number; className?: string }>;
}

export function navItemsForRole(role: User['role'] | undefined): NavItem[] {
  const items: NavItem[] = [];
  if (!role) return items;

  if (role === 'applicant') {
    items.push({ to: '/dashboard',       ar: 'الرئيسية',   en: 'Dashboard',    Icon: LayoutDashboard });
    items.push({ to: '/services',        ar: 'الخدمات',    en: 'E-Services',   Icon: Home });
    items.push({ to: '/my-applications', ar: 'طلباتي',     en: 'My Requests',  Icon: FileText });
  }
  if (role === 'staff' || role === 'auditor' || role === 'admin') {
    items.push({ to: '/review/queue',    ar: 'المراجعة',   en: 'Review',       Icon: ShieldCheck });
  }
  if (role === 'admin' || role === 'superuser') {
    items.push({ to: '/admin',                 ar: 'الإدارة',       en: 'Admin',       Icon: Settings });
    items.push({ to: '/admin/services',        ar: 'إدارة الخدمات', en: 'Services',    Icon: ClipboardList });
    items.push({ to: '/admin/services/new',    ar: 'خدمة جديدة',    en: 'New Service', Icon: PlusCircle });
    items.push({ to: '/admin/integration',     ar: 'Nashmi',        en: 'Nashmi',      Icon: Zap });
    // Both admin and superuser get the user-management lane. The backend
    // decides which roles the actor can act on inside the page.
    items.push({ to: '/admin/users',           ar: 'إدارة المستخدمين', en: 'Users',    Icon: UserIcon });
  }
  return items;
}

function pageTitleFor(pathname: string): { ar: string; en: string } {
  if (pathname === '/dashboard') return { ar: 'الرئيسية', en: 'Dashboard' };
  if (pathname === '/services' || pathname.startsWith('/services/') || pathname.startsWith('/apply/')) return { ar: 'الخدمات الإلكترونية', en: 'E-Services Portal' };
  if (pathname.startsWith('/projects')) return { ar: 'مشاريعي', en: 'My Projects' };
  if (pathname.startsWith('/my-applications')) return { ar: 'طلباتي', en: 'My Requests' };
  if (pathname.startsWith('/review')) return { ar: 'المراجعة', en: 'Review' };
  if (pathname === '/admin') return { ar: 'الإدارة', en: 'Admin Dashboard' };
  if (pathname.startsWith('/admin/services/new')) return { ar: 'خدمة جديدة', en: 'New Service' };
  if (pathname.startsWith('/admin/services')) return { ar: 'إدارة الخدمات', en: 'Services Admin' };
  if (pathname.startsWith('/admin/integration')) return { ar: 'Nashmi', en: 'Integration Cycles' };
  return { ar: 'الرئيسية', en: 'Home' };
}

function isActivePath(pathname: string, to: string): boolean {
  if (to === '/services') return pathname === '/services' || pathname.startsWith('/services/') || pathname.startsWith('/apply/') || pathname.startsWith('/projects');
  if (to === '/admin')    return pathname === '/admin';
  return pathname.startsWith(to);
}

function Header({ user, onMenuToggle }: { user: User | null; onMenuToggle: () => void }) {
  const location = useLocation();
  const title = pageTitleFor(location.pathname);
  const initial = (user?.name ?? '').trim().charAt(0) || '?';

  return (
    <header className="bg-jea-topbar text-white h-14 flex items-center px-4 gap-4 shrink-0" dir="rtl">
      <button
        onClick={onMenuToggle}
        aria-label="فتح القائمة الجانبية · Open sidebar"
        aria-expanded={undefined}
        className="p-1 rounded hover:bg-white/10 transition-colors lg:hidden focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
      >
        <Menu size={20} aria-hidden="true" />
      </button>
      <div className="flex items-center gap-2.5">
        <JEALogo size={38} dark />
        <div className="hidden sm:block">
          <div className="font-bold text-sm leading-tight" lang="ar">نقابة المهندسين الأردنيين</div>
          <div className="text-white/50 text-[10px] leading-tight" lang="en" dir="ltr">Jordan Engineers Association</div>
        </div>
      </div>
      <div className="flex-1 flex items-center gap-2 mx-4" aria-label="breadcrumb">
        <span className="text-white/40 text-xs" aria-hidden="true">›</span>
        <span className="text-white/90 text-sm font-medium" lang="en" dir="ltr">{title.en}</span>
        <span className="sr-only" lang="ar">{title.ar}</span>
      </div>
      <div className="flex items-center gap-2">
        <button
          aria-label="الإشعارات · Notifications"
          className="p-1.5 rounded hover:bg-white/10 transition-colors relative focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
        >
          <Bell size={17} aria-hidden="true" />
          <span className="absolute top-0.5 right-0.5 w-2 h-2 bg-orange-400 rounded-full" aria-hidden="true" />
        </button>
        <div
          className="w-7 h-7 rounded-full bg-jea-primary flex items-center justify-center text-xs font-bold"
          aria-label={user?.name ? `المستخدم: ${user.name}` : undefined}
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
  return (
    <>
      <div className="h-14 flex items-center px-4 border-b border-[#E0E0E0] gap-3">
        <div className="w-11 h-9 rounded-lg flex items-center justify-center shrink-0 px-1 bg-jea-topbar">
          <JEALogo size={36} dark />
        </div>
        <div>
          <p className="text-xs font-bold text-jea-text">نقابة المهندسين الأردنيين</p>
          <p className="text-[10px] text-jea-muted">Jordan Engineers Association</p>
        </div>
      </div>
      <nav className="flex-1 py-3 overflow-y-auto" aria-label="القائمة الرئيسية">
        {items.map(({ to, ar, en, Icon }) => {
          const active = isActivePath(pathname, to);
          return (
            <button
              key={to}
              onClick={() => onNavigate(to)}
              aria-current={active ? 'page' : undefined}
              className={`w-full flex items-center gap-3 px-4 py-2.5 text-sm transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-jea-primary/40 focus-visible:ring-inset ${
                active
                  ? 'bg-jea-accent text-jea-primary font-semibold border-r-4 border-jea-primary'
                  : 'text-[#444] hover:bg-gray-50 hover:text-jea-primary'
              }`}
            >
              <Icon size={16} className={active ? 'text-jea-primary' : 'text-[#999]'} />
              <div className="flex-1 text-right">
                <div lang="ar">{ar}</div>
                <div className="text-[10px] opacity-60" dir="ltr" lang="en">{en}</div>
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
          <span><span lang="ar">تسجيل الخروج</span> · <span lang="en" dir="ltr">Sign out</span></span>
        </button>
      </div>
    </>
  );
}

function Layout({ children }: { children: React.ReactNode }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [sidebarOpen, setSidebarOpen] = useState(false);

  const items = navItemsForRole(user?.role);
  const handleLogout = () => { logout(); navigate('/login'); };
  const handleNavigate = (to: string) => { navigate(to); setSidebarOpen(false); };

  return (
    <div className="h-screen flex flex-col overflow-hidden bg-jea-bg" dir="rtl">
      <SkipToContent />
      <Header user={user} onMenuToggle={() => setSidebarOpen(o => !o)} />

      <div className="flex flex-1 overflow-hidden">
        <aside
          className="hidden lg:flex w-60 shrink-0 bg-white border-l border-[#E0E0E0] flex-col h-full"
          aria-label="القائمة الجانبية · Sidebar navigation"
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
          className={`fixed top-0 right-0 h-full w-60 bg-white border-l border-[#E0E0E0] z-30 flex flex-col transform transition-transform duration-300 lg:hidden ${
            sidebarOpen ? 'translate-x-0' : 'translate-x-full'
          }`}
          aria-label="القائمة الجانبية · Sidebar navigation"
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
 * Blocks users who can't manage the roster. Admin AND superuser both
 * pass — the page itself filters actions by the actor's tier.
 */
function RequireUserManager({ children }: { children: React.ReactNode }) {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  if (!user.can_manage_users) return <Navigate to="/" replace />;
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

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<LoginPage />} />

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

          {/* Reviewer */}
          <Route path="/review/queue"      element={<RequireAuth><Layout><ReviewQueue /></Layout></RequireAuth>} />
          <Route path="/review/:id"        element={<RequireAuth><Layout><ReviewPanel /></Layout></RequireAuth>} />

          {/* Admin */}
          <Route path="/admin"                      element={<RequireAuth><Layout><AdminDashboard /></Layout></RequireAuth>} />
          <Route path="/admin/services"             element={<RequireAuth><Layout><ServicesList /></Layout></RequireAuth>} />
          <Route path="/admin/services/new"         element={<RequireAuth><Layout><NewService /></Layout></RequireAuth>} />
          <Route path="/admin/services/:id/edit"    element={<RequireAuth><Layout><EditService /></Layout></RequireAuth>} />
          <Route path="/admin/integration"          element={<RequireAuth><Layout><IntegrationCycles /></Layout></RequireAuth>} />
          <Route path="/admin/integration/:id"      element={<RequireAuth><Layout><IntegrationCycleDetail /></Layout></RequireAuth>} />

          {/* User management — admin + superuser */}
          <Route path="/admin/users" element={<RequireUserManager><Layout><UserManagement /></Layout></RequireUserManager>} />

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
