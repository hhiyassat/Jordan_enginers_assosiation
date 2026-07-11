import React, { useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { applicationsApi } from '../../api/client';
import type { Application } from '../../types';

const STATUS_CONFIG: Record<string, { label: string; color: string; icon: string }> = {
  draft:               { label: 'مسودة',          color: 'bg-gray-100 text-gray-600',   icon: '📝' },
  submitted:           { label: 'تم التقديم',      color: 'bg-blue-100 text-blue-700',   icon: '📨' },
  initial_review:      { label: 'مراجعة أولية',    color: 'bg-yellow-100 text-yellow-700',icon: '🔍' },
  legal_review:        { label: 'مراجعة قانونية',  color: 'bg-purple-100 text-purple-700',icon: '⚖️' },
  modifications_requested: { label: 'يحتاج تعديل', color: 'bg-orange-100 text-orange-700',icon: '✏️' },
  pending_payment:     { label: 'في انتظار الدفع', color: 'bg-yellow-100 text-yellow-800',icon: '💳' },
  approved:            { label: 'موافق عليه',      color: 'bg-green-100 text-green-700', icon: '✅' },
  rejected:            { label: 'مرفوض',           color: 'bg-red-100 text-red-700',     icon: '❌' },
  certificate_issued:  { label: 'صدرت الشهادة',    color: 'bg-teal-100 text-teal-700',   icon: '🏆' },
};

export function MyApplications() {
  const [apps, setApps]     = useState<Application[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError]   = useState('');
  const location = useLocation();
  const justSubmitted = (location.state as { submitted?: boolean })?.submitted;

  useEffect(() => {
    applicationsApi.list()
      .then(r => setApps(r.applications))
      .catch(e => setError((e as Error).message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="flex justify-center py-20"><div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" /></div>;
  if (error)   return <div className="p-8 text-red-600 text-center">{error}</div>;

  return (
    <div className="max-w-4xl mx-auto px-4 py-8" dir="rtl">
      {justSubmitted && (
        <div className="mb-6 bg-green-50 border border-green-200 text-green-700 px-5 py-4 rounded-xl text-sm">
          ✅ تم تقديم طلبك بنجاح. سيتم إشعارك بأي تحديثات.
        </div>
      )}

      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">طلباتي</h1>
          <p className="text-gray-500 text-sm mt-1">{apps.length} طلب</p>
        </div>
        <Link
          to="/services"
          className="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 font-medium"
        >
          + طلب جديد
        </Link>
      </div>

      {apps.length === 0 ? (
        <div className="text-center py-20 text-gray-400">
          <p className="text-5xl mb-4">📋</p>
          <p className="text-lg font-medium text-gray-500">لا توجد طلبات بعد</p>
          <Link to="/services" className="mt-4 inline-block text-blue-600 hover:underline text-sm">
            تصفح الخدمات المتاحة
          </Link>
        </div>
      ) : (
        <div className="space-y-3">
          {apps.map(app => <ApplicationRow key={app.id} app={app} />)}
        </div>
      )}
    </div>
  );
}

function ApplicationRow({ app }: { app: Application }) {
  const statusInfo = STATUS_CONFIG[app.status] || { label: app.status, color: 'bg-gray-100 text-gray-600', icon: '❓' };
  const needsAction = app.status === 'modifications_requested';

  return (
    <Link
      to={`/applications/${app.id}`}
      className={`block bg-white rounded-xl border-2 p-5 hover:shadow-sm transition-all ${
        needsAction
          ? 'border-orange-400 hover:border-orange-500'
          : 'border-gray-200 hover:border-blue-300'
      }`}
    >
      {/* Action-needed banner — Eqratech methodology: applicant must see when modifications requested */}
      {needsAction && (
        <div className="mb-3 bg-orange-100 text-orange-800 text-xs font-medium px-3 py-2 rounded-lg flex items-center gap-2">
          <span>⚠️</span>
          <span>يحتاج إجراء — طُلب منك تعديل هذا الطلب. اضغط لعرض التفاصيل وملاحظات المراجع.</span>
        </div>
      )}

      <div className="flex items-start justify-between gap-4">
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-3 flex-wrap">
            <span className="font-mono text-xs text-gray-400">{app.reference_number}</span>
            <span className={`text-xs px-2.5 py-0.5 rounded-full font-medium ${statusInfo.color}`}>
              {statusInfo.icon} {statusInfo.label}
            </span>
            {app.sla_breached && (
              <span className="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-600">⚠️ تجاوز الوقت</span>
            )}
          </div>

          <p className="font-semibold text-gray-900 mt-2">
            {app.service_definition?.name_ar || '—'}
          </p>

          <div className="flex items-center gap-4 mt-2 text-xs text-gray-400">
            {app.submitted_at && (
              <span>📅 {new Date(app.submitted_at).toLocaleDateString('ar-EG')}</span>
            )}
            {app.fee_amount > 0 && (
              <span>💰 {app.fee_amount} دينار</span>
            )}
          </div>
        </div>

        <div className="flex-shrink-0 text-gray-300 text-xl">›</div>
      </div>
    </Link>
  );
}
