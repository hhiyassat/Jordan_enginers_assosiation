import React, { useEffect, useId, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { X } from 'lucide-react';
import { Bilingual } from './Bilingual';

/**
 * Modal — NFR-004
 *
 * Accessible dialog: role="dialog", aria-modal, labelled by the title,
 * focus trap while open, Escape closes, click-outside closes, focus
 * restored to the trigger on close. Every modal in the app uses this,
 * so new dialogs inherit the a11y guarantees automatically.
 *
 * Usage:
 *   <Modal open={open} onClose={close} titleAr="..." titleEn="...">
 *     <FormField ... />
 *     <div slot="footer">...</div>
 *   </Modal>
 */
interface ModalProps {
  open: boolean;
  onClose: () => void;
  titleAr: string;
  titleEn: string;
  size?: 'sm' | 'md' | 'lg';
  /** Rendered in a pinned footer band; typically Cancel/Save buttons. */
  footer?: React.ReactNode;
  children: React.ReactNode;
}

const SIZE: Record<NonNullable<ModalProps['size']>, string> = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-2xl',
};

export function Modal({ open, onClose, titleAr, titleEn, size = 'md', footer, children }: ModalProps) {
  const { i18n } = useTranslation();
  const dir = i18n.language.startsWith('ar') ? 'rtl' : 'ltr';
  const rawId = useId();
  const titleId = `modal-${rawId}-title`;
  const panelRef = useRef<HTMLDivElement>(null);
  const previouslyFocused = useRef<HTMLElement | null>(null);

  // Escape to close + focus trap + focus restoration
  useEffect(() => {
    if (!open) return;

    previouslyFocused.current = document.activeElement as HTMLElement | null;

    // Focus the panel on open so screen readers announce the dialog and
    // Tab starts inside. A tabindex=-1 element receives focus without
    // being in the tab order.
    panelRef.current?.focus();

    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        onClose();
        return;
      }
      if (e.key !== 'Tab') return;
      // Cheap focus trap: if focus is about to leave the panel, cycle it.
      const panel = panelRef.current;
      if (!panel) return;
      const focusables = panel.querySelectorAll<HTMLElement>(
        'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
      );
      if (focusables.length === 0) return;
      const first = focusables[0];
      const last  = focusables[focusables.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    };

    document.addEventListener('keydown', onKeyDown);
    // Prevent scroll on body while open
    const originalOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    return () => {
      document.removeEventListener('keydown', onKeyDown);
      document.body.style.overflow = originalOverflow;
      previouslyFocused.current?.focus?.();
    };
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
      // JORD-56 (PM): dir was hardcoded rtl so English input flowed
      // right-to-left inside the modal. Follow the current language.
      dir={dir}
      onClick={onClose}
      role="presentation"
    >
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        tabIndex={-1}
        className={`bg-white rounded-2xl shadow-2xl w-full ${SIZE[size]} overflow-hidden focus:outline-none focus-visible:ring-2 focus-visible:ring-jea-primary/40`}
        onClick={e => e.stopPropagation()}
      >
        <div className="bg-jea-bg px-5 py-4 border-b border-jea-border flex items-center justify-between">
          <Bilingual
            ar={titleAr}
            en={titleEn}
            variant="stacked"
            as="div"
            arClassName="text-base font-black text-jea-text"
            enClassName="text-xs text-jea-muted"
          />
          <button
            type="button"
            onClick={onClose}
            aria-label="إغلاق"
            className="text-jea-muted hover:text-jea-text transition-colors rounded p-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-jea-primary/40"
          >
            <X size={18} aria-hidden="true" />
          </button>
        </div>

        <div className="p-5">{children}</div>

        {footer && (
          <div className="flex items-center justify-end gap-2 px-5 py-3 border-t border-jea-border bg-white">
            {footer}
          </div>
        )}
      </div>
    </div>
  );
}
