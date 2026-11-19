import type { Metadata } from 'next';
import { AdminShell } from '@/features/admin/components/AdminShell';
import { ShippingAdminView } from '@/features/admin/components/ShippingAndReviewsAdminView';

export const metadata: Metadata = {
  title: 'Shipping & Couriers',
};

export default function AdminShippingPage() {
  return (
    <AdminShell
      requiredPermissions={['shipping.view']}
      title="Shipping & Courier Consignments"
      subtitle="Manage Pathao, Steadfast, and REDX consignments, live tracking, and COD reconciliation"
    >
      <ShippingAdminView />
    </AdminShell>
  );
}

