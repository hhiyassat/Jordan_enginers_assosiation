import React from 'react';

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

    return (
      <div dir="rtl" className="min-h-screen flex items-center justify-center bg-jea-bg p-6">
        <div className="max-w-md w-full bg-white border border-red-200 rounded-lg p-6 shadow-sm text-center">
          <div className="text-red-600 text-4xl mb-3" aria-hidden="true">⚠</div>
          <h1 className="text-lg font-bold text-jea-text mb-1" lang="ar">
            حدث خطأ غير متوقع
          </h1>
          <p className="text-xs text-jea-muted mb-4" lang="en" dir="ltr">
            Something went wrong
          </p>
          <p className="text-sm text-jea-muted mb-5">
            نأسف على الإزعاج. يمكنك تحديث الصفحة أو المحاولة مرة أخرى.
          </p>
          <div className="flex gap-2 justify-center">
            <button
              type="button"
              onClick={this.reset}
              className="px-4 py-2 rounded bg-jea-primary text-white text-sm font-semibold hover:opacity-90"
            >
              إعادة المحاولة
            </button>
            <button
              type="button"
              onClick={() => window.location.reload()}
              className="px-4 py-2 rounded border border-jea-border text-sm font-semibold text-jea-text hover:bg-jea-bg"
            >
              تحديث الصفحة
            </button>
          </div>
        </div>
      </div>
    );
  }
}
