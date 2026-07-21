import {
  LayoutDashboard, Home, FileText, ShieldCheck, Settings, ClipboardList,
  PlusCircle, Zap, User as UserIcon, Building2, Gavel, Scale, ArrowRightLeft,
  DollarSign,
  type LucideIcon,
} from 'lucide-react';
import type { User } from '../types';

/**
 * Nav items per role — extracted from App.tsx (JORD-25).
 *
 * The label is stored as an i18n key so the sidebar can flip languages
 * without recomputing this list. Icons come from lucide-react and are
 * intentionally captured by-reference so the shape is stable.
 */
export interface NavItem {
  to: string;
  labelKey: string;
  Icon: LucideIcon;
}

export function navItemsForRole(role: User['role'] | undefined): NavItem[] {
  const items: NavItem[] = [];
  if (!role) return items;

  if (role === 'applicant') {
    items.push({ to: '/dashboard',       labelKey: 'nav.dashboard',    Icon: LayoutDashboard });
    items.push({ to: '/services',        labelKey: 'nav.services',     Icon: Home });
    items.push({ to: '/my-applications', labelKey: 'nav.myRequests',   Icon: FileText });
    // JORD-84: own dues + complaints + sanctions.
    items.push({ to: '/my-office',       labelKey: 'nav.myOffice',     Icon: Building2 });
  }
  if (role === 'staff' || role === 'auditor' || role === 'admin') {
    // JORD-88 (PM): reviewer dashboard lane; queue is a second entry
    // for the users who want the raw table.
    if (role === 'staff' || role === 'auditor') {
      items.push({ to: '/review/dashboard', labelKey: 'nav.reviewDashboard', Icon: LayoutDashboard });
    }
    items.push({ to: '/review/queue',    labelKey: 'nav.review',       Icon: ShieldCheck });
  }
  if (role === 'admin' || role === 'superuser') {
    items.push({ to: '/admin',                 labelKey: 'nav.admin',           Icon: Settings });
    items.push({ to: '/admin/services',        labelKey: 'nav.servicesAdmin',   Icon: ClipboardList });
    items.push({ to: '/admin/services/new',    labelKey: 'nav.newService',      Icon: PlusCircle });
    items.push({ to: '/admin/integration',     labelKey: 'nav.integration',     Icon: Zap });
    // Both admin and superuser get the user-management lane. The backend
    // decides which roles the actor can act on inside the page.
    items.push({ to: '/admin/users',           labelKey: 'nav.users',           Icon: UserIcon });
  }
  // JORD-77 / JORD-81 / JORD-82 / JORD-83: office picker + complaints
  // + legal fines + supervision transfers.
  // Admin-only (superuser scope is user-management, not quota / discipline).
  if (role === 'admin') {
    items.push({ to: '/admin/offices',                labelKey: 'nav.officesSettings',      Icon: Building2 });
    items.push({ to: '/admin/complaints',             labelKey: 'nav.complaints',           Icon: Gavel });
    items.push({ to: '/admin/legal-fines',            labelKey: 'nav.legalFines',           Icon: Scale });
    items.push({ to: '/admin/supervision-transfers',  labelKey: 'nav.supervisionTransfers', Icon: ArrowRightLeft });
    // JORD-85: admin fee editor for every service.
    items.push({ to: '/admin/service-fees',           labelKey: 'nav.serviceFees',          Icon: DollarSign });
  }
  return items;
}

/**
 * True when `to` is the "active" sidebar entry for the given pathname.
 * /services owns everything under services/apply/projects since they
 * all belong to the same top-level section.
 */
export function isActivePath(pathname: string, to: string): boolean {
  if (to === '/services') return pathname === '/services' || pathname.startsWith('/services/') || pathname.startsWith('/apply/') || pathname.startsWith('/projects');
  if (to === '/admin')    return pathname === '/admin';
  return pathname.startsWith(to);
}
