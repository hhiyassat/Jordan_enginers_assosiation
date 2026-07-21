import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Building2, Users, ChevronLeft, ChevronRight, Award, Star, ShieldCheck } from 'lucide-react';
import { adminApi } from '../../api/client';

/**
 * OfficesList — JORD-77
 *
 * Picker page for the admin surface. Lists every engineering office
 * (User with role='applicant') in the admin's organization. Clicking
 * any row navigates to /admin/offices/{id} for the actual settings.
 *
 * Shows a compact per-office summary so the admin can spot which
 * offices already have boosts flipped without opening each one:
 *   • Engineer count (JORD-70 spec-head boost applies per engineer)
 *   • Colored badges for any active boost flag
 *   • Active/inactive tag from user.is_active
 */

interface Office {
  id: number;
  name: string;
  email: string;
  is_active: boolean;
  has_excellence_award: boolean;
  is_bit_khibra: boolean;
  has_iso_cert: boolean;
  engineer_count: number;
}

export function OfficesList() {
  const { i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const isArabic = isRtl;
  const [offices, setOffices] = useState<Office[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    adminApi.listOffices()
      .then(r => setOffices(r.offices))
      .catch(e => setError((e as Error).message))
      .finally(() => setLoading(false));
  }, []);

  const Chevron = isRtl ? ChevronLeft : ChevronRight;

  if (loading) return (
    <div className="flex justify-center py-20">
      <div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" />
    </div>
  );

  return (
    <div className="max-w-4xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      <header className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">
          {isArabic ? 'إعدادات المكاتب الهندسية' : 'Engineering Offices'}
        </h1>
        <p className="text-sm text-gray-500 mt-1">
          {isArabic
            ? 'اختر مكتباً لتعديل مضاعفات السقف السنوي وحالة رؤساء الاختصاص.'
            : 'Pick an office to edit its ceiling boosts and specialization-head flags.'}
        </p>
      </header>

      {error && (
        <div role="alert" className="mb-4 bg-red-50 border border-red-200 rounded-xl p-3 text-red-700 text-sm">
          {error}
        </div>
      )}

      {offices.length === 0 ? (
        <div className="text-center py-20 text-gray-400">
          <Building2 size={48} className="mx-auto mb-3 opacity-40" aria-hidden="true" />
          <p className="text-sm">
            {isArabic ? 'لا يوجد مكاتب مسجّلة في هذه المنظمة.' : 'No offices registered in this organization.'}
          </p>
        </div>
      ) : (
        <div className="space-y-2" data-testid="offices-list">
          {offices.map(office => {
            const anyBoostOn = office.has_excellence_award || office.is_bit_khibra || office.has_iso_cert;
            return (
              <Link
                key={office.id}
                to={`/admin/offices/${office.id}`}
                className={`flex items-center gap-4 p-4 rounded-xl border bg-white hover:border-blue-300 hover:shadow-sm transition-all ${
                  office.is_active ? 'border-gray-200' : 'border-gray-200 opacity-60'
                }`}
                data-testid={`office-row-${office.id}`}
              >
                <div className="w-10 h-10 rounded-lg bg-jea-accent flex items-center justify-center shrink-0 text-jea-primary">
                  <Building2 size={18} aria-hidden="true" />
                </div>

                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <p className="font-semibold text-gray-900">{office.name}</p>
                    {!office.is_active && (
                      <span className="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">
                        {isArabic ? 'غير نشط' : 'inactive'}
                      </span>
                    )}
                  </div>
                  <p className="text-xs text-gray-500 mt-0.5 flex items-center gap-3 flex-wrap font-mono">
                    <span>{office.email}</span>
                    <span className="text-gray-300">·</span>
                    <span className="inline-flex items-center gap-1">
                      <Users size={11} aria-hidden="true" />
                      {office.engineer_count} {isArabic ? 'مهندس' : 'engineers'}
                    </span>
                  </p>
                </div>

                <div className="flex items-center gap-1 shrink-0">
                  {office.has_excellence_award && (
                    <span title={isArabic ? 'جائزة التميز' : 'Excellence Award'}
                          className="w-6 h-6 rounded bg-amber-50 border border-amber-200 flex items-center justify-center">
                      <Award size={12} className="text-amber-600" aria-hidden="true" />
                    </span>
                  )}
                  {office.is_bit_khibra && (
                    <span title={isArabic ? 'بيت خبرة' : 'Bit-Khibra'}
                          className="w-6 h-6 rounded bg-purple-50 border border-purple-200 flex items-center justify-center">
                      <Star size={12} className="text-purple-600" aria-hidden="true" />
                    </span>
                  )}
                  {office.has_iso_cert && (
                    <span title={isArabic ? 'شهادة الأيزو' : 'ISO'}
                          className="w-6 h-6 rounded bg-blue-50 border border-blue-200 flex items-center justify-center">
                      <ShieldCheck size={12} className="text-blue-600" aria-hidden="true" />
                    </span>
                  )}
                  {!anyBoostOn && (
                    <span className="text-[10px] text-gray-400">
                      {isArabic ? 'بدون مضاعفات' : 'no boosts'}
                    </span>
                  )}
                </div>

                <Chevron size={18} className="text-gray-300 shrink-0" aria-hidden="true" />
              </Link>
            );
          })}
        </div>
      )}
    </div>
  );
}
