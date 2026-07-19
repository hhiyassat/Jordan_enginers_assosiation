import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { authApi } from '../../api/client';
import { useAuth } from '../../App';

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
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const isSuperuser = user?.role === 'superuser';

  const [currentPassword, setCurrentPassword] = useState('');
  const [newEmail, setNewEmail]               = useState(user?.email ?? '');
  const [newPassword, setNewPassword]         = useState('');
  const [confirm, setConfirm]                 = useState('');
  const [submitting, setSubmitting]           = useState(false);
  const [error, setError]                     = useState('');

  const canSubmit =
    currentPassword.length > 0 &&
    newPassword.length >= 8 &&
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
      // Simplest way to refresh the session with the cleared flag is to
      // sign out + back in via the normal flow. Log the user out and route
      // them to /login so their next login lands them on the dashboard.
      logout();
      navigate('/login', { replace: true });
    } catch (err) {
      const e = err as Error & { errors?: Record<string, string[]> };
      const first =
        e.errors?.email?.[0] ??
        e.errors?.password?.[0] ??
        e.errors?.current_password?.[0] ??
        e.message;
      setError(first || 'حدث خطأ');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 px-4" dir="rtl">
      <form
        onSubmit={handleSubmit}
        className="w-full max-w-md bg-white rounded-xl shadow-md p-6 space-y-4"
      >
        <div>
          <h1 className="text-xl font-bold text-gray-900">تحديث بيانات الدخول</h1>
          <p className="text-xs text-gray-500 mt-1">
            {isSuperuser
              ? 'يرجى اختيار البريد وكلمة المرور الدائمين — لن يمكن تعديلهما لاحقاً إلا من سطر الأوامر.'
              : 'يرجى اختيار كلمة مرور جديدة للمتابعة.'}
          </p>
        </div>

        {error && (
          <div className="text-sm bg-red-50 border border-red-200 text-red-700 rounded p-3" role="alert">
            {error}
          </div>
        )}

        <label className="block">
          <span className="text-sm font-semibold text-gray-700">كلمة المرور الحالية</span>
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
            <span className="text-sm font-semibold text-gray-700">البريد الإلكتروني الدائم</span>
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
          <span className="text-sm font-semibold text-gray-700">كلمة المرور الجديدة</span>
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
            8 أحرف على الأقل — مع أحرف كبيرة وصغيرة وأرقام
          </span>
        </label>

        <label className="block">
          <span className="text-sm font-semibold text-gray-700">تأكيد كلمة المرور</span>
          <input
            type="password"
            autoComplete="new-password"
            value={confirm}
            onChange={e => setConfirm(e.target.value)}
            className="mt-1 w-full border border-gray-300 rounded px-3 py-2 text-sm"
            required
          />
          {confirm && confirm !== newPassword && (
            <span className="text-xs text-red-600 mt-1 block">كلمتا المرور غير متطابقتين</span>
          )}
        </label>

        <button
          type="submit"
          disabled={!canSubmit || submitting}
          className="w-full py-2.5 rounded bg-blue-600 text-white font-semibold text-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {submitting ? 'جاري الحفظ…' : 'حفظ ومتابعة'}
        </button>
      </form>
    </div>
  );
}
