import React from 'react';
import { Route } from 'react-router-dom';
import { Layout } from '../../layout/Layout';
import { RequireApplicant } from './guards';

const ServiceList          = React.lazy(() => import('../../modules/JeaServices/pages/ServiceList').then(m => ({ default: m.ServiceList })));
const CategoryServicesView = React.lazy(() => import('../../modules/JeaServices/pages/CategoryServicesView').then(m => ({ default: m.CategoryServicesView })));
const Dashboard            = React.lazy(() => import('../../modules/JeaServices/pages/Dashboard').then(m => ({ default: m.Dashboard })));
const Apply                = React.lazy(() => import('../../modules/JeaServices/pages/Apply').then(m => ({ default: m.Apply })));
const MyApplications       = React.lazy(() => import('../../modules/JeaServices/pages/MyApplications').then(m => ({ default: m.MyApplications })));
const ApplicationDetail    = React.lazy(() => import('../../modules/JeaServices/pages/ApplicationDetail').then(m => ({ default: m.ApplicationDetail })));
const ProjectsList         = React.lazy(() => import('../../modules/JeaProjects/pages/ProjectsList').then(m => ({ default: m.ProjectsList })));
const ProjectDetail        = React.lazy(() => import('../../modules/JeaProjects/pages/ProjectDetail').then(m => ({ default: m.ProjectDetail })));
const MyOffice             = React.lazy(() => import('../../modules/JeaProjects/pages/MyOffice').then(m => ({ default: m.MyOffice })));

/**
 * Applicant route elements — exported as an array so they can be spread
 * directly inside <Routes>. React Router v6 does NOT allow custom component
 * wrappers as direct children of <Routes>.
 */
export const applicantRoutes = [
  <Route key="dashboard"    path="/dashboard"              element={<RequireApplicant><Layout><Dashboard /></Layout></RequireApplicant>} />,
  <Route key="services"     path="/services"               element={<RequireApplicant><Layout><ServiceList /></Layout></RequireApplicant>} />,
  <Route key="servicesCat"  path="/services/:categoryCode" element={<RequireApplicant><Layout><CategoryServicesView /></Layout></RequireApplicant>} />,
  <Route key="projects"     path="/projects"               element={<RequireApplicant><Layout><ProjectsList /></Layout></RequireApplicant>} />,
  <Route key="projectDet"   path="/projects/:projectId"    element={<RequireApplicant><Layout><ProjectDetail /></Layout></RequireApplicant>} />,
  <Route key="apply"        path="/apply/:serviceCode"     element={<RequireApplicant><Layout><Apply /></Layout></RequireApplicant>} />,
  <Route key="myApps"       path="/my-applications"        element={<RequireApplicant><Layout><MyApplications /></Layout></RequireApplicant>} />,
  <Route key="appDetail"    path="/applications/:id"       element={<RequireApplicant><Layout><ApplicationDetail /></Layout></RequireApplicant>} />,
  <Route key="myOffice"     path="/my-office"              element={<RequireApplicant><Layout><MyOffice /></Layout></RequireApplicant>} />,
];
