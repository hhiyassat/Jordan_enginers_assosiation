import React, { useState, useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { request } from '../../shared/api/http';

/**
 * ManualReferenceIcon
 * ────────────────────
 * أيقونة (?) بجانب حقل / قاعدة / خدمة، تعرض عند الضغط الأساس النظامي
 * من كتاب التعليمات الفنية 2025. لمستخدمي admin/superuser يظهر زر
 * "تعديل" داخل نفس الـ popover.
 *
 * المصدر: `GET /api/v1/manual-references/{id}` — قابل للقراءة لكل مستخدم
 * مسجَّل. التعديل عبر `PATCH /api/v1/admin/manual-references/{id}`
 * ويرفع flag `needs_reimplementation` في admin dashboard.
 */

export interface ManualReference {
  id: number;
  chapter: string | null;
  section: string | null;
  page: number | null;
  rule_type: string | null;
  text_ar: string;
  text_ar_original: string;
  jord_ticket: string | null;
  target_type: string;
  target_id: string | null;
  target_service_code: string | null;
  edited_by: number | null;
  edited_at: string | null;
  needs_reimplementation: boolean;
}

interface Props {
  referenceId: number;
  /** مستخدم admin/superuser فقط. يخبّي زر التعديل عند false. */
  canEdit?: boolean;
  /** حجم الأيقونة (px). افتراضي 16. */
  size?: number;
}

export function ManualReferenceIcon({ referenceId, canEdit = false, size = 16 }: Props) {
  const { i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const [open, setOpen] = useState(false);
  const [ref, setRef] = useState<ManualReference | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState('');
  const [reason, setReason] = useState('');
  const [saving, setSaving] = useState(false);
  const popoverRef = useRef<HTMLDivElement>(null);

  // Load lazily on first open.
  useEffect(() => {
    if (!open || ref) return;
    setLoading(true);
    setError('');
    request<{ reference: ManualReference }>('GET', `/manual-references/${referenceId}`)
      .then(r => setRef(r.reference))
      .catch(e => setError((e as Error).message))
      .finally(() => setLoading(false));
  }, [open, ref, referenceId]);

  // Close popover on outside click.
  useEffect(() => {
    if (!open) return;
    const onDocClick = (e: MouseEvent) => {
      if (popoverRef.current && !popoverRef.current.contains(e.target as Node)) {
        setOpen(false);
        setEditing(false);
      }
    };
    document.addEventListener('mousedown', onDocClick);
    return () => document.removeEventListener('mousedown', onDocClick);
  }, [open]);

  const startEdit = () => {
    if (!ref) return;
    setDraft(ref.text_ar);
    setReason('');
    setEditing(true);
  };

  const save = async () => {
    if (!ref) return;
    setSaving(true);
    setError('');
    try {
      const r = await request<{ reference: ManualReference }>(
        'PATCH',
        `/admin/manual-references/${ref.id}`,
        { text_ar: draft, edit_reason: reason || undefined },
      );
      setRef(r.reference);
      setEditing(false);
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <span className="relative inline-block" dir={isRtl ? 'rtl' : 'ltr'}>
      <button
        type="button"
        onClick={() => setOpen(!open)}
        aria-label={isRtl ? 'الأساس النظامي' : 'Manual reference'}
        title={isRtl ? 'الأساس النظامي من كتاب التعليمات الفنية' : 'Manual basis'}
        className="inline-flex items-center justify-center rounded-full bg-blue-100 text-blue-700 hover:bg-blue-200 font-bold ms-1"
        style={{ width: size, height: size, fontSize: Math.round(size * 0.65), lineHeight: 1 }}
      >
        ?
      </button>

      {open && (
        <div
          ref={popoverRef}
          role="dialog"
          className="absolute z-50 mt-2 w-96 max-w-[90vw] bg-white border border-gray-200 rounded-xl shadow-lg p-4 text-sm"
          style={{ [isRtl ? 'right' : 'left']: 0 } as React.CSSProperties}
          data-testid="manual-ref-popover"
        >
          {loading && <p className="text-gray-500">{isRtl ? 'جاري التحميل...' : 'Loading...'}</p>}
          {error && <p className="text-red-600">{error}</p>}
          {ref && !editing && (
            <>
              <div className="mb-2 text-xs text-gray-500 flex items-center gap-2 flex-wrap">
                <span>📖 {isRtl ? 'الأساس النظامي' : 'Manual basis'}</span>
                {ref.chapter && <span>·</span>}
                {ref.chapter && <span>{ref.chapter}</span>}
                {ref.page && <span>·</span>}
                {ref.page && <span>{isRtl ? `ص.${ref.page}` : `p.${ref.page}`}</span>}
                {ref.needs_reimplementation && (
                  <span
                    className="text-xs px-2 py-0.5 bg-amber-100 text-amber-800 rounded-full"
                    title={isRtl ? 'تعديل معلَّق يحتاج مراجعة تقنية' : 'Pending edit — needs re-implementation'}
                  >
                    ⚠️ {isRtl ? 'معلَّق' : 'pending'}
                  </span>
                )}
              </div>
              <p className="text-gray-800 leading-relaxed whitespace-pre-wrap">{ref.text_ar}</p>
              {ref.section && (
                <p className="mt-2 text-xs text-gray-400">{ref.section}</p>
              )}
              {canEdit && (
                <button
                  type="button"
                  onClick={startEdit}
                  className="mt-3 px-3 py-1.5 text-xs bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                  data-testid="manual-ref-edit-btn"
                >
                  ✎ {isRtl ? 'تعديل' : 'Edit'}
                </button>
              )}
            </>
          )}
          {ref && editing && (
            <>
              <p className="text-xs text-gray-500 mb-2">
                {isRtl
                  ? 'التعديل سيرفع علامة تحتاج مراجعة تقنية في لوحة الإدارة.'
                  : 'Editing will flag this rule as needing technical review.'}
              </p>
              <textarea
                value={draft}
                onChange={e => setDraft(e.target.value)}
                rows={5}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-2"
                data-testid="manual-ref-textarea"
              />
              <input
                type="text"
                value={reason}
                onChange={e => setReason(e.target.value)}
                placeholder={isRtl ? 'سبب التعديل (اختياري)' : 'Edit reason (optional)'}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-2"
              />
              <div className="flex gap-2 justify-end">
                <button
                  type="button"
                  onClick={() => setEditing(false)}
                  className="px-3 py-1.5 text-xs border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                  {isRtl ? 'إلغاء' : 'Cancel'}
                </button>
                <button
                  type="button"
                  onClick={save}
                  disabled={saving || draft.trim().length < 10}
                  className="px-3 py-1.5 text-xs bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                  data-testid="manual-ref-save-btn"
                >
                  {saving ? '…' : isRtl ? 'حفظ' : 'Save'}
                </button>
              </div>
            </>
          )}
        </div>
      )}
    </span>
  );
}
