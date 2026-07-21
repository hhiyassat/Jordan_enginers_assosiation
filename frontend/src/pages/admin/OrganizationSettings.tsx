import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Award, ShieldCheck, Star, Info } from 'lucide-react';
import { adminApi } from '../../api/client';

/**
 * OrganizationSettings — JORD-76
 *
 * Admin surface for the three org-level ceiling-boost flags
 * (JORD-70) + the per-engineer specialization-head toggle.
 *
 * Each flag corresponds to a JEA 2025 manual rule:
 *   • has_excellence_award  → +5% (Q-06, p.126, King Abdullah Award)
 *   • is_bit_khibra         → +5% (Q-07, p.126, Bit-Khibra recognition)
 *   • has_iso_cert          → +5% (Q-07, p.126, ISO certification)
 *   • is_specialization_head → +20% engineer quota (Q-08, p.125)
 *
 * Save fires immediately on toggle — no draft-and-save. The audit
 * cost is one PATCH per click; the UX cost of an explicit save
 * button would be worse than the extra request.
 */

interface Engineer {
  id: number;
  name_ar: string;
  name_en: string | null;
  membership_number: string;
  specialization: string | null;
  is_specialization_head: boolean;
}

interface OrgFlags {
  has_excellence_award: boolean;
  is_bit_khibra: boolean;
  has_iso_cert: boolean;
}

interface Organization extends OrgFlags {
  id: number;
  name_ar: string;
  name_en: string;
}

