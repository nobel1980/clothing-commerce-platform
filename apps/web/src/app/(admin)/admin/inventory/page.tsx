import type { Metadata } from 'next';
import { AdminShell } from '@/features/admin/components/AdminShell';
import { InventoryAdminView } from '@/features/admin/components/InventoryAdminView';

export const metadata: Metadata = {
  title: 'Inventory & Warehouses',
};

export default function AdminInventoryPage() {
  return (
    <AdminShell
      requiredPermissions={['inventory.view']}
      title="Inventory & Warehouse Stock"
      subtitle="Monitor stock balances, low-stock alerts, stock adjustments, and movement ledger"
    >
      <InventoryAdminView />
    </AdminShell>
  );
}

