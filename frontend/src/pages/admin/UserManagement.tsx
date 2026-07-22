import React, { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Trash2, Edit3, UserPlus, X } from 'lucide-react';
import { userManagementApi } from '../../api/client';
import type { User } from '../../types';
import { ConfirmDialog } from '../../platform/ui/ConfirmDialog';
import { errorMessage } from '../../platform/utils/errorMessage';
import { useAuth } from '../../auth/AuthContext';

/**
 * UserManagement — superuser-only page for managing user accounts.
 * Lists all users in the current organization, lets the superuser create
 * a new one, edit role/status/name, or soft-delete. Attempts to touch the
 * superuser's own credentials are 403'd by the backend — the UI reflects
 * that with an inline hint pointing at the CLI command.
 */

// Role tier config — labels come from i18n at render time so the role
// column follows the active language.
type RoleOption = { value: User['role'] };
const ROLES: RoleOption[] = [
  { value: 'applicant' },
  { value: 'staff' },
  { value: 'auditor' },
  { value: 'admin' },
  { value: 'superuser' },
];

// Mirrors backend User::canManageRole(). Admin can pick from the lower tiers;
// only a superuser can grant admin or superuser. Keeping this in sync with
// the backend prevents the UI from offering an option that the API 403's.
function rolesAssignableBy(actor: User | null): RoleOption[] {
  if (!actor) return [];
  if (actor.role === 'superuser') return ROLES;
  if (actor.role === 'admin')     return ROLES.filter(r => ['applicant', 'staff', 'auditor'].includes(r.value));
  return [];
}

