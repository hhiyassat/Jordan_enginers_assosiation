import React, { useEffect, useMemo, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Lock, Unlock } from 'lucide-react';
import { adminApi } from '../../api/client';
import type { ServiceDefinition } from '../../types';
import { errorMessage } from '../../utils/errorMessage';

const STATUS_COLOR: Record<string, string> = {
  active:   'bg-green-100 text-green-700',
  draft:    'bg-yellow-100 text-yellow-700',
  inactive: 'bg-gray-100 text-gray-500',
};

interface Category {
  code: string;
  name_ar: string;
  name_en: string;
}

export function ServicesList() {
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const isArabic = isRtl;
  const [services, setServices] = useState<ServiceDefinition[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState('');
  const [activating, setActivating] = useState<number | null>(null);
  const location = useLocation();
  const justCreated = (location.state as { created?: string; saved?: string })?.created;
  const justSaved   = (location.state as { saved?: string })?.saved;

  const load = () => {
    adminApi.listServices()
      .then(r => {
        setServices(r.services);
        // categories is a new field; fall back to [] so an older backend
        // that still returns only `services` renders as one un-grouped
        // section instead of crashing.
        setCategories(r.categories ?? []);
      })
      .catch(e => setError(errorMessage(e)))
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, []);

  // Group services by parent_code once per load. The backend already
  // returns them sorted by canonical category then code, so pushing
  // into arrays preserves that order — we don't re-sort here.
  const grouped = useMemo(() => {
    const bucket = new Map<string, ServiceDefinition[]>();
    for (const s of services) {
      const key = s.parent_code ?? '__ungrouped__';
      const arr = bucket.get(key) ?? [];
      arr.push(s);
      bucket.set(key, arr);
    }
    return bucket;
  }, [services]);

  const handleActivate = async (service: ServiceDefinition) => {
    setActivating(service.id);
    try {
      await adminApi.updateServiceStatus(service.id, 'active');
      setServices(prev => prev.map(s => s.id === service.id ? { ...s, status: 'active' } : s));
    } catch (e: unknown) {
      setError(errorMessage(e));
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
      setError(errorMessage(e));
    } finally {
      setActivating(null);
    }
  };

  const handleToggleLock = async (service: ServiceDefinition) => {
    setActivating(service.id);
    try {
      const r = service.is_locked
        ? await adminApi.unlockService(service.id)
        : await adminApi.lockService(service.id);
      setServices(prev => prev.map(s => s.id === service.id ? { ...s, is_locked: r.service.is_locked } : s));
    } catch (e: unknown) {
      setError(errorMessage(e));
    } finally {
      setActivating(null);
    }
  };

  if (loading) return (
    <div className="flex justify-center py-20">
      <div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" />
    </div>
  );

  const renderRow = (service: ServiceDefinition) => {
    const status = service.status ?? 'draft';
    const statusLabel = t(`adminServices.status.${status}`, { defaultValue: status });
    const statusColor = STATUS_COLOR[status] ?? 'bg-gray-100 text-gray-600';
    const isLoading = activating === service.id;
    const serviceName = isArabic ? (service.name_ar || service.name_en) : (service.name_en || service.name_ar);
    return (
      <div
        key={service.id}
        className="bg-white rounded-xl border border-gray-200 p-5 hover:border-gray-300 transition-all"
      >
        <div className="flex items-start justify-between gap-4">
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-3 flex-wrap">
              <span className="font-mono text-xs text-gray-400">{service.code}</span>
              <span className={`text-xs px-2.5 py-0.5 rounded-full font-medium ${statusColor}`}>
                {statusLabel}
              </span>
              {service.is_locked && (
                <span
                  className="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-medium"
                  title={t('adminServices.lockedTitle')}
                >
                  <Lock size={11} aria-hidden="true" /> {t('adminServices.locked')}
                </span>
              )}
            </div>
            <p className="font-semibold text-gray-900 mt-1.5">{serviceName}</p>
          </div>

          <div className="flex items-center gap-2 flex-shrink-0">
            <button
              onClick={() => handleToggleLock(service)}
              disabled={isLoading}
              aria-label={service.is_locked ? t('adminServices.unlockAria', { code: service.code }) : t('adminServices.lockAria', { code: service.code })}
              title={service.is_locked ? t('adminServices.unlockTitle') : t('adminServices.lockTitle')}
              className={`inline-flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg font-medium disabled:opacity-50 ${
                service.is_locked
                  ? 'border border-amber-300 text-amber-700 hover:bg-amber-50'
                  : 'border border-blue-300 text-blue-700 hover:bg-blue-50'
              }`}
            >
              {service.is_locked
                ? (<><Unlock size={12} aria-hidden="true" /> {t('adminServices.unlock')}</>)
                : (<><Lock size={12} aria-hidden="true" /> {t('adminServices.lock')}</>)}
            </button>

            <Link
              to={`/admin/services/${service.id}/edit`}
              aria-disabled={service.is_locked}
              onClick={e => { if (service.is_locked) e.preventDefault(); }}
              className={`px-3 py-1.5 text-xs border rounded-lg font-medium ${
                service.is_locked
                  ? 'border-gray-200 text-gray-300 cursor-not-allowed'
                  : 'border-gray-300 text-gray-600 hover:bg-gray-50'
              }`}
            >
              {t('adminServices.edit')}
            </Link>

            {service.status !== 'active' && (
              <button
                onClick={() => handleActivate(service)}
                disabled={isLoading}
                className="px-3 py-1.5 text-xs bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 font-medium"
              >
                {isLoading ? '...' : `🚀 ${t('adminServices.activate')}`}
              </button>
            )}

            {service.status === 'active' && (
              <button
                onClick={() => handleDeactivate(service)}
                disabled={isLoading}
                className="px-3 py-1.5 text-xs border border-red-300 text-red-600 rounded-lg hover:bg-red-50 disabled:opacity-50 font-medium"
              >
                {isLoading ? '...' : t('adminServices.deactivate')}
              </button>
            )}
          </div>
        </div>
      </div>
    );
  };

  // Categories the backend returned that actually carry services. An
  // empty category (e.g. JEA-ENG with 0 rows on a fresh org) is a
  // meaningless header, so we filter it out.
  const populated = categories.filter(c => (grouped.get(c.code)?.length ?? 0) > 0);

  return (
    <div className="max-w-4xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>

      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{t('adminServices.title')}</h1>
          <p className="text-gray-500 text-sm mt-1">{t('adminServices.count', { count: services.length })}</p>
        </div>
        <Link
          to="/admin/services/new"
          className="px-4 py-2 bg-navy text-white text-sm rounded-lg hover:bg-blue-800 font-medium"
        >
          + {t('adminServices.newService')}
        </Link>
      </div>

      {justCreated && (
        <div className="mb-6 bg-green-50 border border-green-200 rounded-xl p-4 text-green-700 text-sm">
          ✅ {t('adminServices.createdBanner', { name: justCreated })}
        </div>
      )}
      {justSaved && !justCreated && (
        <div className="mb-6 bg-blue-50 border border-blue-200 rounded-xl p-4 text-blue-700 text-sm">
          ✅ {t('adminServices.savedBanner', { name: justSaved })}
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
          <p className="text-lg">{t('adminServices.empty')}</p>
          <Link to="/admin/services/new" className="mt-4 inline-block text-blue-600 hover:underline text-sm">
            {t('adminServices.createFirst')}
          </Link>
        </div>
      ) : populated.length > 0 ? (
        <div className="space-y-8">
          {populated.map(cat => {
            const rows = grouped.get(cat.code) ?? [];
            const catName = isArabic ? (cat.name_ar || cat.name_en) : (cat.name_en || cat.name_ar);
            return (
              <section key={cat.code} aria-labelledby={`cat-${cat.code}`}>
                <div className="flex items-center gap-3 mb-3 border-b border-gray-200 pb-2">
                  <h2
                    id={`cat-${cat.code}`}
                    className="text-lg font-bold text-gray-900"
                  >
                    {catName}
                  </h2>
                  <span className="font-mono text-xs text-gray-400">{cat.code}</span>
                  <span className="text-xs text-gray-500">
                    {t('adminServices.count', { count: rows.length })}
                  </span>
                </div>
                <div className="space-y-3">
                  {rows.map(renderRow)}
                </div>
              </section>
            );
          })}
        </div>
      ) : (
        // Fallback: no categories returned (older backend) — render flat.
        <div className="space-y-3">
          {services.map(renderRow)}
        </div>
      )}
    </div>
  );
}
