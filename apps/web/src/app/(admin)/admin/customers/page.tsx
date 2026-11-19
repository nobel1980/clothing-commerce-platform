import type { Metadata } from 'next';
import { AdminShell } from '@/features/admin/components/AdminShell';
import { CustomersAdminView } from '@/features/admin/components/CustomersAndPromotionsAdminView';

export const metadata: Metadata = {
  title: 'Customers Directory',
};

export default function AdminCustomersPage() {
  return (
    <AdminShell
      requiredPermissions={['customers.view']}
      title="Customers Directory"
      subtitle="View customer accounts, order counts, lifetime spend, and account status"
    >
      <CustomersAdminView />
    </AdminShell>
  );
}

