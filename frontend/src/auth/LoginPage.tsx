import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Building2, User as UserIcon, Eye, EyeOff, AlertTriangle, LogIn } from 'lucide-react';
import { authApi } from '../api/client';
import { JEALogo } from '../components/JEALogo';
import { Button } from '../components/ui/Button';
import { TextField } from '../components/ui/FormField';
import { Captcha } from '../components/ui/Captcha';
import { LanguageSwitcher } from '../components/LanguageSwitcher';
import { useAuth } from './AuthContext';

/**
 * LoginPage — extracted from App.tsx (JORD-25).
 *
 * Standalone route; renders without <Layout> so the sidebar/nav don't
 * flash before authentication. The language switcher is pinned to the
 * top corner so the user can flip the whole page before signing in.
 */
export function LoginPage(): JSX.Element {
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

  const handleSubmit = async (e: React.FormEvent): Promise<void> => {
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
                don't leak on the deployed portal. */}
            {import.meta.env.DEV && (
              <div className="text-[10px] text-jea-muted bg-jea-bg rounded-lg px-3 py-2 leading-relaxed text-center">
                <p className="font-bold text-jea-text mb-0.5">{t('auth.demoAccountsTitle')}</p>
                <p dir="ltr">admin@demo.esp · staff@demo.esp · auditor@demo.esp · ahmed@demo.esp</p>
              </div>
            )}
          </form>
        </div>

        <p className="text-center text-white/70 text-[11px] mt-6">
          {t('copyright', { year: new Date().getFullYear() })}
        </p>
      </div>
    </div>
  );
}
