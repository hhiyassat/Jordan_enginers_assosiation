/**
 * fix/frontend-vite-proxy-port · regression tests for the login page's
 * error surface. The reporter observed HTTP 422 responses causing the
 * global React ErrorBoundary to render "حدث خطأ غير متوقع" instead of
 * an inline validation message. These tests prove that:
 *
 *   1. HTTP 422 with `errors.email` renders inline without unmounting.
 *   2. HTTP 422 with `errors.captcha_answer` renders inline even when
 *      the frontend CAPTCHA widget is off (the config-mismatch scenario
 *      that the fix targets).
 *   3. HTTP 401 renders an invalid-credentials message inline.
 *   4. HTTP 429 renders a rate-limit message inline.
 *   5. Network failure renders a recoverable server-connection message
 *      without an unhandled rejection.
 *
 * Each test wraps the LoginPage in a React ErrorBoundary; if any of
 * these scenarios triggers the boundary the test fails.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import { MemoryRouter } from 'react-router-dom';

// Mock BEFORE importing LoginPage so its `import { authApi }` resolves
// to our stub.
vi.mock('../api/client', () => ({
  authApi: {
    login: vi.fn(),
    logout: vi.fn().mockResolvedValue(undefined),
    me: vi.fn(),
  },
  setUnauthorizedHandler: vi.fn(),
}));

import { LoginPage } from './LoginPage';
import { AuthProvider } from './AuthProvider';
import { authApi } from '../api/client';

// Minimal boundary — mirrors platform/components/ErrorBoundary but keeps
// the test self-contained.
class TestBoundary extends React.Component<
  { children: React.ReactNode },
  { crashed: boolean }
> {
  state = { crashed: false };
  static getDerivedStateFromError() {
    return { crashed: true };
  }
  render() {
    if (this.state.crashed) {
      return <div data-testid="boundary-fired">BOUNDARY_FIRED</div>;
    }
    return this.props.children;
  }
}

function renderLogin() {
  // Stub /auth/me for AuthProvider bootstrap.
  (authApi.me as ReturnType<typeof vi.fn>).mockResolvedValue({ user: null });
  return render(
    <TestBoundary>
      <MemoryRouter>
        <AuthProvider>
          <LoginPage />
        </AuthProvider>
      </MemoryRouter>
    </TestBoundary>,
  );
}

function apiError(status: number, payload: Record<string, unknown>): Error {
  const err = new Error((payload.message as string) ?? '') as Error & {
    status: number;
    errors?: Record<string, string[]>;
  };
  err.status = status;
  err.errors = payload.errors as Record<string, string[]> | undefined;
  return err;
}

beforeEach(() => {
  // vi.clearAllMocks clears call history but NOT queued one-shot
  // implementations (mockRejectedValueOnce). Reset the login mock
  // explicitly so a rejection queued by a prior test doesn't leak
  // into the next.
  (authApi.login as ReturnType<typeof vi.fn>).mockReset();
  (authApi.me as ReturnType<typeof vi.fn>).mockReset();
});

describe('LoginPage · expected non-2xx responses do not crash the app', () => {
  it('renders 422 email validation inline and does NOT fire the ErrorBoundary', async () => {
    (authApi.login as ReturnType<typeof vi.fn>).mockRejectedValueOnce(
      apiError(422, {
        message: 'البيانات المدخلة غير صحيحة.',
        errors: { email: ['البريد الإلكتروني مطلوب.'] },
      }),
    );

    renderLogin();
    const user = userEvent.setup();
    // AuthProvider bootstrap is async — findBy waits for the login
    // form to render.
    await screen.findByPlaceholderText('admin@demo.esp');
    await user.type(screen.getByPlaceholderText('admin@demo.esp'), 'x@x.esp');
    await user.type(screen.getByPlaceholderText('••••••••'), 'password');
    await user.click(screen.getByRole('button', { name: /(sign|login|دخول|تسجيل)/i }));

    // Boundary must NOT fire, and the backend message must render inline.
    await waitFor(() => {
      expect(screen.queryByTestId('boundary-fired')).not.toBeInTheDocument();
      expect(screen.getByRole('alert')).toHaveTextContent('البيانات المدخلة غير صحيحة.');
    });
  });

  it('renders 422 captcha_answer error inline even when the widget is hidden', async () => {
    (authApi.login as ReturnType<typeof vi.fn>).mockRejectedValueOnce(
      apiError(422, {
        message: 'رمز التحقق غير صحيح، يرجى المحاولة مرة أخرى.',
        errors: { captcha_answer: ['رمز التحقق غير صحيح.'] },
      }),
    );

    renderLogin();
    const user = userEvent.setup();
    // AuthProvider bootstrap resolves asynchronously — wait for the
    // login form to render before typing.
    await screen.findByPlaceholderText('admin@demo.esp');
    await user.type(screen.getByPlaceholderText('admin@demo.esp'), 'x@x.esp');
    await user.type(screen.getByPlaceholderText('••••••••'), 'password');
    await user.click(screen.getByRole('button', { name: /(sign|login|دخول|تسجيل)/i }));

    await waitFor(() => {
      expect(screen.queryByTestId('boundary-fired')).not.toBeInTheDocument();
      // In dev builds this is the config-mismatch hint; in prod builds
      // it's the raw backend message. Either way, SOMETHING renders.
      expect(screen.getByRole('alert')).toBeInTheDocument();
    });
  });

  it('renders 401 invalid-credentials inline', async () => {
    (authApi.login as ReturnType<typeof vi.fn>).mockRejectedValueOnce(
      apiError(401, { message: 'بيانات الاعتماد غير صحيحة.' }),
    );

    renderLogin();
    const user = userEvent.setup();
    // AuthProvider bootstrap resolves asynchronously — wait for the
    // login form to render before typing.
    await screen.findByPlaceholderText('admin@demo.esp');
    await user.type(screen.getByPlaceholderText('admin@demo.esp'), 'x@x.esp');
    await user.type(screen.getByPlaceholderText('••••••••'), 'wrong');
    await user.click(screen.getByRole('button', { name: /(sign|login|دخول|تسجيل)/i }));

    await waitFor(() => {
      expect(screen.queryByTestId('boundary-fired')).not.toBeInTheDocument();
      expect(screen.getByRole('alert')).toHaveTextContent('بيانات الاعتماد غير صحيحة.');
    });
  });

  it('renders 429 rate-limit message inline', async () => {
    (authApi.login as ReturnType<typeof vi.fn>).mockRejectedValueOnce(
      apiError(429, { message: 'عدد الطلبات كبير — حاول مرة أخرى بعد قليل.' }),
    );

    renderLogin();
    const user = userEvent.setup();
    // AuthProvider bootstrap resolves asynchronously — wait for the
    // login form to render before typing.
    await screen.findByPlaceholderText('admin@demo.esp');
    await user.type(screen.getByPlaceholderText('admin@demo.esp'), 'x@x.esp');
    await user.type(screen.getByPlaceholderText('••••••••'), 'password');
    await user.click(screen.getByRole('button', { name: /(sign|login|دخول|تسجيل)/i }));

    await waitFor(() => {
      expect(screen.queryByTestId('boundary-fired')).not.toBeInTheDocument();
      expect(screen.getByRole('alert')).toHaveTextContent(/(كبير|طلبات)/);
    });
  });

  it('renders a recoverable server-connection message on network failure', async () => {
    // status=0 is what the http.ts fetch-catch branch throws.
    const netErr = new Error('تعذّر الاتصال بالخادم. تحقق من الاتصال.') as Error & {
      status: number;
    };
    netErr.status = 0;
    (authApi.login as ReturnType<typeof vi.fn>).mockRejectedValueOnce(netErr);

    renderLogin();
    const user = userEvent.setup();
    // AuthProvider bootstrap resolves asynchronously — wait for the
    // login form to render before typing.
    await screen.findByPlaceholderText('admin@demo.esp');
    await user.type(screen.getByPlaceholderText('admin@demo.esp'), 'x@x.esp');
    await user.type(screen.getByPlaceholderText('••••••••'), 'password');
    await user.click(screen.getByRole('button', { name: /(sign|login|دخول|تسجيل)/i }));

    await waitFor(() => {
      expect(screen.queryByTestId('boundary-fired')).not.toBeInTheDocument();
      expect(screen.getByRole('alert')).toHaveTextContent(/(الاتصال|الخادم)/);
    });
  });
});
