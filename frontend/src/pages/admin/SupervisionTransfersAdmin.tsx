import React, { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AlertCircle, ArrowRightLeft, Building2, CheckCircle2, Clock, Download, XCircle } from 'lucide-react';
import { adminApi } from '../../api/client';
import { downloadCsv } from '../../utils/csv';
import { errorMessage } from '../../utils/errorMessage';

/**
 * SupervisionTransfersAdmin — JORD-83 UI
 *
 * Admin queue for supervision-contract transfers created when a
 * suspension_2yr / deregistration sanction hits an office. Rows
 * arrive in `pending`; admin picks a target office → `assigned`;
 * target accepts → `accepted` (terminal) OR declines → back to
 * `pending` for reassignment.
 *
 * Design decisions
 * ----------------
 * • Filter tabs across the top mirror ComplaintsAdmin.tsx pattern
 *   for consistency across the admin surface.
 * • Assign uses a modal (not inline) because it needs an office
 *   list from a second endpoint (listOffices) — the extra fetch
 *   fires only when the modal opens, not on every row render.
 * • Accept/decline uses a small inline confirm bar for `assigned`
 *   rows — one click each, no modal (destructive-decline separated
 *   from accept by color).
 * • Fee-waived is displayed as a chip since it's the manual's
 *   headline requirement ("free-tier waiver for receiving office").
 */

type Transfer = Awaited<ReturnType<typeof adminApi.listSupervisionTransfers>>['transfers'][number];
type Status = Transfer['status'];

const STATUS_STYLE: Record<Status, string> = {
  pending:  'bg-amber-100 text-amber-800',
  assigned: 'bg-blue-100  text-blue-800',
  accepted: 'bg-emerald-100 text-emerald-800',
  declined: 'bg-red-100   text-red-800',
};

const STATUS_LABEL_AR: Record<Status, string> = {
  pending:  'بانتظار التعيين',
  assigned: 'تم التعيين',
  accepted: 'مقبول',
  declined: 'مرفوض',
};

