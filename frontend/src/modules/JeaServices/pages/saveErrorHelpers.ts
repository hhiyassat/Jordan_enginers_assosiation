// Normalizes backend save-service errors into a shape the NewService page
// can render. Two backends produce two shapes and both must be handled:
//   • Laravel FormRequest → { code: ['taken'], ... }         (values are string[])
//   • SchemaStructureValidator → { 'schema.workflow…': 'msg' }  (values are string)
// Kept as a pure module so the reducer logic is testable without mounting
// the full NewService page.

export interface ApiError extends Error {
  errors?: Record<string, string | string[]>;
}

export interface NormalizedSaveError {
  /** Top-level summary message shown as the banner headline. */
  summary: string;
  /** Field → message map rendered as a list under the summary. */
  fieldErrors: Record<string, string>;
}

const CODE_TAKEN_HINTS = ['taken', 'unique'];

/**
 * Turn an API failure into a summary + field-error map.
 *   • `code` errors are pulled up into the summary with a friendly hint.
 *   • Every other error (including dotted schema paths like
 *     `schema.workflow.stages[0].actions`) lands in fieldErrors so the UI
 *     can show the reader exactly which field broke.
 */
export function normalizeSaveError(err: ApiError, submittedCode: string): NormalizedSaveError {
  const rawErrors = err.errors ?? {};
  const flat: Record<string, string> = {};
  for (const [k, v] of Object.entries(rawErrors)) {
    flat[k] = Array.isArray(v) ? String(v[0] ?? '') : String(v);
  }

  const codeMsg = flat.code ?? '';
  const codeTaken =
    CODE_TAKEN_HINTS.some(h => codeMsg.toLowerCase().includes(h)) ||
    CODE_TAKEN_HINTS.some(h => (err.message ?? '').toLowerCase().includes(h));

  const summary = codeTaken
    ? `كود الخدمة "${submittedCode}" مستخدم مسبقاً — غيّر الكود`
    : codeMsg || err.message || '';

  const fieldErrors: Record<string, string> = {};
  for (const [k, v] of Object.entries(flat)) {
    if (k !== 'code') fieldErrors[k] = v;
  }

  return { summary, fieldErrors };
}