export function OrganizationSettings() {
  const { i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const isArabic = isRtl;

  const [org, setOrg] = useState<Organization | null>(null);
  const [engineers, setEngineers] = useState<Engineer[]>([]);
  const [loading, setLoading] = useState(true);
  const [savingFlag, setSavingFlag] = useState<string | null>(null);
  const [savingEngineerId, setSavingEngineerId] = useState<number | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    adminApi.getOrganizationSettings()
      .then(r => {
        setOrg(r.organization);
        setEngineers(r.engineers);
      })
      .catch(e => setError((e as Error).message))
      .finally(() => setLoading(false));
  }, []);

  const handleOrgFlag = async (key: keyof OrgFlags) => {
    if (!org) return;
    const next = !org[key];
    setOrg({ ...org, [key]: next });     // optimistic
    setSavingFlag(key);
    try {
      await adminApi.updateOrganizationFlags({ [key]: next });
    } catch (e) {
      setOrg({ ...org, [key]: !next });  // revert on failure
      setError((e as Error).message);
    } finally {
      setSavingFlag(null);
    }
  };

  const handleEngineerFlag = async (eng: Engineer) => {
    const next = !eng.is_specialization_head;
    setEngineers(prev => prev.map(e =>
      e.id === eng.id ? { ...e, is_specialization_head: next } : e));
    setSavingEngineerId(eng.id);
    try {
      await adminApi.updateEngineerSpecHead(eng.id, next);
    } catch (e) {
      setEngineers(prev => prev.map(x =>
        x.id === eng.id ? { ...x, is_specialization_head: !next } : x));
      setError((e as Error).message);
    } finally {
      setSavingEngineerId(null);
    }
  };

  if (loading) return (
    <div className="flex justify-center py-20">
      <div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" />
    </div>
  );

  if (!org) return null;

  const orgFlags: Array<{
    key: keyof OrgFlags; icon: React.ReactNode; ar: string; en: string; hintAr: string; hintEn: string;
  }> = [
    {
      key: 'has_excellence_award',
      icon: <Award size={16} className="text-amber-500" aria-hidden="true" />,
      ar: 'جائزة الملك عبد الله للتميز', en: 'King Abdullah Excellence Award',
      hintAr: '+5% على سقف المكتب السنوي لكل اختصاص (كتاب التعليمات ص 126).',
      hintEn: '+5% on annual office ceiling per discipline (manual p.126).',
    },
    {
      key: 'is_bit_khibra',
      icon: <Star size={16} className="text-purple-500" aria-hidden="true" />,
      ar: 'مكتب بيت خبرة', en: 'Bit-Khibra Recognition',
      hintAr: '+5% على سقف المكتب السنوي (كتاب التعليمات ص 126).',
      hintEn: '+5% on annual office ceiling (manual p.126).',
    },
    {
      key: 'has_iso_cert',
      icon: <ShieldCheck size={16} className="text-blue-500" aria-hidden="true" />,
      ar: 'شهادة الأيزو', en: 'ISO Certification',
      hintAr: '+5% على سقف المكتب السنوي (كتاب التعليمات ص 126).',
      hintEn: '+5% on annual office ceiling (manual p.126).',
    },
  ];

  return (
    <div className="max-w-4xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      <header className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">
          {isArabic ? 'إعدادات المكتب' : 'Organization Settings'}
        </h1>
        <p className="text-sm text-gray-500 mt-1">
          {isArabic
            ? `${org.name_ar} — ${org.name_en}`
            : `${org.name_en} — ${org.name_ar}`}
        </p>
      </header>

      {error && (
        <div role="alert" className="mb-6 bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm">
          {error}
        </div>
      )}

      {/* Office boosts */}
      <section aria-labelledby="office-boosts-title" className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <h2 id="office-boosts-title" className="text-sm font-bold text-gray-800 mb-1">
          {isArabic ? 'مضاعفات سقف المكتب' : 'Office Ceiling Boosts'}
        </h2>
        <p className="text-xs text-gray-500 mb-4 flex items-start gap-1.5">
          <Info size={12} className="mt-0.5 shrink-0" aria-hidden="true" />
          {isArabic
            ? 'التبديل يُطبَّق فوراً على حسابات الحصص والسقف السنوي لكل مقدّمي المكتب.'
            : 'Toggling any flag applies immediately to quota + ceiling math for every submission this office makes.'}
        </p>
        <div className="space-y-3">
          {orgFlags.map(f => {
            const on = org[f.key];
            const isSaving = savingFlag === f.key;
            return (
              <label
                key={f.key}
                className={`flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${
                  on ? 'border-emerald-300 bg-emerald-50' : 'border-gray-200 hover:border-gray-300'
                }`}
                data-testid={`org-flag-${f.key}`}
              >
                <input
                  type="checkbox"
                  checked={on}
                  disabled={isSaving}
                  onChange={() => handleOrgFlag(f.key)}
                  className="mt-0.5 w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                />
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    {f.icon}
                    <span className="text-sm font-semibold text-gray-800">
                      {isArabic ? f.ar : f.en}
                    </span>
                    {on && (
                      <span className="text-[10px] font-semibold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">
                        +5%
                      </span>
                    )}
                  </div>
                  <p className="text-xs text-gray-500 mt-1">
                    {isArabic ? f.hintAr : f.hintEn}
                  </p>
                </div>
                {isSaving && (
                  <span className="text-xs text-gray-400 shrink-0">
                    {isArabic ? 'جارٍ الحفظ…' : 'Saving…'}
                  </span>
                )}
              </label>
            );
          })}
        </div>
      </section>

      {/* Engineer specialization-head */}
      <section aria-labelledby="eng-heads-title" className="bg-white rounded-xl border border-gray-200 p-6">
        <h2 id="eng-heads-title" className="text-sm font-bold text-gray-800 mb-1">
          {isArabic ? 'رؤساء الاختصاص' : 'Specialization Heads'}
        </h2>
        <p className="text-xs text-gray-500 mb-4 flex items-start gap-1.5">
          <Info size={12} className="mt-0.5 shrink-0" aria-hidden="true" />
          {isArabic
            ? 'المهندس المسجَّل رئيساً للاختصاص يمنحه +20% على حصته السنوية (كتاب التعليمات ص 125).'
            : 'A specialization-head engineer gets +20% on their yearly quota (manual p.125).'}
        </p>
        {engineers.length === 0 ? (
          <p className="text-sm text-gray-400 text-center py-6">
            {isArabic ? 'لا يوجد مهندسون مسجّلون.' : 'No engineers registered.'}
          </p>
        ) : (
          <div className="space-y-2">
            {engineers.map(eng => {
              const on = eng.is_specialization_head;
              const isSaving = savingEngineerId === eng.id;
              const engName = isArabic ? eng.name_ar : (eng.name_en || eng.name_ar);
              return (
                <label
                  key={eng.id}
                  className={`flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${
                    on ? 'border-amber-300 bg-amber-50' : 'border-gray-200 hover:border-gray-300'
                  }`}
                  data-testid={`engineer-flag-${eng.id}`}
                >
                  <input
                    type="checkbox"
                    checked={on}
                    disabled={isSaving}
                    onChange={() => handleEngineerFlag(eng)}
                    className="w-4 h-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                  />
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold text-gray-800">{engName}</p>
                    <p className="text-xs text-gray-500 mt-0.5 font-mono">
                      {eng.membership_number}
                      {eng.specialization && (
                        <span className="mx-2 text-gray-400">·</span>
                      )}
                      {eng.specialization && (
                        <span className="text-gray-500">{eng.specialization}</span>
                      )}
                    </p>
                  </div>
                  {on && (
                    <span className="text-[10px] font-semibold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">
                      +20%
                    </span>
                  )}
                  {isSaving && (
                    <span className="text-xs text-gray-400">
                      {isArabic ? 'جارٍ…' : 'Saving…'}
                    </span>
                  )}
                </label>
              );
            })}
          </div>
        )}
      </section>
    </div>
  );
}
