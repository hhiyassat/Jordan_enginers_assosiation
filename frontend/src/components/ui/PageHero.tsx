import React from 'react';

/**
 * PageHero — NFR-003
 *
 * Shared navy hero for every page under the JEA shell. Enforces the
 * bilingual title pattern (Arabic primary, English subtitle) so new pages
 * inherit the pattern without having to re-implement it.
 *
 * Slots:
 *   breadcrumb: optional row shown ABOVE the title (nav links + separators)
 *   actions:    right-aligned actions (e.g., "إضافة مشروع" button)
 *
 * The heading is emitted as <h1> for correct document outline (WCAG A/AA).
 */
interface PageHeroProps {
  titleAr: string;
  titleEn: string;
  /** Optional subtitle (only shown if provided). Rendered under the title. */
  subtitleAr?: string;
  subtitleEn?: string;
  /** Rendered above the title — usually a breadcrumb row */
  breadcrumb?: React.ReactNode;
  /** Rendered on the flow-end (left in RTL) of the title row */
  actions?: React.ReactNode;
}

export function PageHero({
  titleAr, titleEn, subtitleAr, subtitleEn, breadcrumb, actions,
}: PageHeroProps) {
  return (
    <div className="bg-jea-topbar px-6 py-5 shrink-0" dir="rtl">
      {breadcrumb && <div className="mb-2">{breadcrumb}</div>}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-xl font-black text-white" lang="ar">{titleAr}</h1>
          <p className="text-white/50 text-xs mt-0.5">
            <span lang="en" dir="ltr">{titleEn}</span>
            {subtitleAr && (
              <>
                <span className="opacity-60 mx-1" aria-hidden="true">·</span>
                <span lang="ar">{subtitleAr}</span>
              </>
            )}
            {subtitleEn && (
              <>
                <span className="opacity-60 mx-1" aria-hidden="true">·</span>
                <span lang="en" dir="ltr">{subtitleEn}</span>
              </>
            )}
          </p>
        </div>
        {actions && <div>{actions}</div>}
      </div>
    </div>
  );
}
