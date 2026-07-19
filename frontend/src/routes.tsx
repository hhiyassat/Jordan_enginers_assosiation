import React from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import {
  RequireAuth, RequireGuest, RequireAdmin, RequireReviewer,
  RequireApplicant, RequireUserManager, HomeRedirect,
} from './auth/guards';
import { LoginPage } from './auth/LoginPage';
import { Layout } from './layout/Layout';

/**
 * Route table — extracted from App.tsx (JORD-25).
 *
 * Every route used to live in the same JS chunk (JORD-32). Lazy imports
 * split each page onto its own bundle so an applicant no longer
 * downloads the whole admin + reviewer surface on first load. Pages are
 * named exports — the tiny shim re-shapes each module as { default }
 * so React.lazy is happy.
 */
const ServiceList             = React.lazy(() => import('./pages/applicant/ServiceList').then(m => ({ default: m.ServiceList })));
const CategoryServicesView    = React.lazy(() => import('./pages/applicant/CategoryServicesView').then(m => ({ default: m.CategoryServicesView })));
const ProjectsList            = React.lazy(() => import('./pages/applicant/ProjectsList').then(m => ({ default: m.ProjectsList })));
const ProjectDetail           = React.lazy(() => import('./pages/applicant/ProjectDetail').then(m => ({ default: m.ProjectDetail })));
const Dashboard               = React.lazy(() => import('./pages/applicant/Dashboard').then(m => ({ default: m.Dashboard })));
const Apply                   = React.lazy(() => import('./pages/applicant/Apply').then(m => ({ default: m.Apply })));
const MyApplications          = React.lazy(() => import('./pages/applicant/MyApplications').then(m => ({ default: m.MyApplications })));
const ReviewQueue             = React.lazy(() => import('./pages/reviewer/ReviewQueue').then(m => ({ default: m.ReviewQueue })));
const ReviewPanel             = React.lazy(() => import('./pages/reviewer/ReviewPanel').then(m => ({ default: m.ReviewPanel })));
const AdminDashboard          = React.lazy(() => import('./pages/admin/AdminDashboard').then(m => ({ default: m.AdminDashboard })));
const AdminApplications       = React.lazy(() => import('./pages/admin/AdminApplications').then(m => ({ default: m.AdminApplications })));
const IntegrationCycles       = React.lazy(() => import('./pages/admin/IntegrationCycles').then(m => ({ default: m.IntegrationCycles })));
const IntegrationCycleDetail  = React.lazy(() => import('./pages/admin/IntegrationCycleDetail').then(m => ({ default: m.IntegrationCycleDetail })));
const NewService              = React.lazy(() => import('./pages/admin/NewService').then(m => ({ default: m.NewService })));
const ServicesList            = React.lazy(() => import('./pages/admin/ServicesList').then(m => ({ default: m.ServicesList })));
const EditService             = React.lazy(() => import('./pages/admin/EditService').then(m => ({ default: m.EditService })));
const UserManagement          = React.lazy(() => import('./pages/admin/UserManagement').then(m => ({ default: m.UserManagement })));
const ChangeCredentials       = React.lazy(() => import('./pages/auth/ChangeCredentials').then(m => ({ default: m.ChangeCredentials })));
const Profile                 = React.lazy(() => import('./pages/auth/Profile').then(m => ({ default: m.Profile })));

export function AppRoutes(): JSX.Element {
  return (
    <Routes>
      <Route path="/login" element={<RequireGuest><LoginPage /></RequireGuest>} />

      {/* First-login credential change — reachable by an authenticated user
          carrying the must_change_password flag. Rendered without Layout so
          the sidebar/nav don't leak features they can't use yet. */}
      <Route path="/auth/change-credentials" element={<RequireAuth><ChangeCredentials /></RequireAuth>} />
      <Route path="/profile"                 element={<RequireAuth><Layout><Profile /></Layout></RequireAuth>} />

      <Route path="/" element={<RequireAuth><Layout><HomeRedirect /></Layout></RequireAuth>} />

      {/* Applicant-only */}
      <Route path="/dashboard"               element={<RequireApplicant><Layout><Dashboard /></Layout></RequireApplicant>} />
      <Route path="/services"                element={<RequireApplicant><Layout><ServiceList /></Layout></RequireApplicant>} />
      <Route path="/services/:categoryCode"  element={<RequireApplicant><Layout><CategoryServicesView /></Layout></RequireApplicant>} />
      <Route path="/projects"                element={<RequireApplicant><Layout><ProjectsList /></Layout></RequireApplicant>} />
      <Route path="/projects/:projectId"     element={<RequireApplicant><Layout><ProjectDetail /></Layout></RequireApplicant>} />
      <Route path="/apply/:serviceCode"      element={<RequireApplicant><Layout><Apply /></Layout></RequireApplicant>} />
      <Route path="/my-applications"         element={<RequireApplicant><Layout><MyApplications /></Layout></RequireApplicant>} />

      {/* Reviewer — staff / auditor / admin. Applicants navigating here
          used to see the page render followed by a backend 403; now the
          SPA redirects them to / before the page mounts. */}
      <Route path="/review/queue"      element={<RequireReviewer><Layout><ReviewQueue /></Layout></RequireReviewer>} />
      <Route path="/review/:id"        element={<RequireReviewer><Layout><ReviewPanel /></Layout></RequireReviewer>} />

      {/* Admin — every /admin/* route requires admin or superuser. */}
      <Route path="/admin"                      element={<RequireAdmin><Layout><AdminDashboard /></Layout></RequireAdmin>} />
      <Route path="/admin/applications"         element={<RequireAdmin><Layout><AdminApplications /></Layout></RequireAdmin>} />
      <Route path="/admin/services"             element={<RequireAdmin><Layout><ServicesList /></Layout></RequireAdmin>} />
      <Route path="/admin/services/new"         element={<RequireAdmin><Layout><NewService /></Layout></RequireAdmin>} />
      <Route path="/admin/services/:id/edit"    element={<RequireAdmin><Layout><EditService /></Layout></RequireAdmin>} />
      <Route path="/admin/integration"          element={<RequireAdmin><Layout><IntegrationCycles /></Layout></RequireAdmin>} />
      <Route path="/admin/integration/:id"      element={<RequireAdmin><Layout><IntegrationCycleDetail /></Layout></RequireAdmin>} />

      {/* User management — admin + superuser */}
      <Route path="/admin/users" element={<RequireUserManager><Layout><UserManagement /></Layout></RequireUserManager>} />

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
