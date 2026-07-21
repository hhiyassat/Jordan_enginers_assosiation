import React, { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, ArrowRight, CheckCircle2, Circle, DollarSign, FileText, Plus, AlertCircle } from 'lucide-react';
import { adminApi } from '../../api/client';

/**
 * OfficeDues — JORD-79 UI
 *
 * Admin surface for one office's recurring obligations (registration
 * + annual dues). Shows the rate table for the office's classification
 * tier so the admin can eyeball "correct amount for the tier" before
 * paying, then lists every obligation ordered newest-year first.
 *
 * Pay flow is a small modal that captures payment_reference — the
 * backend computes the late surcharge at pay time, so this UI doesn't
 * need to preview it (the response body carries the total after pay).
 *
 * "Seed registration" button surfaces only when the office has NO
 * registration row for the current year — otherwise it would create
 * a duplicate (blocked by the DB composite unique but the button
 * would be misleading).
 */

interface Obligation {
  id: number;
  kind: 'registration' | 'annual_dues';
  period_year: number;
  period_label_ar: string | null;
  amount_jod: string;
  due_date: string;
  paid_at: string | null;
  payment_reference: string | null;
  late_surcharge_jod: string;
  total_paid_jod: string | null;
}

interface OfficePayload {
  id: number;
  name: string;
  office_classification: string | null;
}

const TIER_LABEL_AR: Record<string, string> = {
  individual_engineer: 'مصنف مهندس',
  engineering:         'مصنف هندسي',
  consultant:          'استشاري',
  foreign:             'مكتب غير أردني',
};

