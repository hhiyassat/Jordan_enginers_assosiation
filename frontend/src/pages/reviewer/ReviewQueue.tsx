import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { reviewApi } from '../../api/client';
import type { Application } from '../../types';
import { errorMessage } from '../../utils/errorMessage';

export function ReviewQueue() {
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const isArabic = isRtl;
  const dateLocale = isArabic ? 'ar-EG' : 'en-JO';
  const [queue, setQueue]     = useState<Application[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError]     = useState('');

  useEffect(() => {
    reviewApi.queue()
      .then(r => setQueue(r.applications))
      .catch(e => setError(errorMessage(e)))
      .finally(() => setLoading(false));
  }, []);

  const slaStatus = (app: Application) => {
    if (app.sla_breached) return 'breached';
    if (!app.sla_deadline) return 'ok';
    const hours = (new Date(app.sla_deadline).getTime() - Date.now()) / 3600000;
    if (hours < 4) return 'urgent';
    if (hours < 12) return 'warning';
    return 'ok';
  };

  if (loading) return <div className="flex justify-center py-20"><div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" /></div>;

  return (
    <div className="max-w-4xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">{t('reviewQueue.title')}</h1>
        <p className="text-gray-500 text-sm mt-1">{t('reviewQueue.subtitle', { count: queue.length })}</p>
      </div>
      {error && (
        <div className="mb-6 bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm">{error}</div>
      )}

      {queue.length === 0 ? (
        <div className="text-center py-20 text-gray-400">
          <p className="text-5xl mb-3">🎉</p>
          <p>{t('reviewQueue.empty')}</p>
        </div>
      ) : (
        <div className="space-y-3">
          {queue.map(app => {
            const sla = slaStatus(app);
            const slaColors: Record<string, string> = {
              breached: 'border-red-400 bg-red-50',
              urgent:   'border-orange-400 bg-orange-50',
              warning:  'border-yellow-300 bg-yellow-50',
              ok:       'border-gray-200 bg-white',
            };
            const stage = app.service_definition?.schema?.workflow?.stages?.find(s => s.id === app.current_stage);
            const stageLabel = stage
              ? (isArabic ? (stage.label_ar || stage.label_en) : (stage.label_en || stage.label_ar))
              : app.current_stage;
            const serviceName = app.service_definition
              ? (isArabic ? (app.service_definition.name_ar || app.service_definition.name_en) : (app.service_definition.name_en || app.service_definition.name_ar))
              : '—';

            return (
              <Link
                key={app.id}
                to={`/review/${app.id}`}
                className={`block rounded-xl border-2 p-5 hover:shadow-md transition-all ${slaColors[sla]}`}
              >
                <div className="flex items-start justify-between gap-4">
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-3 flex-wrap">
                      <span className="font-mono text-xs text-gray-400">{app.reference_number}</span>
                      <span className="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-medium">
                        {stageLabel}
                      </span>
                      {sla === 'breached' && (
                        <span className="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-600 font-medium">
                          ⚠️ {t('reviewQueue.slaBreached')}
                        </span>
                      )}
                      {sla === 'urgent' && (
                        <span className="text-xs px-2 py-0.5 rounded-full bg-orange-100 text-orange-600 font-medium">
                          🔥 {t('reviewQueue.slaUrgent')}
                        </span>
                      )}
                    </div>

                    <p className="font-semibold text-gray-900 mt-2">{serviceName}</p>
                    <p className="text-sm text-gray-500 mt-0.5">
                      {app.applicant?.name} · {app.applicant?.email}
                    </p>

                    {app.sla_deadline && (
                      <p className="text-xs text-gray-400 mt-2">
                        {t('reviewQueue.deadline', { when: new Date(app.sla_deadline).toLocaleString(dateLocale) })}
                      </p>
                    )}
                  </div>

                  <div className="flex-shrink-0">
                    <span className={`text-xs px-3 py-1.5 rounded-lg font-medium ${
                      app.assigned_reviewer_id ? 'bg-orange-100 text-orange-700' : 'bg-blue-600 text-white'
                    }`}>
                      {app.assigned_reviewer_id ? `🔒 ${t('reviewQueue.yourReview')}` : t('reviewQueue.review')}
                    </span>
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
