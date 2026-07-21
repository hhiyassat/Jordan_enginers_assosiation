import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  AlertCircle, CheckCircle2, Circle, DollarSign, FileText, Gavel, Scale,
} from 'lucide-react';
import { myOfficeApi } from '../../api/client';
import type { MyComplaint, MySanction } from '../../api/myOffice';

/**
 * MyOffice — JORD-84
 *
 * Applicant-facing self-service surface. An office user sees:
 *   • their own recurring obligations (F-04 registration + F-05 annual)
 *   • complaints filed against them (reporter identity stripped per manual p.278)
 *   • sanctions on them (active + historical)
 *
 * Read-only. Pay + decide flows stay on the admin surface — the office
 * can see what's owed / pending but can't self-authorize a payment
 * record or reverse a sanction from here. If they want to pay, they
 * bring the reference number to the JEA counter (or the admin surface
 * records it once the transfer clears).
 */

const TIER_LABEL_AR: Record<string, string> = {
  individual_engineer: 'مصنف مهندس',
  engineering:         'مصنف هندسي',
  consultant:          'استشاري',
  foreign:             'مكتب غير أردني',
};

const COMPLAINT_KIND_AR: Record<string, string> = {
  fee_undercutting:  'تخفيض أتعاب',
  contracting_ban:   'مخالفة قرار حظر تعاقد',
  safety_violation:  'مخالفة سلامة',
  other:             'أخرى',
};

const SANCTION_KIND_AR: Record<string, string> = {
  warning:         'إنذار',
  suspension_1yr:  'إيقاف سنة',
  suspension_2yr:  'إيقاف سنتين',
  deregistration:  'شطب',
};

