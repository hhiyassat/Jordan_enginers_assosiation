import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { applicationsApi, projectsApi, servicesApi } from '../../api/client';
import type { FeeBreakdown } from '../../api/applications';
import { DynamicForm, validateAll } from '../../engine/DynamicForm';
import { DocumentUploader } from '../../engine/DocumentUploader';
import type { Application, Project, ServiceDefinition, SchemaDocument, SchemaWorkflowStage } from '../../types';
import { WorkflowStepper } from '../../components/ui/WorkflowStepper';
import { ServiceInfoCard } from '../../components/ui/ServiceInfoCard';
import { ComplianceNotesBanner } from '../../components/ui/ComplianceNotesBanner';
import { ProjectContextHeader } from './ProjectContextHeader';
import { normalizeApplyError, labelForOtherKey, type ApiError } from './applyErrorHelpers';
import { missingRequiredDocsFor } from './missingRequiredDocs';
import { useAuth } from '../../auth/AuthContext';

/**
 * Map an Application.status to the corresponding stage_id in the
 * schema's workflow. Used to highlight the applicant's current
 * position on the WorkflowStepper. Best-effort heuristic — the engine
 * still runs against the fixed ALLOWED_TRANSITIONS today.
 */
function stageIdForApplication(
  stages: SchemaWorkflowStage[],
  application: Application | null,
): string | null {
  if (stages.length === 0) return null;
  if (!application) return stages[0]?.id ?? null;

  switch (application.status) {
    case 'draft':
    case 'modifications_requested':
      return stages[0]?.id ?? null;
    case 'submitted':
    case 'under_review': {
      // Pick the first "review"-type stage (auditor role or *_review id).
      const reviewStage = stages.find(s =>
        s.role === 'auditor' || s.role === 'staff' || /review/i.test(s.id)
      );
      return (reviewStage ?? stages[Math.min(1, stages.length - 1)]).id;
    }
    case 'approved': {
      const paymentStage = stages.find(s => /payment/i.test(s.id));
      return (paymentStage ?? stages[stages.length - 2] ?? stages[stages.length - 1]).id;
    }
    case 'certificate_issued':
      return stages[stages.length - 1]?.id ?? null;
    case 'rejected':
      return null;
    default:
      return stages[0]?.id ?? null;
  }
}

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
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { t, i18n } = useTranslation();
  // JORD-68 (PM): show the current applicant + org above the form so
  // the office user has a visible "I'm submitting as X for org Y"
  // confirmation. Previously the header only carried the service name,
  // which reviewers cited as confusing during shared-terminal QA.
  const { user } = useAuth();
  const isRtl = i18n.language.startsWith('ar');
  const isArabic = isRtl;
  const variantKey = searchParams.get('variant');
  const projectIdParam = searchParams.get('project_id');
  const projectId = projectIdParam ? Number(projectIdParam) : null;
  // JORD-62: when the applicant comes here from ApplicationDetail's
  // "Edit request" CTA (status = modifications_requested), we pre-load
  // the existing application into the wizard so their previous form
  // data + uploads are already there. Absent → normal "new application"
  // flow.
  const editApplicationIdParam = searchParams.get('application_id');
  const editApplicationId = editApplicationIdParam ? Number(editApplicationIdParam) : null;

  const [service, setService]         = useState<ServiceDefinition | null>(null);
  const [project, setProject]         = useState<Project | null>(null);
  const [application, setApplication] = useState<Application | null>(null);
  // JORD-65: itemized fee breakdown for the review step. Populated by
  // applicationsApi.get() which the backend serves live-computed off
  // the app's stored data + service schema. Null before the applicant
  // reaches the review step or when the service carries no fee.
  const [feeBreakdown, setFeeBreakdown] = useState<FeeBreakdown | null>(null);
  const [formData, setFormData]       = useState<Record<string, unknown>>({});
  const [errors, setErrors]           = useState<Record<string, string>>({});
  // Errors that don't map to a schema field/document — project_id,
  // service_code, top-level FormRequest failures. Rendered in the banner
  // so the applicant sees exactly what went wrong instead of just a
  // generic "review the highlighted fields".
  const [otherErrors, setOtherErrors] = useState<Record<string, string>>({});
  const [errorSummary, setErrorSummary] = useState<string>('');
  const [step, setStep]               = useState<Step>('form');
  const [loading, setLoading]         = useState(true);
  const [saving, setSaving]           = useState(false);
  const [submitting, setSubmitting]   = useState(false);

  useEffect(() => {
    if (!serviceCode) return;
    servicesApi.get(serviceCode)
      .then(r => setService(r.service))
      .catch(() => navigate('/services'))
      .finally(() => setLoading(false));
  }, [serviceCode, navigate]);

  // JORD-62: pre-load an existing application when the URL carries
  // ?application_id=X. Populates formData + application state so the
  // wizard opens on Step 1 with the previous entries already filled.
  // Non-editable statuses bounce back to the detail page — the
  // detail-page CTA only appears for editable statuses but a hand-
  // crafted URL shouldn't crash the app either.
  useEffect(() => {
    if (!editApplicationId) return;
    applicationsApi.get(editApplicationId)
      .then(r => {
        setApplication(r.application);
        setFormData((r.application.data ?? {}) as Record<string, unknown>);
        setFeeBreakdown(r.fee_breakdown ?? null);
      })
      .catch(() => navigate(`/applications/${editApplicationId}`));
  }, [editApplicationId, navigate]);

  // Load the project the applicant entered through (if any). Failure to
  // fetch is non-fatal — worst case the form renders without the read-only
  // project header, but the applicant can still submit.
  useEffect(() => {
    if (!projectId) { setProject(null); return; }
    projectsApi.get(projectId)
      .then(r => setProject(r.project))
      .catch(() => setProject(null));
  }, [projectId]);

  const handleFieldChange = (field: string, value: unknown) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    // Clear error for this field as user corrects it. Also clear any
    // banner-level otherErrors so the applicant isn't stuck reading a
    // stale summary after they've started fixing things.
    setErrors(prev => { const next = { ...prev }; delete next[field]; return next; });
    setOtherErrors({});
    setErrorSummary('');
  };

  const handleSaveDraft = async () => {
    if (!service || !service.schema) return;

    // JORD-16: client-side sweep of every visible field BEFORE we hit
    // the network. Cuts the "user hit Next without filling required
    // fields" round-trip and shows every field's problem at once
    // instead of just the first one the backend rejected. Backend
    // still validates on submit — this is an early exit, not a
    // replacement.
    // JORD-93: validateAll now defaults to the current i18n language,
    // so English users see English validation messages when a required
    // field is empty.
    const preflight = validateAll(service.schema, formData);
    if (!preflight.valid) {
      setErrors(preflight.errors);
      setOtherErrors({});
      setErrorSummary(t('apply.errorHeading'));
      setTimeout(() => {
        const firstError = document.querySelector('[data-field-error]') as HTMLElement | null;
        firstError?.focus();
      }, 100);
      return;
    }

    setSaving(true);
    // Fresh save clears any previous error state.
    setErrors({});
    setOtherErrors({});
    setErrorSummary('');
    try {
      if (application) {
        const r = await applicationsApi.update(application.id, formData);
        setApplication(r.application);
      } else {
        // Pass through the project link — the controller re-checks
        // ownership + org so a spoofed URL can't cross-attach.
        const r = await applicationsApi.create(service.code, formData, projectId ?? undefined);
        setApplication(r.application);
      }
      setStep('documents');
    } catch (err: unknown) {
      const { summary, fieldErrors, otherErrors } = normalizeApplyError(err as ApiError, service.schema);
      setErrors(fieldErrors);
      setOtherErrors(otherErrors);
      setErrorSummary(summary);
    } finally {
      setSaving(false);
    }
  };

  const handleDocumentUploaded = async () => {
    if (!application) return;
    const r = await applicationsApi.get(application.id);
    setApplication(r.application);
    // JORD-65: doc upload doesn't change the fee, but refresh anyway
    // so if the applicant edits the form between uploads the review
    // step reflects the latest breakdown from the server.
    setFeeBreakdown(r.fee_breakdown ?? null);
    // JORD-58: a fresh upload may satisfy the missing-docs gate.
    // Clear the "please upload required" banner so the user isn't
    // reading a stale error while they finish the remaining slots.
    setErrorSummary('');
  };

  /**
   * JORD-58: block the docs → review transition when a required
   * document is still missing. The previous flow let the applicant
   * click Next through an empty documents step, then hit Submit on
   * the review step, then bounce back to the FORM step (not docs)
   * with a 422 — the applicant lost context of which slot was empty.
   *
   * `missingDocs` is memoised off the current application + schema
   * so the disabled-state on the Next button stays in sync as
   * uploads complete without re-rendering the whole tree.
   */
  const missingDocs = useMemo<SchemaDocument[]>(
    () => missingRequiredDocsFor(service?.schema?.documents, application, formData),
    [application, service?.schema?.documents, formData],
  );

  const handleNextFromDocs = () => {
    if (missingDocs.length > 0) {
      setErrorSummary(isArabic
        ? 'يجب رفع كل الملفات الإلزامية قبل المتابعة.'
        : 'Please upload every required document before continuing.');
      setTimeout(() => {
        document.querySelector('[data-testid="missing-docs-banner"]')
          ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }, 50);
      return;
    }
    setErrorSummary('');
    setStep('review');
  };

  /**
   * JORD-65: when the applicant lands on the review step, pull the
   * live fee breakdown so the summary table can itemize base +
   * surcharges instead of just showing the single fee_amount. We do
   * this on step change (not on every render) so the breakdown reflects
   * the CURRENT saved state — earlier steps have already persisted the
   * form data via handleSaveDraft.
   */
  useEffect(() => {
    if (step !== 'review' || !application) return;
    applicationsApi.get(application.id)
      .then(r => setFeeBreakdown(r.fee_breakdown ?? null))
      .catch(() => setFeeBreakdown(null));
  }, [step, application?.id]);

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
      const apiErr = err as ApiError;
      const { summary, fieldErrors, otherErrors } = normalizeApplyError(apiErr, service?.schema);
      const hasFieldErrors = Object.keys(fieldErrors).length > 0;
      const hasOtherErrors = Object.keys(otherErrors).length > 0;

      if (hasFieldErrors || hasOtherErrors) {
        // EDA-10: Correctable Defect — return to form step, show inline + banner
        setErrors(fieldErrors);
        setOtherErrors(otherErrors);
        setErrorSummary(summary);
        setStep('form');
        // Announce error to screen readers (WCAG 2.1 AA)
        setTimeout(() => {
          const firstError = document.querySelector('[data-field-error]') as HTMLElement | null;
          firstError?.focus();
        }, 100);
      } else {
        // JORD-71: don't block the tab with window.alert(); surface
        // the summary inline via the same banner used for field-level
        // failures. `otherErrors` stays empty because we already
        // established there are none — the banner degrades to a
        // one-line message.
        setErrorSummary(summary || t('apply.submitError'));
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    } finally {
      setSubmitting(false);
    }
  };

  if (loading || !service || !service.schema) {
    return (
      <div className="flex items-center justify-center h-64" role="status" aria-label={t('loading')}>
        <div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" />
      </div>
    );
  }

  const schema = service.schema;
  const schemaName = isArabic ? (schema.name_ar || schema.name_en) : (schema.name_en || schema.name_ar);
  const steps: { id: Step; label: string }[] = [
    { id: 'form',      label: t('apply.steps.form') },
    { id: 'documents', label: t('apply.steps.documents') },
    { id: 'review',    label: t('apply.steps.review') },
  ];
  const currentStepIndex = steps.findIndex(s => s.id === step);

  // Workflow stages come from schema.workflow.stages by default, or from
  // schema.workflow.variants[variantKey] when the applicant chose an
  // alternate entry point (e.g. ?variant=modification for the
  // "تعديل عقد سابق" flow).
  const workflow = schema?.workflow;
  // `variantKey && expr` returns "" when variantKey is empty, which the
  // ?? chain doesn't fall through — force undefined on the falsy branch
  // so the fallback lands on workflow.stages instead of "".
  const workflowStages: SchemaWorkflowStage[] =
    (variantKey ? workflow?.variants?.[variantKey]?.stages : undefined)
      ?? workflow?.stages
      ?? [];
  const currentStageId = stageIdForApplication(workflowStages, application);
  const activeVariant = variantKey ? workflow?.variants?.[variantKey] : undefined;
  const variantLabel = activeVariant ? (isArabic ? (activeVariant.label_ar || activeVariant.label_en) : (activeVariant.label_en || activeVariant.label_ar)) : '';
  const workflowTitle = activeVariant ? t('apply.workflowTitleVariant', { variant: variantLabel }) : t('apply.workflowTitle');

  return (
    <main className="max-w-3xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      {/* Header */}
      <header className="mb-8">
        <p className="text-sm text-gray-400 mb-1">
          {activeVariant ? variantLabel : t('apply.newApplication')}
        </p>
        <h1 className="text-2xl font-bold text-gray-900">{schemaName}</h1>
        {application && (
          <p className="text-sm text-blue-600 mt-1 font-mono" aria-label={t('apply.referenceAria')}>
            {application.reference_number}
          </p>
        )}
      </header>

      {/* JORD-68 (PM): applicant identity card. Renders even when the
          project header is absent (SRV-013 replacement, MSC-* misc,
          etc.) so the office always sees "who am I submitting as". */}
      {user && (
        <section
          data-testid="apply-applicant-card"
          className="mb-4 bg-white border border-gray-200 rounded-xl p-4"
          aria-label={isArabic ? 'معلومات مقدم الطلب' : 'Applicant information'}
        >
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-full bg-jea-accent text-jea-primary flex items-center justify-center text-sm font-bold" aria-hidden="true">
              {(user.name || user.email).slice(0, 1).toUpperCase()}
            </div>
            <div className="min-w-0 flex-1">
              <p className="text-sm font-semibold text-gray-900 truncate">{user.name}</p>
              <p className="text-xs text-gray-500 truncate">{user.email}</p>
            </div>
            <span className="text-[10px] uppercase tracking-wide px-2 py-0.5 rounded-full bg-blue-50 border border-blue-200 text-blue-700">
              {isArabic ? 'مقدم الطلب' : 'Applicant'}
            </span>
          </div>
        </section>
      )}

      {/* JORD-18: service info card so the applicant sees fee/SLA/doc
          count before starting the form, not just the service name. */}
      <ServiceInfoCard service={service} />

      {/* Workflow stepper — read-only view of the schema's stages so the
          applicant sees the full path their application will take. */}
      {workflowStages.length > 0 && (
        <div className="mb-8">
          <WorkflowStepper
            stages={workflowStages}
            currentStageId={currentStageId}
            titleAr={workflowTitle}
            titleEn={workflowTitle}
            dimForRole="office"
          />
        </div>
      )}

      {/* EDA-10 error banner — headline summary + explicit list of the
          non-schema errors (project_id, service_code, ...). Schema field
          errors are inlined by DynamicForm; we don't repeat them here. */}
      {(Object.keys(errors).length > 0 || Object.keys(otherErrors).length > 0) && step === 'form' && (
        <div
          role="alert"
          className="mb-6 bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700"
        >
          <p className="font-semibold">{errorSummary || t('apply.errorHeading')}</p>
          {Object.keys(otherErrors).length > 0 && (
            <ul className={`mt-2 space-y-1 list-disc ${isRtl ? 'pr-5' : 'pl-5'} text-xs`}>
              {Object.entries(otherErrors).map(([key, msg]) => (
                <li key={key}>
                  <span className="font-semibold">{labelForOtherKey(key)}:</span>
                  <span className="mx-1">{msg}</span>
                </li>
              ))}
            </ul>
          )}
          {application && (
            <p className="mt-2 text-red-500 text-xs">
              {t('apply.errorDraftSaved')}
            </p>
          )}
        </div>
      )}

      {/* JORD-61: policy / compliance callouts that the platform can't
          gate mechanically but the applicant must see before submit
          (e.g. JORD-60's 10-day materials-sample retention). Renders
          nothing when schema.compliance_notes is empty. */}
      {schema.compliance_notes && schema.compliance_notes.length > 0 && (
        <div className="mb-6">
          <ComplianceNotesBanner notes={schema.compliance_notes} />
        </div>
      )}

      {/* Step indicator */}
      <nav aria-label={t('apply.stepIndicatorAria')} className="mb-8 flex items-center gap-0">
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
          {project && <ProjectContextHeader project={project} />}
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
              {t('apply.cancel')}
            </button>
            <button
              type="button"
              onClick={handleSaveDraft}
              disabled={saving}
              aria-busy={saving}
              className="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium"
            >
              {saving ? t('apply.savingDraft') : t('apply.nextDocuments')}
            </button>
          </div>
        </section>
      )}

      {/* Step 2 — Documents */}
      {step === 'documents' && application && (
        <section aria-labelledby="step-docs-title" className="space-y-6">
          <div className="bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm text-blue-700">
            <p className="font-medium mb-1">{t('apply.docsHeading')}</p>
            <p className="text-blue-500">{t('apply.docsHint')}</p>
          </div>
          {missingDocs.length > 0 && errorSummary && (
            <div
              role="alert"
              data-testid="missing-docs-banner"
              className="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-800"
            >
              <p className="font-medium mb-1">
                {isArabic
                  ? 'يرجى رفع كل الملفات الإلزامية قبل المتابعة:'
                  : 'Please upload every required document before continuing:'}
              </p>
              <ul className="list-disc ms-5 mt-1 space-y-0.5">
                {missingDocs.map(d => (
                  <li key={d.id}>{isArabic ? d.label_ar : d.label_en}</li>
                ))}
              </ul>
            </div>
          )}
          <DocumentUploader
            documents={schema.documents}
            application={application}
            formData={formData}
            onUploaded={handleDocumentUploaded}
          />
          <div className="flex justify-between pt-4">
            <button type="button" onClick={() => setStep('form')}
              className="px-5 py-2.5 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">
              {t('apply.back')}
            </button>
            <button
              type="button"
              onClick={handleNextFromDocs}
              data-testid="docs-next-btn"
              className="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium disabled:opacity-50"
              disabled={missingDocs.length > 0}
              aria-disabled={missingDocs.length > 0}
              title={missingDocs.length > 0
                ? (isArabic ? 'يجب رفع الملفات الإلزامية أولاً' : 'Upload the required documents first')
                : undefined}
            >
              {t('apply.nextReview')}
            </button>
          </div>
        </section>
      )}

      {/* Step 3 — Review & Submit */}
      {step === 'review' && application && (
        <section aria-labelledby="step-review-title" className="space-y-6">
          <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div className="bg-slate-800 px-6 py-3">
              <h2 id="step-review-title" className="text-white font-semibold text-sm">{t('apply.summaryHeading')}</h2>
            </div>
            <dl className="p-6 space-y-3">
              <div className="flex justify-between text-sm">
                <dt className="text-gray-500">{t('apply.summaryReference')}</dt>
                <dd className="font-mono font-medium">{application.reference_number}</dd>
              </div>
              <div className="flex justify-between text-sm">
                <dt className="text-gray-500">{t('apply.summaryService')}</dt>
                <dd className="font-medium">{schemaName}</dd>
              </div>

              {/* JORD-65: itemized fee breakdown. Falls back to the
                  single fee_amount row when the breakdown hasn't
                  loaded yet or the service carries no fee. */}
              {feeBreakdown && (feeBreakdown.surcharges.length > 0 || feeBreakdown.base > 0) ? (
                <>
                  <div className="flex justify-between text-sm pt-2 border-t border-gray-100">
                    <dt className="text-gray-500">{t('apply.summaryBaseFee', { defaultValue: 'الرسوم الأساسية' })}</dt>
                    <dd className="font-medium">{feeBreakdown.base.toFixed(2)} {feeBreakdown.currency}</dd>
                  </div>
                  {feeBreakdown.surcharges.map(s => (
                    <div key={s.code} className="flex justify-between text-xs" data-testid={`surcharge-${s.code}`}>
                      <dt className="text-gray-500">
                        {isArabic ? s.label_ar : s.label_en}
                      </dt>
                      <dd className="font-medium text-gray-600">
                        + {s.amount.toFixed(2)} {feeBreakdown.currency}
                      </dd>
                    </div>
                  ))}
                  <div className="flex justify-between text-sm pt-2 border-t border-gray-100">
                    <dt className="text-gray-500 font-semibold">{t('apply.summaryTotalFee', { defaultValue: 'الإجمالي' })}</dt>
                    <dd className="font-bold text-blue-600" data-testid="fee-total">
                      {feeBreakdown.total.toFixed(2)} {feeBreakdown.currency}
                    </dd>
                  </div>
                </>
              ) : (
                <div className="flex justify-between text-sm">
                  <dt className="text-gray-500">{t('apply.summaryFee')}</dt>
                  <dd className="font-medium text-blue-600">
                    {application.fee_amount} {service.currency}
                  </dd>
                </div>
              )}

              <div className="flex justify-between text-sm">
                <dt className="text-gray-500">{t('apply.summaryDocs')}</dt>
                <dd className="font-medium">{String(application.documents?.length ?? 0)}</dd>
              </div>
            </dl>
          </div>

          <div role="note" className="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-700">
            <p className="font-medium">{t('apply.confirmHeading')}</p>
            <p className="mt-1 text-amber-600">{t('apply.confirmBody')}</p>
          </div>

          <div className="flex justify-between pt-4">
            <button type="button" onClick={() => setStep('documents')}
              className="px-5 py-2.5 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">
              {t('apply.back')}
            </button>
            <button
              type="button"
              onClick={handleSubmit}
              disabled={submitting}
              aria-busy={submitting}
              className="px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 text-sm font-medium"
            >
              {submitting ? t('apply.submitting') : `✓ ${t('apply.submit')}`}
            </button>
          </div>
        </section>
      )}
    </main>
  );
}

