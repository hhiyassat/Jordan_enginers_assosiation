import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Building2, User as UserIcon, Plus, Trash2, CheckCircle2, AlertTriangle } from 'lucide-react';
import { JEALogo } from '../../components/JEALogo';
import { Button } from '../../platform/ui/Button';
import { TextField } from '../../platform/ui/FormField';
import { LanguageSwitcher } from '../../platform/components/LanguageSwitcher';
import { officeRegistrationsApi, type OfficeRegistrationEngineerInput } from '../../api/officeRegistrations';
import type { ApiError } from '../../api/http';

/**
 * RegisterOfficePage — public office signup.
 *
 * Reachable at /register-office (no auth). Rate-limited server-side at
 * 5 requests/min per IP so a scripted attacker can't flood the queue.
 *
 * Submits to POST /api/v1/office-registrations. On success, shows the
 * reference number and a note that JEA will review. On validation
 * failure, extracts per-field messages from the 422 response and
 * displays them next to the offending inputs.
 *
 * Business rules (enforced server-side; mirrored here for UX):
 *   • At least 1 engineer required.
 *   • If exactly 1 engineer, discipline must be structural (civil alias)
 *     OR architectural.
 *   • Each engineer needs name (Arabic) + JEA membership number.
 *
 * Deferred:
 *   • Captcha (only /login has it today; adding here is a future step).
 *   • Real HTTP JeaMembershipVerifier — current server uses FakeVerifier
 *     which accepts any non-empty pair for demo.
 */

const DISCIPLINE_OPTIONS: Array<{ value: string; label_ar: string; label_en: string }> = [
  { value: 'architectural', label_ar: 'معماري',   label_en: 'Architectural' },
  { value: 'structural',    label_ar: 'مدني (إنشائي)', label_en: 'Civil / Structural' },
  { value: 'electrical',    label_ar: 'كهربائي',  label_en: 'Electrical' },
  { value: 'mechanical',    label_ar: 'ميكانيكي', label_en: 'Mechanical' },
  { value: 'environmental', label_ar: 'بيئي',     label_en: 'Environmental' },
];

function makeEmptyEngineer(): OfficeRegistrationEngineerInput {
  return { name_ar: '', name_en: '', membership_number: '', specialization: 'architectural' };
}

