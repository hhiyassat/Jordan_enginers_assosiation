import type { Application, SchemaDocument } from '../../../types';

/**
 * JORD-58: compute the set of REQUIRED documents that the applicant
 * has not yet uploaded, honouring conditional documents (only visible
 * when their gate field matches the expected value).
 *
 * Extracted as a pure helper so the docs-step gate can be unit-tested
 * without mounting the full Apply wizard.
 */
export function missingRequiredDocs(
  schemaDocuments: SchemaDocument[] | undefined | null,
  uploadedDocumentIds: Iterable<string>,
  formData: Record<string, unknown>,
): SchemaDocument[] {
  if (!schemaDocuments || schemaDocuments.length === 0) return [];
  const uploaded = new Set(uploadedDocumentIds);
  return schemaDocuments.filter(doc => {
    if (!doc.required) return false;
    if (doc.conditional && formData[doc.conditional.field] !== doc.conditional.value) {
      return false;
    }
    return !uploaded.has(doc.id);
  });
}

/**
 * Convenience overload that reads the uploaded set off an Application
 * — matches the shape callers in the wizard actually have.
 */
export function missingRequiredDocsFor(
  schemaDocuments: SchemaDocument[] | undefined | null,
  application: Application | null,
  formData: Record<string, unknown>,
): SchemaDocument[] {
  const ids = (application?.documents ?? []).map(d => d.document_id);
  return missingRequiredDocs(schemaDocuments, ids, formData);
}
