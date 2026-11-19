import type { Metadata } from 'next';
import { AdminShell } from '@/features/admin/components/AdminShell';
import { ProductsAdminView } from '@/features/admin/components/CatalogModulesView';

export const metadata: Metadata = {
  title: 'Products Management',
};

export default function AdminProductsPage() {
  return (
    <AdminShell
      requiredPermissions={['catalog.view']}
      title="Products Management"
      subtitle="Search, filter, publish, create, update, and export catalog products"
    >
      <ProductsAdminView />
    </AdminShell>
  );
}

