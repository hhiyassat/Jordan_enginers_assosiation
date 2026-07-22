/**
 * Route-to-title mapping.
 *
 * Returns an i18n key rather than the resolved string so <Header /> can
 * rerender the title when the language flips without any extra plumbing.
 * Extracted from App.tsx as part of JORD-25.
 */
export function pageTitleKeyFor(pathname: string): string {
  if (pathname === '/dashboard') return 'pageTitle.dashboard';
  if (pathname === '/services' || pathname.startsWith('/services/') || pathname.startsWith('/apply/')) return 'pageTitle.services';
  if (pathname.startsWith('/projects')) return 'pageTitle.projects';
  if (pathname.startsWith('/my-applications')) return 'pageTitle.myRequests';
  if (pathname.startsWith('/review')) return 'pageTitle.review';
  if (pathname === '/admin') return 'pageTitle.admin';
  if (pathname.startsWith('/admin/services/new')) return 'pageTitle.newService';
  if (pathname.startsWith('/admin/services')) return 'pageTitle.servicesAdmin';
  if (pathname.startsWith('/admin/integration')) return 'pageTitle.integration';
  return 'pageTitle.home';
}
