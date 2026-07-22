import React, { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { Application, SchemaDocument } from '../types';
import { applicationsApi } from '../api/client';

/**
 * DocumentUploader
 *
 * Renders all required (and optional) document upload slots
 * based on the service schema's documents array.
 * Handles conditional documents (e.g., health cert only for F&B).
 *
 * JORD-94: retrofit with react-i18next. The old `locale` prop still
 * works (existing callers pass it explicitly) but it's now optional —
 * absent → we read the current language from i18n. Every visible
 * label routes through t() so the widget flips with the rest of
 * the UI.
 */
interface Props {
  documents: SchemaDocument[];
  application: Application;
  formData: Record<string, unknown>;
  onUploaded: () => void;
  locale?: 'ar' | 'en';
}

export function DocumentUploader({ documents, application, formData, onUploaded, locale }: Props) {
  const { i18n } = useTranslation();
  const effectiveLocale: 'ar' | 'en' = locale ?? (i18n.language.startsWith('ar') ? 'ar' : 'en');
  const label = (doc: SchemaDocument) =>
    effectiveLocale === 'ar' ? doc.label_ar : (doc.label_en || doc.label_ar);

  const isVisible = (doc: SchemaDocument): boolean => {
    if (!doc.conditional) return true;
    return formData[doc.conditional.field] === doc.conditional.value;
  };

  const uploadedIds = (application.documents || []).map(d => d.document_id);

  return (
    <div dir={effectiveLocale === 'ar' ? 'rtl' : 'ltr'} className="space-y-4">
      {documents.filter(isVisible).map(doc => (
        <DocumentSlot
          key={doc.id}
          doc={doc}
          applicationId={application.id}
          isUploaded={uploadedIds.includes(doc.id)}
          existingFile={application.documents?.find(d => d.document_id === doc.id)}
          onUploaded={onUploaded}
          label={label(doc)}
        />
      ))}
    </div>
  );
}

interface SlotProps {
  doc: SchemaDocument;
  applicationId: number;
  isUploaded: boolean;
  existingFile?: { original_filename: string; status: string };
  onUploaded: () => void;
  label: string;
}

function DocumentSlot({ doc, applicationId, isUploaded, existingFile, onUploaded, label }: SlotProps) {
  const { t, i18n } = useTranslation();
  const isArabic = i18n.language.startsWith('ar');
  const description = isArabic ? doc.description_ar : (doc.description_en || doc.description_ar);
  const [uploading, setUploading] = useState(false);
  const [error, setError]         = useState('');
  const inputRef = useRef<HTMLInputElement>(null);

  const handleFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (file.size > doc.max_size_mb * 1024 * 1024) {
      setError(t('documentUploader.sizeExceeded', { max: doc.max_size_mb }));
      return;
    }

    setUploading(true);
    setError('');
    try {
      await applicationsApi.uploadDocument(applicationId, doc.id, file);
      onUploaded();
    } catch (err: unknown) {
      const e = err as Error;
      setError(e.message || t('documentUploader.upload'));
    } finally {
      setUploading(false);
    }
  };

  const borderColor = isUploaded
    ? 'border-green-400 bg-green-50'
    : doc.required
      ? 'border-gray-300'
      : 'border-dashed border-gray-300';

  return (
    <div className={`rounded-lg border-2 p-4 transition-colors ${borderColor}`}>
      <div className="flex items-start justify-between gap-4">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2">
            <span className="text-sm font-medium text-gray-800">{label}</span>
            {doc.required && (
              <span className="text-xs text-red-500 bg-red-50 px-1.5 py-0.5 rounded">
                {t('documentUploader.required')}
              </span>
            )}
          </div>
          {description && (
            <p className="text-xs text-gray-500 mt-0.5">{description}</p>
          )}
          <p className="text-xs text-gray-400 mt-1">
            {t('documentUploader.accepted')} {doc.accept.join(', ')} · {t('documentUploader.maxSize')} {doc.max_size_mb}MB
          </p>

          {existingFile && (
            <div className="mt-2 flex items-center gap-2 text-xs">
              <span className="text-green-600">✓</span>
              <span className="text-gray-600 truncate">{existingFile.original_filename}</span>
              <span className={`px-1.5 py-0.5 rounded text-xs ${
                existingFile.status === 'accepted' ? 'bg-green-100 text-green-700' :
                existingFile.status === 'rejected' ? 'bg-red-100 text-red-700' :
                'bg-yellow-100 text-yellow-700'
              }`}>
                {existingFile.status}
              </span>
            </div>
          )}

          {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>

        <div className="flex-shrink-0">
          <button
            type="button"
            onClick={() => inputRef.current?.click()}
            disabled={uploading}
            className={`px-3 py-2 text-xs rounded-lg font-medium transition-colors ${
              isUploaded
                ? 'bg-green-100 text-green-700 hover:bg-green-200'
                : 'bg-blue-600 text-white hover:bg-blue-700'
            } disabled:opacity-50`}
          >
            {uploading
              ? t('documentUploader.uploading')
              : isUploaded
                ? t('documentUploader.replace')
                : t('documentUploader.upload')}
          </button>
          <input
            ref={inputRef}
            type="file"
            className="hidden"
            accept={doc.accept.map(ext => `.${ext}`).join(',')}
            onChange={handleFile}
          />
        </div>
      </div>
    </div>
  );
}
