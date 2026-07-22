import { describe, it, expect } from 'vitest';
import { errorMessage } from './errorMessage';

/**
 * JORD-77: safely extract a message from an unknown catch value.
 * The old `(e as Error).message` anti-pattern crashed silently
 * when the thrown value wasn't an Error (bare string, plain
 * object with `.message`, or nothing at all).
 */
describe('errorMessage', () => {
  it('returns the message of a real Error', () => {
    expect(errorMessage(new Error('boom'))).toBe('boom');
  });

  it('returns the message of an Error subclass', () => {
    class HttpError extends Error {}
    expect(errorMessage(new HttpError('http-500'))).toBe('http-500');
  });

  it('returns a bare string as-is', () => {
    expect(errorMessage('a string was thrown')).toBe('a string was thrown');
  });

  it('reads .message off a plain object', () => {
    expect(errorMessage({ message: 'from object' })).toBe('from object');
  });

  it('falls back to the default for empty messages', () => {
    expect(errorMessage(new Error(''))).toBe('حدث خطأ غير متوقع');
    expect(errorMessage('')).toBe('حدث خطأ غير متوقع');
    expect(errorMessage({ message: '' })).toBe('حدث خطأ غير متوقع');
  });

  it('honours the custom fallback', () => {
    expect(errorMessage(undefined, 'custom-default')).toBe('custom-default');
    expect(errorMessage(null,      'custom-default')).toBe('custom-default');
    expect(errorMessage(42,        'custom-default')).toBe('custom-default');
  });

  it('does NOT treat a non-string .message as valid', () => {
    // A rejection value with `message: { nested: 'x' }` shouldn't
    // stringify to "[object Object]" — the fallback should win.
    expect(errorMessage({ message: { nested: 'x' } }, 'safe')).toBe('safe');
  });
});
