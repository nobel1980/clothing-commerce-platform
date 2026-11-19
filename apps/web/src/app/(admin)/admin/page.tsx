import type { Metadata } from 'next';
import { AdminShell } from '@/features/admin/components/AdminShell';
import { DashboardOverviewView } from '@/features/admin/components/DashboardOverviewView';

export const metadata: Metadata = {
  title: 'Dashboard Overview',
};

export default function AdminDashboardPage() {
  return (
    <AdminShell
      requiredPermissions={['reports.view', 'orders.view']}
      title="Dashboard Overview"
      subtitle="Real-time sales, revenue, order volume, low-stock alerts, and best-selling products"
    >
      <DashboardOverviewView />
    </AdminShell>
  );
}

