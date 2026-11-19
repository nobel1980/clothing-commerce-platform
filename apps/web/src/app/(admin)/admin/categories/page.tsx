import type { Metadata } from 'next';
import { AdminShell } from '@/features/admin/components/AdminShell';
import { CategoriesAdminView } from '@/features/admin/components/CatalogModulesView';

export const metadata: Metadata = {
  title: 'Categories Management',
};

export default function AdminCategoriesPage() {
  return (
    <AdminShell
      requiredPermissions={['catalog.view']}
      title="Categories Management"
      subtitle="Manage hierarchical product categories, visibility, and sort order"
    >
      <CategoriesAdminView />
    </AdminShell>
  );
}

