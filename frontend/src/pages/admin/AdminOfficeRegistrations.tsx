import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Building2, User as UserIcon, CheckCircle2, XCircle, RefreshCw } from 'lucide-react';
import { Button } from '../../platform/ui/Button';
import { officeRegistrationsApi, type OfficeRegistrationRow } from '../../api/officeRegistrations';
import type { ApiError } from '../../api/http';

/**
 * AdminOfficeRegistrations — JEA-admin review page for public office
 * signups. Mounted at /admin/office-registrations (RequireAdmin).
 *
 * Lists pending registrations by default; can filter by status. Each
 * row expands to show the office details + engineers table, and admin
 * can Approve (with optional notes) or Reject (notes required).
 *
 * Approve creates Organization + admin User + Engineer rows in a
 * single DB transaction on the server. Reject just marks the request
 * as rejected with the reason.
 *
 * See docs/architecture/office-registration-flow.md.
 */

type StatusFilter = 'pending' | 'approved' | 'rejected' | 'all';

const STATUS_LABELS: Record<string, { ar: string; en: string; color: string }> = {
  pending:  { ar: 'قيد المراجعة', en: 'Pending review', color: 'bg-amber-100 text-amber-800' },
  approved: { ar: 'معتمد',        en: 'Approved',       color: 'bg-emerald-100 text-emerald-800' },
  rejected: { ar: 'مرفوض',        en: 'Rejected',       color: 'bg-red-100 text-red-700' },
};