export function MyOffice() {
  const { i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const isArabic = isRtl;

  const [me, setMe] = useState<{ id: number; name: string; office_classification: string | null } | null>(null);
  const [obligations, setObligations] = useState<
    Array<{ id: number; kind: string; period_year: number; amount_jod: string; due_date: string;
            paid_at: string | null; late_surcharge_jod: string; total_paid_jod: string | null }>
  >([]);
  const [rateTable, setRateTable] = useState<Record<string, { registration: number; annual_dues: number }>>({});
  const [complaints, setComplaints] = useState<MyComplaint[]>([]);
  const [sanctions, setSanctions]   = useState<MySanction[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);
    Promise.all([myOfficeApi.dues(), myOfficeApi.complaints(), myOfficeApi.sanctions()])
      .then(([duesRes, complaintsRes, sanctionsRes]) => {
        setMe(duesRes.me);
        setObligations(duesRes.obligations);
        setRateTable(duesRes.rate_table);
        setComplaints(complaintsRes.complaints);
        setSanctions(sanctionsRes.sanctions);
      })
      .catch(e => setError((e as Error).message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return (
    <div className="flex justify-center py-20">
      <div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" />
    </div>
  );

  const outstanding = obligations.filter(o => o.paid_at === null);
  const activeSanctions = sanctions.filter(s => {
    if (s.effective_until === null) return true;
    return new Date(s.effective_until) >= new Date();
  });

  const tier = me?.office_classification ?? 'individual_engineer';
  const rate = rateTable[tier];

  return (
    <div className="max-w-5xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      <header className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">
          {isArabic ? 'مكتبي' : 'My Office'}
        </h1>
        <p className="text-sm text-gray-500 mt-1">
          {me?.name} · {TIER_LABEL_AR[tier] ?? tier}
        </p>
      </header>

      {error && (
        <div role="alert" className="mb-4 bg-red-50 border border-red-200 rounded-xl p-3 text-red-700 text-sm flex items-start gap-2">
          <AlertCircle size={16} className="mt-0.5 shrink-0" aria-hidden="true" />
          <span>{error}</span>
        </div>
      )}

      {/* Summary strip */}
      <section className="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
        <div className="bg-white border border-gray-200 rounded-xl p-4" data-testid="summary-outstanding">
          <div className="flex items-center gap-2 text-xs text-gray-500 mb-1">
            <DollarSign size={12} aria-hidden="true" />
            {isArabic ? 'التزامات مستحقة' : 'Outstanding dues'}
          </div>
          <div className="text-2xl font-bold text-gray-900">{outstanding.length}</div>
        </div>
        <div className="bg-white border border-gray-200 rounded-xl p-4" data-testid="summary-complaints">
          <div className="flex items-center gap-2 text-xs text-gray-500 mb-1">
            <Gavel size={12} aria-hidden="true" />
            {isArabic ? 'شكاوى مفتوحة' : 'Open complaints'}
          </div>
          <div className="text-2xl font-bold text-gray-900">
            {complaints.filter(c => c.status === 'open' || c.status === 'investigating').length}
          </div>
        </div>
        <div className="bg-white border border-gray-200 rounded-xl p-4" data-testid="summary-sanctions">
          <div className="flex items-center gap-2 text-xs text-gray-500 mb-1">
            <Scale size={12} aria-hidden="true" />
            {isArabic ? 'عقوبات سارية' : 'Active sanctions'}
          </div>
          <div className="text-2xl font-bold text-gray-900">{activeSanctions.length}</div>
        </div>
      </section>

      {/* Rate reference for the office's tier */}
      {rate && (
        <section className="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
          <h2 className="text-xs font-bold text-blue-900 mb-2 flex items-center gap-1">
            <FileText size={12} aria-hidden="true" />
            {isArabic ? 'الأسعار المطبَّقة على تصنيف مكتبك' : 'Rates for your tier'}
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

      {/* Obligations */}
      <section aria-labelledby="my-obligations-heading" className="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
        <div className="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
          <h2 id="my-obligations-heading" className="text-sm font-bold text-gray-800">
            {isArabic ? 'التزاماتي' : 'My obligations'}
          </h2>
          <span className="text-xs text-gray-500">{obligations.length}</span>
        </div>
        {obligations.length === 0 ? (
          <p className="text-sm text-gray-400 text-center py-10" data-testid="obligations-empty">
            {isArabic ? 'لا توجد التزامات مسجّلة.' : 'No obligations on file.'}
          </p>
        ) : (
          <table className="w-full text-sm" data-testid="my-obligations-table">
            <thead className="bg-gray-50 text-xs text-gray-600 uppercase">
              <tr>
                <th className="px-5 py-2 text-start">{isArabic ? 'النوع' : 'Kind'}</th>
                <th className="px-5 py-2 text-start">{isArabic ? 'السنة' : 'Year'}</th>
                <th className="px-5 py-2 text-start">{isArabic ? 'المبلغ' : 'Amount'}</th>
                <th className="px-5 py-2 text-start">{isArabic ? 'الاستحقاق' : 'Due'}</th>
                <th className="px-5 py-2 text-start">{isArabic ? 'الحالة' : 'Status'}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {obligations.map(o => {
                const isPaid = o.paid_at !== null;
                const surcharge = parseFloat(o.late_surcharge_jod);
                return (
                  <tr key={o.id} data-testid={`my-obligation-row-${o.id}`}>
                    <td className="px-5 py-3">
                      <span className={`text-xs px-2 py-0.5 rounded ${
                        o.kind === 'registration' ? 'bg-indigo-50 text-indigo-700' : 'bg-teal-50 text-teal-700'
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
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
        {outstanding.length > 0 && (
          <div className="px-5 py-3 bg-amber-50 border-t border-amber-200 text-xs text-amber-900">
            {isArabic
              ? 'الدفع يُسجَّل من قبل نقابة المهندسين. راجع مقرّ النقابة أو انتظر تسجيل الحوالة.'
              : 'Payments are recorded by JEA. Visit the association or wait for the transfer to be posted.'}
          </div>
        )}
      </section>

      {/* Complaints against me */}
      <section aria-labelledby="my-complaints-heading" className="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
        <div className="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
          <h2 id="my-complaints-heading" className="text-sm font-bold text-gray-800">
            {isArabic ? 'الشكاوى المقدَّمة بحقّ مكتبي' : 'Complaints against my office'}
          </h2>
          <span className="text-xs text-gray-500">{complaints.length}</span>
        </div>
        {complaints.length === 0 ? (
          <p className="text-sm text-gray-400 text-center py-10" data-testid="complaints-empty">
            {isArabic ? 'لا توجد شكاوى.' : 'No complaints on file.'}
          </p>
        ) : (
          <ul className="divide-y divide-gray-100">
            {complaints.map(c => (
              <li key={c.id} data-testid={`my-complaint-${c.id}`} className="px-5 py-4">
                <div className="flex items-start justify-between gap-3">
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold text-gray-800">
                      {COMPLAINT_KIND_AR[c.kind] ?? c.kind}
                    </p>
                    <p className="text-xs text-gray-500 mt-0.5">
                      {isArabic ? 'تاريخ الإيداع:' : 'Filed:'} {c.created_at.slice(0, 10)}
                      {' · '}
                      {isArabic ? 'مهلة التحقيق:' : 'Deadline:'} {c.investigation_deadline}
                    </p>
                    <p className="text-sm text-gray-700 mt-2 whitespace-pre-wrap">{c.description}</p>
                    {c.sanctions.length > 0 && (
                      <div className="mt-2 flex flex-wrap gap-1">
                        {c.sanctions.map(s => (
                          <span key={s.id} className="text-[10px] px-2 py-0.5 rounded bg-red-50 text-red-700">
                            {SANCTION_KIND_AR[s.kind] ?? s.kind}
                            {' · '}
                            {s.effective_from}
                            {s.effective_until ? ` → ${s.effective_until}` : ''}
                          </span>
                        ))}
                      </div>
                    )}
                  </div>
                  <span
                    data-testid={`my-complaint-status-${c.id}`}
                    className={`text-[10px] px-2 py-0.5 rounded shrink-0 ${
                      c.status === 'open' || c.status === 'investigating'
                        ? 'bg-amber-100 text-amber-800'
                        : c.status === 'decided'
                          ? 'bg-red-100 text-red-800'
                          : 'bg-gray-100 text-gray-700'
                    }`}
                  >
                    {c.status}
                  </span>
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>

      {/* Sanctions on me */}
      <section aria-labelledby="my-sanctions-heading" className="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div className="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
          <h2 id="my-sanctions-heading" className="text-sm font-bold text-gray-800">
            {isArabic ? 'العقوبات' : 'Sanctions'}
          </h2>
          <span className="text-xs text-gray-500">{sanctions.length}</span>
        </div>
        {sanctions.length === 0 ? (
          <p className="text-sm text-gray-400 text-center py-10" data-testid="sanctions-empty">
            {isArabic ? 'لا توجد عقوبات.' : 'No sanctions on file.'}
          </p>
        ) : (
          <ul className="divide-y divide-gray-100">
            {sanctions.map(s => {
              const isActive = s.effective_until === null || new Date(s.effective_until) >= new Date();
              return (
                <li key={s.id} data-testid={`my-sanction-${s.id}`} className="px-5 py-3 flex items-center justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-gray-800">
                      {SANCTION_KIND_AR[s.kind] ?? s.kind}
                    </p>
                    <p className="text-xs text-gray-500 mt-0.5">
                      {s.effective_from}
                      {s.effective_until ? ` → ${s.effective_until}` : ''}
                    </p>
                    {s.reason && <p className="text-xs text-gray-600 mt-1">{s.reason}</p>}
                  </div>
                  <span className={`text-[10px] px-2 py-0.5 rounded shrink-0 ${
                    isActive ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600'
                  }`}>
                    {isActive
                      ? (isArabic ? 'سارية' : 'active')
                      : (isArabic ? 'منتهية' : 'expired')}
                  </span>
                </li>
              );
            })}
          </ul>
        )}
      </section>
    </div>
  );
}
