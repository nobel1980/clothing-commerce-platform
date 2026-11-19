import type { Metadata } from 'next';
import { AdminShell } from '@/features/admin/components/AdminShell';
import { UsersAndPermissionsAdminView } from '@/features/admin/components/ReportsSettingsUsersAdminView';

export const metadata: Metadata = {
  title: 'Users, Roles & Audit Trail',
};

export default function AdminUsersPage() {
  return (
    <AdminShell
      requiredPermissions={['users.view', 'roles.view', 'audit.view']}
      title="Users, Permissions & Audit Trail"
      subtitle="Manage staff accounts, Spatie RBAC permissions matrix, and immutable Audit History"
    >
      <UsersAndPermissionsAdminView />
    </AdminShell>
  );
}

