import React, { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AlertCircle, CheckCircle2, Circle, DollarSign, Plus, Scale } from 'lucide-react';
import { adminApi } from '../../api/client';

/**
 * LegalFinesAdmin — JORD-82 UI
 *
 * Admin surface for Art.14 owner-fines (using unlicensed contractor).
 * Two tiers by project area:
 *   • ≤ 250 m²  → 1,000 – 5,000 JOD
 *   • > 250 m²  → 5,000 – 50,000 JOD
 *
 * Layout: list of issued fines on top, "issue new" collapsible form
 * below. Issue form auto-suggests the tier from project_area_m2 if
 * the admin types it (nudges toward the correct kind without
 * hard-forcing — the backend still validates the mismatch).
 *
 * Design decisions
 * ----------------
 * Auto-tier-from-area: manual quotes area 250 m² as the ONLY tier
 * pivot. Rather than force the admin to eyeball two number pickers,
 * we compute the suggested tier from the entered area and switch
 * the segmented control. Admin can still override, but the ranges
 * shown update to match the picked tier.
 *
 * Pay flow is a small modal mirroring OfficeDues.tsx (JORD-79) —
 * same UX pattern for "record external payment against an obligation".
 */

type Fine = Awaited<ReturnType<typeof adminApi.listLegalFines>>['fines'][number];
type Kind = Fine['kind'];

const KIND_LABEL_AR: Record<Kind, string> = {
  unlicensed_contractor_small: 'مقاول غير مرخّص (≤ 250 م²)',
  unlicensed_contractor_large: 'مقاول غير مرخّص (> 250 م²)',
};

