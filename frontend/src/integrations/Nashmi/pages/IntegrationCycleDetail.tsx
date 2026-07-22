import React, { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { integrationApi, type IntegrationCycle } from '../../../api/client';
import { errorMessage } from '../../../platform/utils/errorMessage';

const STATUS_COLOR: Record<string, string> = {
  requirements_received: 'bg-blue-100 text-blue-700',
  code_done:             'bg-orange-100 text-orange-700',
  feedback_received:     'bg-yellow-100 text-yellow-700',
  closed:                'bg-green-100 text-green-700',
};

export function IntegrationCycleDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const dateLocale = isRtl ? 'ar-EG' : 'en-JO';

  const [cycle, setCycle]     = useState<IntegrationCycle | null>(null);
  const [loading, setLoading] = useState(true);
  const [notifying, setNotifying] = useState(false);
  const [notifyError, setNotifyError] = useState('');
  const [notifySuccess, setNotifySuccess] = useState('');

  // Code-done form state
  const [gitBranch, setGitBranch]   = useState('main');
  const [gitCommit, setGitCommit]   = useState('');
  const [notesText, setNotesText]   = useState('');
  const [endpoints, setEndpoints]   = useState('');
  const [pages, setPages]           = useState('');
  const [tables, setTables]         = useState('');

  useEffect(() => {
    if (!id) return;
    integrationApi.cycle(Number(id))
      .then(r => setCycle(r.data))
      .finally(() => setLoading(false));
  }, [id]);

  const handleNotifyCodeDone = async () => {
    if (!cycle) return;
    setNotifying(true);
    setNotifyError('');
    setNotifySuccess('');
    try {
      const result = await integrationApi.notifyCodeDone(cycle.id, {
        git_branch:     gitBranch || 'main',
        git_commit:     gitCommit || undefined,
        api_endpoints:  endpoints.split('\n').map(s => s.trim()).filter(Boolean),
        frontend_pages: pages.split('\n').map(s => s.trim()).filter(Boolean),
        db_tables:      tables.split(',').map(s => s.trim()).filter(Boolean),
        notes:          notesText || undefined,
      });
      setNotifySuccess(`✅ ${t('integrationDetail.notified')} — ${result.message}`);
      // Refresh cycle
      integrationApi.cycle(cycle.id).then(r => setCycle(r.data));
    } catch (e: unknown) {
      setNotifyError(errorMessage(e));
    } finally {
      setNotifying(false);
    }
  };

  if (loading || !cycle) return (
    <div className="flex justify-center py-20">
      <div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" />
    </div>
  );

  const statusLabel = t(`integration.status.${cycle.status}`, { defaultValue: cycle.status });
  const statusColor = STATUS_COLOR[cycle.status] ?? 'bg-gray-100 text-gray-600';
  const canNotify = cycle.status === 'requirements_received' || cycle.status === 'feedback_received';

  return (
    <div className="max-w-4xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      {/* Header */}
      <div className="mb-8">
        <button onClick={() => navigate('/admin/integration')} className="text-sm text-gray-400 hover:text-gray-600 mb-2">
          {t('integrationDetail.backToList')}
        </button>
        <div className="flex items-start justify-between gap-4">
          <div>
            <h1 className="text-xl font-bold text-gray-900">{cycle.service_name}</h1>
            <p className="font-mono text-xs text-gray-400 mt-1">{cycle.cycle_ref}</p>
          </div>
          <span className={`text-xs px-3 py-1.5 rounded-full font-medium ${statusColor}`}>
            {statusLabel}
          </span>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left: cycle details */}
        <div className="lg:col-span-2 space-y-6">

          {/* Lifecycle timeline */}
          <div className="bg-white rounded-xl border border-gray-200 p-6">
            <h3 className="font-semibold text-gray-800 text-sm mb-4">مراحل الدورة</h3>
            <div className="space-y-3 text-sm">
              <TimelineItem
                done={!!cycle.requirements_received_at}
                label="متطلبات واردة من Nashmi"
                time={cycle.requirements_received_at}
              />
              <TimelineItem
                done={!!cycle.code_done_notified_at}
                label="تم إشعار Nashmi بجاهزية الكود"
                time={cycle.code_done_notified_at}
              />
              <TimelineItem
                done={!!cycle.feedback_received_at}
                label="ملاحظات واردة من Nashmi"
                time={cycle.feedback_received_at}
              />
              <TimelineItem
                done={cycle.status === 'closed'}
                label="الدورة مغلقة"
                time={cycle.status === 'closed' ? cycle.updated_at : null}
              />
            </div>
          </div>

          {/* Requirements meta */}
          {cycle.requirements_meta && (
            <div className="bg-white rounded-xl border border-gray-200 p-6">
              <h3 className="font-semibold text-gray-800 text-sm mb-3">بيانات المتطلبات</h3>
              <pre className="text-xs text-gray-600 bg-gray-50 rounded-lg p-3 overflow-auto">
                {JSON.stringify(cycle.requirements_meta, null, 2)}
              </pre>
            </div>
          )}

          {/* Feedback */}
          {cycle.feedback && (() => {
            const fb = cycle.feedback as {
              overall_status?: string;
              score?: number;
              reviewer_notes?: string;
              tester_notes?: string;
              qa_notes?: string;
            };
            return (
            <div className="bg-white rounded-xl border border-gray-200 p-6">
              <h3 className="font-semibold text-gray-800 text-sm mb-3">ملاحظات Nashmi</h3>
              <div className="space-y-2 text-sm">
                {fb.overall_status && (
                  <div className="flex gap-2">
                    <span className="text-gray-500 w-28 flex-shrink-0">الحالة الكلية:</span>
                    <span className={`font-medium ${
                      fb.overall_status === 'approved' ? 'text-green-700' :
                      fb.overall_status === 'rejected' ? 'text-red-700' :
                      'text-orange-700'
                    }`}>{fb.overall_status}</span>
                  </div>
                )}
                {fb.score !== undefined && fb.score !== null && (
                  <div className="flex gap-2">
                    <span className="text-gray-500 w-28 flex-shrink-0">النقاط:</span>
                    <span className="font-medium">{fb.score}/100</span>
                  </div>
                )}
                {fb.reviewer_notes && (
                  <div>
                    <p className="text-gray-500 mb-1">ملاحظات المراجع:</p>
                    <p className="bg-gray-50 rounded p-2 text-gray-700">{fb.reviewer_notes}</p>
                  </div>
                )}
                {fb.tester_notes && (
                  <div>
                    <p className="text-gray-500 mb-1">ملاحظات المختبر:</p>
                    <p className="bg-gray-50 rounded p-2 text-gray-700">{fb.tester_notes}</p>
                  </div>
                )}
                {fb.qa_notes && (
                  <div>
                    <p className="text-gray-500 mb-1">ملاحظات الجودة:</p>
                    <p className="bg-gray-50 rounded p-2 text-gray-700">{fb.qa_notes}</p>
                  </div>
                )}
              </div>
            </div>
            );
          })()}

          {/* Code summary */}
          {cycle.code_summary && (
            <div className="bg-white rounded-xl border border-gray-200 p-6">
              <h3 className="font-semibold text-gray-800 text-sm mb-3">ملخص الكود المُرسَل</h3>
              <pre className="text-xs text-gray-600 bg-gray-50 rounded-lg p-3 overflow-auto">
                {JSON.stringify(cycle.code_summary, null, 2)}
              </pre>
            </div>
          )}
        </div>

        {/* Right: notify-code-done panel */}
        <div className="space-y-4">
          {canNotify ? (
            <div className="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
              <h3 className="font-semibold text-gray-800 text-sm">{t('integrationDetail.notifyDone')}</h3>

              <div>
                <label className="block text-xs text-gray-500 mb-1">{t('integrationDetail.gitBranch')}</label>
                <input
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                  value={gitBranch}
                  onChange={e => setGitBranch(e.target.value)}
                  placeholder="main"
                />
              </div>

              <div>
                <label className="block text-xs text-gray-500 mb-1">{t('integrationDetail.gitCommit')}</label>
                <input
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"
                  value={gitCommit}
                  onChange={e => setGitCommit(e.target.value)}
                  placeholder="abc123f"
                />
              </div>

              <div>
                <label className="block text-xs text-gray-500 mb-1">{t('integrationDetail.apiEndpoints')}</label>
                <textarea
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"
                  rows={3}
                  value={endpoints}
                  onChange={e => setEndpoints(e.target.value)}
                  placeholder="POST /api/v1/services&#10;GET /api/v1/services/{code}"
                />
              </div>

              <div>
                <label className="block text-xs text-gray-500 mb-1">{t('integrationDetail.frontendPages')}</label>
                <textarea
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                  rows={3}
                  value={pages}
                  onChange={e => setPages(e.target.value)}
                  placeholder="ServiceList&#10;Apply&#10;ReviewQueue"
                />
              </div>

              <div>
                <label className="block text-xs text-gray-500 mb-1">{t('integrationDetail.dbTables')}</label>
                <input
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"
                  value={tables}
                  onChange={e => setTables(e.target.value)}
                  placeholder="applications, documents, reviews"
                />
              </div>

              <div>
                <label className="block text-xs text-gray-500 mb-1">{t('integrationDetail.notesLabel')}</label>
                <textarea
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                  rows={3}
                  value={notesText}
                  onChange={e => setNotesText(e.target.value)}
                  placeholder=""
                />
              </div>

              {notifyError && (
                <p className="text-xs text-red-600 bg-red-50 rounded p-2">{notifyError}</p>
              )}
              {notifySuccess && (
                <p className="text-xs text-green-700 bg-green-50 rounded p-2">{notifySuccess}</p>
              )}

              <button
                onClick={handleNotifyCodeDone}
                disabled={notifying}
                className="w-full py-2.5 bg-navy text-white rounded-lg hover:bg-blue-800 disabled:opacity-50 text-sm font-medium"
              >
                {notifying ? t('integrationDetail.notifying') : `📤 ${t('integrationDetail.notifyDone')}`}
              </button>
            </div>
          ) : (
            <div className="bg-gray-50 rounded-xl border border-gray-200 p-5 text-center text-sm text-gray-500">
              {cycle.status === 'code_done' && <p>⏳ {t('integration.status.code_done')}</p>}
              {cycle.status === 'closed' && <p>✅ {t('integration.status.closed')}</p>}
            </div>
          )}

          {/* Cycle metadata */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 text-xs text-gray-500 space-y-2">
            <div className="flex justify-between">
              <span>المصدر:</span>
              <span className="font-medium text-gray-700">{cycle.requirements_source}</span>
            </div>
            {cycle.nashmi_project_id && (
              <div className="flex justify-between">
                <span>مشروع Nashmi:</span>
                <span className="font-medium text-gray-700">#{cycle.nashmi_project_id}</span>
              </div>
            )}
            <div className="flex justify-between">
              <span>—</span>
              <span>{new Date(cycle.created_at).toLocaleDateString(dateLocale)}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function TimelineItem({ done, label, time }: {
  done: boolean;
  label: string;
  time: string | null | undefined;
}) {
  return (
    <div className={`flex items-start gap-3 ${done ? 'text-gray-900' : 'text-gray-400'}`}>
      <span className={`mt-0.5 w-5 h-5 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold ${
        done ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400'
      }`}>
        {done ? '✓' : '○'}
      </span>
      <div>
        <p className="text-sm">{label}</p>
        {time && (
          <p className="text-xs text-gray-400">
            {new Date(time).toLocaleString('ar-EG')}
          </p>
        )}
      </div>
    </div>
  );
}
