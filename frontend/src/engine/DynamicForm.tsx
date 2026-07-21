import React, { useState } from 'react';
import type { SchemaField, SchemaSection, ServiceSchema } from '../types';

interface Props {
  schema: ServiceSchema;
  values: Record<string, unknown>;
  errors?: Record<string, string>;
  onChange: (field: string, value: unknown) => void;
  disabled?: boolean;
  locale?: 'ar' | 'en';
}

/**
 * DynamicForm
 *
 * Renders any service's form fields from its schema definition.
 * No service-specific code — everything comes from the JSON schema.
 *
 * Validation:
 * - Real-time: fires on blur for required, pattern, min/max length
 * - Server: EDA-10 errors passed via `errors` prop take priority
 */
export function DynamicForm({ schema, values, errors = {}, onChange, disabled = false, locale = 'ar' }: Props) {
  const label = (field: { label_ar: string; label_en: string }) =>
    locale === 'ar' ? field.label_ar : field.label_en;

  const sections: SchemaSection[] = schema.sections || [
    { id: '__default', label_ar: 'البيانات', label_en: 'Details' },
  ];

  /**
   * JORD-48a: fields render in schema-array order by default; when a
   * field carries an explicit display_order integer, that wins. Stable
   * secondary sort by original index preserves current behaviour for
   * schemas that haven't opted in yet.
   */
  const fieldsBySection = (sectionId: string) => {
    const filtered = schema.fields
      .map((f, i) => ({ field: f, idx: i }))
      .filter(({ field }) => (field.section || '__default') === sectionId);
    filtered.sort((a, b) => {
      const oa = a.field.display_order ?? Number.POSITIVE_INFINITY;
      const ob = b.field.display_order ?? Number.POSITIVE_INFINITY;
      if (oa !== ob) return oa - ob;
      return a.idx - b.idx;
    });
    return filtered.map(x => x.field);
  };

  const isVisible = (field: SchemaField): boolean => {
    if (!field.conditional) return true;
    return values[field.conditional.field] === field.conditional.value;
  };

  return (
    <div dir={locale === 'ar' ? 'rtl' : 'ltr'} className="space-y-8">
      {sections.map(section => {
        const fields = fieldsBySection(section.id).filter(isVisible);
        if (fields.length === 0) return null;

        return (
          <div key={section.id} className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div className="bg-navy px-6 py-3">
              <h3 className="text-white font-semibold text-sm">{label(section)}</h3>
            </div>

            <div className="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
              {fields.map(field => (
                <FieldWrapper
                  key={field.id}
                  field={field}
                  value={values[field.id]}
                  serverError={errors[field.id]}
                  onChange={onChange}
                  disabled={disabled}
                  locale={locale}
                />
              ))}
            </div>
          </div>
        );
      })}
    </div>
  );
}

// ── Field wrapper with local blur-validation ──────────────────────────────────

