import React, { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { applicationsApi, servicesApi } from '../../api/client';
import { DynamicForm } from '../../engine/DynamicForm';
import { DocumentUploader } from '../../engine/DocumentUploader';
import type { Application, ServiceDefinition } from '../../types';

type Step = 'form' | 'documents' | 'review';

/**
 * Apply — Generic 3-step application wizard
 *
 * NFR-003: Bilingual Arabic/English.
 * NFR-004: WCAG 2.1 AA — proper aria-labels, semantic HTML, focus management.
 *
 * EDA-10 Correctable Defect handling (BUILD CONTRACT §3):
 *   - Submission validation failure → HTTP 422 with field errors
 *   - Navigate back to form step, show errors inline
 *   - Application stays in draft — case identity preserved
 *   - NEVER silently discards validation errors
 */
export function Apply() {
  const { serviceCode } = useParams<{ serviceCode: string }>();
  const navigate = useNavigate();

  const [service, setService]         = useState<ServiceDefinition | null>(null);
  const [application, setApplication] = useState<Application | null>(null);
  const [formData, setFormData]       = useState<Record<string, unknown>>({});
  const [errors, setErrors]           = useState<Record<string, string>>({});
  const [step, setStep]               = useState<Step>('form');
  const [loading, setLoading]         = useState(true);
  const [saving, setSaving]           = useState(false);
  const [submitting, setSubmitting]   = useState(false);

  useEffect(() => {
    if (!serviceCode) return;
    servicesApi.get(serviceCode)
      .then(r => setService((r as { service: ServiceDefinition }).service))
      .catch(() => navigate('/services'))
      .finally(() => setLoading(false));
  }, [serviceCode, navigate]);

  const handleFieldChange = (field: string, value: unknown) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    // Clear error for this field as user corrects it
    setErrors(prev => { const next = { ...prev }; delete next[field]; return next; });
  };

  const handleSaveDraft = async () => {
    if (!service) return;
    setSaving(true);
    try {
      if (application) {
        const r = await applicationsApi.update(application.id, formData);
        setApplication((r as { application: Application }).application);
      } else {
        const r = await applicationsApi.create(service.code, formData);
        setApplication((r as { application: Application }).application);
      }
      setStep('documents');
    } catch (err: unknown) {
      const e = err as { errors?: Record<string, string> };
      if (e.errors) setErrors(e.errors);
    } finally {
      setSaving(false);
    }
  };

  const handleDocumentUploaded = async () => {
    if (!application) return;
    const r = await applicationsApi.get(application.id);
    setApplication((r as { application: Application }).application);
  };

  /**
   * EDA-10 Correctable Defect (BUILD CONTRACT §3):
   * On 422 with field errors → return to form step with errors shown inline.
   * Application stays in draft. Case identity preserved.
   * BUILD CONTRACT P-1: validation errors are NEVER silently removed.
   */
  const handleSubmit = async () => {
    if (!application) return;
    setSubmitting(true);
    try {
      await applicationsApi.submit(application.id);
      navigate('/my-applications', { state: { submitted: true } });
    } catch (err: unknown) {
      const e = err as { errors?: Record<string, string>; message?: string };
      if (e.errors) {
        // EDA-10: Correctable Defect — return to form step, show field errors inline
        setErrors(e.errors);
        setStep('form');
        // Announce error to screen readers (WCAG 2.1 AA)
        setTimeout(() => {
          const firstError = document.querySelector('[data-field-error]') as HTMLElement | null;
          firstError?.focus();
        }, 100);
      } else {
        alert((e as Error).message || 'حدث خطأ أثناء التقديم. يرجى المحاولة مرة أخرى.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  if (loading || !service) {
    return (
      <div className="flex items-center justify-center h-64" role="status" aria-label="جارٍ التحميل">
        <div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" />
      </div>
    );
  }

  const schema = service.schema;
  const steps: { id: Step; label: string }[] = [
    { id: 'form',      label: 'البيانات' },
    { id: 'documents', label: 'المستندات' },
    { id: 'review',    label: 'المراجعة' },
  ];
  const currentStepIndex = steps.findIndex(s => s.id === step);

  return (
    <main className="max-w-3xl mx-auto px-4 py-8" dir="rtl">
      {/* Header */}
      <header className="mb-8">
        <p className="text-sm text-gray-400 mb-1">تقديم طلب جديد</p>
        <h1 className="text-2xl font-bold text-gray-900">{schema.name_ar}</h1>
        {application && (
          <p className="text-sm text-blue-600 mt-1 font-mono" aria-label="رقم الطلب">
            {application.reference_number}
          </p>
        )}
      </header>

      {/* EDA-10 error banner — shown when returning from submission failure */}
      {Object.keys(errors).length > 0 && step === 'form' && (
        <div
          role="alert"
          className="mb-6 bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700"
        >
          <p className="font-semibold">يوجد أخطاء في البيانات — يرجى مراجعة الحقول المحددة</p>
          <p className="mt-1 text-red-500 text-xs">
            تم حفظ طلبك. بعد تصحيح الأخطاء يمكنك المتابعة.
          </p>
        </div>
      )}

      {/* Step indicator */}
      <nav aria-label="خطوات التقديم" className="mb-8 flex items-center gap-0">
        {steps.map((s, i) => (
          <React.Fragment key={s.id}>
            <div
              className={`flex items-center gap-2 ${step === s.id ? 'text-blue-600' : 'text-gray-400'}`}
              aria-current={step === s.id ? 'step' : undefined}
            >
              <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold border-2 ${
                step === s.id
                  ? 'border-blue-600 bg-blue-600 text-white'
                  : currentStepIndex > i
                  ? 'border-green-500 bg-green-500 text-white'
                  : 'border-gray-300'
              }`}>
                {currentStepIndex > i ? '✓' : i + 1}
              </div>
              <span className="text-sm font-medium hidden sm:block">{s.label}</span>
            </div>
            {i < steps.length - 1 && <div className="flex-1 h-0.5 mx-2 bg-gray-200" />}
          </React.Fragment>
        ))}
      </nav>

      {/* Step 1 — Form */}
      {step === 'form' && (
        <section aria-labelledby="step-form-title" className="space-y-6">
          <DynamicForm
            schema={schema}
            values={formData}
            errors={errors}
            onChange={handleFieldChange}
          />
          <div className="flex justify-between pt-4">
            <button
              type="button"
              onClick={() => navigate('/services')}
              className="px-5 py-2.5 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm"
            >
              إلغاء
            </button>
            <button
              type="button"
              onClick={handleSaveDraft}
              disabled={saving}
              aria-busy={saving}
              className="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium"
            >
              {saving ? 'جارٍ الحفظ...' : 'التالي: المستندات ←'}
            </button>
          </div>
        </section>
      )}

      {/* Step 2 — Documents */}
      {step === 'documents' && application && (
        <section aria-labelledby="step-docs-title" className="space-y-6">
          <div className="bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm text-blue-700">
            <p className="font-medium mb-1">المستندات المطلوبة</p>
            <p className="text-blue-500">يرجى رفع جميع المستندات الإلزامية قبل تقديم الطلب</p>
          </div>
          <DocumentUploader
            documents={schema.documents}
            application={application}
            formData={formData}
            onUploaded={handleDocumentUploaded}
          />
          <div className="flex justify-between pt-4">
            <button type="button" onClick={() => setStep('form')}
              className="px-5 py-2.5 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">
              → رجوع
            </button>
            <button type="button" onClick={() => setStep('review')}
              className="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
              التالي: المراجعة ←
            </button>
          </div>
        </section>
      )}

      {/* Step 3 — Review & Submit */}
      {step === 'review' && application && (
        <section aria-labelledby="step-review-title" className="space-y-6">
          <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div className="bg-slate-800 px-6 py-3">
              <h2 id="step-review-title" className="text-white font-semibold text-sm">ملخص الطلب</h2>
            </div>
            <dl className="p-6 space-y-3">
              {[
                { label: 'رقم الطلب',         value: application.reference_number, mono: true },
                { label: 'الخدمة',             value: schema.name_ar },
                { label: 'الرسوم',             value: `${application.fee_amount} ${service.currency}`, blue: true },
                { label: 'المستندات المرفوعة', value: String(application.documents?.length ?? 0) },
              ].map(row => (
                <div key={row.label} className="flex justify-between text-sm">
                  <dt className="text-gray-500">{row.label}</dt>
                  <dd className={`font-medium ${row.mono ? 'font-mono' : ''} ${row.blue ? 'text-blue-600' : ''}`}>
                    {row.value}
                  </dd>
                </div>
              ))}
            </dl>
          </div>

          <div role="note" className="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-700">
            <p className="font-medium">تأكيد التقديم</p>
            <p className="mt-1 text-amber-600">بعد التقديم، لن تتمكن من تعديل البيانات إلا بعد طلب التعديل من الجهة المختصة.</p>
          </div>

          <div className="flex justify-between pt-4">
            <button type="button" onClick={() => setStep('documents')}
              className="px-5 py-2.5 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">
              → رجوع
            </button>
            <button
              type="button"
              onClick={handleSubmit}
              disabled={submitting}
              aria-busy={submitting}
              className="px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 text-sm font-medium"
            >
              {submitting ? 'جارٍ التقديم...' : '✓ تقديم الطلب'}
            </button>
          </div>
        </section>
      )}
    </main>
  );
}
