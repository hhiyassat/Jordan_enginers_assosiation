import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Captcha } from './Captcha';

/**
 * JORD-44 + JORD-45 regressions.
 *
 * • JORD-44: React StrictMode fires effects twice in dev. The captcha
 *   challenge is single-use — the second render was burning the fresh
 *   token before the user typed anything. The inFlight ref coalesces
 *   the pair to a single request per resetKey change.
 * • JORD-45: SVG comes from our own server today, but a compromised or
 *   proxy-poisoned response used to reach the DOM verbatim via
 *   dangerouslySetInnerHTML. DOMPurify with the SVG profile strips any
 *   <script>, event handler, or foreign HTML before we mount it.
 */

const onChange = vi.fn();

beforeEach(() => {
  onChange.mockReset();
  vi.restoreAllMocks();
});

function stubFetch(payload: unknown, delayMs = 0): ReturnType<typeof vi.fn> {
  const spy = vi.fn(async () =>
    new Response(JSON.stringify(payload), { status: 200, headers: { 'Content-Type': 'application/json' } })
  );
  const delayed = delayMs === 0
    ? spy
    : vi.fn(async () => { await new Promise(r => setTimeout(r, delayMs)); return spy(); });
  vi.stubGlobal('fetch', delayed);
  return delayed;
}

describe('Captcha — JORD-44 double-fire guard', () => {
  it('fires exactly one request even when the effect runs twice (StrictMode)', async () => {
    const fetchSpy = stubFetch({ id: 'A', svg: '<svg xmlns="http://www.w3.org/2000/svg"></svg>' }, 20);

    const { rerender } = render(<Captcha onChange={onChange} resetKey={1} />);
    // Force the SAME resetKey to re-run the load effect the way StrictMode does.
    rerender(<Captcha onChange={onChange} resetKey={1} />);

    await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(1));
  });

  it('does fetch again after resetKey bumps (e.g. failed submit)', async () => {
    const fetchSpy = stubFetch({ id: 'A', svg: '<svg xmlns="http://www.w3.org/2000/svg"></svg>' });
    const { rerender } = render(<Captcha onChange={onChange} resetKey={1} />);
    await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(1));
    rerender(<Captcha onChange={onChange} resetKey={2} />);
    await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(2));
  });
});

describe('Captcha — JORD-45 DOMPurify sanitization', () => {
  it('strips <script> tags from the server SVG before mounting', async () => {
    stubFetch({
      id: 'A',
      svg: '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><path d="M0 0L10 10"/></svg>',
    });
    render(<Captcha onChange={onChange} resetKey={1} />);
    // Wait for the render.
    await waitFor(() => expect(document.querySelector('svg')).toBeInTheDocument());
    // The <script> tag must not have made it into the DOM.
    expect(document.querySelector('script')).toBeNull();
    // Legitimate SVG primitives survive.
    expect(document.querySelector('path')).toBeInTheDocument();
  });

  it('strips inline event handlers from the SVG', async () => {
    stubFetch({
      id: 'B',
      svg: '<svg xmlns="http://www.w3.org/2000/svg"><rect onload="alert(1)" width="10" height="10"/></svg>',
    });
    render(<Captcha onChange={onChange} resetKey={1} />);
    await waitFor(() => expect(document.querySelector('rect')).toBeInTheDocument());
    // The onload attribute must have been sanitized away.
    expect(document.querySelector('rect')?.getAttributeNames()).not.toContain('onload');
  });
});

describe('Captcha — friendly error surface (JORD-43 regression)', () => {
  it('shows the localized Arabic 5xx message instead of raw "HTTP 500"', async () => {
    // Regression: previously Captcha called fetch() directly and threw
    // `new Error("HTTP ${res.status}")`, so a 500 surfaced verbatim.
    // Routing through the api client picks up friendlyMessage() which
    // returns "حدث خطأ في الخادم..." for any 5xx.
    vi.stubGlobal('fetch', vi.fn(async () => new Response(
      '', { status: 500, headers: { 'Content-Type': 'application/json' } }
    )));
    render(<Captcha onChange={onChange} resetKey={1} />);
    // The error paragraph carries the localized message; "HTTP 500"
    // must NOT appear anywhere in the widget.
    await waitFor(() => expect(screen.getByText(/حدث خطأ في الخادم/)).toBeInTheDocument());
    expect(screen.queryByText(/HTTP\s*500/)).toBeNull();
  });

  it('shows the localized 429 message when the throttle bucket is empty', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response(
      JSON.stringify({ message: '' }),
      { status: 429, headers: { 'Content-Type': 'application/json' } }
    )));
    render(<Captcha onChange={onChange} resetKey={1} />);
    // friendlyMessage() returns "عدد الطلبات كبير..." for 429.
    await waitFor(() => expect(screen.getByText(/عدد الطلبات كبير/)).toBeInTheDocument());
    expect(screen.queryByText(/HTTP\s*429/)).toBeNull();
  });
});

describe('Captcha — user typing propagates', () => {
  it('reports uppercased trimmed input to the parent', async () => {
    stubFetch({ id: 'A', svg: '<svg xmlns="http://www.w3.org/2000/svg"></svg>' });
    render(<Captcha onChange={onChange} resetKey={1} />);
    await waitFor(() => expect(onChange).toHaveBeenCalled());
    onChange.mockClear();
    const input = screen.getByPlaceholderText('ABC123');
    await userEvent.type(input, 'ab c123');
    // Final call should have the trimmed uppercased answer.
    const last = onChange.mock.calls.at(-1)![0];
    expect(last.answer).toBe('ABC123');
  });
});