export function RegisterOfficePage(): JSX.Element {
  const { i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');

  // Office fields
  const [officeNameAr, setOfficeNameAr]     = useState('');
  const [officeNameEn, setOfficeNameEn]     = useState('');
  const [licenseNo,    setLicenseNo]        = useState('');
  const [city,         setCity]             = useState('');
  const [address,      setAddress]          = useState('');
  const [phone,        setPhone]            = useState('');
  const [email,        setEmail]            = useState('');

  // Engineers list (min 1)
  const [engineers, setEngineers] = useState<OfficeRegistrationEngineerInput[]>([makeEmptyEngineer()]);

  // Submission state
  const [submitting, setSubmitting] = useState(false);
  const [errors,     setErrors]     = useState<Record<string, string[]>>({});
  const [topError,   setTopError]   = useState('');
  const [successRef, setSuccessRef] = useState<string | null>(null);

  const addEngineer = (): void => {
    setEngineers([...engineers, makeEmptyEngineer()]);
  };

  const removeEngineer = (index: number): void => {
    if (engineers.length <= 1) return; // rule: min 1
    setEngineers(engineers.filter((_, i) => i !== index));
  };

  const updateEngineer = (index: number, patch: Partial<OfficeRegistrationEngineerInput>): void => {
    setEngineers(engineers.map((e, i) => (i === index ? { ...e, ...patch } : e)));
  };

  const errFor = (key: string): string | undefined => errors[key]?.[0];

  const handleSubmit = async (e: React.FormEvent): Promise<void> => {
    e.preventDefault();
    setErrors({});
    setTopError('');
    setSubmitting(true);
    try {
      const res = await officeRegistrationsApi.submit({
        office_name_ar:    officeNameAr.trim(),
        office_name_en:    officeNameEn.trim(),
        office_license_no: licenseNo.trim(),
        office_city:       city.trim(),
        office_address_ar: address.trim(),
        office_phone:      phone.trim(),
        office_email:      email.trim(),
        engineers,
      });
      setSuccessRef(res.reference_number);
    } catch (err) {
      const apiErr = err as ApiError;
      // Laravel 422 payload: { message, errors: {"field": ["msg"]} }
      // http.ts populates apiErr.body — check it for structured errors.
      const body = (apiErr as unknown as { body?: { errors?: Record<string, string[]>; message?: string } }).body;
      if (body?.errors) {
        setErrors(body.errors);
      }
      setTopError(body?.message ?? apiErr.message ?? (isRtl ? 'تعذّر تقديم الطلب.' : 'Could not submit request.'));
    } finally {
      setSubmitting(false);
    }
  };

  // ── success view ─────────────────────────────────────────────
  if (successRef) {
    return (
      <div dir={isRtl ? 'rtl' : 'ltr'} className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="max-w-lg w-full bg-white rounded-2xl shadow-lg p-8 text-center">
          <div className="mx-auto w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mb-4">
            <CheckCircle2 size={40} className="text-green-600" />
          </div>
          <h1 className="text-xl font-bold text-gray-800 mb-2">
            {isRtl ? 'تم استلام طلب تسجيل المكتب' : 'Office registration received'}
          </h1>
          <p className="text-sm text-gray-600 mb-4">
            {isRtl
              ? 'ستراجعه النقابة قريباً. عند الاعتماد سيتم إنشاء الحساب وإعلامك بالبريد الإلكتروني.'
              : 'JEA will review shortly. Once approved, an account will be created and you will be notified by email.'}
          </p>
          <div className="mb-6 rounded-lg bg-gray-50 border border-gray-200 p-3">
            <p className="text-xs text-gray-500 mb-1">
              {isRtl ? 'الرقم المرجعي' : 'Reference number'}
            </p>
            <p className="text-lg font-mono font-semibold text-gray-800" data-testid="office-registration-ref">
              {successRef}
            </p>
          </div>
          <Link
            to="/login"
            className="inline-block px-6 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700"
          >
            {isRtl ? 'العودة لتسجيل الدخول' : 'Back to login'}
          </Link>
        </div>
      </div>
    );
  }

  // ── form view ────────────────────────────────────────────────
  return (
    <div dir={isRtl ? 'rtl' : 'ltr'} className="min-h-screen bg-gray-50 py-8 px-4">
      <div className="fixed top-4 end-4 z-10">
        <LanguageSwitcher />
      </div>

      <div className="max-w-3xl mx-auto">
        <div className="text-center mb-6">
          <div className="inline-block mb-3"><JEALogo /></div>
          <h1 className="text-2xl font-bold text-gray-800">
            {isRtl ? 'تسجيل مكتب هندسي جديد' : 'Register a new engineering office'}
          </h1>
          <p className="text-sm text-gray-600 mt-1">
            {isRtl
              ? 'يستلم النظام الطلب وترسله إلى نقابة المهندسين للمراجعة والاعتماد.'
              : 'The request is queued for JEA review and approval.'}
          </p>
        </div>

        {topError && (
          <div
            role="alert"
            data-testid="office-registration-top-error"
            className="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 flex items-start gap-2"
          >
            <AlertTriangle size={18} className="text-red-600 shrink-0 mt-0.5" />
            <p className="text-sm text-red-800">{topError}</p>
          </div>
        )}

        <form onSubmit={handleSubmit} className="bg-white rounded-2xl shadow-md p-6 space-y-6">
          {/* Office section */}
          <section aria-labelledby="office-section">
            <h2 id="office-section" className="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
              <Building2 size={16} />
              {isRtl ? 'بيانات المكتب' : 'Office details'}
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <TextField
                label={isRtl ? 'اسم المكتب بالعربية' : 'Office name (Arabic)'}
                required
                value={officeNameAr}
                onChange={setOfficeNameAr}
                error={errFor('office_name_ar')}
              />
              <TextField
                label={isRtl ? 'اسم المكتب بالإنجليزية' : 'Office name (English)'}
                required
                value={officeNameEn}
                onChange={setOfficeNameEn}
                error={errFor('office_name_en')}
              />
              <TextField
                label={isRtl ? 'رقم رخصة المكتب' : 'Office license number'}
                required
                value={licenseNo}
                onChange={setLicenseNo}
                error={errFor('office_license_no')}
              />
              <TextField
                label={isRtl ? 'المدينة' : 'City'}
                required
                value={city}
                onChange={setCity}
                error={errFor('office_city')}
              />
              <div className="md:col-span-2">
                <TextField
                  label={isRtl ? 'العنوان بالعربية' : 'Address (Arabic)'}
                  required
                  value={address}
                  onChange={setAddress}
                  error={errFor('office_address_ar')}
                />
              </div>
              <TextField
                label={isRtl ? 'رقم الهاتف' : 'Phone'}
                required
                value={phone}
                onChange={setPhone}
                error={errFor('office_phone')}
              />
              <TextField
                label={isRtl ? 'البريد الإلكتروني' : 'Email'}
                required
                type="email"
                autoComplete="email"
                value={email}
                onChange={setEmail}
                error={errFor('office_email')}
              />
            </div>
          </section>

          {/* Engineers section */}
          <section aria-labelledby="engineers-section">
            <div className="flex items-center justify-between mb-3">
              <h2 id="engineers-section" className="text-sm font-bold text-gray-700 flex items-center gap-2">
                <UserIcon size={16} />
                {isRtl ? 'كادر المهندسين' : 'Engineers'}
              </h2>
              <button
                type="button"
                onClick={addEngineer}
                className="text-xs px-3 py-1 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 flex items-center gap-1"
                data-testid="add-engineer-btn"
              >
                <Plus size={14} /> {isRtl ? 'إضافة مهندس' : 'Add engineer'}
              </button>
            </div>
            {errFor('engineers') && (
              <p className="text-xs text-red-600 mb-2">{errFor('engineers')}</p>
            )}
            <p className="text-xs text-gray-500 mb-3">
              {isRtl
                ? 'يجب إضافة مهندس واحد على الأقل. إذا كان مهندساً واحداً، يجب أن يكون مدني (إنشائي) أو معمارياً.'
                : 'At least one engineer required. If only one, discipline must be civil/structural OR architectural.'}
            </p>

            <div className="space-y-4">
              {engineers.map((eng, i) => (
                <div
                  key={i}
                  data-testid={`engineer-row-${i}`}
                  className="rounded-lg border border-gray-200 p-4 bg-gray-50"
                >
                  <div className="flex items-center justify-between mb-3">
                    <span className="text-xs font-semibold text-gray-600">
                      {isRtl ? `المهندس رقم ${i + 1}` : `Engineer #${i + 1}`}
                    </span>
                    {engineers.length > 1 && (
                      <button
                        type="button"
                        onClick={() => removeEngineer(i)}
                        className="text-xs text-red-600 hover:text-red-800 flex items-center gap-1"
                        aria-label={isRtl ? 'حذف المهندس' : 'Remove engineer'}
                        data-testid={`remove-engineer-${i}`}
                      >
                        <Trash2 size={14} /> {isRtl ? 'حذف' : 'Remove'}
                      </button>
                    )}
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <TextField
                      label={isRtl ? 'الاسم بالعربية' : 'Name (Arabic)'}
                      required
                      value={eng.name_ar}
                      onChange={v => updateEngineer(i, { name_ar: v })}
                      error={errFor(`engineers.${i}.name_ar`)}
                    />
                    <TextField
                      label={isRtl ? 'الاسم بالإنجليزية' : 'Name (English)'}
                      value={eng.name_en ?? ''}
                      onChange={v => updateEngineer(i, { name_en: v })}
                      error={errFor(`engineers.${i}.name_en`)}
                    />
                    <TextField
                      label={isRtl ? 'رقم عضوية النقابة' : 'JEA membership number'}
                      required
                      value={eng.membership_number}
                      onChange={v => updateEngineer(i, { membership_number: v })}
                      error={errFor(`engineers.${i}.membership_number`)}
                    />
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1.5">
                        {isRtl ? 'التخصص' : 'Discipline'}
                        <span className="text-red-500 ms-0.5">*</span>
                      </label>
                      <select
                        value={eng.specialization}
                        onChange={e => updateEngineer(i, { specialization: e.target.value })}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                        data-testid={`engineer-discipline-${i}`}
                      >
                        {DISCIPLINE_OPTIONS.map(opt => (
                          <option key={opt.value} value={opt.value}>
                            {isRtl ? opt.label_ar : opt.label_en}
                          </option>
                        ))}
                      </select>
                      {errFor(`engineers.${i}.specialization`) && (
                        <p className="text-xs text-red-600 mt-1">{errFor(`engineers.${i}.specialization`)}</p>
                      )}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </section>

          <div className="flex items-center justify-between pt-4 border-t border-gray-100">
            <Link to="/login" className="text-sm text-gray-500 hover:text-gray-700">
              {isRtl ? 'لديك حساب؟ تسجيل الدخول' : 'Already have an account? Sign in'}
            </Link>
            <Button type="submit" disabled={submitting} loading={submitting} data-testid="office-registration-submit">
              {isRtl ? 'إرسال طلب التسجيل' : 'Submit registration'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
