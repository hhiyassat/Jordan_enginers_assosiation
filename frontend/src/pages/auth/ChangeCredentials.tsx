import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { authApi } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';

/**
 * ChangeCredentials — landing page for users whose account still carries the
 * bootstrap flag (must_change_password=true). Superusers get an extra field
 * that lets them pick their own login email in the same flow; other roles
 * only rotate their password. After a successful submit the page reloads the
 * session so the RequireAuth gate lets the user through to /.
 *
 * This page is BEHIND RequireAuth but IN FRONT of the credential-change gate,
 * so it's reachable by an authenticated user carrying the change-required
 * flag but nothing else.
 */
export function ChangeCredentials() {
  const { user, token, login } = useAuth();
  const navigate = useNavigate();
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');

  const isSuperuser = user?.role === 'superuser';

  const [currentPassword, setCurrentPassword] = useState('');
  const [newEmail, setNewEmail]               = useState(user?.email ?? '');
  const [newPassword, setNewPassword]         = useState('');
  const [confirm, setConfirm]                 = useState('');
  const [submitting, setSubmitting]           = useState(false);
  const [error, setError]                     = useState('');

  // JORD-46: mirror the backend rule `Password::min(8)->mixedCase()->numbers()`.
  // Previously the input only enforced minLength=8 while the hint text
  // demanded three character classes, so users typed "password" and got
  // a 422 back from the server. Better to catch it inline.
  const passwordMeetsBackendRule = (pw: string): boolean =>
    pw.length >= 8 && /[a-z]/.test(pw) && /[A-Z]/.test(pw) && /\d/.test(pw);

  const canSubmit =
    currentPassword.length > 0 &&
    passwordMeetsBackendRule(newPassword) &&
    newPassword === confirm &&
    (!isSuperuser || newEmail.trim().length > 0);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    if (!canSubmit) return;
    setSubmitting(true);
    try {
      await authApi.changePassword(
        currentPassword,
        newPassword,
        confirm,
        isSuperuser && newEmail.trim() !== user?.email ? newEmail.trim() : undefined,
      );
      // JORD-47: previously we force-logged out + bounced to /login here,
      // which meant every voluntary rotation cost the user a second sign-in
      // (and every first-login user had to type their brand-new password
      // twice in a row). Instead, re-fetch /auth/me — the server has just
      // cleared must_change_password + rotated the token's password hash,
      // so the fresh payload lets the AuthContext keep the session alive.
      // The RequireAuth gate then unblocks the route the user actually
      // wanted (home / dashboard). Token arg is ignored post-JORD-30
      // (session lives in a httpOnly cookie); passing null is fine.
      const me = await authApi.me();
      login(token, me.user);
      navigate('/', { replace: true });
    } catch (err) {
      const e = err as Error & { errors?: Record<string, string[]> };
      const first =
        e.errors?.email?.[0] ??
        e.errors?.password?.[0] ??
        e.errors?.current_password?.[0] ??
        e.message;
      setError(first || t('changeCredentials.genericError'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 px-4" dir={isRtl ? 'rtl' : 'ltr'}>
      <form
        onSubmit={handleSubmit}
        className="w-full max-w-md bg-white rounded-xl shadow-md p-6 space-y-4"
      >
        <div>
          <h1 className="text-xl font-bold text-gray-900">{t('changeCredentials.title')}</h1>
          <p className="text-xs text-gray-500 mt-1">
            {isSuperuser
              ? t('changeCredentials.subtitleSuperuser')
              : t('changeCredentials.subtitleUser')}
          </p>
        </div>

        {error && (
          <div className="text-sm bg-red-50 border border-red-200 text-red-700 rounded p-3" role="alert">
            {error}
          </div>
        )}

        <label className="block">
          <span className="text-sm font-semibold text-gray-700">{t('changeCredentials.currentPassword')}</span>
          <input
            type="password"
            autoComplete="current-password"
            value={currentPassword}
            onChange={e => setCurrentPassword(e.target.value)}
            className="mt-1 w-full border border-gray-300 rounded px-3 py-2 text-sm"
            required
          />
        </label>

        {isSuperuser && (
          <label className="block">
            <span className="text-sm font-semibold text-gray-700">{t('changeCredentials.newEmail')}</span>
            <input
              type="email"
              autoComplete="email"
              value={newEmail}
              onChange={e => setNewEmail(e.target.value)}
              className="mt-1 w-full border border-gray-300 rounded px-3 py-2 text-sm"
              required
              dir="ltr"
            />
          </label>
        )}

        <label className="block">
          <span className="text-sm font-semibold text-gray-700">{t('changeCredentials.newPassword')}</span>
          <input
            type="password"
            autoComplete="new-password"
            value={newPassword}
            onChange={e => setNewPassword(e.target.value)}
            className="mt-1 w-full border border-gray-300 rounded px-3 py-2 text-sm"
            required
            minLength={8}
          />
          <span className="text-xs text-gray-500 mt-1 block">
            {t('changeCredentials.passwordHint')}
          </span>
          {newPassword.length > 0 && !passwordMeetsBackendRule(newPassword) && (
            <span className="text-xs text-red-600 mt-1 block">
              {t('changeCredentials.passwordInvalid')}
            </span>
          )}
        </label>

        <label className="block">
          <span className="text-sm font-semibold text-gray-700">{t('changeCredentials.confirmPassword')}</span>
          <input
            type="password"
            autoComplete="new-password"
            value={confirm}
            onChange={e => setConfirm(e.target.value)}
            className="mt-1 w-full border border-gray-300 rounded px-3 py-2 text-sm"
            required
          />
          {confirm && confirm !== newPassword && (
            <span className="text-xs text-red-600 mt-1 block">{t('changeCredentials.passwordsMismatch')}</span>
          )}
        </label>

        <button
          type="submit"
          disabled={!canSubmit || submitting}
          className="w-full py-2.5 rounded bg-blue-600 text-white font-semibold text-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {submitting ? t('common.saving') : t('changeCredentials.submit')}
        </button>
      </form>
    </div>
  );
}
