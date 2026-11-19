import type { Metadata } from 'next';
import { AdminShell } from '@/features/admin/components/AdminShell';
import { OrdersAdminView } from '@/features/admin/components/OrdersAndPaymentsAdminView';

export const metadata: Metadata = {
  title: 'Orders Management',
};

export default function AdminOrdersPage() {
  return (
    <AdminShell
      requiredPermissions={['orders.view']}
      title="Orders Management"
      subtitle="Inspect order snapshots, transition fulfilment status, cancel orders, and export CSV"
    >
      <OrdersAdminView />
    </AdminShell>
  );
}

