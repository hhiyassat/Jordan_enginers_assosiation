import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  AlertCircle, ArrowLeft, ArrowRight, CheckCircle2, ClipboardList, Download, Edit3, FileText,
} from 'lucide-react';
import { applicationsApi } from '../../api/client';
import type { Application, ApplicationReview } from '../../types';
import { errorMessage } from '../../platform/utils/errorMessage';

/**
 * ApplicationDetail — JORD-59 / JORD-62
 *
 * Read-only view of a single application, reachable from the "My
 * Requests" list. Before this page existed, clicking a row in
 * MyApplications navigated to `/applications/:id`, which had no
 * route and got caught by the wildcard redirect back to `/` — so
 * an applicant could never open their own submission.
 *
 * The page also unblocks the amendment loop (JORD-62): when the
 * auditor uses "request modifications", the app moves to
 * `modifications_requested` and the backend re-opens it for editing.
 * We show the auditor's latest notes in a warning banner and expose
 * an "Edit request" CTA that jumps back into the Apply wizard
 * pre-loaded with the existing form data.
 *
 * Note on scope: this page is intentionally passive — no state
 * mutations beyond the initial fetch. Payments + submissions live
 * on the wizard; certificate download hangs off the signed URL the
 * backend already returns; everything else is display-only.
 */

const STATUS_STYLE: Record<string, { color: string; icon: string }> = {
  draft:                   { color: 'bg-gray-100 text-gray-700', icon: '📝' },
  submitted:               { color: 'bg-blue-100 text-blue-700', icon: '📤' },
  under_review:            { color: 'bg-indigo-100 text-indigo-700', icon: '🔍' },
  modifications_requested: { color: 'bg-orange-100 text-orange-800', icon: '⚠️' },
  approved:                { color: 'bg-emerald-100 text-emerald-800', icon: '✅' },
  rejected:                { color: 'bg-red-100 text-red-700', icon: '❌' },
  certificate_issued:      { color: 'bg-teal-100 text-teal-800', icon: '📄' },
};

const EDITABLE_STATUSES = new Set(['draft', 'modifications_requested']);

