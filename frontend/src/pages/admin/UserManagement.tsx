import React, { useEffect, useState } from 'react';
import { Trash2, Edit3, UserPlus, X } from 'lucide-react';
import { userManagementApi } from '../../api/client';
import type { User } from '../../types';
import { useAuth } from '../../auth/AuthContext';

/**
 * UserManagement — superuser-only page for managing user accounts.
 * Lists all users in the current organization, lets the superuser create
 * a new one, edit role/status/name, or soft-delete. Attempts to touch the
 * superuser's own credentials are 403'd by the backend — the UI reflects
 * that with an inline hint pointing at the CLI command.
 */

type RoleOption = { value: User['role']; label_ar: string };
const ROLES: RoleOption[] = [
  { value: 'applicant', label_ar: 'مقدّم طلب' },
  { value: 'staff',     label_ar: 'موظف مراجعة' },
  { value: 'auditor',   label_ar: 'مدقق قانوني' },
  { value: 'admin',     label_ar: 'مدير' },
  { value: 'superuser', label_ar: 'مستخدم أعلى' },
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
  const [users, setUsers]     = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError]     = useState('');
  const [editing, setEditing] = useState<User | 'new' | null>(null);
  const [banner, setBanner]   = useState<{ type: 'ok' | 'err'; text: string } | null>(null);

  const reload = () => {
    setLoading(true);
    userManagementApi.list()
      .then(r => setUsers(r.users))
      .catch(e => setError((e as Error).message))
      .finally(() => setLoading(false));
  };

  useEffect(() => { reload(); }, []);

  const handleDelete = async (u: User) => {
    if (!window.confirm(`حذف المستخدم ${u.email}؟`)) return;
    try {
      await userManagementApi.destroy(u.id);
      setBanner({ type: 'ok', text: `تم حذف ${u.email}` });
      reload();
    } catch (e) {
      setBanner({ type: 'err', text: (e as Error).message });
    }
  };

  return (
    <div className="max-w-5xl mx-auto px-4 py-8" dir="rtl">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">إدارة المستخدمين</h1>
          <p className="text-gray-500 text-sm mt-1">
            User Management · إضافة وتعديل وحذف حسابات المستخدمين في المؤسسة
          </p>
        </div>
        <button
          onClick={() => setEditing('new')}
          className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold text-sm hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
        >
          <UserPlus size={16} aria-hidden="true" />
          إضافة مستخدم
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

      {loading && <p className="text-sm text-gray-500">جارٍ التحميل…</p>}
      {error && <p className="text-sm text-red-600">{error}</p>}

      {!loading && !error && (
        <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-right">
              <tr>
                <th className="px-4 py-2 font-semibold">الاسم</th>
                <th className="px-4 py-2 font-semibold">البريد</th>
                <th className="px-4 py-2 font-semibold">الدور</th>
                <th className="px-4 py-2 font-semibold">الحالة</th>
                <th className="px-4 py-2 font-semibold w-24">إجراءات</th>
              </tr>
            </thead>
            <tbody>
              {users.map(u => (
                <tr key={u.id} className="border-t border-gray-100">
                  <td className="px-4 py-2">{u.name}</td>
                  <td className="px-4 py-2" dir="ltr">{u.email}</td>
                  <td className="px-4 py-2">{ROLES.find(r => r.value === u.role)?.label_ar ?? u.role}</td>
                  <td className="px-4 py-2">
                    <span className={`text-xs px-2 py-0.5 rounded-full ${u.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                      {u.is_active ? 'نشط' : 'موقوف'}
                    </span>
                    {u.must_change_password && (
                      <span className="ml-2 text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">
                        بحاجة لتغيير كلمة المرور
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-2 space-x-1 space-x-reverse">
                    <button
                      onClick={() => setEditing(u)}
                      aria-label={`تعديل ${u.email}`}
                      className="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-gray-100 text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
                    >
                      <Edit3 size={14} aria-hidden="true" />
                    </button>
                    <button
                      onClick={() => handleDelete(u)}
                      aria-label={`حذف ${u.email}`}
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
          onSaved={() => { setEditing(null); reload(); setBanner({ type: 'ok', text: 'تم الحفظ' }); }}
        />
      )}
    </div>
  );
}

function UserEditor({ user, assignableRoles, onClose, onSaved }: {
  user: User | null;
  assignableRoles: RoleOption[];
  onClose: () => void;
  onSaved: () => void;
}) {
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
    } catch (e) {
      const apiErr = e as Error & { errors?: Record<string, string | string[]> };
      const firstField = apiErr.errors ? Object.values(apiErr.errors)[0] : undefined;
      const firstMsg   = Array.isArray(firstField) ? firstField[0] : (firstField ?? apiErr.message);
      setErr(firstMsg ?? 'حدث خطأ');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div
      className="fixed inset-0 bg-black/40 flex items-center justify-center z-40 p-4"
      role="dialog"
      aria-modal="true"
    >
      <form onSubmit={handleSubmit} className="bg-white rounded-lg shadow-lg w-full max-w-md p-5" dir="rtl">
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-lg font-bold">{isNew ? 'إضافة مستخدم' : `تعديل ${user?.email}`}</h2>
          <button type="button" onClick={onClose} aria-label="إغلاق">
            <X size={18} aria-hidden="true" />
          </button>
        </div>

        {err && <div className="mb-3 text-sm bg-red-50 text-red-700 border border-red-200 rounded p-2" role="alert">{err}</div>}

        <label className="block mb-3">
          <span className="text-sm font-semibold text-gray-700">الاسم</span>
          <input value={name} onChange={e => setName(e.target.value)} required className="mt-1 w-full border rounded px-3 py-2 text-sm" />
        </label>
        <label className="block mb-3">
          <span className="text-sm font-semibold text-gray-700">البريد</span>
          <input type="email" value={email} onChange={e => setEmail(e.target.value)} required dir="ltr" className="mt-1 w-full border rounded px-3 py-2 text-sm" />
        </label>
        <label className="block mb-3">
          <span className="text-sm font-semibold text-gray-700">الدور</span>
          <select value={role} onChange={e => setRole(e.target.value as User['role'])} className="mt-1 w-full border rounded px-3 py-2 text-sm">
            {assignableRoles.map(r => <option key={r.value} value={r.value}>{r.label_ar}</option>)}
          </select>
        </label>
        {!isNew && (
          <label className="flex items-center gap-2 mb-3 text-sm">
            <input type="checkbox" checked={isActive} onChange={e => setIsActive(e.target.checked)} />
            حساب نشط
          </label>
        )}
        <label className="block mb-4">
          <span className="text-sm font-semibold text-gray-700">
            {isNew ? 'كلمة المرور المؤقتة' : 'كلمة مرور جديدة (اختياري)'}
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
            8 أحرف على الأقل — سيُطلب من المستخدم تغييرها عند أول تسجيل دخول.
          </span>
        </label>

        <div className="flex gap-2">
          <button
            type="submit"
            disabled={saving}
            className="flex-1 py-2 rounded bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 disabled:opacity-50"
          >
            {saving ? 'جاري الحفظ…' : 'حفظ'}
          </button>
          <button type="button" onClick={onClose} className="px-4 py-2 rounded border text-sm">
            إلغاء
          </button>
        </div>
      </form>
    </div>
  );
}
