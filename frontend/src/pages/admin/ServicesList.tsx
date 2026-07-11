import React, { useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { adminApi } from '../../api/client';
import type { ServiceDefinition } from '../../types';

const STATUS_CONFIG: Record<string, { label: string; color: string }> = {
  active:   { label: 'نشطة',    color: 'bg-green-100 text-green-700' },
  draft:    { label: 'مسودة',   color: 'bg-yellow-100 text-yellow-700' },
  inactive: { label: 'معطلة',   color: 'bg-gray-100 text-gray-500' },
};

export function ServicesList() {
  const [services, setServices] = useState<ServiceDefinition[]>([]);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState('');
  const [activating, setActivating] = useState<number | null>(null);
  const location = useLocation();
  const justCreated = (location.state as { created?: string; saved?: string })?.created;
  const justSaved   = (location.state as { saved?: string })?.saved;

  const load = () => {
    adminApi.listServices()
      .then(r => setServices(r.services))
      .catch(e => setError((e as Error).message))
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, []);

  const handleActivate = async (service: ServiceDefinition) => {
    setActivating(service.id);
    try {
      await adminApi.updateServiceStatus(service.id, 'active');
      setServices(prev => prev.map(s => s.id === service.id ? { ...s, status: 'active' } : s));
    } catch (e: unknown) {
      setError((e as Error).message);
    } finally {
      setActivating(null);
    }
  };

  const handleDeactivate = async (service: ServiceDefinition) => {
    setActivating(service.id);
    try {
      await adminApi.updateServiceStatus(service.id, 'inactive');
      setServices(prev => prev.map(s => s.id === service.id ? { ...s, status: 'inactive' } : s));
    } catch (e: unknown) {
      setError((e as Error).message);
    } finally {
      setActivating(null);
    }
  };

  if (loading) return (
    <div className="flex justify-center py-20">
      <div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" />
    </div>
  );

  return (
    <div className="max-w-4xl mx-auto px-4 py-8" dir="rtl">

      {/* Header */}
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">إدارة الخدمات</h1>
          <p className="text-gray-500 text-sm mt-1">{services.length} خدمة</p>
        </div>
        <Link
          to="/admin/services/new"
          className="px-4 py-2 bg-navy text-white text-sm rounded-lg hover:bg-blue-800 font-medium"
        >
          + خدمة جديدة بالذكاء الاصطناعي
        </Link>
      </div>

      {justCreated && (
        <div className="mb-6 bg-green-50 border border-green-200 rounded-xl p-4 text-green-700 text-sm">
          ✅ تم إنشاء الخدمة <strong>{justCreated}</strong> بنجاح
        </div>
      )}
      {justSaved && !justCreated && (
        <div className="mb-6 bg-blue-50 border border-blue-200 rounded-xl p-4 text-blue-700 text-sm">
          ✅ تم حفظ تعديلات الخدمة <strong>{justSaved}</strong>
        </div>
      )}

      {error && (
        <div className="mb-6 bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm">
          {error}
        </div>
      )}

      {services.length === 0 ? (
        <div className="text-center py-20 text-gray-400">
          <p className="text-5xl mb-3">📋</p>
          <p className="text-lg">لا توجد خدمات بعد</p>
          <Link to="/admin/services/new" className="mt-4 inline-block text-blue-600 hover:underline text-sm">
            أنشئ أول خدمة
          </Link>
        </div>
      ) : (
        <div className="space-y-3">
          {services.map(service => {
            const st = STATUS_CONFIG[service.status] ?? { label: service.status, color: 'bg-gray-100 text-gray-600' };
            const isLoading = activating === service.id;

            return (
              <div
                key={service.id}
                className="bg-white rounded-xl border border-gray-200 p-5 hover:border-gray-300 transition-all"
              >
                <div className="flex items-start justify-between gap-4">
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-3 flex-wrap">
                      <span className="font-mono text-xs text-gray-400">{service.code}</span>
                      <span className={`text-xs px-2.5 py-0.5 rounded-full font-medium ${st.color}`}>
                        {st.label}
                      </span>
                    </div>
                    <p className="font-semibold text-gray-900 mt-1.5">{service.name_ar}</p>
                    <p className="text-sm text-gray-400">{service.name_en}</p>
                  </div>

                  <div className="flex items-center gap-2 flex-shrink-0">
                    {/* Edit */}
                    <Link
                      to={`/admin/services/${service.id}/edit`}
                      className="px-3 py-1.5 text-xs border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 font-medium"
                    >
                      تعديل
                    </Link>

                    {/* Activate */}
                    {service.status !== 'active' && (
                      <button
                        onClick={() => handleActivate(service)}
                        disabled={isLoading}
                        className="px-3 py-1.5 text-xs bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 font-medium"
                      >
                        {isLoading ? '...' : '🚀 تفعيل'}
                      </button>
                    )}

                    {/* Deactivate */}
                    {service.status === 'active' && (
                      <button
                        onClick={() => handleDeactivate(service)}
                        disabled={isLoading}
                        className="px-3 py-1.5 text-xs border border-red-300 text-red-600 rounded-lg hover:bg-red-50 disabled:opacity-50 font-medium"
                      >
                        {isLoading ? '...' : 'تعطيل'}
                      </button>
                    )}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
