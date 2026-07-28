import React from 'react';
import { Route } from 'react-router-dom';
import { Layout } from '../../layout/Layout';
import { RequireAdmin, RequireUserManager } from './guards';

const AdminDashboard          = React.lazy(() => import('../../modules/JeaServices/pages/AdminDashboard').then(m => ({ default: m.AdminDashboard })));
const AdminApplications       = React.lazy(() => import('../../modules/JeaServices/pages/AdminApplications').then(m => ({ default: m.AdminApplications })));
const ServicesList            = React.lazy(() => import('../../modules/JeaServices/pages/ServicesList').then(m => ({ default: m.ServicesList })));
const NewService              = React.lazy(() => import('../../modules/JeaServices/pages/NewService').then(m => ({ default: m.NewService })));
const EditService             = React.lazy(() => import('../../modules/JeaServices/pages/EditService').then(m => ({ default: m.EditService })));
const UserManagement          = React.lazy(() => import('../../platform/pages/admin/UserManagement').then(m => ({ default: m.UserManagement })));
const ServiceFeesAdmin        = React.lazy(() => import('../../modules/JeaServices/pages/ServiceFeesAdmin').then(m => ({ default: m.ServiceFeesAdmin })));
const ComplaintsAdmin         = React.lazy(() => import('../../modules/JeaDiscipline/pages/ComplaintsAdmin').then(m => ({ default: m.ComplaintsAdmin })));
const LegalFinesAdmin         = React.lazy(() => import('../../modules/JeaDiscipline/pages/LegalFinesAdmin').then(m => ({ default: m.LegalFinesAdmin })));
const SupervisionTransfersAdmin = React.lazy(() => import('../../modules/JeaDiscipline/pages/SupervisionTransfersAdmin').then(m => ({ default: m.SupervisionTransfersAdmin })));
const IntegrationCycles       = React.lazy(() => import('../../integrations/Nashmi/pages/IntegrationCycles').then(m => ({ default: m.IntegrationCycles })));
const IntegrationCycleDetail  = React.lazy(() => import('../../integrations/Nashmi/pages/IntegrationCycleDetail').then(m => ({ default: m.IntegrationCycleDetail })));
const OfficesList             = React.lazy(() => import('../../modules/JeaProjects/pages/OfficesList').then(m => ({ default: m.OfficesList })));
const OfficeSettings          = React.lazy(() => import('../../modules/JeaProjects/pages/OfficeSettings').then(m => ({ default: m.OfficeSettings })));
const OfficeDues              = React.lazy(() => import('../../modules/JeaDues/pages/OfficeDues').then(m => ({ default: m.OfficeDues })));

/**
 * Admin route elements — exported as an array so they can be spread
 * directly inside <Routes>.
 */
export const adminRoutes = [
  <Route key="admin"              path="/admin"                       element={<RequireAdmin><Layout><AdminDashboard /></Layout></RequireAdmin>} />,
  <Route key="adminApps"         path="/admin/applications"          element={<RequireAdmin><Layout><AdminApplications /></Layout></RequireAdmin>} />,
  <Route key="adminServices"     path="/admin/services"              element={<RequireAdmin><Layout><ServicesList /></Layout></RequireAdmin>} />,
  <Route key="adminSvcNew"       path="/admin/services/new"          element={<RequireAdmin><Layout><NewService /></Layout></RequireAdmin>} />,
  <Route key="adminSvcEdit"      path="/admin/services/:id/edit"     element={<RequireAdmin><Layout><EditService /></Layout></RequireAdmin>} />,
  <Route key="adminIntegration"  path="/admin/integration"           element={<RequireAdmin><Layout><IntegrationCycles /></Layout></RequireAdmin>} />,
  <Route key="adminIntegDet"     path="/admin/integration/:id"       element={<RequireAdmin><Layout><IntegrationCycleDetail /></Layout></RequireAdmin>} />,
  <Route key="adminOffices"      path="/admin/offices"               element={<RequireAdmin><Layout><OfficesList /></Layout></RequireAdmin>} />,
  <Route key="adminOfficeId"     path="/admin/offices/:id"           element={<RequireAdmin><Layout><OfficeSettings /></Layout></RequireAdmin>} />,
  <Route key="adminOfficeDues"   path="/admin/offices/:id/dues"      element={<RequireAdmin><Layout><OfficeDues /></Layout></RequireAdmin>} />,
  <Route key="adminComplaints"   path="/admin/complaints"            element={<RequireAdmin><Layout><ComplaintsAdmin /></Layout></RequireAdmin>} />,
  <Route key="adminFines"        path="/admin/legal-fines"           element={<RequireAdmin><Layout><LegalFinesAdmin /></Layout></RequireAdmin>} />,
  <Route key="adminSupervision"  path="/admin/supervision-transfers" element={<RequireAdmin><Layout><SupervisionTransfersAdmin /></Layout></RequireAdmin>} />,
  <Route key="adminFees"         path="/admin/service-fees"          element={<RequireAdmin><Layout><ServiceFeesAdmin /></Layout></RequireAdmin>} />,
  <Route key="adminUsers"        path="/admin/users"                 element={<RequireUserManager><Layout><UserManagement /></Layout></RequireUserManager>} />,
];
