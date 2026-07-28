import React from 'react';
import { useTranslation } from 'react-i18next';
import type { Application, SchemaDocument } from '../../shared/types';
import { ManualReferenceIcon } from './ManualReferenceIcon';

/**
 * ReportsPanel
 * ────────────
 * A reusable panel for services that produce a REPORT deliverable
 * (SRV-001 site-investigation report is the first consumer). Filters
 * the service's document set to category === 'REPORT' and renders each
 * with the manual references that govern it — signature obligations,
 * technical content, direct-submission channel, etc.
 *
 * Non-report documents (contracts, ownership deeds) are NOT rendered
 * here — they stay in the ordinary DocumentUploader / attachments
 * section. Rendering both would duplicate the file in the UI.
 *
 * Empty when the service has no REPORT documents — safe to mount on
 * any Apply / ApplicationDetail page without conditional guards.
 */
interface Props {
  documents: SchemaDocument[];
  application?: Application;
}

export function ReportsPanel({ documents, application }: Props) {
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');

  const reports = documents.filter(d => d.category === 'REPORT');
  if (reports.length === 0) return null;

  const uploadedById = new Map(
    (application?.documents ?? []).map(d => [d.document_id, d]),
  );

  return (
    <section
      dir={isRtl ? 'rtl' : 'ltr'}
      data-testid="reports-panel"
      className="rounded-xl border border-blue-100 bg-blue-50/40 p-4 md:p-5 mt-4"
    >
      <header className="mb-3 flex items-center gap-2">
        <span className="text-lg">📊</span>
        <h3 className="font-semibold text-gray-800 text-sm md:text-base">
          {isRtl ? 'التقارير الفنية' : 'Technical Reports'}
        </h3>
      </header>
      <ul className="space-y-3">
        {reports.map(doc => {
          const label = isRtl ? doc.label_ar : (doc.label_en || doc.label_ar);
          const existing = uploadedById.get(doc.id);
          const refIds = Array.isArray(doc.manual_reference_ids)
            ? doc.manual_reference_ids
            : [];
          return (
            <li
              key={doc.id}
              data-testid={`report-${doc.id}`}
              className="rounded-lg bg-white border border-gray-200 p-3"
            >
              <div className="flex items-start justify-between gap-3 flex-wrap">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-1 flex-wrap">
                    <span className="text-sm font-medium text-gray-900">{label}</span>
                    {doc.required && (
                      <span className="text-[10px] px-1.5 py-0.5 rounded bg-red-50 text-red-600 uppercase">
                        {isRtl ? 'مطلوب' : 'required'}
                      </span>
                    )}
                    {doc.report_type && (
                      <span className="text-[10px] px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 font-mono">
                        {doc.report_type}
                      </span>
                    )}
                    {refIds.map(id => (
                      <ManualReferenceIcon
                        key={id}
                        referenceId={id}
                        canEdit={false}
                        size={16}
                      />
                    ))}
                  </div>
                  {doc.signature_requirements?.signing_role && (
                    <p className="mt-1 text-xs text-gray-500">
                      {isRtl ? 'التوقيع: ' : 'Signature: '}
                      <code className="font-mono text-gray-700">
                        {doc.signature_requirements.signing_role}
                      </code>
                    </p>
                  )}
                  <p className="mt-1 text-xs text-gray-400">
                    {t('documentUploader.accepted') || 'Accepted:'}{' '}
                    {doc.accept.join(', ')} · {doc.max_size_mb}MB
                  </p>
                </div>
                <div className="text-xs">
                  {existing ? (
                    <span className="text-green-700">
                      ✓ {isRtl ? 'مرفوع' : 'uploaded'}
                    </span>
                  ) : (
                    <span className="text-gray-400">
                      {isRtl ? 'لم يُرفع بعد' : 'not uploaded'}
                    </span>
                  )}
                </div>
              </div>
            </li>
          );
        })}
      </ul>
    </section>
  );
}