export function UserManagement() {
  const { user: actor } = useAuth();
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const [users, setUsers]     = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError]     = useState('');
  const [editing, setEditing] = useState<User | 'new' | null>(null);
  const [banner, setBanner]   = useState<{ type: 'ok' | 'err'; text: string } | null>(null);
  // JORD-70: replaces the blocking window.confirm() with the in-app
  // ConfirmDialog. Stashing the target user keeps the confirm flow
  // out of a callback closure — the dialog reads from state, not
  // from the click handler.
  const [pendingDelete, setPendingDelete] = useState<User | null>(null);
  const [deleting, setDeleting] = useState(false);

  // JORD-77: useCallback + errorMessage so the effect can honestly
  // list `reload` as a dependency and non-Error rejections don't
  // crash the setter.
  const reload = useCallback(() => {
    setLoading(true);
    userManagementApi.list()
      .then(r => setUsers(r.users))
      .catch(err => setError(errorMessage(err)))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { reload(); }, [reload]);

  const handleDelete = (u: User) => setPendingDelete(u);

  const confirmDelete = async () => {
    if (!pendingDelete) return;
    setDeleting(true);
    try {
      await userManagementApi.destroy(pendingDelete.id);
      setBanner({ type: 'ok', text: t('userManagement.deletedBanner', { email: pendingDelete.email }) });
      setPendingDelete(null);
      reload();
    } catch (err) {
      setBanner({ type: 'err', text: errorMessage(err) });
    } finally {
      setDeleting(false);
    }
  };

  return (
    <div className="max-w-5xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{t('userManagement.title')}</h1>
          <p className="text-gray-500 text-sm mt-1">{t('userManagement.subtitle')}</p>
        </div>
        <button
          onClick={() => setEditing('new')}
          className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold text-sm hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
        >
          <UserPlus size={16} aria-hidden="true" />
          {t('userManagement.addUser')}
        </button>
      </div>

      {banner && (
        <div
          className={`mb-4 rounded p-3 text-sm ${banner.type === 'ok' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'}`}
          role="alert"
        >
          {banner.text}
        </div>
      )}

      {loading && <p className="text-sm text-gray-500">{t('loading')}…</p>}
      {error && <p className="text-sm text-red-600">{error}</p>}

      {!loading && !error && (
        <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
          <table className="w-full text-sm">
            <thead className={`bg-gray-50 ${isRtl ? 'text-right' : 'text-left'}`}>
              <tr>
                <th className="px-4 py-2 font-semibold w-4"><span className="sr-only">{t('presence.column')}</span></th>
                <th className="px-4 py-2 font-semibold">{t('userManagement.columns.name')}</th>
                <th className="px-4 py-2 font-semibold">{t('userManagement.columns.email')}</th>
                <th className="px-4 py-2 font-semibold">{t('userManagement.columns.role')}</th>
                <th className="px-4 py-2 font-semibold">{t('userManagement.columns.status')}</th>
                <th className="px-4 py-2 font-semibold w-24">{t('userManagement.columns.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {users.map(u => (
                <tr key={u.id} className="border-t border-gray-100">
                  <td className="px-2 py-2">
                    <PresenceDot presence={u.presence} />
                  </td>
                  <td className="px-4 py-2">{u.name}</td>
                  <td className="px-4 py-2" dir="ltr">{u.email}</td>
                  <td className="px-4 py-2">{t(`userManagement.roles.${u.role}`, { defaultValue: u.role })}</td>
                  <td className="px-4 py-2">
                    <span className={`text-xs px-2 py-0.5 rounded-full ${u.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                      {u.is_active ? t('userManagement.statusActive') : t('userManagement.statusSuspended')}
                    </span>
                    {u.must_change_password && (
                      <span className="ml-2 text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">
                        {t('userManagement.needsPasswordChange')}
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-2 space-x-1 space-x-reverse">
                    <button
                      onClick={() => setEditing(u)}
                      aria-label={t('userManagement.editAria', { email: u.email })}
                      className="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-gray-100 text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
                    >
                      <Edit3 size={14} aria-hidden="true" />
                    </button>
                    <button
                      onClick={() => handleDelete(u)}
                      aria-label={t('userManagement.deleteAria', { email: u.email })}
                      className="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-red-50 text-red-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400"
                    >
                      <Trash2 size={14} aria-hidden="true" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {editing !== null && (
        <UserEditor
          user={editing === 'new' ? null : editing}
          assignableRoles={rolesAssignableBy(actor)}
          onClose={() => setEditing(null)}
          onSaved={() => { setEditing(null); reload(); setBanner({ type: 'ok', text: t('userManagement.savedBanner') }); }}
        />
      )}

      {/* JORD-70: accessible in-app confirmation for destructive delete
          instead of the browser's blocking window.confirm(). */}
      <ConfirmDialog
        open={pendingDelete !== null}
        title={t('userManagement.deleteDialogTitle', { defaultValue: 'حذف المستخدم' })}
        message={t('userManagement.deleteConfirm', { email: pendingDelete?.email ?? '' })}
        destructive
        busy={deleting}
        onConfirm={confirmDelete}
        onCancel={() => setPendingDelete(null)}
      />
    </div>
  );
}

/**
 * JORD-24: coloured dot showing a user's server-computed presence.
 *   • online  — filled green with a subtle pulse ring
 *   • idle    — filled amber
 *   • offline — hollow grey
 *
 * The label sits in a native `title` attribute so admins can hover to
 * confirm before acting on a row.
 */
function PresenceDot({ presence }: { presence?: User['presence'] }): JSX.Element {
  const { t } = useTranslation();
  const state = presence ?? 'offline';
  const cls =
    state === 'online' ? 'bg-emerald-500 ring-2 ring-emerald-100'
    : state === 'idle' ? 'bg-amber-400'
    : 'bg-transparent border border-gray-300';
  return (
    <span
      role="status"
      aria-label={t(`presence.${state}`)}
      title={t(`presence.${state}`)}
      className={`inline-block w-2.5 h-2.5 rounded-full ${cls}`}
    />
  );
}

function UserEditor({ user, assignableRoles, onClose, onSaved }: {
  user: User | null;
  assignableRoles: RoleOption[];
  onClose: () => void;
  onSaved: () => void;
}) {
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const isNew = !user;
  const defaultRole: User['role'] = user?.role
    ?? (assignableRoles[0]?.value ?? 'staff');
  const [name, setName]         = useState(user?.name ?? '');
  const [email, setEmail]       = useState(user?.email ?? '');
  const [role, setRole]         = useState<User['role']>(defaultRole);
  const [isActive, setIsActive] = useState(user?.is_active ?? true);
  const [password, setPassword] = useState('');
  const [saving, setSaving]     = useState(false);
  const [err, setErr]           = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErr('');
    setSaving(true);
    try {
      if (isNew) {
        await userManagementApi.create({ name, email, role, password });
      } else {
        const body: Record<string, unknown> = { name, role, is_active: isActive };
        // Only send email/password when they actually changed — the backend
        // refuses email/password writes on a post-init superuser account.
        if (email !== user!.email) body.email = email;
        if (password) body.password = password;
        await userManagementApi.update(user!.id, body);
      }
      onSaved();
    } catch (err) {
      const apiErr = err as { errors?: Record<string, string | string[]> };
      const firstField = apiErr.errors ? Object.values(apiErr.errors)[0] : undefined;
      const firstMsg   = Array.isArray(firstField) ? firstField[0] : firstField;
      setErr(firstMsg ?? errorMessage(err, t('userManagement.genericError')));
    } finally {
      setSaving(false);
    }
  };

  const editorTitle = isNew
    ? t('userManagement.editorTitle')
    : t('userManagement.editorTitleEdit', { email: user?.email });

  return (
    <div
      className="fixed inset-0 bg-black/40 flex items-center justify-center z-40 p-4"
      role="dialog"
      aria-modal="true"
      // JORD-79: screen readers previously announced the dialog with no
      // name because neither aria-label nor aria-labelledby was set.
      // Wire aria-labelledby to the heading below so the announcement
      // carries the actual editor title (new vs. editing which email).
      aria-labelledby="user-editor-title"
    >
      <form onSubmit={handleSubmit} className="bg-white rounded-lg shadow-lg w-full max-w-md p-5" dir={isRtl ? 'rtl' : 'ltr'}>
        <div className="flex items-center justify-between mb-3">
          <h2 id="user-editor-title" className="text-lg font-bold">
            {editorTitle}
          </h2>
          <button type="button" onClick={onClose} aria-label={t('userManagement.closeAria')}>
            <X size={18} aria-hidden="true" />
          </button>
        </div>

        {err && <div className="mb-3 text-sm bg-red-50 text-red-700 border border-red-200 rounded p-2" role="alert">{err}</div>}

        <label className="block mb-3">
          <span className="text-sm font-semibold text-gray-700">{t('userManagement.form.name')}</span>
          <input value={name} onChange={e => setName(e.target.value)} required className="mt-1 w-full border rounded px-3 py-2 text-sm" />
        </label>
        <label className="block mb-3">
          <span className="text-sm font-semibold text-gray-700">{t('userManagement.form.email')}</span>
          <input type="email" value={email} onChange={e => setEmail(e.target.value)} required dir="ltr" className="mt-1 w-full border rounded px-3 py-2 text-sm" />
        </label>
        <label className="block mb-3">
          <span className="text-sm font-semibold text-gray-700">{t('userManagement.form.role')}</span>
          <select value={role} onChange={e => setRole(e.target.value as User['role'])} className="mt-1 w-full border rounded px-3 py-2 text-sm">
            {assignableRoles.map(r => (
              <option key={r.value} value={r.value}>{t(`userManagement.roles.${r.value}`)}</option>
            ))}
          </select>
        </label>
        {!isNew && (
          <label className="flex items-center gap-2 mb-3 text-sm">
            <input type="checkbox" checked={isActive} onChange={e => setIsActive(e.target.checked)} />
            {t('userManagement.form.active')}
          </label>
        )}
        <label className="block mb-4">
          <span className="text-sm font-semibold text-gray-700">
            {isNew ? t('userManagement.form.passwordNew') : t('userManagement.form.passwordEdit')}
          </span>
          <input
            type="password"
            value={password}
            onChange={e => setPassword(e.target.value)}
            required={isNew}
            minLength={isNew ? 8 : undefined}
            className="mt-1 w-full border rounded px-3 py-2 text-sm"
            autoComplete="new-password"
          />
          <span className="text-xs text-gray-500 mt-1 block">
            {t('userManagement.form.passwordHint')}
          </span>
        </label>

        <div className="flex gap-2">
          <button
            type="submit"
            disabled={saving}
            className="flex-1 py-2 rounded bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 disabled:opacity-50"
          >
            {saving ? t('common.saving') : t('common.save')}
          </button>
          <button type="button" onClick={onClose} className="px-4 py-2 rounded border text-sm">
            {t('common.cancel')}
          </button>
        </div>
      </form>
    </div>
  );
}