interface FieldProps {
  field: SchemaField;
  value: unknown;
  serverError?: string;
  onChange: (id: string, value: unknown) => void;
  disabled: boolean;
  locale: 'ar' | 'en';
}

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * JORD-16: exported so callers (e.g. Apply's handleSaveDraft) can
 * re-validate every visible field client-side before hitting the
 * backend. Returns a { fieldId → error } map with only failing rows,
 * plus a computed .valid boolean for convenience.
 */
export function validateAll(
  schema: ServiceSchema,
  values: Record<string, unknown>,
  locale: 'ar' | 'en' = 'ar',
): { valid: boolean; errors: Record<string, string> } {
  const errors: Record<string, string> = {};
  for (const field of schema.fields) {
    // Skip fields hidden by a conditional guard — you can't require
    // the applicant to fill a field they can't see.
    if (field.conditional && values[field.conditional.field] !== field.conditional.value) {
      continue;
    }
    const msg = validateField(field, values[field.id], locale);
    if (msg) errors[field.id] = msg;
  }
  return { valid: Object.keys(errors).length === 0, errors };
}

function validateField(field: SchemaField, value: unknown, locale: 'ar' | 'en'): string {
  const ar = locale === 'ar';

  // Handle array types (multiselect / checkbox_group)
  if (field.type === 'multiselect' || field.type === 'checkbox_group') {
    const arr = Array.isArray(value) ? value : [];
    if (field.required && arr.length === 0)
      return ar ? 'يرجى اختيار خيار واحد على الأقل' : 'Select at least one option';
    return '';
  }

  const v = String(value ?? '').trim();

  // Required check
  if (field.required && !v)
    return ar ? 'هذا الحقل مطلوب' : 'This field is required';

  if (!v) return ''; // optional + empty → no error

  // Type-specific format checks
  if (field.type === 'email' && !EMAIL_RE.test(v))
    return ar ? 'البريد الإلكتروني غير صحيح' : 'Invalid email address';

  if (field.type === 'number') {
    const n = Number(v);
    if (isNaN(n))               return ar ? 'يجب أن يكون رقماً' : 'Must be a number';
    if (field.min !== undefined && n < field.min)
      return ar ? `الحد الأدنى ${field.min}` : `Minimum value is ${field.min}`;
    if (field.max !== undefined && n > field.max)
      return ar ? `الحد الأقصى ${field.max}` : `Maximum value is ${field.max}`;
    return '';
  }

  if (field.type === 'date' && isNaN(Date.parse(v)))
    return ar ? 'تاريخ غير صحيح' : 'Invalid date';

  // Schema-defined pattern (e.g. national ID)
  if (field.pattern) {
    try {
      if (!new RegExp(`^(?:${field.pattern})$`).test(v))
        return ar ? 'صيغة غير صحيحة' : 'Invalid format';
    } catch { /* bad regex — skip */ }
  }

  // Length constraints
  if (field.min_length !== undefined && v.length < field.min_length)
    return ar
      ? `يجب أن يكون ${field.min_length} خانة على الأقل`
      : `Minimum ${field.min_length} characters`;

  if (field.max_length !== undefined && v.length > field.max_length)
    return ar
      ? `الحد الأقصى ${field.max_length} خانة`
      : `Maximum ${field.max_length} characters`;

  return '';
}

function FieldWrapper({ field, value, serverError, onChange, disabled, locale }: FieldProps) {
  const [localError, setLocalError] = useState('');
  const [touched, setTouched] = useState(false);

  const handleBlur = () => {
    setTouched(true);
    setLocalError(validateField(field, value, locale));
  };

  // Clear local error as user corrects the field
  const handleChange = (id: string, v: unknown) => {
    onChange(id, v);
    if (touched) {
      setLocalError(validateField(field, v, locale));
    }
  };

  // Server error (EDA-10) takes priority; then local blur error
  const displayError = serverError || (touched ? localError : '');
  const label = locale === 'ar' ? field.label_ar : field.label_en;
  const isWide = field.type === 'textarea';

  return (
    <div className={isWide ? 'md:col-span-2' : ''}>
      <label className="block text-sm font-medium text-gray-700 mb-1.5">
        {label}
        {field.required && <span className="text-red-500 ms-0.5">*</span>}
      </label>

      <FieldInput
        field={field}
        value={value}
        onChange={v => handleChange(field.id, v)}
        onBlur={handleBlur}
        disabled={disabled}
        hasError={!!displayError}
        placeholder={field.placeholder_ar || ''}
        locale={locale}
      />

      {displayError ? (
        <p
          data-field-error
          role="alert"
          className="mt-1 text-xs text-red-600 flex items-center gap-1"
        >
          <span>⚠</span> {displayError}
        </p>
      ) : (
        <>
          {field.min_length !== undefined && field.max_length !== undefined && field.min_length === field.max_length && (
            <p className="mt-1 text-xs text-gray-400">
              {locale === 'ar' ? `يجب أن يكون ${field.min_length} خانة` : `Must be exactly ${field.min_length} characters`}
            </p>
          )}
          {field.description_ar && locale === 'ar' && (
            <p className="mt-1 text-xs text-gray-400">{field.description_ar}</p>
          )}
        </>
      )}
    </div>
  );
}

// ── Input rendering ───────────────────────────────────────────────────────────

interface InputProps {
  field: SchemaField;
  value: unknown;
  onChange: (v: unknown) => void;
  onBlur: () => void;
  disabled: boolean;
  hasError: boolean;
  placeholder: string;
  locale: 'ar' | 'en';
}

function FieldInput({ field, value, onChange, onBlur, disabled, hasError, placeholder, locale }: InputProps) {
  const base = `
    w-full rounded-lg border px-3 py-2.5 text-sm
    focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
    disabled:bg-gray-50 disabled:text-gray-400
    transition-colors
  `.trim();

  const borderClass = hasError
    ? 'border-red-400 bg-red-50 focus:ring-red-400'
    : 'border-gray-300 bg-white';

  const cls = `${base} ${borderClass}`;

  switch (field.type) {
    case 'textarea':
      return (
        <textarea
          className={`${cls} min-h-[100px] resize-y`}
          value={String(value ?? '')}
          onChange={e => onChange(e.target.value)}
          onBlur={onBlur}
          disabled={disabled}
          placeholder={placeholder}
          maxLength={field.max_length}
          rows={4}
        />
      );

    case 'select':
      return (
        <DynamicSelect
          field={field}
          cls={cls}
          value={value}
          onChange={onChange}
          onBlur={onBlur}
          disabled={disabled}
          locale={locale}
        />
      );

    case 'radio':
      return (
        <div className="space-y-2 pt-1">
          {field.options?.map(opt => (
            <label key={opt.value} className="flex items-center gap-2 cursor-pointer">
              <input
                type="radio"
                name={field.id}
                value={opt.value}
                checked={value === opt.value}
                onChange={() => { onChange(opt.value); onBlur(); }}
                disabled={disabled}
                className="text-blue-600"
              />
              <span className="text-sm text-gray-700">
                {locale === 'ar' ? opt.label_ar : opt.label_en}
              </span>
            </label>
          ))}
        </div>
      );

    case 'multiselect':
    case 'checkbox_group': {
      const selected = Array.isArray(value) ? (value as string[]) : [];
      return (
        <div className="space-y-2 pt-1">
          {field.options?.map(opt => (
            <label key={opt.value} className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={selected.includes(opt.value)}
                onChange={e => {
                  const next = e.target.checked
                    ? [...selected, opt.value]
                    : selected.filter(v => v !== opt.value);
                  onChange(next);
                  onBlur();
                }}
                disabled={disabled}
                className="text-blue-600 rounded"
              />
              <span className="text-sm text-gray-700">
                {locale === 'ar' ? opt.label_ar : opt.label_en}
              </span>
            </label>
          ))}
        </div>
      );
    }

    case 'number':
      return (
        <input
          type="number"
          className={cls}
          value={value !== undefined && value !== null ? String(value) : ''}
          onChange={e => onChange(e.target.value === '' ? '' : Number(e.target.value))}
          onBlur={onBlur}
          disabled={disabled}
          min={field.min}
          max={field.max}
          placeholder={placeholder}
        />
      );

    case 'date':
      return (
        <input
          type="date"
          className={cls}
          value={String(value ?? '')}
          onChange={e => onChange(e.target.value)}
          onBlur={onBlur}
          disabled={disabled}
        />
      );

    case 'email':
      return (
        <input
          type="email"
          className={cls}
          value={String(value ?? '')}
          onChange={e => onChange(e.target.value)}
          onBlur={onBlur}
          disabled={disabled}
          placeholder={placeholder}
        />
      );

    default: // text
      return (
        <input
          type="text"
          className={cls}
          value={String(value ?? '')}
          onChange={e => onChange(e.target.value)}
          onBlur={onBlur}
          disabled={disabled}
          placeholder={placeholder}
          maxLength={field.max_length}
        />
      );
  }
}

/**
 * JORD-69: select with either static options[] or a runtime-fetched
 * option list from field.options_endpoint. Split out of FieldInput's
 * switch because it needs its own useEffect / useState — the switch
 * arms can't hold hooks. Any select field without options_endpoint
 * behaves exactly as before (options[] passed in).
 */
interface DynamicSelectProps {
  field: SchemaField;
  cls: string;
  value: unknown;
  onChange: (v: unknown) => void;
  onBlur: () => void;
  disabled: boolean;
  locale: 'ar' | 'en';
}

function DynamicSelect({ field, cls, value, onChange, onBlur, disabled, locale }: DynamicSelectProps) {
  const [dynamicOptions, setDynamicOptions] = React.useState<SchemaField['options'] | null>(null);
  const [loading, setLoading] = React.useState<boolean>(!!field.options_endpoint);

  React.useEffect(() => {
    if (!field.options_endpoint) return;
    // The endpoint is expected to return `{ <collection>: [{ id, name_ar, ... }] }`.
    // /engineers is the only consumer today so we specialize:
    //  - shape: { engineers: [{ id, name_ar, name_en, specialization }] }
    //  - value: engineer.id (numeric)
    //  - label: engineer.name_ar (with (specialization) suffix)
    // Future endpoints will need their own mapper here; a generic
    // "list at .data[]" shape is the natural next extension.
    import('../api/http').then(({ request }) => {
      return request<{ engineers?: Array<{ id: number; name_ar: string; name_en?: string; specialization?: string }> }>(
        'GET', field.options_endpoint!
      );
    }).then(res => {
      const list = res.engineers ?? [];
      setDynamicOptions(list.map(e => ({
        value: String(e.id),
        label_ar: e.specialization ? `${e.name_ar} (${e.specialization})` : e.name_ar,
        label_en: e.name_en ?? e.name_ar,
      })));
    }).catch(() => {
      setDynamicOptions([]);
    }).finally(() => setLoading(false));
  }, [field.options_endpoint]);

  const options = dynamicOptions ?? field.options ?? [];

  return (
    <select
      className={cls}
      value={String(value ?? '')}
      onChange={e => onChange(e.target.value)}
      onBlur={onBlur}
      disabled={disabled || loading}
    >
      <option value="">
        {loading
          ? (locale === 'ar' ? 'جارٍ التحميل…' : 'Loading…')
          : `— ${locale === 'ar' ? 'اختر' : 'Select'} —`}
      </option>
      {options.map(opt => (
        <option key={opt.value} value={opt.value}>
          {locale === 'ar' ? opt.label_ar : opt.label_en}
        </option>
      ))}
    </select>
  );
}
