import type { Metadata } from 'next';
import { AdminShell } from '@/features/admin/components/AdminShell';
import { PromotionsAdminView } from '@/features/admin/components/CustomersAndPromotionsAdminView';

export const metadata: Metadata = {
  title: 'Promotions & Coupons',
};

export default function AdminPromotionsPage() {
  return (
    <AdminShell
      requiredPermissions={['promotions.view']}
      title="Promotions & Coupons"
      subtitle="Create and manage promotional discount codes, usage limits, and redemptions"
    >
      <PromotionsAdminView />
    </AdminShell>
  );
}

