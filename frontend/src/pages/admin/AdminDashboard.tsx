import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { adminApi } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';

interface Stats {
  total_applications: number;
  pending_review: number;
  approved_today: number;
  certificates_issued: number;
  active_services: number;
  total_users: number;
}

export function AdminDashboard() {
  const { user } = useAuth();
  const [stats, setStats]   = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError]   = useState('');
  // Defense in depth — the /admin route is gated at the SPA layer, but if
  // that gate ever slips this still hides user-mgmt affordances from an
  // actor who can't act on them.
  const canManageUsers = user?.can_manage_users ?? false;

  useEffect(() => {
    adminApi.dashboard()
      .then(r => setStats(r.stats as Stats))
      .catch(e => setError((e as Error).message))
      .finally(() => setLoading(false));
  }, []);

  const cards = stats ? [
    { label: 'إجمالي الطلبات',    value: stats.total_applications, icon: '📋', link: '/admin/applications', color: 'bg-blue-50 border-blue-200' },
    { label: 'في انتظار المراجعة', value: stats.pending_review,     icon: '🔍', link: '/review/queue',      color: 'bg-yellow-50 border-yellow-200' },
    { label: 'موافق عليها اليوم',  value: stats.approved_today,     icon: '✅', link: '/admin/applications', color: 'bg-green-50 border-green-200' },
    { label: 'الشهادات الصادرة',   value: stats.certificates_issued, icon: '🏆', link: '/admin/certificates', color: 'bg-teal-50 border-teal-200' },
    { label: 'الخدمات النشطة',     value: stats.active_services,    icon: '⚙️', link: '/admin/services',     color: 'bg-purple-50 border-purple-200' },
    ...(canManageUsers ? [{ label: 'المستخدمون', value: stats.total_users, icon: '👥', link: '/admin/users', color: 'bg-gray-50 border-gray-200' }] : []),
  ] : [];

  return (
    <div className="max-w-5xl mx-auto px-4 py-8" dir="rtl">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">لوحة التحكم</h1>
        <p className="text-gray-500 text-sm mt-1">نظرة عامة على النظام</p>
      </div>
      {error && (
        <div className="mb-6 bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm">{error}</div>
      )}

      {loading ? (
        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
          {[1,2,3,4,5,6].map(i => (
            <div key={i} className="h-28 bg-gray-100 rounded-xl animate-pulse" />
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
          {cards.map(card => (
            <Link
              key={card.label}
              to={card.link}
              className={`rounded-xl border-2 p-5 hover:shadow-md transition-all ${card.color}`}
            >
              <div className="text-3xl mb-2">{card.icon}</div>
              <div className="text-3xl font-bold text-gray-900">{card.value.toLocaleString('ar')}</div>
              <div className="text-sm text-gray-600 mt-1">{card.label}</div>
            </Link>
          ))}
        </div>
      )}

      {/* Quick links */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div className="bg-white rounded-xl border border-gray-200 p-6">
          <h3 className="font-semibold text-gray-800 mb-4">إجراءات سريعة</h3>
          <div className="space-y-2">
            <Link to="/review/queue" className="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-sm text-gray-700 transition-colors">
              <span>🔍</span> قائمة المراجعة
            </Link>
            {canManageUsers && (
              <Link to="/admin/users" className="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-sm text-gray-700 transition-colors">
                <span>👥</span> إدارة المستخدمين
              </Link>
            )}
            <Link to="/admin/services" className="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-sm text-gray-700 transition-colors">
              <span>⚙️</span> إدارة الخدمات
            </Link>
            <Link to="/admin/audit-logs" className="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-sm text-gray-700 transition-colors">
              <span>📜</span> سجل العمليات
            </Link>
          </div>
        </div>

        <div className="bg-navy rounded-xl p-6 text-white">
          <h3 className="font-semibold mb-2">eqratech-services-platform</h3>
          <p className="text-blue-200 text-sm leading-relaxed">
            منصة خدمات إلكترونية جنيسة. أي خدمة حكومية تُعرَّف كـ JSON Schema
            وتعمل مباشرة — بدون كود إضافي.
          </p>
          <div className="mt-4 text-xs text-blue-300 space-y-1">
            <p>✓ نموذج ديناميكي من المخطط</p>
            <p>✓ محرك سير العمل العام</p>
            <p>✓ إصدار شهادات تلقائي</p>
            <p>✓ متعدد المستأجرين</p>
          </div>
        </div>
      </div>
    </div>
  );
}
