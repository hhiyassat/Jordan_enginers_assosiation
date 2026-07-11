import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { servicesApi } from '../../api/client';
import type { ServiceDefinition } from '../../types';

export function ServiceList() {
  const [services, setServices] = useState<ServiceDefinition[]>([]);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState('');

  useEffect(() => {
    servicesApi.list()
      .then(r => setServices(r.services))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <LoadingGrid />;
  if (error)   return <div className="p-8 text-red-600">{error}</div>;

  return (
    <div className="max-w-5xl mx-auto px-4 py-8" dir="rtl">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">الخدمات الإلكترونية</h1>
        <p className="text-gray-500 mt-1">اختر الخدمة التي تريد التقدم بطلب لها</p>
      </div>

      {services.length === 0 ? (
        <div className="text-center py-16 text-gray-400">
          <p className="text-4xl mb-3">📋</p>
          <p>لا توجد خدمات متاحة حالياً</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          {services.map(service => (
            <ServiceCard key={service.id} service={service} />
          ))}
        </div>
      )}
    </div>
  );
}

function ServiceCard({ service }: { service: ServiceDefinition }) {
  const categoryColors: Record<string, string> = {
    licensing: 'bg-blue-100 text-blue-700',
    permits:   'bg-green-100 text-green-700',
    default:   'bg-gray-100 text-gray-700',
  };
  const color = categoryColors[service.category || ''] || categoryColors.default;

  return (
    <Link
      to={`/apply/${service.code}`}
      className="block bg-white rounded-xl border border-gray-200 p-6 hover:border-blue-400 hover:shadow-md transition-all group"
    >
      <div className="flex items-start justify-between mb-4">
        <div className="w-12 h-12 bg-navy rounded-xl flex items-center justify-center text-white text-xl">
          📄
        </div>
        {service.category && (
          <span className={`text-xs px-2 py-1 rounded-full font-medium ${color}`}>
            {service.category}
          </span>
        )}
      </div>

      <h3 className="font-bold text-gray-900 text-lg group-hover:text-blue-600 transition-colors">
        {service.name_ar}
      </h3>
      <p className="text-sm text-gray-400 mt-0.5">{service.name_en}</p>

      {service.description_ar && (
        <p className="text-sm text-gray-500 mt-3 line-clamp-2">{service.description_ar}</p>
      )}

      <div className="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between text-sm text-gray-500">
        <span>💰 {service.base_fee} {service.currency}</span>
        <span>⏱ {service.sla_hours} ساعة</span>
      </div>
    </Link>
  );
}

function LoadingGrid() {
  return (
    <div className="max-w-5xl mx-auto px-4 py-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      {[1, 2, 3].map(i => (
        <div key={i} className="bg-gray-100 rounded-xl h-48 animate-pulse" />
      ))}
    </div>
  );
}
