import React from 'react';
import type { SchemaDocument } from '../types';

/**
 * DocumentPreviewCard
 *
 * A visual clone of the applicant's DocumentSlot (from DocumentUploader),
 * but wired for the admin PREVIEW surface. The upload button is disabled
 * and carries a "معاينة" badge so nobody mistakes it for a live control —
 * but the layout, accept list, size hint, required badge, and dashed
 * border for optional slots match what the applicant actually sees.
 *
 * Why this exists: previously the EditService / NewService previews
 * listed documents as one-line text summaries. Admins looked at the
 * preview after the AI added a document, saw no upload button, and
 * concluded the change hadn't taken. Rendering the real widget (in
 * disabled form) makes the schema's intent immediately verifiable.
 */
export function DocumentPreviewCard({ doc, locale = 'ar' }: { doc: SchemaDocument; locale?: 'ar' | 'en' }) {
  const label     = locale === 'ar' ? doc.label_ar : doc.label_en;
  const borderCls = doc.required ? 'border-gray-300' : 'border-dashed border-gray-300';

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
            {doc.required && (
              <span className="text-xs text-red-500 bg-red-50 px-1.5 py-0.5 rounded">
                {locale === 'ar' ? 'إلزامي' : 'Required'}
              </span>
            )}
            {doc.conditional && (
              <span className="text-xs text-orange-500 bg-orange-50 px-1.5 py-0.5 rounded">
                {locale === 'ar' ? 'شرطي' : 'Conditional'}
              </span>
            )}
            <span className="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded-full bg-blue-50 border border-blue-200 text-blue-700">
              معاينة
            </span>
          </div>
          {doc.description_ar && locale === 'ar' && (
            <p className="text-xs text-gray-500 mt-0.5">{doc.description_ar}</p>
          )}
          <p className="text-xs text-gray-400 mt-1">
            {locale === 'ar' ? 'الصيغ المقبولة:' : 'Accepted:'} {doc.accept.join(', ')}
            {' · '}
            {locale === 'ar' ? 'الحد الأقصى:' : 'Max:'} {doc.max_size_mb}MB
          </p>
        </div>

        <div className="flex-shrink-0">
          <button
            type="button"
            disabled
            aria-disabled="true"
            title="لا يمكن الرفع من شاشة المعاينة — يظهر هذا الزر عند المتقدم"
            className="px-3 py-2 text-xs rounded-lg font-medium bg-blue-600 text-white opacity-40 cursor-not-allowed"
          >
            {locale === 'ar' ? 'رفع الملف' : 'Upload'}
          </button>
        </div>
      </div>
    </div>
  );
}