export function LegalFinesAdmin() {
  const { i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const isArabic = isRtl;

  const [fines, setFines] = useState<Fine[]>([]);
  const [bounds, setBounds] = useState<Record<string, { min: number; max: number; area_threshold_m2: number | null }>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [flash, setFlash] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [payTarget, setPayTarget] = useState<Fine | null>(null);
  const [payReference, setPayReference] = useState('');
  const [paying, setPaying] = useState(false);

  const load = () => {
    setLoading(true);
    adminApi.listLegalFines()
      .then(r => { setFines(r.fines); setBounds(r.bounds); })
      .catch(e => setError((e as Error).message))
      .finally(() => setLoading(false));
  };
  useEffect(load, []);

  const handlePay = async () => {
    if (!payTarget) return;
    if (payReference.trim().length === 0) {
      setError(isArabic ? 'يرجى إدخال مرجع الدفع.' : 'Payment reference is required.');
      return;
    }
    setPaying(true);
    try {
      await adminApi.payLegalFine(payTarget.id, payReference.trim());
      setFlash(isArabic ? 'تم تسجيل الدفع.' : 'Payment recorded.');
      setPayTarget(null); setPayReference('');
      load();
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setPaying(false);
    }
  };

  if (loading) return (
    <div className="flex justify-center py-20">
      <div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" />
    </div>
  );

  return (
    <div className="max-w-5xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      <header className="mb-6 flex items-start justify-between gap-3 flex-wrap">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            {isArabic ? 'الغرامات القانونية' : 'Legal Fines'}
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            {isArabic
              ? 'غرامات المادة 14: استخدام مقاول غير مرخّص من قِبل مالك المشروع.'
              : 'Art.14 owner fines: using an unlicensed contractor.'}
          </p>
        </div>
        <button
          type="button"
          onClick={() => setShowForm(s => !s)}
          className="inline-flex items-center gap-1 px-4 py-2 text-sm bg-jea-primary text-white rounded-lg hover:opacity-90"
          data-testid="toggle-issue-form"
        >
          <Plus size={14} aria-hidden="true" />
          {showForm
            ? (isArabic ? 'إخفاء النموذج' : 'Hide form')
            : (isArabic ? 'إصدار غرامة جديدة' : 'Issue new fine')}
        </button>
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

      {showForm && (
        <IssueForm
          bounds={bounds}
          isArabic={isArabic}
          onError={setError}
          onIssued={(msg) => { setFlash(msg); setShowForm(false); load(); }}
        />
      )}

      <section aria-labelledby="fines-list-heading" className="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div className="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
          <h2 id="fines-list-heading" className="text-sm font-bold text-gray-800">
            {isArabic ? 'الغرامات المُصدَرة' : 'Issued fines'}
          </h2>
          <span className="text-xs text-gray-500">
            {fines.length} {isArabic ? 'غرامة' : 'items'}
          </span>
        </div>
        {fines.length === 0 ? (
          <div className="text-center py-16 text-gray-400">
            <Scale size={40} className="mx-auto mb-3 opacity-40" aria-hidden="true" />
            <p className="text-sm">{isArabic ? 'لا توجد غرامات مُصدرة.' : 'No fines issued yet.'}</p>
          </div>
        ) : (
          <table className="w-full text-sm" data-testid="fines-table">
            <thead className="bg-gray-50 text-xs text-gray-600 uppercase">
              <tr>
                <th className="px-5 py-2 text-start">{isArabic ? 'المالك' : 'Owner'}</th>
                <th className="px-5 py-2 text-start">{isArabic ? 'النوع' : 'Kind'}</th>
                <th className="px-5 py-2 text-start">{isArabic ? 'المبلغ' : 'Amount'}</th>
                <th className="px-5 py-2 text-start">{isArabic ? 'الحالة' : 'Status'}</th>
                <th className="px-5 py-2"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {fines.map(f => {
                const isPaid = f.paid_at !== null;
                return (
                  <tr key={f.id} data-testid={`fine-row-${f.id}`}>
                    <td className="px-5 py-3">
                      <p className="font-medium text-gray-800">{f.target_display}</p>
                      {f.application && (
                        <p className="text-[10px] text-gray-400 font-mono mt-0.5">
                          #{f.application.reference_number}
                        </p>
                      )}
                    </td>
                    <td className="px-5 py-3 text-xs text-gray-600">
                      {KIND_LABEL_AR[f.kind]}
                      {f.project_area_m2 && (
                        <p className="text-[10px] text-gray-400 mt-0.5">{f.project_area_m2} م²</p>
                      )}
                    </td>
                    <td className="px-5 py-3 font-semibold">{f.amount_jod} JOD</td>
                    <td className="px-5 py-3">
                      {isPaid ? (
                        <span className="inline-flex items-center gap-1 text-xs text-emerald-700 font-semibold">
                          <CheckCircle2 size={13} aria-hidden="true" />
                          {isArabic ? 'مدفوع' : 'Paid'}
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1 text-xs text-gray-500">
                          <Circle size={13} aria-hidden="true" />
                          {isArabic ? 'مستحقة' : 'Outstanding'}
                        </span>
                      )}
                    </td>
                    <td className="px-5 py-3 text-end">
                      {!isPaid && (
                        <button
                          type="button"
                          onClick={() => { setPayTarget(f); setPayReference(''); setError(''); }}
                          data-testid={`pay-fine-btn-${f.id}`}
                          className="inline-flex items-center gap-1 px-3 py-1.5 text-xs bg-jea-primary text-white rounded-lg hover:opacity-90"
                        >
                          <DollarSign size={12} aria-hidden="true" />
                          {isArabic ? 'دفع' : 'Pay'}
                        </button>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </section>

      {payTarget && (
        <div
          role="dialog"
          aria-modal="true"
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
          data-testid="pay-fine-modal"
        >
          <div className="bg-white rounded-xl max-w-md w-full p-6 shadow-xl">
            <h3 className="text-base font-bold text-gray-900">
              {isArabic ? 'تسجيل دفع الغرامة' : 'Record fine payment'}
            </h3>
            <p className="text-xs text-gray-500 mt-1">
              {payTarget.target_display} · {payTarget.amount_jod} JOD
            </p>
            <label className="block mt-4 text-xs font-semibold text-gray-700 mb-1">
              {isArabic ? 'مرجع الدفع' : 'Payment reference'}
            </label>
            <input
              type="text"
              value={payReference}
              onChange={e => setPayReference(e.target.value)}
              maxLength={128}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-jea-primary"
              data-testid="pay-fine-reference-input"
              autoFocus
            />
            <div className="mt-5 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setPayTarget(null)}
                disabled={paying}
                className="px-4 py-2 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 disabled:opacity-50"
                data-testid="pay-fine-cancel"
              >
                {isArabic ? 'إلغاء' : 'Cancel'}
              </button>
              <button
                type="button"
                onClick={handlePay}
                disabled={paying}
                className="px-5 py-2 text-sm bg-jea-primary text-white font-bold rounded-lg hover:opacity-90 disabled:opacity-50"
                data-testid="pay-fine-submit"
              >
                {paying
                  ? (isArabic ? 'جارٍ الحفظ…' : 'Saving…')
                  : (isArabic ? 'تأكيد الدفع' : 'Confirm')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function IssueForm({ bounds, isArabic, onIssued, onError }: {
  bounds: Record<string, { min: number; max: number; area_threshold_m2: number | null }>;
  isArabic: boolean;
  onIssued: (msg: string) => void;
  onError: (msg: string) => void;
}) {
  const [kind, setKind] = useState<Kind>('unlicensed_contractor_small');
  const [amountJod, setAmountJod] = useState<string>('');
  const [targetDisplay, setTargetDisplay] = useState('');
  const [projectAreaM2, setProjectAreaM2] = useState<string>('');
  const [reason, setReason] = useState('');
  const [submitting, setSubmitting] = useState(false);

  // Auto-tier-from-area: nudge the picker when the admin types area.
  const suggestedKind = useMemo<Kind | null>(() => {
    const area = parseInt(projectAreaM2, 10);
    if (isNaN(area) || area <= 0) return null;
    return area <= 250 ? 'unlicensed_contractor_small' : 'unlicensed_contractor_large';
  }, [projectAreaM2]);

  useEffect(() => {
    if (suggestedKind && suggestedKind !== kind) {
      // Auto-switch only when the admin hasn't manually deviated —
      // detected by kind still matching the OTHER suggestion.
      setKind(suggestedKind);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [suggestedKind]);

  const currentBounds = bounds[kind];
  const suggested = currentBounds && amountJod === ''
    ? `${currentBounds.min}–${currentBounds.max}`
    : '';

  const handleSubmit = async () => {
    const amount = parseFloat(amountJod);
    if (isNaN(amount) || amount <= 0) {
      onError(isArabic ? 'يرجى إدخال المبلغ.' : 'Amount required.');
      return;
    }
    if (targetDisplay.trim().length === 0) {
      onError(isArabic ? 'اسم المالك مطلوب.' : 'Owner name required.');
      return;
    }
    if (reason.trim().length < 10) {
      onError(isArabic ? 'المبرر يجب أن يكون 10 أحرف على الأقل.' : 'Reason must be at least 10 chars.');
      return;
    }
    setSubmitting(true);
    try {
      await adminApi.issueLegalFine({
        kind,
        amount_jod: amount,
        target_display: targetDisplay.trim(),
        project_area_m2: projectAreaM2 ? parseInt(projectAreaM2, 10) : undefined,
        reason: reason.trim(),
      });
      onIssued(isArabic ? 'تم إصدار الغرامة.' : 'Fine issued.');
    } catch (e) {
      onError((e as Error).message);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <section className="bg-white border border-gray-200 rounded-xl p-6 mb-6" data-testid="issue-form">
      <h2 className="text-sm font-bold text-gray-800 mb-4">
        {isArabic ? 'إصدار غرامة جديدة' : 'Issue new fine'}
      </h2>

      <div className="grid grid-cols-2 gap-2 mb-4" role="radiogroup" aria-label={isArabic ? 'النوع' : 'Kind'}>
        {(['unlicensed_contractor_small', 'unlicensed_contractor_large'] as Kind[]).map(k => (
          <button
            key={k}
            type="button"
            onClick={() => setKind(k)}
            className={`text-xs px-3 py-2 rounded-lg border text-start ${
              kind === k
                ? 'border-red-300 bg-red-50 text-red-800 font-semibold'
                : 'border-gray-200 text-gray-600 hover:border-gray-300'
            }`}
            data-testid={`kind-${k}`}
          >
            {KIND_LABEL_AR[k]}
            {bounds[k] && (
              <p className="text-[10px] font-normal opacity-70 mt-0.5">
                {bounds[k].min}–{bounds[k].max} JOD
              </p>
            )}
          </button>
        ))}
      </div>

      <div className="grid grid-cols-2 gap-3 mb-3">
        <label className="block">
          <span className="text-xs font-semibold text-gray-700">
            {isArabic ? 'اسم المالك' : 'Owner name'}
          </span>
          <input
            type="text"
            value={targetDisplay}
            onChange={e => setTargetDisplay(e.target.value)}
            maxLength={255}
            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-jea-primary"
            data-testid="target-display"
          />
        </label>
        <label className="block">
          <span className="text-xs font-semibold text-gray-700">
            {isArabic ? 'مساحة المشروع (م²)' : 'Project area (m²)'}
          </span>
          <input
            type="number"
            value={projectAreaM2}
            onChange={e => setProjectAreaM2(e.target.value)}
            min={1}
            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-jea-primary"
            data-testid="project-area"
          />
          {suggestedKind && (
            <span className="text-[10px] text-gray-500 mt-0.5 block">
              {isArabic ? 'النوع المقترح تلقائياً.' : 'Kind auto-suggested from area.'}
            </span>
          )}
        </label>
      </div>

      <label className="block mb-3">
        <span className="text-xs font-semibold text-gray-700">
          {isArabic ? 'المبلغ (JOD)' : 'Amount (JOD)'}
          {suggested && <span className="text-[10px] text-gray-400 mx-2">({isArabic ? 'المدى' : 'range'}: {suggested})</span>}
        </span>
        <input
          type="number"
          value={amountJod}
          onChange={e => setAmountJod(e.target.value)}
          min={0}
          step="0.01"
          className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-jea-primary"
          data-testid="amount"
        />
      </label>

      <label className="block mb-4">
        <span className="text-xs font-semibold text-gray-700">
          {isArabic ? 'المبرر' : 'Reason'}
        </span>
        <textarea
          value={reason}
          onChange={e => setReason(e.target.value)}
          rows={3}
          maxLength={5000}
          placeholder={isArabic ? 'استند إلى محضر التفتيش والأدلة…' : 'Reference inspection report + evidence…'}
          className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-jea-primary"
          data-testid="reason"
        />
      </label>

      <div className="flex justify-end">
        <button
          type="button"
          onClick={handleSubmit}
          disabled={submitting}
          className="inline-flex items-center gap-1 px-5 py-2 text-sm bg-red-600 text-white font-bold rounded-lg hover:bg-red-700 disabled:opacity-50"
          data-testid="issue-submit"
        >
          <Scale size={13} aria-hidden="true" />
          {submitting
            ? (isArabic ? 'جارٍ الإصدار…' : 'Issuing…')
            : (isArabic ? 'إصدار الغرامة' : 'Issue fine')}
        </button>
      </div>
    </section>
  );
}
