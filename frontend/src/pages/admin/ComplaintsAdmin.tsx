import React, { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AlertTriangle, ChevronDown, CheckCircle2, XCircle, Gavel, Info } from 'lucide-react';
import { adminApi } from '../../api/client';

/**
 * ComplaintsAdmin — JORD-81 UI
 *
 * Admin queue for disciplinary complaints. Table of complaints in
 * the admin's org, filterable by status. Row expander opens the
 * decide form: pick either "sanction" with a ladder choice, or
 * "dismiss". Sanction requires a reason; dismiss allows notes only.
 *
 * Design decisions
 * ----------------
 * Inline expander instead of modal so the full complaint description
 * stays visible while the admin composes the decision — sanction
 * reason often quotes back the complaint text and paging between
 * views loses the context.
 *
 * Decided/dismissed complaints render collapsed with their final
 * disposition + linked sanction badge. No re-decide UI (backend
 * refuses double-decision).
 *
 * Sanction ladder is a segmented control (warning / 1yr / 2yr /
 * deregistration) so the escalation is visually obvious.
 */

type Complaint = Awaited<ReturnType<typeof adminApi.listComplaints>>['complaints'][number];
type Status = Complaint['status'];
type SanctionKind = 'warning' | 'suspension_1yr' | 'suspension_2yr' | 'deregistration';

const KIND_LABEL_AR: Record<Complaint['kind'], string> = {
  fee_undercutting: 'تخفيض أتعاب',
  contracting_ban:  'ممارسة مقاولة محظورة',
  safety_violation: 'مخالفة سلامة',
  other:            'أخرى',
};

const STATUS_STYLE: Record<Status, string> = {
  open:          'bg-blue-100  text-blue-700',
  investigating: 'bg-amber-100 text-amber-700',
  decided:       'bg-red-100   text-red-700',
  dismissed:     'bg-gray-100  text-gray-500',
};

const SANCTION_LABEL_AR: Record<SanctionKind, string> = {
  warning:         'إنذار (تنبيه فقط)',
  suspension_1yr:  'إيقاف سنة',
  suspension_2yr:  'إيقاف سنتين',
  deregistration:  'شطب دائم',
};