export function OfficeDues() {
  const { id } = useParams<{ id: string }>();
  const officeId = Number(id);
  const { i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const isArabic = isRtl;

  const [office, setOffice] = useState<OfficePayload | null>(null);
  const [obligations, setObligations] = useState<Obligation[]>([]);
  const [rateTable, setRateTable] = useState<Record<string, { registration: number; annual_dues: number }>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [payTarget, setPayTarget] = useState<Obligation | null>(null);
  const [payReference, setPayReference] = useState('');
  const [paying, setPaying] = useState(false);
  const [seeding, setSeeding] = useState(false);
  const [flash, setFlash] = useState('');

  const load = () => {
    setLoading(true);
    adminApi.listOfficeDues(officeId)
      .then(r => {
        setOffice(r.office);
        setObligations(r.obligations);
        setRateTable(r.rate_table);
      })
      .catch(e => setError((e as Error).message))
      .finally(() => setLoading(false));
  };
  useEffect(load, [officeId]);

  const currentYear = new Date().getFullYear();
  const hasCurrentRegistration = useMemo(
    () => obligations.some(o => o.kind === 'registration' && o.period_year === currentYear),
    [obligations, currentYear],
  );

  const handleSeedRegistration = async () => {
    setSeeding(true);
    setError('');
    try {
      await adminApi.seedOfficeRegistration(officeId);
      setFlash(isArabic ? 'تم إنشاء رسوم التسجيل.' : 'Registration fee created.');
      load();
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setSeeding(false);
    }
  };

  const handlePay = async () => {
    if (!payTarget) return;
    if (payReference.trim().length === 0) {
      setError(isArabic ? 'يرجى إدخال مرجع الدفع.' : 'Payment reference is required.');
      return;
    }
    setPaying(true);
    setError('');
    try {
      await adminApi.payDue(payTarget.id, payReference.trim());
      setFlash(isArabic ? 'تم تسجيل الدفع.' : 'Payment recorded.');
      setPayTarget(null);
      setPayReference('');
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

  if (!office) return null;

  const tier = office.office_classification ?? 'individual_engineer';
  const rate = rateTable[tier];
  const Back = isRtl ? ArrowRight : ArrowLeft;

  return (
    <div className="max-w-4xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      <header className="mb-6">
        <Link to={`/admin/offices/${office.id}`}
              className="inline-flex items-center gap-1 text-sm text-jea-primary hover:underline mb-3">
          <Back size={14} aria-hidden="true" />
          {isArabic ? 'العودة لإعدادات المكتب' : 'Back to office settings'}
        </Link>
        <h1 className="text-2xl font-bold text-gray-900">
          {isArabic ? 'الرسوم والاشتراكات' : 'Fees & Dues'}
        </h1>
        <p className="text-sm text-gray-500 mt-1">
          {office.name} · {TIER_LABEL_AR[tier] ?? tier}
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

      {/* Rate summary for this office's tier */}
      {rate && (
        <section className="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
          <h2 className="text-xs font-bold text-blue-900 mb-2 flex items-center gap-1">
            <FileText size={12} aria-hidden="true" />
            {isArabic ? 'الأسعار المطبَّقة على هذا التصنيف' : 'Rates for this tier'}
          </h2>
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div>
              <p className="text-xs text-blue-700">{isArabic ? 'رسوم التسجيل' : 'Registration'}</p>
              <p className="font-bold text-blue-900">{rate.registration} JOD</p>
            </div>
            <div>
              <p className="text-xs text-blue-700">{isArabic ? 'الرسوم السنوية' : 'Annual dues'}</p>
              <p className="font-bold text-blue-900">{rate.annual_dues} JOD</p>
            </div>
          </div>
        </section>
      )}

      {/* Seed registration CTA (only when missing) */}
      {!hasCurrentRegistration && (
        <section className="bg-white border-2 border-dashed border-amber-300 rounded-xl p-4 mb-6 flex items-center gap-3">
          <div className="w-10 h-10 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
            <AlertCircle size={18} aria-hidden="true" />
          </div>
          <div className="flex-1">
            <p className="text-sm font-semibold text-gray-800">
              {isArabic ? `لا توجد رسوم تسجيل مسجّلة لعام ${currentYear}.` : `No registration fee for ${currentYear}.`}
            </p>
            <p className="text-xs text-gray-500 mt-0.5">
              {isArabic ? 'اضغط لإنشاء الالتزام حسب تصنيف المكتب الحالي.' : 'Click to create the obligation at the current tier rate.'}
            </p>
          </div>
          <button
            type="button"
            onClick={handleSeedRegistration}
            disabled={seeding}
            data-testid="seed-registration-btn"
            className="inline-flex items-center gap-1 px-4 py-2 text-sm bg-amber-600 text-white rounded-lg hover:bg-amber-700 disabled:opacity-50 shrink-0"
          >
            <Plus size={14} aria-hidden="true" />
            {seeding
              ? (isArabic ? 'جارٍ…' : 'Seeding…')
              : (isArabic ? 'إنشاء رسوم التسجيل' : 'Seed registration')}
          </button>
        </section>
      )}

      {/* Obligations table */}
      <section aria-labelledby="obligations-heading" className="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div className="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
          <h2 id="obligations-heading" className="text-sm font-bold text-gray-800">
            {isArabic ? 'الالتزامات' : 'Obligations'}
          </h2>
          <span className="text-xs text-gray-500">
            {obligations.length} {isArabic ? 'التزام' : 'items'}
          </span>
        </div>
        {obligations.length === 0 ? (
          <p className="text-sm text-gray-400 text-center py-10">
            {isArabic ? 'لا توجد التزامات مسجّلة.' : 'No obligations on file.'}
          </p>
        ) : (
          <table className="w-full text-sm" data-testid="obligations-table">
            <thead className="bg-gray-50 text-xs text-gray-600 uppercase">
              <tr>
                <th className="px-5 py-2 text-start">{isArabic ? 'النوع' : 'Kind'}</th>
                <th className="px-5 py-2 text-start">{isArabic ? 'السنة' : 'Year'}</th>
                <th className="px-5 py-2 text-start">{isArabic ? 'المبلغ' : 'Amount'}</th>
                <th className="px-5 py-2 text-start">{isArabic ? 'الاستحقاق' : 'Due'}</th>
                <th className="px-5 py-2 text-start">{isArabic ? 'الحالة' : 'Status'}</th>
                <th className="px-5 py-2"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {obligations.map(o => {
                const isPaid = o.paid_at !== null;
                const surcharge = parseFloat(o.late_surcharge_jod);
                return (
                  <tr key={o.id} data-testid={`obligation-row-${o.id}`}>
                    <td className="px-5 py-3">
                      <span className={`text-xs px-2 py-0.5 rounded ${
                        o.kind === 'registration'
                          ? 'bg-indigo-50 text-indigo-700'
                          : 'bg-teal-50 text-teal-700'
                      }`}>
                        {o.kind === 'registration'
                          ? (isArabic ? 'تسجيل' : 'Registration')
                          : (isArabic ? 'سنوية' : 'Annual')}
                      </span>
                    </td>
                    <td className="px-5 py-3 font-mono text-xs">{o.period_year}</td>
                    <td className="px-5 py-3">
                      <span className="font-semibold">{o.amount_jod} JOD</span>
                      {surcharge > 0 && (
                        <span className="block text-[10px] text-red-600 mt-0.5">
                          + {o.late_surcharge_jod} {isArabic ? 'رسم تأخير' : 'late surcharge'}
                        </span>
                      )}
                    </td>
                    <td className="px-5 py-3 text-xs text-gray-600">{o.due_date}</td>
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
                          onClick={() => { setPayTarget(o); setPayReference(''); setError(''); }}
                          data-testid={`pay-btn-${o.id}`}
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

      {/* Pay modal */}
      {payTarget && (
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby="pay-modal-title"
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
          data-testid="pay-modal"
        >
          <div className="bg-white rounded-xl max-w-md w-full p-6 shadow-xl">
            <h3 id="pay-modal-title" className="text-base font-bold text-gray-900">
              {isArabic ? 'تسجيل دفع' : 'Record Payment'}
            </h3>
            <p className="text-xs text-gray-500 mt-1">
              {payTarget.period_label_ar ?? `${payTarget.kind} ${payTarget.period_year}`}
              &nbsp;· {payTarget.amount_jod} JOD
            </p>
            <label className="block mt-4 text-xs font-semibold text-gray-700 mb-1">
              {isArabic ? 'مرجع الدفع (تحويل بنكي، شيك، …)' : 'Payment reference'}
            </label>
            <input
              type="text"
              value={payReference}
              onChange={e => setPayReference(e.target.value)}
              maxLength={128}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-jea-primary"
              data-testid="pay-reference-input"
              autoFocus
            />
            <p className="text-[10px] text-gray-500 mt-1">
              {isArabic
                ? 'الغرامة (15%/30%) تُحسَب تلقائياً بناءً على تاريخ الاستحقاق.'
                : 'Late surcharge (15%/30%) is computed automatically from due_date.'}
            </p>
            <div className="mt-5 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setPayTarget(null)}
                disabled={paying}
                className="px-4 py-2 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 disabled:opacity-50"
                data-testid="pay-cancel"
              >
                {isArabic ? 'إلغاء' : 'Cancel'}
              </button>
              <button
                type="button"
                onClick={handlePay}
                disabled={paying}
                className="px-5 py-2 text-sm bg-jea-primary text-white font-bold rounded-lg hover:opacity-90 disabled:opacity-50"
                data-testid="pay-submit"
              >
                {paying
                  ? (isArabic ? 'جارٍ الحفظ…' : 'Saving…')
                  : (isArabic ? 'تأكيد الدفع' : 'Confirm payment')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
