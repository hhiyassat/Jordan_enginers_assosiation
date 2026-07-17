import React, { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { applicationsApi, reviewApi, type StageAction } from '../../api/client';
import { DynamicForm } from '../../engine/DynamicForm';
import type { Application } from '../../types';

/**
 * Per-variant button colour classes. The backend registry classifies
 * each action into one of these variants; the UI just maps colour.
 */
const VARIANT_STYLES: Record<StageAction['variant'], { active: string; idle: string }> = {
  primary: {
    active: 'border-jea-primary bg-jea-accent text-jea-primary',
    idle:   'border-gray-200 text-gray-600 hover:border-jea-primary/40',
  },
  success: {
    active: 'border-emerald-400 bg-emerald-50 text-emerald-700',
    idle:   'border-gray-200 text-gray-600 hover:border-emerald-300',
  },
  warn: {
    active: 'border-orange-400 bg-orange-50 text-orange-700',
    idle:   'border-gray-200 text-gray-600 hover:border-orange-300',
  },
  danger: {
    active: 'border-red-400 bg-red-50 text-red-700',
    idle:   'border-gray-200 text-gray-600 hover:border-red-300',
  },
  neutral: {
    active: 'border-gray-400 bg-gray-100 text-gray-700',
    idle:   'border-gray-200 text-gray-600 hover:border-gray-300',
  },
};

function notesLabelFor(action: StageAction | null): { label: string; placeholder: string } {
  if (!action) return { label: 'ملاحظات', placeholder: '' };
  if (!action.requires_notes) {
    return { label: `ملاحظات (اختياري) — ${action.label_ar}`, placeholder: 'أضف ملاحظة اختيارية...' };
  }
  if (action.id === 'reject') {
    return { label: 'سبب الرفض *', placeholder: 'اذكر سبب الرفض بوضوح — سيظهر هذا للمتقدم...' };
  }
  if (action.id === 'request_modifications') {
    return { label: 'وصف التعديلات المطلوبة *', placeholder: 'اذكر بالتفصيل ما يجب تعديله — سيظهر هذا للمتقدم...' };
  }
  return { label: `${action.label_ar} — سبب مطلوب *`, placeholder: 'اذكر السبب...' };
}

export function ReviewPanel() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();

  const [app, setApp]                       = useState<Application | null>(null);
  const [availableActions, setAvailableActions] = useState<StageAction[]>([]);
  const [selectedActionId, setSelectedActionId] = useState<string>('');
  const [loading, setLoading]     = useState(true);
  const [claiming, setClaiming]   = useState(false);
  const [deciding, setDeciding]   = useState(false);
  const [notes, setNotes]         = useState('');
  const [notesError, setNotesError] = useState('');
  const [pageError, setPageError]   = useState('');

  const reload = () => {
    if (!id) return;
    setLoading(true);
    applicationsApi.get(Number(id))
      .then(r => {
        setApp(r.application);
        setAvailableActions(r.available_actions ?? []);
      })
      .catch(e => setPageError((e as Error).message))
      .finally(() => setLoading(false));
  };

  useEffect(() => { reload(); /* eslint-disable-next-line react-hooks/exhaustive-deps */ }, [id]);

  // Only actions that actually cause a status transition are decision
  // candidates — the others are informational (e.g. classify_technical).
  const decisionActions = availableActions.filter(a => a.decision !== null);
  const selectedAction = decisionActions.find(a => a.id === selectedActionId) ?? null;

  const handleClaim = async () => {
    if (!app) return;
    setClaiming(true);
    setPageError('');
    try {
      const r = await reviewApi.claim(app.id);
      setApp(r.application);
      // Claiming may unlock new available actions for this actor.
      reload();
    } catch (e: unknown) {
      setPageError((e as Error).message);
    } finally {
      setClaiming(false);
    }
  };

  const handleDecide = async () => {
    if (!app || !selectedAction || !selectedAction.decision) return;

    // Backend also enforces notes-required; front-end guard for UX only.
    if (selectedAction.requires_notes && !notes.trim()) {
      setNotesError('هذا الحقل مطلوب — يجب ذكر السبب وفق منهجية عقراتك');
      return;
    }

    setDeciding(true);
    setPageError('');
    setNotesError('');
    try {
      // Actions carry an annotation payload (e.g. override_first_auditor=true)
      // — merge it into the review record for audit traceability.
      const annotations = { action_id: selectedAction.id, ...selectedAction.annotation };
      await reviewApi.decide(app.id, selectedAction.decision, notes.trim() || undefined, annotations);
      navigate('/review/queue', { state: { decided: true, decision: selectedAction.decision, action: selectedAction.id } });
    } catch (err: unknown) {
      const apiErr = err as Error & { errors?: Record<string, string[]> };
      if (apiErr.errors?.notes) {
        setNotesError(apiErr.errors.notes[0]);
      } else {
        setPageError(apiErr.message);
      }
    } finally {
      setDeciding(false);
    }
  };

  const handleIssueCert = async () => {
    if (!app) return;
    setPageError('');
    try {
      const r = await reviewApi.issueCertificate(app.id);
      setApp(r.application);
      alert(`✅ تم إصدار الشهادة رقم: ${r.certificate.certificate_number}`);
    } catch (err: unknown) {
      setPageError((err as Error).message);
    }
  };

  if (loading || !app) return (
    <div className="flex justify-center py-20">
      <div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" />
    </div>
  );

  const schema     = app.service_definition?.schema;
  // Fix: was assigned_to_id (wrong) — backend column is assigned_reviewer_id
  const isClaimed  = !!app.assigned_reviewer_id;

  // Resolve current_stage ID → Arabic label from schema workflow stages
  const stageLabel = schema?.workflow?.stages?.find(s => s.id === app.current_stage)?.label_ar
    ?? app.current_stage;

  const notesUiCopy = notesLabelFor(selectedAction);

  return (
    <div className="max-w-4xl mx-auto px-4 py-8" dir="rtl">

      {/* Header */}
      <div className="flex items-start justify-between mb-8 gap-4">
        <div>
          <button onClick={() => navigate(-1)} className="text-sm text-gray-400 hover:text-gray-600 mb-2">
            → رجوع للقائمة
          </button>
          <h1 className="text-xl font-bold text-gray-900">
            {schema?.name_ar ?? app.service_definition?.name_ar}
          </h1>
          <p className="font-mono text-xs text-gray-400 mt-1">{app.reference_number}</p>
        </div>

        <div className="flex items-center gap-3 flex-wrap justify-end">
          {stageLabel && (
            <span className="text-xs px-3 py-1.5 rounded-full bg-blue-100 text-blue-700 font-medium">
              {stageLabel}
            </span>
          )}

          {/* Claim — only when submitted and not yet claimed by anyone */}
          {app.status === 'submitted' && !isClaimed && (
            <button
              onClick={handleClaim}
              disabled={claiming}
              className="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 disabled:opacity-50 font-medium"
            >
              {claiming ? 'جارٍ...' : '🔒 استلام الطلب'}
            </button>
          )}

          {isClaimed && app.status === 'under_review' && (
            <span className="text-xs px-3 py-1.5 rounded-full bg-orange-100 text-orange-700 font-medium">
              🔒 قيد مراجعتك
            </span>
          )}
        </div>
      </div>

      {pageError && (
        <div className="mb-6 bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm">
          {pageError}
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {/* Left: application data (read-only) */}
        <div className="lg:col-span-2 space-y-6">
          {schema && (
            <DynamicForm
              schema={schema}
              values={app.data as Record<string, unknown>}
              onChange={() => {}}
              disabled={true}
            />
          )}

          {/* Uploaded documents */}
          {(app.documents?.length ?? 0) > 0 && (
            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
              <div className="bg-navy px-6 py-3">
                <h3 className="text-white font-semibold text-sm">المستندات المرفوعة</h3>
              </div>
              <div className="p-5 space-y-3">
                {app.documents?.map(doc => (
                  <div key={doc.id} className="flex items-center justify-between text-sm">
                    <div>
                      <p className="font-medium text-gray-700">{doc.document_id}</p>
                      <p className="text-gray-400 text-xs">{doc.original_filename}</p>
                    </div>
                    <span className={`text-xs px-2 py-0.5 rounded-full ${
                      doc.status === 'accepted' ? 'bg-green-100 text-green-700' :
                      doc.status === 'rejected'  ? 'bg-red-100 text-red-700' :
                      'bg-yellow-100 text-yellow-700'
                    }`}>{doc.status}</span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Right: review panel */}
        <div className="space-y-4">

          {/* Previous reviews log */}
          {(app.reviews?.length ?? 0) > 0 && (
            <div className="bg-white rounded-xl border border-gray-200 p-5">
              <h3 className="font-semibold text-gray-800 text-sm mb-4">سجل المراجعات</h3>
              <div className="space-y-3">
                {app.reviews?.map(r => (
                  <div key={r.id} className="text-xs border-t border-gray-100 pt-3 first:border-t-0 first:pt-0">
                    <div className="flex justify-between">
                      <span className="font-medium text-gray-700">{r.stage}</span>
                      <span className={`px-1.5 py-0.5 rounded ${
                        r.decision === 'approved'                ? 'bg-green-100 text-green-700' :
                        r.decision === 'rejected'                ? 'bg-red-100 text-red-700' :
                        r.decision === 'modifications_requested' ? 'bg-orange-100 text-orange-700' :
                        'bg-gray-100 text-gray-600'
                      }`}>{
                        r.decision === 'approved'                ? 'موافقة' :
                        r.decision === 'rejected'                ? 'مرفوض' :
                        r.decision === 'modifications_requested' ? 'طلب تعديل' :
                        r.decision
                      }</span>
                    </div>
                    {r.notes && (
                      <p className="text-gray-600 mt-1.5 bg-gray-50 rounded p-1.5">{r.notes}</p>
                    )}
                    <p className="text-gray-400 mt-1">{r.reviewer?.name}</p>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Issue certificate (post-approval) */}
          {app.status === 'approved' && (
            <div className="bg-green-50 border border-green-200 rounded-xl p-5">
              <p className="text-green-700 font-medium text-sm mb-3">✅ الطلب موافق عليه</p>
              <button
                onClick={handleIssueCert}
                className="w-full py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium"
              >
                إصدار الشهادة
              </button>
            </div>
          )}

          {/* Terminal state badges */}
          {app.status === 'rejected' && (
            <div className="bg-red-50 border border-red-200 rounded-xl p-5 text-center text-sm text-red-700">
              ❌ هذا الطلب مرفوض
            </div>
          )}
          {app.status === 'certificate_issued' && (
            <div className="bg-teal-50 border border-teal-200 rounded-xl p-5 text-center text-sm text-teal-700">
              🏆 تم إصدار الشهادة
            </div>
          )}

          {/* Decision form — only when claimed and under_review.
              Buttons are generated from the schema's stage.actions[]
              (returned as available_actions on the GET response), so
              services with an override_first_auditor action get an extra
              button, and a service with no reviewer actions renders an
              empty state instead of the wrong buttons. */}
          {isClaimed && app.status === 'under_review' && (
            <div className="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
              <h3 className="font-semibold text-gray-800 text-sm">
                قرار المراجعة
                <span className="text-gray-400 font-normal text-xs mx-1" dir="ltr">· Review decision</span>
              </h3>

              {decisionActions.length === 0 && (
                <div role="status" className="text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2">
                  لا توجد إجراءات متاحة في هذه المرحلة — يرجى مراجعة الإعداد.
                </div>
              )}

              {decisionActions.length > 0 && (
                <>
                  <div className="space-y-2" role="group" aria-label="خيارات القرار">
                    {decisionActions.map(action => {
                      const cls = VARIANT_STYLES[action.variant];
                      const isSelected = selectedActionId === action.id;
                      return (
                        <button
                          key={action.id}
                          type="button"
                          onClick={() => { setSelectedActionId(action.id); setNotesError(''); setNotes(''); }}
                          aria-pressed={isSelected}
                          className={`w-full text-right px-4 py-3 rounded-lg border-2 text-sm font-medium transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-jea-primary/40 ${
                            isSelected ? cls.active : cls.idle
                          }`}
                        >
                          <span lang="ar">{action.label_ar}</span>
                          <span className="text-gray-400 font-normal text-xs mx-1" lang="en" dir="ltr">· {action.label_en}</span>
                        </button>
                      );
                    })}
                  </div>

                  {selectedAction && (
                    <div>
                      <label className="block text-xs font-medium text-gray-700 mb-1.5">
                        {notesUiCopy.label}
                        {selectedAction.requires_notes && (
                          <span className="text-red-500 mr-1 text-xs">— مطلوب وفق منهجية عقراتك</span>
                        )}
                      </label>
                      <textarea
                        value={notes}
                        onChange={e => { setNotes(e.target.value); if (notesError) setNotesError(''); }}
                        className={`w-full border rounded-lg p-3 text-sm focus:ring-2 focus:ring-jea-primary/40 focus:outline-none resize-none ${
                          notesError ? 'border-red-400 bg-red-50' : 'border-gray-300'
                        }`}
                        rows={4}
                        placeholder={notesUiCopy.placeholder}
                        aria-invalid={notesError ? true : undefined}
                        aria-describedby={notesError ? 'notes-error' : undefined}
                      />
                      {notesError && (
                        <p id="notes-error" role="alert" className="mt-1 text-xs text-red-600">⚠ {notesError}</p>
                      )}
                    </div>
                  )}

                  <button
                    onClick={handleDecide}
                    disabled={!selectedAction || deciding}
                    className="w-full py-2.5 bg-jea-topbar text-white rounded-lg hover:bg-jea-hover disabled:opacity-50 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-jea-primary/40"
                  >
                    {deciding ? 'جارٍ الحفظ...' : 'تأكيد القرار · Confirm decision'}
                  </button>
                </>
              )}
            </div>
          )}

          {/* Prompt to claim if submitted and unclaimed */}
          {app.status === 'submitted' && !isClaimed && (
            <div className="bg-blue-50 border border-blue-200 rounded-xl p-5 text-center text-sm text-blue-700">
              <p className="font-medium mb-1">الطلب ينتظر مراجعاً</p>
              <p className="text-xs text-blue-500">اضغط "استلام الطلب" لبدء المراجعة</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
