import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { ErrorBoundary } from './ErrorBoundary';

/**
 * JORD-27 regression: a render-time throw below the boundary must not
 * unmount the whole app; the fallback UI has to appear and let the user
 * retry.
 */

function Boom(): JSX.Element {
  throw new Error('kaboom');
}

describe('ErrorBoundary', () => {
  it('renders children when nothing throws', () => {
    render(
      <ErrorBoundary>
        <span>ok</span>
      </ErrorBoundary>
    );
    expect(screen.getByText('ok')).toBeInTheDocument();
  });

  it('renders the default fallback and swallows the throw', () => {
    // Silence expected React error boundary log noise for this one test.
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});
    render(
      <ErrorBoundary>
        <Boom />
      </ErrorBoundary>
    );
    expect(screen.getByText(/حدث خطأ غير متوقع/)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /إعادة المحاولة/ })).toBeInTheDocument();
    spy.mockRestore();
  });

  it('resets to children when reset button clicked', () => {
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});

    // Use a state flag so we can flip the throw off before re-rendering.
    let shouldThrow = true;
    function Flaky(): JSX.Element {
      if (shouldThrow) throw new Error('once');
      return <span>recovered</span>;
    }

    const { rerender } = render(
      <ErrorBoundary>
        <Flaky />
      </ErrorBoundary>
    );
    expect(screen.getByText(/حدث خطأ/)).toBeInTheDocument();

    shouldThrow = false;
    fireEvent.click(screen.getByRole('button', { name: /إعادة المحاولة/ }));
    rerender(
      <ErrorBoundary>
        <Flaky />
      </ErrorBoundary>
    );
    expect(screen.getByText('recovered')).toBeInTheDocument();
    spy.mockRestore();
  });

  it('honours a custom fallback', () => {
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});
    render(
      <ErrorBoundary fallback={(err) => <span>caught: {err.message}</span>}>
        <Boom />
      </ErrorBoundary>
    );
    expect(screen.getByText('caught: kaboom')).toBeInTheDocument();
    spy.mockRestore();
  });
});
