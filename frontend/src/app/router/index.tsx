import React from 'react';
import { Navigate, Routes, Route } from 'react-router-dom';
import { Layout } from '../../layout/Layout';
import { RequireAuth, RequireGuest, HomeRedirect } from './guards';
import { adminRoutes } from './AdminRoutes';
import { applicantRoutes } from './ApplicantRoutes';
import { reviewerRoutes } from './ReviewerRoutes';

const LoginPage         = React.lazy(() => import('../../auth/LoginPage').then(m => ({ default: m.LoginPage })));
const ChangeCredentials = React.lazy(() => import('../../platform/pages/auth/ChangeCredentials').then(m => ({ default: m.ChangeCredentials })));
const Profile           = React.lazy(() => import('../../platform/pages/auth/Profile').then(m => ({ default: m.Profile })));

/**
 * AppRoutes — root route table (Task 2 FSD migration).
 *
 * Sub-routes are exported as Route element arrays (NOT as components) because
 * React Router v6 requires that all direct children of <Routes> are <Route>
 * or <React.Fragment> — custom component wrappers are not allowed.
 */
export function AppRoutes(): JSX.Element {
  return (
    <Routes>
      <Route path="/login" element={<RequireGuest><LoginPage /></RequireGuest>} />

      {/* First-login credential change — rendered without Layout */}
      <Route path="/auth/change-credentials" element={<RequireAuth><ChangeCredentials /></RequireAuth>} />
      <Route path="/profile"                 element={<RequireAuth><Layout><Profile /></Layout></RequireAuth>} />

      <Route path="/" element={<RequireAuth><Layout><HomeRedirect /></Layout></RequireAuth>} />

      {/* Spread route arrays — valid children of <Routes> */}
      {applicantRoutes}
      {reviewerRoutes}
      {adminRoutes}

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