export function ComplaintsAdmin() {
  const { i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const isArabic = isRtl;
  const [complaints, setComplaints] = useState<Complaint[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [flash, setFlash] = useState('');
  const [filter, setFilter] = useState<Status | 'all'>('all');
  const [expandedId, setExpandedId] = useState<number | null>(null);

  const load = () => {
    setLoading(true);
    adminApi.listComplaints()
      .then(r => setComplaints(r.complaints))
      .catch(e => setError((e as Error).message))
      .finally(() => setLoading(false));
  };
  useEffect(load, []);

  const filtered = useMemo(() => {
    if (filter === 'all') return complaints;
    return complaints.filter(c => c.status === filter);
  }, [complaints, filter]);

  if (loading) return (
    <div className="flex justify-center py-20">
      <div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" />
    </div>
  );

  const filterTabs: Array<Status | 'all'> = ['all', 'open', 'investigating', 'decided', 'dismissed'];
  const tabLabelAr: Record<Status | 'all', string> = {
    all: 'الكل', open: 'مفتوحة', investigating: 'قيد التحقيق',
    decided: 'محسومة', dismissed: 'مرفوضة',
  };

  return (
    <div className="max-w-5xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      <header className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">
          {isArabic ? 'الشكاوى التأديبية' : 'Disciplinary Complaints'}
        </h1>
        <p className="text-sm text-gray-500 mt-1">
          {isArabic
            ? 'مهلة التحقيق 30 يوماً. القرار: إصدار عقوبة أو رفض.'
            : '30-day investigation window. Decision: issue sanction or dismiss.'}
        </p>
      </header>

      {flash && (
        <div role="status" className="mb-4 bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-emerald-800 text-sm">
          ✓ {flash}
        </div>
      )}
      {error && (
        <div role="alert" className="mb-4 bg-red-50 border border-red-200 rounded-xl p-3 text-red-700 text-sm">
          {error}
        </div>
      )}

      <div className="flex gap-1 mb-4 border-b border-gray-200" data-testid="filter-tabs">
        {filterTabs.map(t => {
          const count = t === 'all' ? complaints.length : complaints.filter(c => c.status === t).length;
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

      {filtered.length === 0 ? (
        <div className="text-center py-16 text-gray-400">
          <Gavel size={40} className="mx-auto mb-3 opacity-40" aria-hidden="true" />
          <p className="text-sm">{isArabic ? 'لا توجد شكاوى في هذا التصنيف.' : 'No complaints in this bucket.'}</p>
        </div>
      ) : (
        <div className="space-y-3">
          {filtered.map(c => (
            <ComplaintRow
              key={c.id}
              complaint={c}
              isExpanded={expandedId === c.id}
              onExpand={() => setExpandedId(expandedId === c.id ? null : c.id)}
              onDecided={(msg) => { setFlash(msg); setExpandedId(null); load(); }}
              onError={setError}
              isArabic={isArabic}
            />
          ))}
        </div>
      )}
    </div>
  );
}

function ComplaintRow({
  complaint, isExpanded, onExpand, onDecided, onError, isArabic,
}: {
  complaint: Complaint;
  isExpanded: boolean;
  onExpand: () => void;
  onDecided: (msg: string) => void;
  onError: (msg: string) => void;
  isArabic: boolean;
}) {
  const canDecide = complaint.status === 'open' || complaint.status === 'investigating';
  const deadlineDate = new Date(complaint.investigation_deadline);
  const daysUntilDeadline = Math.ceil((deadlineDate.getTime() - Date.now()) / (1000 * 60 * 60 * 24));
  const deadlineWarn = canDecide && daysUntilDeadline <= 7;

  return (
    <div
      className={`bg-white border rounded-xl overflow-hidden ${
        deadlineWarn ? 'border-amber-300 shadow-sm' : 'border-gray-200'
      }`}
      data-testid={`complaint-row-${complaint.id}`}
    >
      <button
        type="button"
        onClick={onExpand}
        className="w-full text-start p-4 flex items-start gap-3 hover:bg-gray-50 transition-colors"
      >
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap">
            <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${STATUS_STYLE[complaint.status]}`}>
              {complaint.status}
            </span>
            <span className="text-xs px-2 py-0.5 rounded bg-purple-50 text-purple-700 font-medium">
              {KIND_LABEL_AR[complaint.kind]}
            </span>
            {deadlineWarn && (
              <span className="inline-flex items-center gap-1 text-xs text-amber-700 font-semibold">
                <AlertTriangle size={11} aria-hidden="true" />
                {isArabic ? `${daysUntilDeadline} يوم متبقٍ` : `${daysUntilDeadline}d left`}
              </span>
            )}
            {complaint.sanctions.length > 0 && (
              <span className="text-xs px-2 py-0.5 rounded bg-red-50 text-red-700 font-semibold">
                {complaint.sanctions[0].kind}
              </span>
            )}
          </div>
          <p className="text-sm text-gray-800 mt-2 line-clamp-2">
            <span className="font-semibold">
              {isArabic ? 'ضد:' : 'Target:'} {complaint.target_office?.name ?? '—'}
            </span>
            <span className="mx-2 text-gray-300">·</span>
            <span className="text-gray-600">
              {isArabic ? 'من:' : 'By:'} {complaint.reporter?.name ?? complaint.reporter_display ?? (isArabic ? 'مجهول' : 'anonymous')}
            </span>
          </p>
          <p className="text-xs text-gray-500 mt-1 line-clamp-1">{complaint.description}</p>
        </div>
        <ChevronDown
          size={16}
          className={`text-gray-400 shrink-0 mt-2 transition-transform ${isExpanded ? 'rotate-180' : ''}`}
          aria-hidden="true"
        />
      </button>

      {isExpanded && (
        <div className="border-t border-gray-100 p-4 bg-gray-50" data-testid={`complaint-body-${complaint.id}`}>
          <div className="mb-4">
            <p className="text-xs font-semibold text-gray-700 mb-1">
              {isArabic ? 'نص الشكوى' : 'Complaint text'}
            </p>
            <p className="text-sm text-gray-800 whitespace-pre-wrap">{complaint.description}</p>
          </div>
          <div className="mb-4 text-xs text-gray-500 flex items-center gap-1.5">
            <Info size={11} aria-hidden="true" />
            {isArabic ? `مهلة التحقيق: ${complaint.investigation_deadline}` : `Deadline: ${complaint.investigation_deadline}`}
          </div>

          {canDecide ? (
            <DecideForm
              complaintId={complaint.id}
              onDecided={onDecided}
              onError={onError}
              isArabic={isArabic}
            />
          ) : (
            <p className="text-xs text-gray-500 italic">
              {isArabic
                ? `الشكوى ${complaint.status === 'decided' ? 'محسومة' : 'مرفوضة'} — لا يمكن اتخاذ قرار جديد.`
                : `Complaint ${complaint.status} — no further decision.`}
            </p>
          )}
        </div>
      )}
    </div>
  );
}

function DecideForm({ complaintId, onDecided, onError, isArabic }: {
  complaintId: number;
  onDecided: (msg: string) => void;
  onError: (msg: string) => void;
  isArabic: boolean;
}) {
  const [decision, setDecision] = useState<'sanction' | 'dismiss'>('sanction');
  const [sanctionKind, setSanctionKind] = useState<SanctionKind>('warning');
  const [reason, setReason] = useState('');
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async () => {
    if (decision === 'sanction' && reason.trim().length < 10) {
      onError(isArabic
        ? 'مبرر العقوبة يجب أن يكون 10 أحرف على الأقل.'
        : 'Sanction reason must be at least 10 characters.');
      return;
    }
    setSubmitting(true);
    try {
      const payload = decision === 'sanction'
        ? { decision: 'sanction' as const, sanction_kind: sanctionKind, reason: reason.trim(), notes: notes.trim() || undefined }
        : { decision: 'dismiss' as const, notes: notes.trim() || undefined };
      const res = await adminApi.decideComplaint(complaintId, payload);
      const extra = (res.transfers_opened ?? 0) > 0
        ? (isArabic ? ` (${res.transfers_opened} طلب نقل إشراف تم فتحه)` : ` (${res.transfers_opened} transfers opened)`)
        : '';
      onDecided(res.message + extra);
    } catch (e) {
      onError((e as Error).message);
    } finally {
      setSubmitting(false);
    }
  };

  const kinds: SanctionKind[] = ['warning', 'suspension_1yr', 'suspension_2yr', 'deregistration'];

  return (
    <div data-testid={`decide-form-${complaintId}`}>
      <div className="flex gap-2 mb-4" role="radiogroup" aria-label={isArabic ? 'نوع القرار' : 'Decision'}>
        <button
          type="button"
          onClick={() => setDecision('sanction')}
          className={`flex-1 px-3 py-2 text-sm rounded-lg border ${
            decision === 'sanction' ? 'border-red-300 bg-red-50 text-red-700 font-semibold' : 'border-gray-200 text-gray-600'
          }`}
          data-testid="decision-sanction"
        >
          <Gavel size={13} className="inline mx-1" aria-hidden="true" />
          {isArabic ? 'إصدار عقوبة' : 'Issue sanction'}
        </button>
        <button
          type="button"
          onClick={() => setDecision('dismiss')}
          className={`flex-1 px-3 py-2 text-sm rounded-lg border ${
            decision === 'dismiss' ? 'border-gray-400 bg-gray-100 text-gray-700 font-semibold' : 'border-gray-200 text-gray-600'
          }`}
          data-testid="decision-dismiss"
        >
          <XCircle size={13} className="inline mx-1" aria-hidden="true" />
          {isArabic ? 'رفض الشكوى' : 'Dismiss'}
        </button>
      </div>

      {decision === 'sanction' && (
        <>
          <p className="text-xs font-semibold text-gray-700 mb-2">
            {isArabic ? 'درجة العقوبة' : 'Sanction kind'}
          </p>
          <div className="grid grid-cols-2 gap-2 mb-4">
            {kinds.map(k => (
              <button
                key={k}
                type="button"
                onClick={() => setSanctionKind(k)}
                className={`text-xs px-3 py-2 rounded-lg border text-start ${
                  sanctionKind === k
                    ? 'border-red-300 bg-red-50 text-red-800 font-semibold'
                    : 'border-gray-200 text-gray-600 hover:border-gray-300'
                }`}
                data-testid={`sanction-kind-${k}`}
              >
                {SANCTION_LABEL_AR[k]}
              </button>
            ))}
          </div>

          <label className="block mb-3">
            <span className="text-xs font-semibold text-gray-700">
              {isArabic ? 'مبرر العقوبة' : 'Reason'}
            </span>
            <textarea
              value={reason}
              onChange={e => setReason(e.target.value)}
              rows={3}
              maxLength={2000}
              placeholder={isArabic ? 'استند إلى تحقيق النقابة والأدلة…' : 'Reference JEA investigation + evidence…'}
              className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-jea-primary"
              data-testid="sanction-reason"
            />
          </label>
        </>
      )}

      <label className="block mb-3">
        <span className="text-xs font-semibold text-gray-700">
          {isArabic ? 'ملاحظات (اختياري)' : 'Notes (optional)'}
        </span>
        <textarea
          value={notes}
          onChange={e => setNotes(e.target.value)}
          rows={2}
          maxLength={2000}
          className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-jea-primary"
          data-testid="decide-notes"
        />
      </label>

      <div className="flex justify-end">
        <button
          type="button"
          onClick={handleSubmit}
          disabled={submitting}
          className={`inline-flex items-center gap-1 px-5 py-2 text-sm font-bold rounded-lg text-white ${
            decision === 'sanction' ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-600 hover:bg-gray-700'
          } disabled:opacity-50`}
          data-testid="decide-submit"
        >
          {submitting
            ? (isArabic ? 'جارٍ…' : 'Deciding…')
            : (decision === 'sanction'
                ? (isArabic ? 'إصدار العقوبة' : 'Issue sanction')
                : (isArabic ? 'رفض الشكوى' : 'Dismiss complaint'))}
          <CheckCircle2 size={13} aria-hidden="true" />
        </button>
      </div>
    </div>
  );
}
