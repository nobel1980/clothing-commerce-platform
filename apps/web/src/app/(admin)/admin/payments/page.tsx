import type { Metadata } from 'next';
import { AdminShell } from '@/features/admin/components/AdminShell';
import { PaymentsAdminView } from '@/features/admin/components/OrdersAndPaymentsAdminView';

export const metadata: Metadata = {
  title: 'Payments & Refunds',
};

export default function AdminPaymentsPage() {
  return (
    <AdminShell
      requiredPermissions={['payments.view']}
      title="Payments & Refunds"
      subtitle="Monitor bKash, Nagad, SSLCommerz, and COD transactions and process verified refunds"
    >
      <PaymentsAdminView />
    </AdminShell>
  );
}

