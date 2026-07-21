import React from 'react';
import i18n from '../i18n';

/**
 * Route-level error boundary.
 *
 * Without this, a render-time throw anywhere below the router unmounts
 * the whole SPA and leaves the tab on a white screen — no way for the
 * user to recover except a hard reload. This catches the throw, logs
 * it, and shows a bilingual retry surface.
 *
 * Boundaries only catch render/lifecycle errors — event-handler and
 * async-request failures are handled by the api client's central
 * error surface (see api/client.ts). Two complementary layers.
 *
 * JORD-96: use i18n.t() directly (not the hook — class components
 * can't call hooks) with defaultValue fallbacks. If translations
 * haven't loaded (async race) the defaults still render, so the
 * boundary keeps working even in a truly degraded state.
 */
interface State {
  error: Error | null;
}

interface Props {
  children: React.ReactNode;
  /** Optional custom fallback — passed the caught error + a reset function. */
  fallback?: (err: Error, reset: () => void) => React.ReactNode;
}

export class ErrorBoundary extends React.Component<Props, State> {
  state: State = { error: null };

  static getDerivedStateFromError(error: Error): State {
    return { error };
  }

  componentDidCatch(error: Error, info: React.ErrorInfo): void {
    // Log so ops can see repeat crashes. If a real error-reporting
    // service (Sentry etc.) gets wired in later, hook it here.
    // eslint-disable-next-line no-console
    console.error('[ErrorBoundary] caught', error, info?.componentStack);
  }

  private reset = (): void => this.setState({ error: null });

  render(): React.ReactNode {
    const { error } = this.state;
    if (!error) return this.props.children;

    if (this.props.fallback) return this.props.fallback(error, this.reset);

    const isRtl = (i18n.language || 'ar').startsWith('ar');
    // Existing i18n.error.* keys already carry the copy — no new keys needed.
    const title = i18n.t('error.unexpected', { defaultValue: 'حدث خطأ غير متوقع' });
    const body = i18n.t('error.sorry', {
      defaultValue: 'نأسف على الإزعاج. يمكنك تحديث الصفحة أو المحاولة مرة أخرى.',
    });
    const retry = i18n.t('error.retry',   { defaultValue: 'إعادة المحاولة' });
    const reload = i18n.t('error.refresh', { defaultValue: 'تحديث الصفحة' });

    return (
      <div dir={isRtl ? 'rtl' : 'ltr'} className="min-h-screen flex items-center justify-center bg-jea-bg p-6">
        <div className="max-w-md w-full bg-white border border-red-200 rounded-lg p-6 shadow-sm text-center">
          <div className="text-red-600 text-4xl mb-3" aria-hidden="true">⚠</div>
          <h1 className="text-lg font-bold text-jea-text mb-3">{title}</h1>
          <p className="text-sm text-jea-muted mb-5">{body}</p>
          <div className="flex gap-2 justify-center">
            <button
              type="button"
              onClick={this.reset}
              className="px-4 py-2 rounded bg-jea-primary text-white text-sm font-semibold hover:opacity-90"
            >
              {retry}
            </button>
            <button
              type="button"
              onClick={() => window.location.reload()}
              className="px-4 py-2 rounded border border-jea-border text-sm font-semibold text-jea-text hover:bg-jea-bg"
            >
              {reload}
            </button>
          </div>
        </div>
      </div>
    );
  }
}
