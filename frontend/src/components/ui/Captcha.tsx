import React, { useCallback, useEffect, useId, useState } from 'react';
import { RefreshCw } from 'lucide-react';

/**
 * Captcha — text captcha wrapper for public forms
 *
 * Fetches a 6-char SVG challenge from GET /api/v1/captcha on mount, renders
 * it with a reload button, and exposes an input for the user's answer.
 * Reports {id, answer} to the parent via onChange so the parent can submit
 * them alongside the form (backend expects captcha_id + captcha_answer).
 *
 * The parent is responsible for calling `reload()` after a failed submit —
 * the backend consumes the challenge on any verify attempt (single-use).
 * The `resetKey` prop is a convenience: bump it and the widget auto-reloads.
 */
interface CaptchaChallenge {
  id: string;
  svg: string;
}

interface CaptchaProps {
  /** Called whenever id or answer changes so parent can attach to form. */
  onChange: (data: { id: string; answer: string }) => void;
  /** Bump this to force a fresh challenge (e.g., after a failed submit). */
  resetKey?: number;
  /** Optional server error text shown under the input. */
  error?: string;
}

export function Captcha({ onChange, resetKey, error }: CaptchaProps) {
  const rawId  = useId();
  const inputId = `captcha-${rawId}`;
  const errId   = error ? `${inputId}-error` : undefined;

  const [challenge, setChallenge] = useState<CaptchaChallenge | null>(null);
  const [answer,    setAnswer]    = useState('');
  const [loading,   setLoading]   = useState(false);
  const [loadErr,   setLoadErr]   = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setLoadErr('');
    setAnswer('');
    try {
      const res = await fetch('/api/v1/captcha', { headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = (await res.json()) as CaptchaChallenge;
      setChallenge(data);
      onChange({ id: data.id, answer: '' });
    } catch (e: unknown) {
      setLoadErr((e as Error).message || 'تعذّر تحميل رمز التحقق');
    } finally {
      setLoading(false);
    }
  }, [onChange]);

  // Initial load + on resetKey bump
  useEffect(() => { load(); /* eslint-disable-next-line react-hooks/exhaustive-deps */ }, [resetKey]);

  const onInput = (v: string) => {
    // Uppercase + strip whitespace; server compares case-insensitively but
    // showing the user their input in the canonical form reduces confusion.
    const clean = v.toUpperCase().replace(/\s/g, '').slice(0, 6);
    setAnswer(clean);
    if (challenge) onChange({ id: challenge.id, answer: clean });
  };

  return (
    <div>
      <label htmlFor={inputId} className="block text-sm font-bold text-jea-text mb-1.5">
        <span lang="ar">رمز التحقق</span>
        <span className="text-jea-muted font-normal text-xs" lang="en" dir="ltr"> · Captcha</span>
        <span className="text-jea-danger mx-1" aria-hidden="true">*</span>
      </label>

      <div className="flex items-center gap-2" dir="ltr">
        {/* SVG image */}
        <div
          className="rounded-lg border border-jea-border bg-jea-bg overflow-hidden shrink-0"
          style={{ width: 180, height: 60 }}
          aria-label="captcha challenge image"
          role="img"
          // The endpoint returns SVG as a string. Rendering it via
          // dangerouslySetInnerHTML is safe here because it's our own
          // server response (not user content) and the SVG has no
          // <script>/foreign elements.
          dangerouslySetInnerHTML={challenge ? { __html: challenge.svg } : undefined}
        >
          {!challenge && !loadErr && (
            <span className="sr-only">Loading…</span>
          )}
        </div>

        {/* Reload button */}
        <button
          type="button"
          onClick={load}
          disabled={loading}
          aria-label="تحديث رمز التحقق"
          className="p-2 rounded-lg text-jea-primary hover:bg-jea-accent transition-colors disabled:opacity-60 focus:outline-none focus-visible:ring-2 focus-visible:ring-jea-primary/40"
        >
          <RefreshCw size={16} className={loading ? 'animate-spin' : ''} aria-hidden="true" />
        </button>
      </div>

      {loadErr && (
        <p role="alert" className="text-xs text-jea-danger mt-1">{loadErr}</p>
      )}

      <input
        id={inputId}
        type="text"
        value={answer}
        onChange={e => onInput(e.target.value)}
        placeholder="ABC123"
        autoComplete="off"
        inputMode="text"
        maxLength={6}
        aria-required
        aria-invalid={error ? true : undefined}
        aria-describedby={errId}
        className={[
          'mt-2 w-full border rounded-xl px-4 py-3 text-sm outline-none bg-white transition-all',
          'placeholder:text-[#A0BCCC] font-mono tracking-widest text-center uppercase',
          'focus:border-jea-primary focus:ring-2 focus:ring-jea-primary/20',
          error ? 'border-jea-danger' : 'border-jea-border',
        ].join(' ')}
      />

      {error && (
        <p id={errId} role="alert" className="text-xs text-jea-danger mt-1">{error}</p>
      )}
    </div>
  );
}
