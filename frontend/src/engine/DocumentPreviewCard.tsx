import React from 'react';
import { useTranslation } from 'react-i18next';
import type { SchemaDocument } from '../types';

/**
 * DocumentPreviewCard
 *
 * A visual clone of the applicant's DocumentSlot (from DocumentUploader),
 * but wired for the admin PREVIEW surface. The upload button is disabled
 * and carries a "preview" badge so nobody mistakes it for a live control —
 * but the layout, accept list, size hint, required badge, and dashed
 * border for optional slots match what the applicant actually sees.
 *
 * Why this exists: previously the EditService / NewService previews
 * listed documents as one-line text summaries. Admins looked at the
 * preview after the AI added a document, saw no upload button, and
 * concluded the change hadn't taken. Rendering the real widget (in
 * disabled form) makes the schema's intent immediately verifiable.
 *
 * JORD-95: retrofit with react-i18next. The `locale` prop still works
 * for callers that need to force a specific rendering (e.g. a preview
 * of the Arabic view from an English admin) but is now optional —
 * absent → read the current language from i18n.
 */
export function DocumentPreviewCard({
  doc,
  locale,
  onToggleRequired,
}: {
  doc: SchemaDocument;
  locale?: 'ar' | 'en';
  /** When provided, renders an inline checkbox that flips `required`
   *  and calls back with the new value. Absent = read-only preview. */
  onToggleRequired?: (docId: string, nextRequired: boolean) => void;
}) {
  const { t, i18n } = useTranslation();
  const effectiveLocale: 'ar' | 'en' = locale ?? (i18n.language.startsWith('ar') ? 'ar' : 'en');
  const label       = effectiveLocale === 'ar' ? doc.label_ar : (doc.label_en || doc.label_ar);
  const description = effectiveLocale === 'ar' ? doc.description_ar : (doc.description_en || doc.description_ar);
  const borderCls   = doc.required ? 'border-gray-300' : 'border-dashed border-gray-300';
  const editable    = typeof onToggleRequired === 'function';

  return (
    <div
      className={`rounded-lg border-2 p-4 bg-white ${borderCls}`}
      data-testid="document-preview-card"
      data-doc-id={doc.id}
    >
      <div className="flex items-start justify-between gap-4">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap">
            <span className="text-sm font-medium text-gray-800">{label}</span>
            {doc.required && !editable && (
              <span className="text-xs text-red-500 bg-red-50 px-1.5 py-0.5 rounded">
                {t('documentUploader.required')}
              </span>
            )}
            {doc.conditional && (
              <span className="text-xs text-orange-500 bg-orange-50 px-1.5 py-0.5 rounded">
                {t('documentUploader.conditional')}
              </span>
            )}
            <span className="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded-full bg-blue-50 border border-blue-200 text-blue-700">
              {t('documentUploader.preview')}
            </span>
          </div>
          {description && (
            <p className="text-xs text-gray-500 mt-0.5">{description}</p>
          )}
          <p className="text-xs text-gray-400 mt-1">
            {t('documentUploader.accepted')} {doc.accept.join(', ')}
            {' · '}
            {t('documentUploader.maxSize')} {doc.max_size_mb}MB
          </p>

          {/* Inline required toggle — only when onToggleRequired is passed.
              Kept in the "info" column (not the button column) so it reads
              as a *setting for this row* rather than an action to perform.
              We also swap the plain badge above for this control when
              editable, since showing both would be visual noise. */}
          {editable && (
            <label
              className="inline-flex items-center gap-2 mt-2 text-xs text-gray-700 cursor-pointer select-none"
              data-testid={`toggle-required-${doc.id}`}
            >
              <input
                type="checkbox"
                checked={!!doc.required}
                onChange={e => onToggleRequired!(doc.id, e.target.checked)}
                className="w-4 h-4 rounded border-gray-300 text-red-600 focus:ring-red-500"
                aria-label={`${t('documentUploader.required')}: ${label}`}
              />
              <span className={doc.required ? 'text-red-600 font-semibold' : 'text-gray-500'}>
                {doc.required
                  ? t('documentUploader.requiredHint')
                  : t('documentUploader.optional')}
              </span>
            </label>
          )}
        </div>

        <div className="flex-shrink-0">
          <button
            type="button"
            disabled
            aria-disabled="true"
            title={t('documentUploader.previewDisabledTitle')}
            className="px-3 py-2 text-xs rounded-lg font-medium bg-blue-600 text-white opacity-40 cursor-not-allowed"
          >
            {t('documentUploader.upload')}
          </button>
        </div>
      </div>
    </div>
  );
}
