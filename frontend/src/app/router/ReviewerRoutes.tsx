import React from 'react';
import { Route } from 'react-router-dom';
import { Layout } from '../../layout/Layout';
import { RequireReviewer } from './guards';

const ReviewQueue     = React.lazy(() => import('../../modules/JeaServices/pages/reviewer/ReviewQueue').then(m => ({ default: m.ReviewQueue })));
const ReviewPanel     = React.lazy(() => import('../../modules/JeaServices/pages/reviewer/ReviewPanel').then(m => ({ default: m.ReviewPanel })));
const ReviewDashboard = React.lazy(() => import('../../modules/JeaServices/pages/reviewer/ReviewDashboard').then(m => ({ default: m.ReviewDashboard })));

/**
 * Reviewer route elements — exported as an array so they can be spread
 * directly inside <Routes>.
 */
export const reviewerRoutes = [
  <Route key="reviewDash"  path="/review/dashboard" element={<RequireReviewer><Layout><ReviewDashboard /></Layout></RequireReviewer>} />,
  <Route key="reviewQueue" path="/review/queue"     element={<RequireReviewer><Layout><ReviewQueue /></Layout></RequireReviewer>} />,
  <Route key="reviewPanel" path="/review/:id"       element={<RequireReviewer><Layout><ReviewPanel /></Layout></RequireReviewer>} />,
];
