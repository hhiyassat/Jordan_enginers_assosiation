import type { ServiceSchema } from '../../../types';
import i18n from '../../../i18n';

// The Apply page catches errors from three distinct backend paths:
//
//   • Laravel FormRequest (POST /applications, PUT /applications/:id):
//       { service_code: ['…'], data: ['…'], project_id: ['…'] }
//
//   • SchemaValidator (POST /applications/:id/submit) — returns per-field
//     data errors keyed directly by the schema `fields[].id`:
//       { owner_name: 'اسم المالك مطلوب.', … }
//
//   • Document validator (also on submit) — keyed by `documents[].id`:
//       { permit_pdf: 'الترخيص مطلوب.', … }
//
// This helper turns any of those into three buckets:
//   - fieldErrors:  errors keyed by a schema field/document id (DynamicForm
//                   inlines them next to the affected input).
//   - otherErrors:  everything else (project_id, service_code, top-level
//                   FormRequest failures). Rendered as a bulleted list in
//                   the banner so the applicant sees exactly what's wrong.
//   - summary:      one-line headline for the banner.
//
// The key insight — until this helper existed, an unmatched error key
// (e.g. `data` or `project_id`) fell into the DynamicForm's untouched
// errors object, so the banner said "please review the highlighted fields"
// and there were no highlighted fields to review. Applicants were stuck.

export interface ApiError extends Error {
  errors?: Record<string, string | string[]>;
}

export interface NormalizedApplyError {
  summary: string;
  fieldErrors: Record<string, string>;
  otherErrors: Record<string, string>;
}

/**
 * Bucket API errors against the schema so the UI can render them in the
 * right place. Field-shaped keys go inline; everything else surfaces in
 * the banner.
 */
export function normalizeApplyError(err: ApiError, schema?: ServiceSchema): NormalizedApplyError {
  const raw = err.errors ?? {};
  const flat: Record<string, string> = {};
  for (const [k, v] of Object.entries(raw)) {
    flat[k] = Array.isArray(v) ? String(v[0] ?? '') : String(v);
  }

  const knownFieldIds = new Set([
    ...(schema?.fields?.map(f => f.id) ?? []),
    ...(schema?.documents?.map(d => d.id) ?? []),
  ]);

  const fieldErrors: Record<string, string> = {};
  const otherErrors: Record<string, string> = {};

  for (const [key, msg] of Object.entries(flat)) {
    // Direct hit: SchemaValidator + document validator both key by id.
    if (knownFieldIds.has(key)) {
      fieldErrors[key] = msg;
      continue;
    }
    // FormRequest may nest as `data.field_name` — strip and re-check.
    if (key.startsWith('data.')) {
      const bare = key.slice(5);
      if (knownFieldIds.has(bare)) {
        fieldErrors[bare] = msg;
        continue;
      }
    }
    otherErrors[key] = msg;
  }

  // The top-level message is the summary. If the backend didn't provide
  // one and we have field errors, use a generic prompt.
  // JORD-90: pull the fallback copy from i18n so English users don't
  // see an Arabic "please review the highlighted fields" line. The
  // defaultValue keeps the Arabic in place if the key hasn't been
  // added (or i18n hasn't finished loading yet).
  const summary = err.message
    || (Object.keys(fieldErrors).length > 0
        ? i18n.t('applyError.hasFieldErrors',
            { defaultValue: 'يوجد أخطاء في الحقول المحددة أدناه — يرجى مراجعتها والمتابعة.' })
        : i18n.t('applyError.generic',
            { defaultValue: 'تعذر إرسال الطلب — راجع الأخطاء المذكورة.' }));

  return { summary, fieldErrors, otherErrors };
}

/**
 * Human-readable label for the meta-field keys that turn up in
 * otherErrors. Falls back to the raw key if we don't know it.
 * JORD-90: routes through i18n; defaults preserve the previous
 * Arabic copy so translations can be filled in later without
 * regressing the current UX.
 */
export function labelForOtherKey(key: string): string {
  const defaults: Record<string, string> = {
    project_id:   'المشروع',
    service_code: 'رمز الخدمة',
    data:         'بيانات الطلب',
  };
  const fallback = defaults[key];
  if (!fallback) return key;
  return i18n.t(`applyError.otherKeys.${key}`, { defaultValue: fallback });
}
