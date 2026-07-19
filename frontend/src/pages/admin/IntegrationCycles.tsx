import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { integrationApi, type IntegrationCycle } from '../../api/client';

const STATUS_COLOR: Record<string, string> = {
  requirements_received: 'bg-blue-100 text-blue-700',
  code_done:             'bg-orange-100 text-orange-700',
  feedback_received:     'bg-yellow-100 text-yellow-700',
  closed:                'bg-green-100 text-green-700',
};

export function IntegrationCycles() {
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const dateLocale = isRtl ? 'ar-EG' : 'en-JO';
  const [cycles, setCycles]   = useState<IntegrationCycle[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError]     = useState('');

  useEffect(() => {
    integrationApi.cycles()
      .then(r => setCycles(r.data))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return (
    <div className="flex justify-center py-20">
      <div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" />
    </div>
  );

  return (
    <div className="max-w-5xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      <div className="mb-8 flex items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{t('integration.title')}</h1>
          <p className="text-gray-500 text-sm mt-1">{t('integration.count', { count: cycles.length })}</p>
        </div>
        <div className="text-xs text-gray-400 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 max-w-xs">
          <p className="font-medium text-gray-600 mb-1">{t('integration.endpointsTitle')}</p>
          <p>↙ POST /api/integration/receive-requirements</p>
          <p>↙ POST /api/integration/receive-feedback</p>
          <p>↗ POST /api/integration/cycles/{'{id}'}/notify-done</p>
        </div>
      </div>

      {error && (
        <div className="mb-6 bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm">
          {error}
        </div>
      )}

      {cycles.length === 0 ? (
        <div className="text-center py-20 text-gray-400">
          <p className="text-5xl mb-3">🔗</p>
          <p className="font-medium">{t('integration.empty')}</p>
          <p className="text-sm mt-1">
            {t('integration.emptyHint')}{' '}
            <code className="bg-gray-100 px-1 rounded">POST /api/integration/receive-requirements</code>
          </p>
        </div>
      ) : (
        <div className="space-y-3">
          {cycles.map(cycle => {
            const statusLabel = t(`integration.status.${cycle.status}`, { defaultValue: cycle.status });
            const statusColor = STATUS_COLOR[cycle.status] ?? 'bg-gray-100 text-gray-600';
            return (
              <Link
                key={cycle.id}
                to={`/admin/integration/${cycle.id}`}
                className="block rounded-xl border border-gray-200 bg-white p-5 hover:shadow-md transition-all"
              >
                <div className="flex items-start justify-between gap-4">
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-3 flex-wrap mb-2">
                      <span className="font-mono text-xs text-gray-400">{cycle.cycle_ref}</span>
                      <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${statusColor}`}>
                        {statusLabel}
                      </span>
                      {cycle.nashmi_project_id && (
                        <span className="text-xs px-2 py-0.5 rounded-full bg-purple-100 text-purple-700">
                          Nashmi #{cycle.nashmi_project_id}
                        </span>
                      )}
                    </div>
                    <p className="font-semibold text-gray-900">{cycle.service_name}</p>
                    <p className="text-sm text-gray-500 mt-0.5">{cycle.requirements_source}</p>
                  </div>

                  <div className={`flex-shrink-0 text-xs text-gray-400 ${isRtl ? 'text-left' : 'text-right'} space-y-1`}>
                    {cycle.requirements_received_at && (
                      <p>📥 {new Date(cycle.requirements_received_at).toLocaleDateString(dateLocale)}</p>
                    )}
                    {cycle.code_done_notified_at && (
                      <p>📤 {new Date(cycle.code_done_notified_at).toLocaleDateString(dateLocale)}</p>
                    )}
                    {cycle.feedback_received_at && (
                      <p>💬 {new Date(cycle.feedback_received_at).toLocaleDateString(dateLocale)}</p>
                    )}
                  </div>
                </div>
              </Link>
            );
          })}
        </div>
      )}
    </div>
  );
}
