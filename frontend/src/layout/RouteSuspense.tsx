import React, { Suspense } from 'react';
import { useTranslation } from 'react-i18next';

/**
 * Route-wide Suspense fallback for the code-split page chunks
 * (JORD-25 / JORD-32). Intentionally spartan — the chunks are small;
 * a full skeleton would flicker on cached loads.
 */
export function RouteSuspense({ children }: { children: React.ReactNode }): JSX.Element {
  const { t } = useTranslation();
  return (
    <Suspense
      fallback={
        <div
          className="flex items-center justify-center h-full min-h-[240px]"
          role="status"
          aria-live="polite"
          aria-label={t('loading')}
        >
          <div className="animate-spin w-8 h-8 border-4 border-jea-primary border-t-transparent rounded-full" />
        </div>
      }
    >
      {children}
    </Suspense>
  );
}