export function SupervisionTransfersAdmin() {
  const { i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const isArabic = isRtl;

  const [transfers, setTransfers] = useState<Transfer[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [flash, setFlash] = useState('');
  const [filter, setFilter] = useState<Status | 'all'>('all');
  const [assignTarget, setAssignTarget] = useState<Transfer | null>(null);

  const load = () => {
    setLoading(true);
    adminApi.listSupervisionTransfers()
      .then(r => setTransfers(r.transfers))
      .catch(e => setError(errorMessage(e)))
      .finally(() => setLoading(false));
  };
  useEffect(load, []);

  const filtered = useMemo(() => {
    if (filter === 'all') return transfers;
    return transfers.filter(t => t.status === filter);
  }, [transfers, filter]);

  if (loading) return (
    <div className="flex justify-center py-20">
      <div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" />
    </div>
  );

  const filterTabs: Array<Status | 'all'> = ['all', 'pending', 'assigned', 'accepted', 'declined'];
  const tabLabelAr: Record<Status | 'all', string> = {
    all: 'الكل', pending: 'بانتظار', assigned: 'مُعيَّن',
    accepted: 'مقبول', declined: 'مرفوض',
  };

  return (
    <div className="max-w-5xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      <header className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">
          {isArabic ? 'نقل الإشراف' : 'Supervision Transfers'}
        </h1>
        <p className="text-sm text-gray-500 mt-1">
          {isArabic
            ? 'إعادة توزيع عقود الإشراف لمكاتب موقوفة أو مشطوبة (المادة 30).'
            : 'Reassign supervision contracts from suspended/deregistered offices (Art.30).'}
        </p>
      </header>

      {flash && (
        <div role="status" className="mb-4 bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-emerald-800 text-sm">
          ✓ {flash}
        </div>
      )}
      {error && (
        <div role="alert" className="mb-4 bg-red-50 border border-red-200 rounded-xl p-3 text-red-700 text-sm flex items-start gap-2">
          <AlertCircle size={16} className="mt-0.5 shrink-0" aria-hidden="true" />
          <span>{error}</span>
        </div>
      )}

      <div className="flex items-center justify-between mb-4 border-b border-gray-200">
        <div className="flex gap-1" data-testid="filter-tabs">
          {filterTabs.map(t => {
            const count = t === 'all' ? transfers.length : transfers.filter(x => x.status === t).length;
            const active = filter === t;
            return (
              <button
                key={t}
                type="button"
                onClick={() => setFilter(t)}
                className={`px-3 py-2 text-sm border-b-2 transition-colors ${
                  active
                    ? 'border-jea-primary text-jea-primary font-semibold'
                    : 'border-transparent text-gray-500 hover:text-gray-700'
                }`}
                data-testid={`filter-${t}`}
              >
                {tabLabelAr[t]} <span className="text-xs opacity-60">({count})</span>
              </button>
            );
          })}
        </div>
        {filtered.length > 0 && (
          <button
            type="button"
            onClick={() => downloadCsv(
              `supervision-transfers-${new Date().toISOString().slice(0, 10)}`,
              filtered,
              [
                { header: 'ID',            get: t => t.id },
                { header: 'Status',        get: t => t.status },
                { header: 'Fee waived',    get: t => t.fee_waived ? 'yes' : 'no' },
                { header: 'Reference',     get: t => t.application?.reference_number ?? '' },
                { header: 'Service',       get: t => t.application?.service_definition?.name_en ?? t.application?.service_definition?.name_ar ?? '' },
                { header: 'Source office', get: t => t.source_office?.name ?? '' },
                { header: 'Target office', get: t => t.target_office?.name ?? '' },
                { header: 'Created at',    get: t => t.created_at },
                { header: 'Assigned at',   get: t => t.assigned_at ?? '' },
                { header: 'Accepted at',   get: t => t.accepted_at ?? '' },
                { header: 'Notes',         get: t => t.notes ?? '' },
              ],
            )}
            data-testid="transfers-export-csv"
            className="mb-1 inline-flex items-center gap-1 px-2.5 py-1 text-xs border border-gray-300 rounded hover:bg-gray-50"
            title={isArabic ? 'تصدير CSV' : 'Export CSV'}
          >
            <Download size={12} aria-hidden="true" />
            {isArabic ? 'تصدير' : 'CSV'}
          </button>
        )}
      </div>

      {filtered.length === 0 ? (
        <div className="text-center py-16 text-gray-400">
          <ArrowRightLeft size={40} className="mx-auto mb-3 opacity-40" aria-hidden="true" />
          <p className="text-sm">{isArabic ? 'لا توجد طلبات نقل.' : 'No transfers in this bucket.'}</p>
        </div>
      ) : (
        <div className="space-y-3">
          {filtered.map(t => (
            <TransferRow
              key={t.id}
              transfer={t}
              isArabic={isArabic}
              onAssign={() => setAssignTarget(t)}
              onSuccess={(msg) => { setFlash(msg); load(); }}
              onError={setError}
            />
          ))}
        </div>
      )}

      {assignTarget && (
        <AssignModal
          transfer={assignTarget}
          isArabic={isArabic}
          onClose={() => setAssignTarget(null)}
          onAssigned={(msg) => {
            setFlash(msg);
            setAssignTarget(null);
            load();
          }}
          onError={setError}
        />
      )}
    </div>
  );
}

function TransferRow({ transfer, isArabic, onAssign, onSuccess, onError }: {
  transfer: Transfer;
  isArabic: boolean;
  onAssign: () => void;
  onSuccess: (msg: string) => void;
  onError: (msg: string) => void;
}) {
  const [decision, setDecision] = useState<'accept' | 'decline' | null>(null);
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const handleAcceptOrDecline = async () => {
    if (!decision) return;
    setSubmitting(true);
    try {
      await adminApi.acceptOrDeclineSupervisionTransfer(transfer.id, decision, notes.trim() || undefined);
      onSuccess(decision === 'accept'
        ? (isArabic ? 'تم قبول نقل الإشراف.' : 'Transfer accepted.')
        : (isArabic ? 'تم رفض النقل — الطلب متاح لإعادة التعيين.' : 'Transfer declined — back to pending.'));
      setDecision(null);
      setNotes('');
    } catch (e) {
      onError(errorMessage(e));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div
      className="bg-white border border-gray-200 rounded-xl p-4"
      data-testid={`transfer-row-${transfer.id}`}
    >
      <div className="flex items-start justify-between gap-3 flex-wrap">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap mb-2">
            <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${STATUS_STYLE[transfer.status]}`}>
              {STATUS_LABEL_AR[transfer.status]}
            </span>
            {transfer.fee_waived && (
              <span className="text-xs px-2 py-0.5 rounded bg-teal-50 text-teal-700 font-medium">
                {isArabic ? 'مُعفى من الرسوم' : 'Fee waived'}
              </span>
            )}
          </div>
          {transfer.application && (
            <p className="text-sm font-semibold text-gray-800">
              {isArabic ? transfer.application.service_definition?.name_ar : transfer.application.service_definition?.name_en}
              <span className="mx-2 text-gray-300">·</span>
              <span className="font-mono text-xs text-gray-500">#{transfer.application.reference_number}</span>
            </p>
          )}
          <div className="mt-2 text-xs text-gray-600 space-y-0.5">
            <p className="flex items-center gap-1">
              <Building2 size={11} aria-hidden="true" />
              <span className="font-semibold">{isArabic ? 'من:' : 'From:'}</span>
              <span>{transfer.source_office?.name ?? '—'}</span>
            </p>
            {transfer.target_office && (
              <p className="flex items-center gap-1">
                <Building2 size={11} className="text-emerald-600" aria-hidden="true" />
                <span className="font-semibold">{isArabic ? 'إلى:' : 'To:'}</span>
                <span>{transfer.target_office.name}</span>
              </p>
            )}
          </div>
        </div>

        <div className="flex items-center gap-2 shrink-0">
          {(transfer.status === 'pending' || transfer.status === 'declined') && (
            <button
              type="button"
              onClick={onAssign}
              className="inline-flex items-center gap-1 px-3 py-1.5 text-xs bg-jea-primary text-white rounded-lg hover:opacity-90"
              data-testid={`assign-btn-${transfer.id}`}
            >
              <ArrowRightLeft size={12} aria-hidden="true" />
              {isArabic ? 'تعيين مكتب مستلم' : 'Assign target'}
            </button>
          )}
          {transfer.status === 'assigned' && (
            <>
              <button
                type="button"
                onClick={() => setDecision('accept')}
                className="inline-flex items-center gap-1 px-3 py-1.5 text-xs bg-emerald-600 text-white rounded-lg hover:bg-emerald-700"
                data-testid={`accept-btn-${transfer.id}`}
              >
                <CheckCircle2 size={12} aria-hidden="true" />
                {isArabic ? 'قبول' : 'Accept'}
              </button>
              <button
                type="button"
                onClick={() => setDecision('decline')}
                className="inline-flex items-center gap-1 px-3 py-1.5 text-xs border border-red-300 text-red-700 rounded-lg hover:bg-red-50"
                data-testid={`decline-btn-${transfer.id}`}
              >
                <XCircle size={12} aria-hidden="true" />
                {isArabic ? 'رفض' : 'Decline'}
              </button>
            </>
          )}
          {transfer.status === 'accepted' && (
            <span className="inline-flex items-center gap-1 text-xs text-emerald-700 font-semibold">
              <CheckCircle2 size={13} aria-hidden="true" />
              {isArabic ? 'مكتمل' : 'Completed'}
            </span>
          )}
        </div>
      </div>

      {decision && (
        <div className="mt-3 border-t border-gray-100 pt-3" data-testid={`confirm-${decision}-${transfer.id}`}>
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            {isArabic ? 'ملاحظات (اختياري)' : 'Notes (optional)'}
          </label>
          <textarea
            value={notes}
            onChange={e => setNotes(e.target.value)}
            rows={2}
            maxLength={2000}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-jea-primary"
            data-testid={`confirm-notes-${transfer.id}`}
          />
          <div className="mt-2 flex justify-end gap-2">
            <button
              type="button"
              onClick={() => { setDecision(null); setNotes(''); }}
              disabled={submitting}
              className="px-3 py-1.5 text-xs border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 disabled:opacity-50"
            >
              {isArabic ? 'إلغاء' : 'Cancel'}
            </button>
            <button
              type="button"
              onClick={handleAcceptOrDecline}
              disabled={submitting}
              className={`px-4 py-1.5 text-xs text-white rounded-lg font-bold ${
                decision === 'accept' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700'
              } disabled:opacity-50`}
              data-testid={`confirm-submit-${transfer.id}`}
            >
              {submitting
                ? (isArabic ? 'جارٍ…' : 'Submitting…')
                : (decision === 'accept'
                    ? (isArabic ? 'تأكيد القبول' : 'Confirm accept')
                    : (isArabic ? 'تأكيد الرفض' : 'Confirm decline'))}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

function AssignModal({ transfer, isArabic, onClose, onAssigned, onError }: {
  transfer: Transfer;
  isArabic: boolean;
  onClose: () => void;
  onAssigned: (msg: string) => void;
  onError: (msg: string) => void;
}) {
  const [offices, setOffices] = useState<Array<{ id: number; name: string; email: string; engineer_count: number }>>([]);
  const [officesLoading, setOfficesLoading] = useState(true);
  const [targetId, setTargetId] = useState<number | null>(null);
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    adminApi.listOffices()
      .then(r => {
        // Filter out the source office — can't reassign to itself.
        setOffices(r.offices.filter(o => o.id !== transfer.source_office?.id));
      })
      .catch(e => onError(errorMessage(e)))
      .finally(() => setOfficesLoading(false));
  }, []);

  const handleSubmit = async () => {
    if (targetId === null) {
      onError(isArabic ? 'يرجى اختيار المكتب المستلم.' : 'Please pick a target office.');
      return;
    }
    setSubmitting(true);
    try {
      await adminApi.assignSupervisionTransfer(transfer.id, targetId, notes.trim() || undefined);
      onAssigned(isArabic ? 'تم تعيين المكتب المستلم.' : 'Target office assigned.');
    } catch (e) {
      onError(errorMessage(e));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div
      role="dialog"
      aria-modal="true"
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
      data-testid="assign-modal"
    >
      <div className="bg-white rounded-xl max-w-lg w-full p-6 shadow-xl max-h-[85vh] overflow-y-auto">
        <h3 className="text-base font-bold text-gray-900 mb-1">
          {isArabic ? 'تعيين مكتب مستلم' : 'Assign target office'}
        </h3>
        <p className="text-xs text-gray-500 mb-4">
          {isArabic ? 'من:' : 'From:'} <b>{transfer.source_office?.name}</b>
          {transfer.application && (
            <>
              &nbsp;· <span className="font-mono">#{transfer.application.reference_number}</span>
            </>
          )}
        </p>

        <label className="block mb-3">
          <span className="text-xs font-semibold text-gray-700">
            {isArabic ? 'المكتب المستلم' : 'Target office'}
          </span>
          {officesLoading ? (
            <p className="text-xs text-gray-400 py-2">
              {isArabic ? 'جارٍ التحميل…' : 'Loading offices…'}
            </p>
          ) : offices.length === 0 ? (
            <p className="text-xs text-amber-700 py-2">
              {isArabic
                ? 'لا يوجد مكاتب أخرى متاحة في هذه المنظمة.'
                : 'No other offices available in this organization.'}
            </p>
          ) : (
            <div className="mt-1 space-y-1 max-h-64 overflow-y-auto border border-gray-200 rounded-lg p-1">
              {offices.map(o => (
                <label
                  key={o.id}
                  className={`flex items-center gap-2 p-2 rounded cursor-pointer hover:bg-gray-50 ${
                    targetId === o.id ? 'bg-blue-50 ring-1 ring-blue-300' : ''
                  }`}
                  data-testid={`target-office-${o.id}`}
                >
                  <input
                    type="radio"
                    name="target-office"
                    checked={targetId === o.id}
                    onChange={() => setTargetId(o.id)}
                    className="text-jea-primary"
                  />
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold text-gray-800">{o.name}</p>
                    <p className="text-[10px] font-mono text-gray-500">
                      {o.email} · {o.engineer_count} {isArabic ? 'مهندس' : 'engineers'}
                    </p>
                  </div>
                </label>
              ))}
            </div>
          )}
        </label>

        <label className="block mb-4">
          <span className="text-xs font-semibold text-gray-700">
            {isArabic ? 'ملاحظات (اختياري)' : 'Notes (optional)'}
          </span>
          <textarea
            value={notes}
            onChange={e => setNotes(e.target.value)}
            rows={2}
            maxLength={2000}
            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-jea-primary"
            data-testid="assign-notes"
          />
        </label>

        <div className="flex justify-end gap-2">
          <button
            type="button"
            onClick={onClose}
            disabled={submitting}
            className="px-4 py-2 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 disabled:opacity-50"
            data-testid="assign-cancel"
          >
            {isArabic ? 'إلغاء' : 'Cancel'}
          </button>
          <button
            type="button"
            onClick={handleSubmit}
            disabled={submitting || targetId === null}
            className="px-5 py-2 text-sm bg-jea-primary text-white font-bold rounded-lg hover:opacity-90 disabled:opacity-50"
            data-testid="assign-submit"
          >
            <Clock size={12} className="inline mx-1" aria-hidden="true" />
            {submitting
              ? (isArabic ? 'جارٍ التعيين…' : 'Assigning…')
              : (isArabic ? 'تأكيد التعيين' : 'Confirm assign')}
          </button>
        </div>
      </div>
    </div>
  );
}
