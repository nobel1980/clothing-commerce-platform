import type { Metadata } from 'next';
import { AdminShell } from '@/features/admin/components/AdminShell';
import { VariantsAdminView } from '@/features/admin/components/CatalogModulesView';

export const metadata: Metadata = {
  title: 'Variants & SKUs',
};

export default function AdminVariantsPage() {
  return (
    <AdminShell
      requiredPermissions={['catalog.view']}
      title="Variants & SKUs Management"
      subtitle="Manage SKUs, colors, sizes, and integer paisa prices across all products"
    >
      <VariantsAdminView />
    </AdminShell>
  );
}