export function ApplicationDetail() {
  const { id } = useParams<{ id: string }>();
  const applicationId = Number(id);
  const navigate = useNavigate();
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const isArabic = isRtl;

  const [application, setApplication] = useState<Application | null>(null);
  const [certificateUrl, setCertificateUrl] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!Number.isFinite(applicationId) || applicationId <= 0) {
      setError(isArabic ? 'رقم الطلب غير صالح.' : 'Invalid application id.');
      setLoading(false);
      return;
    }
    setLoading(true);
    applicationsApi.get(applicationId)
      .then(r => {
        setApplication(r.application);
        setCertificateUrl(r.certificate_pdf_url ?? null);
      })
      .catch(e => setError(errorMessage(e)))
      .finally(() => setLoading(false));
  }, [applicationId, isArabic]);

  if (loading) return (
    <div className="flex justify-center py-20">
      <div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" />
    </div>
  );

  if (error || !application) {
    return (
      <div className="max-w-3xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
        <div
          role="alert"
          data-testid="application-error"
          className="bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm flex items-start gap-2"
        >
          <AlertCircle size={16} className="mt-0.5 shrink-0" aria-hidden="true" />
          <span>{error || (isArabic ? 'تعذّر تحميل الطلب.' : 'Failed to load application.')}</span>
        </div>
        <Link
          to="/my-applications"
          className="mt-4 inline-flex items-center gap-1 text-sm text-jea-primary hover:underline"
        >
          {isRtl ? <ArrowRight size={14} aria-hidden="true" /> : <ArrowLeft size={14} aria-hidden="true" />}
          {isArabic ? 'العودة إلى طلباتي' : 'Back to My Requests'}
        </Link>
      </div>
    );
  }

  const style = STATUS_STYLE[application.status] ?? { color: 'bg-gray-100 text-gray-600', icon: '❓' };
  const statusLabel = t(`status.${application.status}`, { defaultValue: application.status });
  const service = application.service_definition;
  const serviceName = service ? (isArabic ? service.name_ar : (service.name_en || service.name_ar)) : '—';
  const canEdit = EDITABLE_STATUSES.has(application.status);
  // Latest review = highest review_round, then latest created_at.
  const latestReview: ApplicationReview | null = (application.reviews ?? [])
    .slice()
    .sort((a, b) => (b.review_round - a.review_round) || (b.created_at.localeCompare(a.created_at)))[0] ?? null;
  const showAmendmentBanner = application.status === 'modifications_requested' && latestReview;
  const Back = isRtl ? ArrowRight : ArrowLeft;

  const handleEdit = () => {
    if (!service?.code) {
      setError(isArabic
        ? 'لا يمكن فتح التعديل — بيانات الخدمة غير متوفرة.'
        : 'Cannot open edit — service data is missing.');
      return;
    }
    navigate(`/apply/${service.code}?application_id=${application.id}`);
  };

  return (
    <div className="max-w-4xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      <Link
        to="/my-applications"
        data-testid="back-to-my-applications"
        className="inline-flex items-center gap-1 text-sm text-jea-primary hover:underline mb-3"
      >
        <Back size={14} aria-hidden="true" />
        {isArabic ? 'العودة إلى طلباتي' : 'Back to My Requests'}
      </Link>

      <header className="mb-6">
        <div className="flex items-center gap-3 flex-wrap">
          <span className="font-mono text-xs text-gray-400" data-testid="application-reference">
            {application.reference_number}
          </span>
          {application.contract_no && (
            <span className="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-jea-accent text-jea-primary">
              {isArabic ? 'رقم العقد' : 'Contract'} · {application.contract_no}
            </span>
          )}
          <span
            className={`text-xs px-2.5 py-0.5 rounded-full font-medium ${style.color}`}
            data-testid="application-status-badge"
          >
            {style.icon} {statusLabel}
          </span>
        </div>
        <h1 className="text-2xl font-bold text-gray-900 mt-2">{serviceName}</h1>
      </header>

      {/* Modifications-requested banner + edit CTA */}
      {showAmendmentBanner && (
        <section
          role="alert"
          data-testid="modifications-banner"
          className="bg-orange-50 border border-orange-200 rounded-xl p-4 mb-6"
        >
          <div className="flex items-start gap-3">
            <AlertCircle size={20} className="text-orange-600 shrink-0 mt-0.5" aria-hidden="true" />
            <div className="flex-1 min-w-0">
              <h2 className="text-sm font-bold text-orange-900">
                {isArabic ? 'المدقق يطلب تعديلات على الطلب' : 'The reviewer has requested modifications'}
              </h2>
              {latestReview.notes && (
                <p
                  className="text-sm text-orange-900/90 mt-2 whitespace-pre-wrap"
                  data-testid="reviewer-notes"
                >
                  {latestReview.notes}
                </p>
              )}
              {latestReview.reviewer?.name && (
                <p className="text-xs text-orange-800/70 mt-2">
                  {isArabic ? 'المدقق:' : 'Reviewer:'} {latestReview.reviewer.name}
                  {' · '}
                  {new Date(latestReview.created_at).toLocaleDateString(isArabic ? 'ar-EG' : 'en-JO')}
                </p>
              )}
            </div>
          </div>
          <div className="mt-3 flex justify-end">
            <button
              type="button"
              onClick={handleEdit}
              data-testid="edit-application-btn"
              className="inline-flex items-center gap-1 px-4 py-2 bg-orange-600 text-white text-sm rounded-lg hover:bg-orange-700 font-medium"
            >
              <Edit3 size={14} aria-hidden="true" />
              {isArabic ? 'تعديل الطلب' : 'Edit request'}
            </button>
          </div>
        </section>
      )}

      {/* JORD-64 (PM): payment-required banner. Auditor approval moves
          the app to `approved` with `payment_status = pending`; the
          applicant had no on-screen instruction telling them how to
          settle the fee. Payment itself is off-platform (JEA counter
          / bank transfer per the manual), so this banner surfaces
          the amount + reference number and points to the counter. */}
      {application.status === 'approved' && application.payment_status === 'pending' && (
        <section
          role="alert"
          data-testid="payment-required-banner"
          className="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6"
        >
          <div className="flex items-start gap-3">
            <AlertCircle size={20} className="text-amber-700 shrink-0 mt-0.5" aria-hidden="true" />
            <div className="flex-1 min-w-0">
              <h2 className="text-sm font-bold text-amber-900">
                {isArabic ? 'مطلوب دفع الرسوم' : 'Payment required'}
              </h2>
              <p className="text-sm text-amber-900/90 mt-1">
                {isArabic
                  ? `مبلغ ${application.fee_amount} ${service?.currency ?? 'JOD'} — يُدفع في مقرّ نقابة المهندسين مع ذكر رقم الطلب أدناه. تُصدَر الشهادة فور تسجيل الدفع.`
                  : `Amount ${application.fee_amount} ${service?.currency ?? 'JOD'} — pay at the JEA counter and quote the reference number below. The certificate is issued as soon as payment is recorded.`}
              </p>
              <p
                className="text-xs font-mono text-amber-900 bg-amber-100 border border-amber-200 rounded px-2 py-1 mt-2 inline-block"
                data-testid="payment-reference-hint"
              >
                {application.reference_number}
              </p>
            </div>
          </div>
        </section>
      )}

      {/* Summary grid */}
      <section className="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
        <div className="bg-white border border-gray-200 rounded-xl p-4">
          <div className="flex items-center gap-2 text-xs text-gray-500 mb-1">
            <FileText size={12} aria-hidden="true" />
            {isArabic ? 'تاريخ التقديم' : 'Submitted'}
          </div>
          <div className="text-sm font-semibold text-gray-900">
            {application.submitted_at
              ? new Date(application.submitted_at).toLocaleDateString(isArabic ? 'ar-EG' : 'en-JO')
              : (isArabic ? 'لم يُقدَّم بعد' : 'Not submitted')}
          </div>
        </div>
        <div className="bg-white border border-gray-200 rounded-xl p-4">
          <div className="flex items-center gap-2 text-xs text-gray-500 mb-1">
            <ClipboardList size={12} aria-hidden="true" />
            {isArabic ? 'الرسوم' : 'Fee'}
          </div>
          <div className="text-sm font-semibold text-gray-900">
            {application.fee_amount} {service?.currency ?? 'JOD'}
            {' · '}
            <span className="text-xs text-gray-500">{application.payment_status}</span>
          </div>
        </div>
        <div className="bg-white border border-gray-200 rounded-xl p-4">
          <div className="flex items-center gap-2 text-xs text-gray-500 mb-1">
            <CheckCircle2 size={12} aria-hidden="true" />
            {isArabic ? 'الجولة' : 'Review round'}
          </div>
          <div className="text-sm font-semibold text-gray-900">{application.review_round}</div>
        </div>
      </section>

      {/* Documents */}
      <section
        aria-labelledby="app-detail-docs"
        className="bg-white border border-gray-200 rounded-xl overflow-hidden mb-6"
      >
        <div className="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
          <h2 id="app-detail-docs" className="text-sm font-bold text-gray-800">
            {isArabic ? 'المرفقات' : 'Attachments'}
          </h2>
          <span className="text-xs text-gray-500">{(application.documents ?? []).length}</span>
        </div>
        {(application.documents ?? []).length === 0 ? (
          <div className="text-center py-10 text-gray-400" data-testid="documents-empty">
            <FileText size={36} className="mx-auto mb-2 opacity-40" aria-hidden="true" />
            <p className="text-sm">{isArabic ? 'لا توجد مرفقات.' : 'No attachments.'}</p>
          </div>
        ) : (
          <ul className="divide-y divide-gray-100">
            {(application.documents ?? []).map(d => (
              <li key={d.id} className="px-5 py-3 flex items-center justify-between gap-3">
                <div className="min-w-0 flex-1">
                  <p className="text-sm text-gray-800 truncate">{d.original_filename}</p>
                  <p className="text-xs text-gray-400 mt-0.5">
                    {(d.size_bytes / 1024).toFixed(1)} KB · {d.mime_type}
                  </p>
                </div>
                <span
                  className={`text-[10px] px-2 py-0.5 rounded shrink-0 ${
                    d.status === 'accepted' ? 'bg-emerald-100 text-emerald-800'
                    : d.status === 'rejected' ? 'bg-red-100 text-red-700'
                    : 'bg-amber-100 text-amber-800'
                  }`}
                >
                  {d.status}
                </span>
              </li>
            ))}
          </ul>
        )}
      </section>

      {/* Certificate — issued cases only */}
      {certificateUrl && (
        <section className="bg-emerald-50 border border-emerald-200 rounded-xl p-4 mb-6 flex items-center justify-between gap-3">
          <div>
            <p className="text-sm font-semibold text-emerald-900">
              {isArabic ? 'الشهادة الصادرة' : 'Issued certificate'}
            </p>
            <p className="text-xs text-emerald-800/80 mt-0.5">
              {isArabic ? 'يمكن تحميل الشهادة الرسمية.' : 'You can download the official certificate.'}
            </p>
          </div>
          <a
            href={certificateUrl}
            target="_blank"
            rel="noreferrer"
            data-testid="certificate-download"
            className="inline-flex items-center gap-1 px-4 py-2 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700 font-medium"
          >
            <Download size={14} aria-hidden="true" />
            {isArabic ? 'تنزيل' : 'Download'}
          </a>
        </section>
      )}

      {/* Reviews history */}
      {(application.reviews ?? []).length > 0 && (
        <section
          aria-labelledby="app-detail-reviews"
          className="bg-white border border-gray-200 rounded-xl overflow-hidden"
        >
          <div className="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 id="app-detail-reviews" className="text-sm font-bold text-gray-800">
              {isArabic ? 'سجل المراجعة' : 'Review history'}
            </h2>
            <span className="text-xs text-gray-500">{(application.reviews ?? []).length}</span>
          </div>
          <ul className="divide-y divide-gray-100">
            {(application.reviews ?? []).slice().sort((a, b) =>
              (b.review_round - a.review_round) || b.created_at.localeCompare(a.created_at)
            ).map(r => (
              <li key={r.id} className="px-5 py-4" data-testid={`review-${r.id}`}>
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-gray-800">
                      {t(`status.${r.decision}`, { defaultValue: r.decision })}
                    </p>
                    <p className="text-xs text-gray-500 mt-0.5">
                      {r.reviewer?.name ?? '—'}
                      {' · '}
                      {isArabic ? 'الجولة' : 'Round'} {r.review_round}
                      {' · '}
                      {new Date(r.created_at).toLocaleDateString(isArabic ? 'ar-EG' : 'en-JO')}
                    </p>
                    {r.notes && (
                      <p className="text-sm text-gray-700 mt-2 whitespace-pre-wrap">{r.notes}</p>
                    )}
                  </div>
                </div>
              </li>
            ))}
          </ul>
        </section>
      )}

      {/* Edit CTA for editable non-modifications states (drafts) */}
      {canEdit && !showAmendmentBanner && (
        <div className="mt-6 flex justify-end">
          <button
            type="button"
            onClick={handleEdit}
            data-testid="edit-application-btn"
            className="inline-flex items-center gap-1 px-4 py-2 bg-jea-primary text-white text-sm rounded-lg hover:opacity-90 font-medium"
          >
            <Edit3 size={14} aria-hidden="true" />
            {isArabic ? 'متابعة التعديل' : 'Continue editing'}
          </button>
        </div>
      )}
    </div>
  );
}
