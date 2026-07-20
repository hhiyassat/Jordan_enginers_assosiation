import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { User as UserIcon, Save, Lock } from 'lucide-react';
import { authApi } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';

/**
 * Profile — user's own account page (JORD-10).
 *
 * Scope kept deliberately narrow to match backend:
 *   • name + phone are editable (PATCH /auth/me).
 *   • email is shown but locked — rotating it is a login-identity change
 *     and lives on the credential-change flow / admin surface.
 *   • role is shown for context, never editable here.
 *   • password rotation links out to /auth/change-credentials so both
 *     first-login and voluntary rotations share the same tested form.
 */
export function Profile(): JSX.Element {
  const { user, token, login } = useAuth();
  const navigate = useNavigate();
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');

  const [name,  setName]  = useState(user?.name ?? '');
  const [phone, setPhone] = useState(user?.phone ?? '');
  const [saving, setSaving] = useState(false);
  const [saved,  setSaved]  = useState(false);
  const [error,  setError]  = useState('');

  const dirty =
    name.trim() !== (user?.name ?? '').trim() ||
    (phone ?? '').trim() !== (user?.phone ?? '').trim();

  const handleSubmit = async (e: React.FormEvent): Promise<void> => {
    e.preventDefault();
    if (!user) return;
    setSaving(true);
    setError('');
    setSaved(false);
    try {
      const r = await authApi.updateProfile({
        name:  name.trim(),
        phone: phone.trim() === '' ? null : phone.trim(),
      });
      // Refresh AuthContext with the new payload so the header avatar +
      // any consumer that reads `useAuth().user` sees the updated name.
      // Token arg is ignored post-JORD-30 (httpOnly cookie) but the
      // signature stays (token, user) for callsite compatibility.
      login(token, r.user);
      setSaved(true);
    } catch (err: unknown) {
      const e = err as Error & { errors?: Record<string, string[]> };
      const first = e.errors?.name?.[0] ?? e.errors?.phone?.[0] ?? e.message;
      setError(first || t('userManagement.genericError'));
    } finally {
      setSaving(false);
    }
  };

  if (!user) {
    // Belt-and-braces — RequireAuth guards this route so this branch
    // should never render. Return a Navigate as a last-resort fallback.
    navigate('/login');
    return <div />;
  }

  return (
    <div className="max-w-2xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      <header className="mb-8 flex items-center gap-4">
        <div className="w-14 h-14 rounded-full bg-jea-primary flex items-center justify-center text-white text-xl font-bold shrink-0">
          {(user.name ?? '').trim().charAt(0) || '?'}
        </div>
        <div>
          <h1 className="text-2xl font-bold text-jea-text">{t('profile.title')}</h1>
          <p className="text-jea-muted text-sm mt-0.5">{t('profile.subtitle')}</p>
        </div>
      </header>

      {saved && (
        <div className="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-2.5 text-sm" role="status">
          ✓ {t('profile.savedBanner')}
        </div>
      )}
      {error && (
        <div className="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-2.5 text-sm" role="alert">
          {error}
        </div>
      )}

      {/* Account details */}
      <form onSubmit={handleSubmit} className="bg-white rounded-xl border border-jea-border p-6 shadow-sm mb-4">
        <h2 className="text-sm font-bold text-jea-text mb-4 flex items-center gap-2">
          <UserIcon size={16} className="text-jea-primary" aria-hidden="true" />
          {t('profile.sectionAccount')}
        </h2>

        <div className="grid grid-cols-1 gap-4">
          <label className="block">
            <span className="text-sm font-semibold text-jea-text">{t('profile.name')}</span>
            <input
              type="text"
              value={name}
              onChange={e => setName(e.target.value)}
              required
              maxLength={120}
              className="mt-1 w-full border border-jea-border rounded-lg px-3 py-2 text-sm outline-none focus:border-jea-primary focus:ring-2 focus:ring-jea-primary/20"
            />
          </label>

          <label className="block">
            <span className="text-sm font-semibold text-jea-text">{t('profile.email')}</span>
            <input
              type="email"
              value={user.email}
              disabled
              dir="ltr"
              aria-describedby="email-hint"
              className="mt-1 w-full border border-jea-border rounded-lg px-3 py-2 text-sm bg-jea-bg text-jea-muted cursor-not-allowed"
            />
            <span id="email-hint" className="text-xs text-jea-muted mt-1 block">
              🔒 {t('profile.emailLocked')}
            </span>
          </label>

          <label className="block">
            <span className="text-sm font-semibold text-jea-text">{t('profile.phone')}</span>
            <input
              type="tel"
              value={phone ?? ''}
              onChange={e => setPhone(e.target.value)}
              maxLength={32}
              dir="ltr"
              placeholder={t('profile.phonePlaceholder')}
              className="mt-1 w-full border border-jea-border rounded-lg px-3 py-2 text-sm outline-none focus:border-jea-primary focus:ring-2 focus:ring-jea-primary/20"
            />
          </label>

          <label className="block">
            <span className="text-sm font-semibold text-jea-text">{t('profile.role')}</span>
            <input
              type="text"
              value={t(`userManagement.roles.${user.role}`, { defaultValue: user.role })}
              disabled
              className="mt-1 w-full border border-jea-border rounded-lg px-3 py-2 text-sm bg-jea-bg text-jea-muted cursor-not-allowed"
            />
          </label>
        </div>

        <div className="mt-6 flex justify-end">
          <button
            type="submit"
            disabled={!dirty || saving}
            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-jea-primary text-white text-sm font-bold hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <Save size={15} aria-hidden="true" />
            {saving ? t('common.saving') : t('profile.saveProfile')}
          </button>
        </div>
      </form>

      {/* Security */}
      <section aria-labelledby="security-heading" className="bg-white rounded-xl border border-jea-border p-6 shadow-sm">
        <h2 id="security-heading" className="text-sm font-bold text-jea-text mb-4 flex items-center gap-2">
          <Lock size={16} className="text-jea-primary" aria-hidden="true" />
          {t('profile.sectionSecurity')}
        </h2>
        <p className="text-xs text-jea-muted mb-3">{t('profile.linkToChangePassword')}</p>
        <Link
          to="/auth/change-credentials"
          className="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-jea-border text-sm font-semibold text-jea-text hover:bg-jea-bg"
        >
          {t('profile.changePassword')}
        </Link>
      </section>
    </div>
  );
}
