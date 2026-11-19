import type { Metadata } from 'next';
import { AdminShell } from '@/features/admin/components/AdminShell';
import { ReportsAdminView } from '@/features/admin/components/ReportsSettingsUsersAdminView';

export const metadata: Metadata = {
  title: 'Analytics & Reports',
};

export default function AdminReportsPage() {
  return (
    <AdminShell
      requiredPermissions={['reports.view']}
      title="Sales & Operational Reports"
      subtitle="Analyze daily sales series, payment gateway breakdown, best sellers, and stock health"
    >
      <ReportsAdminView />
    </AdminShell>
  );
}

