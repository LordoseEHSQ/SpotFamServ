import { Routes, Route, Navigate } from 'react-router-dom';
import { LoginPage } from '../pages/LoginPage';
import { DashboardPage } from '../pages/DashboardPage';
import { ProfilesPage } from '../pages/ProfilesPage';
import { ProfileDetailPage } from '../pages/ProfileDetailPage';
import { SetupWizardPage } from '../features/setup-wizard/SetupWizardPage';
import { CardsPage } from '../pages/CardsPage';
import { ScanLogsPage } from '../pages/ScanLogsPage';
import { DevicesPage } from '../pages/DevicesPage';
import { ReadersPage } from '../pages/ReadersPage';
import { ActivityPage } from '../pages/ActivityPage';
import { SystemPage } from '../pages/SystemPage';

export function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/" element={<DashboardPage />} />
      <Route path="/profiles" element={<ProfilesPage />} />
      <Route path="/profiles/:profileId" element={<ProfileDetailPage />} />
      <Route path="/profiles/:profileId/setup" element={<SetupWizardPage />} />
      <Route path="/devices" element={<DevicesPage />} />
      <Route path="/readers" element={<ReadersPage />} />
      <Route path="/cards" element={<CardsPage />} />
      <Route path="/activity" element={<ActivityPage />} />
      <Route path="/scan-logs" element={<ScanLogsPage />} />
      <Route path="/system" element={<SystemPage />} />
      <Route path="/rules" element={<Navigate to="/" replace />} />
      <Route path="/setup" element={<Navigate to="/profiles" replace />} />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
