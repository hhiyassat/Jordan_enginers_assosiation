/**
 * errorMessage — JORD-77
 *
 * Safely extract a human-readable message from an unknown catch value.
 * Replaces the `(e as Error).message` anti-pattern that treats every
 * thrown value as an Error — which is wrong when a Promise rejects
 * with a string, an object literal, or a non-Error class instance.
 *
 * Preference order:
 *   1. Real Error subclass → its .message
 *   2. Object with a string `message` field → that field
 *   3. Bare string → itself
 *   4. Anything else → the generic fallback
 */
export function errorMessage(err: unknown, fallback = 'حدث خطأ غير متوقع'): string {
  if (err instanceof Error) return err.message || fallback;
  if (typeof err === 'string') return err || fallback;
  if (err && typeof err === 'object' && 'message' in err) {
    const m = (err as { message: unknown }).message;
    if (typeof m === 'string' && m.length > 0) return m;
  }
  return fallback;
}
