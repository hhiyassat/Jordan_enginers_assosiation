/**
 * HTTP client core — extracted from the monolithic client.ts (JORD-22).
 *
 * Every domain file in `src/api/*.ts` funnels its requests through
 * `request()` here so timeouts, 401 handling, error normalisation, and
 * token attachment live in one place.
 */

export const BASE = '/api/v1';

/**
 * Default per-request timeout. Anything over this cancels via AbortController
 * — network unavailability used to hang UI spinners forever because fetch()
 * has no default timeout. Uploads (FormData) skip this cap since they can
 * take longer than the default; individual callers can pass a shorter one.
 */
export const DEFAULT_TIMEOUT_MS = 30_000;

/**
 * JORD-30: token was moved from sessionStorage to a backend-managed
 * httpOnly + SameSite=Strict cookie so JavaScript (and any XSS) can't
 * reach it. The browser attaches the cookie automatically on every
 * same-origin request, so the client no longer touches the token at
 * all. `credentials: 'include'` on every fetch tells the browser to
 * carry the cookie.
 *
 * The setToken/getToken pair below is kept as a stub because some
 * legacy consumers (tests, the multi-tab demo flow) still look up a
 * token; returning null now signals "no in-JS token; rely on the
 * cookie" which is exactly the right behaviour post-migration.
 */
function getToken(): string | null {
  return null;
}

/**
 * The AuthProvider registers a callback here on mount so the client can
 * clear session state when the server hands us a 401. Kept off React
 * context so it's usable inside plain modules that don't have hooks.
 */
type SessionInvalidator = () => void;
let onUnauthorized: SessionInvalidator | null = null;
export function setUnauthorizedHandler(fn: SessionInvalidator | null): void {
  onUnauthorized = fn;
}

/** Human-readable message keyed off HTTP status. Never leaks stack traces. */
function friendlyMessage(status: number, backendMessage?: string): string {
  // Trust bilingual/Arabic backend messages from our own API — those are
  // authored for end users. Only fall back to generic strings when the
  // backend gave us nothing useful.
  if (backendMessage && backendMessage.trim().length > 0 && !/^HTTP\s\d/.test(backendMessage)) {
    return backendMessage;
  }
  if (status === 401) return 'انتهت جلستك — يرجى تسجيل الدخول مجدداً.';
  if (status === 403) return 'ليست لديك صلاحية لتنفيذ هذا الإجراء.';
  if (status === 404) return 'العنصر المطلوب غير موجود.';
  if (status === 422) return 'البيانات المدخلة غير صحيحة.';
  if (status === 429) return 'عدد الطلبات كبير — حاول مرة أخرى بعد قليل.';
  if (status >= 500)  return 'حدث خطأ في الخادم. يرجى المحاولة لاحقاً.';
  return 'حدث خطأ غير متوقع.';
}

/**
 * ApiError enriches the standard Error with the server-provided validation
 * bucket + Hukm governance fields. Exported so React Query hooks can narrow
 * onError callbacks cleanly.
 */
export type ApiError = Error & {
  errors?:   Record<string, string>;
  status?:   number;
  blockers?: unknown[];
  verdict?:  string;
};

export async function request<T>(
  method: string,
  path: string,
  body?: unknown,
  isFormData = false,
): Promise<T> {
  const headers: Record<string, string> = {};
  const token = getToken();

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  // Always tell Laravel we expect JSON — without this, validation errors
  // return 302 redirects instead of 422 JSON responses.
  headers['Accept'] = 'application/json';

  if (!isFormData && body) {
    headers['Content-Type'] = 'application/json';
  }

  // AbortController + timeout: raw fetch() has no timeout. A dead server
  // or dropped connection would hang UI spinners forever. Skip the cap on
  // FormData (uploads can legitimately take longer).
  const controller = new AbortController();
  const timeoutId = isFormData
    ? null
    : setTimeout(() => controller.abort(), DEFAULT_TIMEOUT_MS);

  let res: Response;
  try {
    res = await fetch(`${BASE}${path}`, {
      // JORD-30: send the httpOnly session cookie on every same-origin
      // request. Without this, the browser drops the cookie and every
      // request 401s.
      credentials: 'include',
      method,
      headers,
      body: isFormData ? (body as FormData) : body ? JSON.stringify(body) : undefined,
      signal: controller.signal,
    });
  } catch (fetchErr) {
    if (timeoutId) clearTimeout(timeoutId);
    // Distinguish timeout from other network failures so the UI can
    // show something useful instead of DOMException / TypeError.
    const isAbort = fetchErr instanceof DOMException && fetchErr.name === 'AbortError';
    const err = new Error(
      isAbort
        ? 'انتهت مهلة الطلب. يرجى المحاولة مرة أخرى.'
        : 'تعذّر الاتصال بالخادم. تحقق من الاتصال.'
    ) as ApiError;
    err.status = 0;
    throw err;
  }
  if (timeoutId) clearTimeout(timeoutId);

  const json = await res.json().catch(() => ({}));

  if (!res.ok) {
    // JORD-29: central 401 handler — clear the session once and let the
    // AuthProvider trigger the redirect via its normal `!user` guard, so
    // callers don't each reinvent the wheel.
    if (res.status === 401 && onUnauthorized) {
      try { onUnauthorized(); } catch { /* swallow — invalidator must never throw */ }
    }

    // EDA-10: Surface field-level errors and Hukm governance fields to the caller.
    // JORD-43: friendlyMessage() replaces raw `HTTP 500` etc. with a
    // localized string; backend-provided messages pass through.
    const err = new Error(friendlyMessage(res.status, json.message)) as ApiError;
    err.errors   = json.errors;
    err.status   = res.status;
    err.blockers = json.blockers;   // Hukm: fatal blockers that halted generation
    err.verdict  = json.verdict;    // Hukm: verdict when halted (always 'batil')
    throw err;
  }

  return json as T;
}