export function AdminOfficeRegistrations(): JSX.Element {
  const { i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');

  const [status, setStatus]     = useState<StatusFilter>('pending');
  const [rows, setRows]         = useState<OfficeRegistrationRow[]>([]);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState('');
  const [expandedId, setExpanded] = useState<number | null>(null);

  const load = async (): Promise<void> => {
    setLoading(true);
    setError('');
    try {
      const r = await officeRegistrationsApi.index(status);
      setRows(r.registrations.data ?? []);
    } catch (err) {
      setError((err as ApiError).message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { void load(); }, [status]);

  return (
    <div dir={isRtl ? 'rtl' : 'ltr'} className="max-w-6xl mx-auto py-6 px-4">
      <header className="flex items-center justify-between mb-4">
        <h1 className="text-xl font-bold text-gray-800 flex items-center gap-2">
          <Building2 size={20} />
          {isRtl ? 'طلبات تسجيل المكاتب' : 'Office registration requests'}
        </h1>
        <div className="flex items-center gap-2">
          <select
            value={status}
            onChange={e => setStatus(e.target.value as StatusFilter)}
            className="text-sm border border-gray-300 rounded-lg px-3 py-1.5 bg-white"
            data-testid="admin-office-reg-status-filter"
          >
            <option value="pending">{isRtl ? 'قيد المراجعة' : 'Pending'}</option>
            <option value="approved">{isRtl ? 'المعتمدة' : 'Approved'}</option>
            <option value="rejected">{isRtl ? 'المرفوضة' : 'Rejected'}</option>
            <option value="all">{isRtl ? 'الكل' : 'All'}</option>
          </select>
          <button
            type="button"
            onClick={() => void load()}
            className="text-xs px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 flex items-center gap-1"
            aria-label={isRtl ? 'تحديث' : 'Refresh'}
          >
            <RefreshCw size={14} /> {isRtl ? 'تحديث' : 'Refresh'}
          </button>
        </div>
      </header>

      {error && (
        <div role="alert" className="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-800">
          {error}
        </div>
      )}

      {loading && (
        <div className="text-center py-12 text-gray-400">
          <RefreshCw size={20} className="mx-auto mb-2 animate-spin opacity-60" />
          {isRtl ? 'جاري التحميل...' : 'Loading...'}
        </div>
      )}

      {!loading && rows.length === 0 && (
        <div data-testid="office-registrations-empty" className="text-center py-12 text-gray-400 bg-white rounded-xl border border-gray-100">
          {isRtl ? 'لا توجد طلبات في هذه الحالة.' : 'No registrations in this state.'}
        </div>
      )}

      <ul className="space-y-3">
        {rows.map(row => (
          <RegistrationRow
            key={row.id}
            row={row}
            expanded={expandedId === row.id}
            onToggle={() => setExpanded(expandedId === row.id ? null : row.id)}
            onChanged={() => void load()}
            isRtl={isRtl}
          />
        ))}
      </ul>
    </div>
  );
}

// ── individual row ───────────────────────────────────────────────

interface RowProps {
  row: OfficeRegistrationRow;
  expanded: boolean;
  onToggle: () => void;
  onChanged: () => void;
  isRtl: boolean;
}

function RegistrationRow({ row, expanded, onToggle, onChanged, isRtl }: RowProps): JSX.Element {
  const [notes, setNotes]     = useState('');
  const [busy, setBusy]       = useState<'approve' | 'reject' | null>(null);
  const [actionError, setErr] = useState('');
  const statusMeta = STATUS_LABELS[row.status] ?? STATUS_LABELS.pending;

  const approve = async (): Promise<void> => {
    setBusy('approve');
    setErr('');
    try {
      await officeRegistrationsApi.approve(row.id, notes || undefined);
      onChanged();
    } catch (err) {
      setErr((err as ApiError).message);
    } finally {
      setBusy(null);
    }
  };

  const reject = async (): Promise<void> => {
    if (!notes.trim()) {
      setErr(isRtl ? 'سبب الرفض مطلوب.' : 'Rejection reason required.');
      return;
    }
    setBusy('reject');
    setErr('');
    try {
      await officeRegistrationsApi.reject(row.id, notes.trim());
      onChanged();
    } catch (err) {
      setErr((err as ApiError).message);
    } finally {
      setBusy(null);
    }
  };

  return (
    <li
      data-testid={`office-registration-row-${row.id}`}
      className="bg-white rounded-xl border border-gray-200 overflow-hidden"
    >
      <button
        type="button"
        onClick={onToggle}
        className="w-full p-4 flex items-center justify-between gap-3 text-start hover:bg-gray-50"
      >
        <div className="flex-1 min-w-0">
          <p className="text-sm font-semibold text-gray-800 truncate">{row.office_name_ar}</p>
          <p className="text-xs text-gray-500 truncate">{row.office_name_en} · {row.office_license_no}</p>
        </div>
        <div className="text-xs text-gray-500 shrink-0">
          {new Date(row.created_at).toLocaleDateString(isRtl ? 'ar-EG' : 'en-JO')}
        </div>
        <span className={`text-[10px] px-2 py-0.5 rounded ${statusMeta.color} shrink-0`}>
          {isRtl ? statusMeta.ar : statusMeta.en}
        </span>
      </button>

      {expanded && (
        <div className="p-4 border-t border-gray-100 space-y-4">
          {/* Office info grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
            <Field label={isRtl ? 'رقم الرخصة' : 'License'} value={row.office_license_no} />
            <Field label={isRtl ? 'المدينة' : 'City'} value={row.office_city} />
            <Field label={isRtl ? 'الهاتف' : 'Phone'} value={row.office_phone} />
            <Field label={isRtl ? 'البريد' : 'Email'} value={row.office_email} />
            <div className="md:col-span-2">
              <Field label={isRtl ? 'العنوان' : 'Address'} value={row.office_address_ar} />
            </div>
            <div className="md:col-span-2">
              <Field label={isRtl ? 'الرقم المرجعي' : 'Reference'} value={row.reference_number} mono />
            </div>
          </div>

          {/* Engineers table */}
          <section aria-labelledby={`engineers-${row.id}`}>
            <h3 id={`engineers-${row.id}`} className="text-xs font-bold text-gray-700 mb-2 flex items-center gap-1">
              <UserIcon size={12} />
              {isRtl ? `الكادر الهندسي (${row.engineers.length})` : `Engineers (${row.engineers.length})`}
            </h3>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-xs text-gray-500 border-b border-gray-200">
                    <th className="text-start py-1.5">{isRtl ? 'الاسم' : 'Name'}</th>
                    <th className="text-start py-1.5">{isRtl ? 'رقم العضوية' : 'Membership'}</th>
                    <th className="text-start py-1.5">{isRtl ? 'التخصص' : 'Discipline'}</th>
                  </tr>
                </thead>
                <tbody>
                  {row.engineers.map((eng, i) => (
                    <tr key={i} className="border-b border-gray-100 last:border-0">
                      <td className="py-1.5">{eng.name_ar}{eng.name_en ? ` · ${eng.name_en}` : ''}</td>
                      <td className="py-1.5 font-mono text-xs">{eng.membership_number}</td>
                      <td className="py-1.5">{eng.specialization}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>

          {/* Review section */}
          {row.status === 'pending' ? (
            <div className="rounded-lg bg-gray-50 border border-gray-200 p-3 space-y-2">
              <label className="block text-xs font-semibold text-gray-700">
                {isRtl ? 'ملاحظات الاعتماد / الرفض' : 'Review notes'}
                <span className="text-xs text-gray-500 font-normal">
                  {isRtl ? ' — اختياري للاعتماد، مطلوب للرفض' : ' — optional to approve, required to reject'}
                </span>
              </label>
              <textarea
                value={notes}
                onChange={e => setNotes(e.target.value)}
                rows={2}
                className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2"
                placeholder={isRtl ? 'اكتب سبب القرار...' : 'Reason for the decision...'}
                data-testid={`office-registration-notes-${row.id}`}
              />
              {actionError && <p className="text-xs text-red-600">{actionError}</p>}
              <div className="flex items-center gap-2 justify-end">
                <button
                  type="button"
                  onClick={reject}
                  disabled={busy !== null}
                  className="px-4 py-1.5 text-xs bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 flex items-center gap-1"
                  data-testid={`office-registration-reject-${row.id}`}
                >
                  <XCircle size={14} /> {isRtl ? 'رفض' : 'Reject'}
                </button>
                <button
                  type="button"
                  onClick={approve}
                  disabled={busy !== null}
                  className="px-4 py-1.5 text-xs bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 flex items-center gap-1"
                  data-testid={`office-registration-approve-${row.id}`}
                >
                  <CheckCircle2 size={14} /> {isRtl ? 'اعتماد' : 'Approve'}
                </button>
              </div>
            </div>
          ) : (
            <div className="text-xs text-gray-500">
              {row.reviewed_at && (
                <p>
                  {isRtl ? 'تمت المراجعة في' : 'Reviewed at'}{' '}
                  {new Date(row.reviewed_at).toLocaleString(isRtl ? 'ar-EG' : 'en-JO')}
                  {row.reviewer ? ` · ${row.reviewer.name}` : ''}
                </p>
              )}
              {row.review_notes && (
                <p className="mt-1 italic">
                  {isRtl ? 'الملاحظات: ' : 'Notes: '} {row.review_notes}
                </p>
              )}
            </div>
          )}
        </div>
      )}
    </li>
  );
}

function Field({ label, value, mono }: { label: string; value: string; mono?: boolean }): JSX.Element {
  return (
    <div>
      <p className="text-xs text-gray-500 mb-0.5">{label}</p>
      <p className={`text-sm text-gray-800 ${mono ? 'font-mono' : ''}`}>{value}</p>
    </div>
  );
}
