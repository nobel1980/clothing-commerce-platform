import type { Metadata } from 'next';
import { AdminShell } from '@/features/admin/components/AdminShell';
import { SettingsAdminView } from '@/features/admin/components/ReportsSettingsUsersAdminView';

export const metadata: Metadata = {
  title: 'Platform Settings',
};

export default function AdminSettingsPage() {
  return (
    <AdminShell
      requiredPermissions={['settings.view']}
      title="Platform Settings"
      subtitle="Configure store profile, BDT commerce defaults, shipping fees, and maintenance mode"
    >
      <SettingsAdminView />
    </AdminShell>
  );
}

