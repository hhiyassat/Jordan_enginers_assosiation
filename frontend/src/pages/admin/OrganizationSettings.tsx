import React, { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Award, ShieldCheck, Star, Info, Save } from 'lucide-react';
import { adminApi } from '../../api/client';

/**
 * OrganizationSettings — JORD-76
 *
 * Admin surface for the three org-level ceiling-boost flags
 * (JORD-70) + the per-engineer specialization-head toggle.
 *
 * Each flag corresponds to a JEA 2025 manual rule:
 *   • has_excellence_award   → +5% (Q-06, p.126, King Abdullah Award)
 *   • is_bit_khibra          → +5% (Q-07, p.126, Bit-Khibra recognition)
 *   • has_iso_cert           → +5% (Q-07, p.126, ISO certification)
 *   • is_specialization_head → +20% engineer quota (Q-08, p.125)
 *
 * Draft-and-save UX: toggling any control marks the page dirty.
 * The "حفظ التعديلات" button at the bottom PATCHes only the
 * changed subset (diffed against the server-loaded state) and
 * reloads on success. The revert-on-failure branch keeps the
 * user's edits so they can retry.
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

  // "server" state — what the server last confirmed. "draft" state —
  // what the user has edited but not yet saved. Diff-on-save produces
  // the PATCH payload; also drives the "dirty" indicator.
  const [serverOrg, setServerOrg] = useState<Organization | null>(null);
  const [draftOrg,  setDraftOrg]  = useState<Organization | null>(null);
  const [serverEngineers, setServerEngineers] = useState<Engineer[]>([]);
  const [draftEngineers,  setDraftEngineers]  = useState<Engineer[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving,  setSaving]  = useState(false);
  const [error, setError] = useState('');
  const [savedBanner, setSavedBanner] = useState(false);

  const load = () => {
    adminApi.getOrganizationSettings()
      .then(r => {
        setServerOrg(r.organization);
        setDraftOrg(r.organization);
        setServerEngineers(r.engineers);
        setDraftEngineers(r.engineers);
      })
      .catch(e => setError((e as Error).message))
      .finally(() => setLoading(false));
  };

  useEffect(load, []);

  // What changed since the last successful load.
  const orgDiff: Partial<OrgFlags> = useMemo(() => {
    if (!serverOrg || !draftOrg) return {};
    const out: Partial<OrgFlags> = {};
    (Object.keys({ has_excellence_award: 0, is_bit_khibra: 0, has_iso_cert: 0 }) as (keyof OrgFlags)[])
      .forEach(k => { if (serverOrg[k] !== draftOrg[k]) out[k] = draftOrg[k]; });
    return out;
  }, [serverOrg, draftOrg]);

  const engineerDiff: Engineer[] = useMemo(() => {
    return draftEngineers.filter(d => {
      const server = serverEngineers.find(s => s.id === d.id);
      return server && server.is_specialization_head !== d.is_specialization_head;
    });
  }, [serverEngineers, draftEngineers]);

  const isDirty = Object.keys(orgDiff).length > 0 || engineerDiff.length > 0;

  const toggleOrgFlag = (key: keyof OrgFlags) => {
    if (!draftOrg) return;
    setDraftOrg({ ...draftOrg, [key]: !draftOrg[key] });
    setSavedBanner(false);
    setError('');
  };

  const toggleEngineerFlag = (id: number) => {
    setDraftEngineers(prev => prev.map(e =>
      e.id === id ? { ...e, is_specialization_head: !e.is_specialization_head } : e));
    setSavedBanner(false);
    setError('');
  };

  const handleReset = () => {
    setDraftOrg(serverOrg);
    setDraftEngineers(serverEngineers);
    setError('');
    setSavedBanner(false);
  };

  const handleSave = async () => {
    if (!isDirty) return;
    setSaving(true);
    setError('');
    try {
      // Fire org PATCH + engineer PATCHes in parallel — they touch
      // different rows and don't depend on each other.
      const calls: Promise<unknown>[] = [];
      if (Object.keys(orgDiff).length > 0) {
        calls.push(adminApi.updateOrganizationFlags(orgDiff));
      }
      for (const eng of engineerDiff) {
        calls.push(adminApi.updateEngineerSpecHead(eng.id, eng.is_specialization_head));
      }
      await Promise.all(calls);
      // Re-baseline: server now matches draft.
      setServerOrg(draftOrg);
      setServerEngineers(draftEngineers);
      setSavedBanner(true);
    } catch (e) {
      // Keep the user's edits so they can retry. Don't touch draft state.
      setError((e as Error).message);
    } finally {
      setSaving(false);
    }
  };

  if (loading) return (
    <div className="flex justify-center py-20">
      <div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" />
    </div>
  );

  if (!draftOrg) return null;

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

  const pendingCount = Object.keys(orgDiff).length + engineerDiff.length;

  return (
    <div className="max-w-4xl mx-auto px-4 py-8 pb-32" dir={isRtl ? 'rtl' : 'ltr'}>
      <header className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">
          {isArabic ? 'إعدادات المكتب' : 'Organization Settings'}
        </h1>
        <p className="text-sm text-gray-500 mt-1">
          {isArabic
            ? `${draftOrg.name_ar} — ${draftOrg.name_en}`
            : `${draftOrg.name_en} — ${draftOrg.name_ar}`}
        </p>
      </header>

      {savedBanner && (
        <div role="status" className="mb-4 bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-emerald-800 text-sm">
          ✓ {isArabic ? 'تم حفظ التعديلات.' : 'Changes saved.'}
        </div>
      )}
      {error && (
        <div role="alert" className="mb-4 bg-red-50 border border-red-200 rounded-xl p-3 text-red-700 text-sm">
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
            ? 'كل بند يمنح +5% على سقف المكتب السنوي. اضغط "حفظ التعديلات" بعد الانتهاء لتطبيق التغييرات.'
            : 'Each flag grants +5% on annual office ceiling. Click "Save changes" when done.'}
        </p>
        <div className="space-y-3">
          {orgFlags.map(f => {
            const on = draftOrg[f.key];
            const changed = orgDiff[f.key] !== undefined;
            return (
              <label
                key={f.key}
                className={`flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${
                  on ? 'border-emerald-300 bg-emerald-50' : 'border-gray-200 hover:border-gray-300'
                } ${changed ? 'ring-2 ring-blue-200' : ''}`}
                data-testid={`org-flag-${f.key}`}
              >
                <input
                  type="checkbox"
                  checked={on}
                  onChange={() => toggleOrgFlag(f.key)}
                  className="mt-0.5 w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                />
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    {f.icon}
                    <span className="text-sm font-semibold text-gray-800">
                      {isArabic ? f.ar : f.en}
                    </span>
                    {on && (
                      <span className="text-[10px] font-semibold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">
                        +5%
                      </span>
                    )}
                    {changed && (
                      <span className="text-[10px] font-semibold text-blue-700 bg-blue-100 px-1.5 py-0.5 rounded">
                        {isArabic ? 'تغيير غير محفوظ' : 'unsaved'}
                      </span>
                    )}
                  </div>
                  <p className="text-xs text-gray-500 mt-1">
                    {isArabic ? f.hintAr : f.hintEn}
                  </p>
                </div>
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
        {draftEngineers.length === 0 ? (
          <p className="text-sm text-gray-400 text-center py-6">
            {isArabic ? 'لا يوجد مهندسون مسجّلون.' : 'No engineers registered.'}
          </p>
        ) : (
          <div className="space-y-2">
            {draftEngineers.map(eng => {
              const on = eng.is_specialization_head;
              const changed = engineerDiff.some(e => e.id === eng.id);
              const engName = isArabic ? eng.name_ar : (eng.name_en || eng.name_ar);
              return (
                <label
                  key={eng.id}
                  className={`flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${
                    on ? 'border-amber-300 bg-amber-50' : 'border-gray-200 hover:border-gray-300'
                  } ${changed ? 'ring-2 ring-blue-200' : ''}`}
                  data-testid={`engineer-flag-${eng.id}`}
                >
                  <input
                    type="checkbox"
                    checked={on}
                    onChange={() => toggleEngineerFlag(eng.id)}
                    className="w-4 h-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                  />
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold text-gray-800">{engName}</p>
                    <p className="text-xs text-gray-500 mt-0.5 font-mono">
                      {eng.membership_number}
                      {eng.specialization && <span className="mx-2 text-gray-400">·</span>}
                      {eng.specialization && <span className="text-gray-500">{eng.specialization}</span>}
                    </p>
                  </div>
                  {on && (
                    <span className="text-[10px] font-semibold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">
                      +20%
                    </span>
                  )}
                  {changed && (
                    <span className="text-[10px] font-semibold text-blue-700 bg-blue-100 px-1.5 py-0.5 rounded">
                      {isArabic ? 'غير محفوظ' : 'unsaved'}
                    </span>
                  )}
                </label>
              );
            })}
          </div>
        )}
      </section>

      {/* Sticky save bar. Only shown when dirty so a clean page has
          no visual footprint. */}
      {isDirty && (
        <div
          className={`fixed bottom-0 ${isRtl ? 'right-0 left-0' : 'left-0 right-0'} bg-white border-t border-gray-200 shadow-lg p-4 z-40`}
          data-testid="save-bar"
        >
          <div className="max-w-4xl mx-auto flex items-center justify-between gap-4">
            <span className="text-sm text-gray-600">
              {isArabic
                ? `لديك ${pendingCount} تعديل غير محفوظ`
                : `${pendingCount} unsaved change${pendingCount === 1 ? '' : 's'}`}
            </span>
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={handleReset}
                disabled={saving}
                className="px-4 py-2 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 disabled:opacity-50"
                data-testid="reset-btn"
              >
                {isArabic ? 'تراجع' : 'Revert'}
              </button>
              <button
                type="button"
                onClick={handleSave}
                disabled={saving}
                className="inline-flex items-center gap-2 px-5 py-2 bg-jea-primary text-white text-sm font-bold rounded-lg hover:opacity-90 disabled:opacity-50"
                data-testid="save-btn"
              >
                <Save size={14} aria-hidden="true" />
                {saving
                  ? (isArabic ? 'جارٍ الحفظ…' : 'Saving…')
                  : (isArabic ? 'حفظ التعديلات' : 'Save changes')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
