/**
 * SkipToContent — NFR-004
 *
 * Visually-hidden link that jumps to the main content region. Becomes
 * visible when focused (Tab from URL bar) so keyboard users can skip the
 * header/sidebar. The target element must have id="main-content" and
 * ideally tabIndex={-1} so focus lands there after the jump.
 *
 * Include once per app, at the very top of the Layout children.
 */
export function SkipToContent() {
  return (
    <a
      href="#main-content"
      className="
        sr-only focus:not-sr-only
        focus:fixed focus:top-2 focus:right-2 focus:z-[100]
        focus:px-4 focus:py-2 focus:rounded-lg
        focus:bg-jea-primary focus:text-white focus:font-bold focus:text-sm
        focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-white
      "
    >
      تخطي إلى المحتوى · Skip to content
    </a>
  );
}
