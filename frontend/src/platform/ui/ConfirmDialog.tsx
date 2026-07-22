import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';

/**
 * ConfirmDialog — JORD-70
 *
 * Accessible in-app replacement for `window.confirm()`.
 *
 * `window.confirm` blocks the whole event loop, can't be styled or
 * translated per-app (browser chrome dictates the button text), and
 * is unreachable to some assistive tech. This dialog:
 *   • Renders inside the React tree so RTL / theming / translations
 *     match the surrounding UI.
 *   • Traps initial focus on the confirm button so keyboard users
 *     never have to hunt for it.
 *   • Closes on Escape.
 *   • Uses role="alertdialog" + aria-labelledby / aria-describedby
 *     so screen readers announce the destructive intent.
 *
 * Callers keep the imperative feel by mounting the dialog with an
 * `open` prop and wiring `onConfirm` / `onCancel`.
 */
export function ConfirmDialog({
  open,
  title,
  message,
  onConfirm,
  onCancel,
  confirmLabel,
  cancelLabel,
  destructive = false,
  busy = false,
}: {
  open: boolean;
  title: string;
  message: string;
  onConfirm: () => void | Promise<void>;
  onCancel: () => void;
  confirmLabel?: string;
  cancelLabel?: string;
  /** When true, styles the confirm button red — used for delete flows. */
  destructive?: boolean;
  /** When true, disables both buttons + shows a busy label. Optional. */
  busy?: boolean;
}) {
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const confirmRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!open) return;
    // Focus the confirm button on open so keyboard users can Enter
    // straight through if they intended to proceed.
    confirmRef.current?.focus();
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !busy) onCancel();
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, busy, onCancel]);

  if (!open) return null;

  const confirmText = confirmLabel ?? t('common.confirm', { defaultValue: 'تأكيد' });
  const cancelText  = cancelLabel  ?? t('common.cancel',  { defaultValue: 'إلغاء' });

  return (
    <div
      role="alertdialog"
      aria-modal="true"
      aria-labelledby="confirm-dialog-title"
      aria-describedby="confirm-dialog-body"
      data-testid="confirm-dialog"
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
      dir={isRtl ? 'rtl' : 'ltr'}
      onClick={e => { if (e.target === e.currentTarget && !busy) onCancel(); }}
    >
      <div className="bg-white rounded-xl max-w-sm w-full p-6 shadow-xl">
        <h3
          id="confirm-dialog-title"
          className="text-base font-bold text-gray-900"
        >
          {title}
        </h3>
        <p id="confirm-dialog-body" className="text-sm text-gray-600 mt-2 whitespace-pre-wrap">
          {message}
        </p>
        <div className="mt-5 flex justify-end gap-2">
          <button
            type="button"
            onClick={onCancel}
            disabled={busy}
            data-testid="confirm-dialog-cancel"
            className="px-4 py-2 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 disabled:opacity-50"
          >
            {cancelText}
          </button>
          <button
            ref={confirmRef}
            type="button"
            onClick={() => void onConfirm()}
            disabled={busy}
            data-testid="confirm-dialog-confirm"
            className={`px-5 py-2 text-sm text-white font-bold rounded-lg hover:opacity-90 disabled:opacity-50 ${
              destructive ? 'bg-red-600' : 'bg-jea-primary'
            }`}
          >
            {busy ? t('common.saving', { defaultValue: 'جارٍ الحفظ…' }) : confirmText}
          </button>
        </div>
      </div>
    </div>
  );
}
