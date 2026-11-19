'use client';

import * as React from 'react';
import type {
  AdminAuditLogRecord,
  AdminComprehensiveReport,
  AdminPlatformSetting,
  AdminRoleRecord,
  AdminStaffUser,
  PaginationMeta,
} from '@clothing-commerce/types';
import {
  createAdminUser,
  deleteAdminUser,
  downloadAdminCsvExport,
  fetchAdminAuditLogs,
  fetchAdminPermissions,
  fetchAdminReportOverview,
  fetchAdminRoles,
  fetchAdminSettings,
  fetchAdminUsers,
  toggleAdminUserStatus,
  updateAdminRolePermissions,
  updateAdminSettings,
} from '@/features/admin/api/adminApi';
import { useAdminAuth } from '@/features/admin/hooks/useAdminAuth';
import {
  AdminDataToolbar,
  AdminPaginationBar,
  ConfirmDialog,
} from '@/features/admin/components/AdminDataToolbar';
import { formatPrice } from '@/lib/utils/format';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

// ─── 1. Reports Module View ──────────────────────────────────────────────────

export function ReportsAdminView({
  initialReport = null,
}: {
  initialReport?: AdminComprehensiveReport | null;
}) {
  const [report, setReport] = React.useState<AdminComprehensiveReport | null>(
    initialReport
  );
  const [fromDate, setFromDate] = React.useState('');
  const [toDate, setToDate] = React.useState('');

  const loadReport = React.useCallback(async () => {
    try {
      const res = await fetchAdminReportOverview({
        from_date: fromDate || undefined,
        to_date: toDate || undefined,
      });
      setReport(res.data);
    } catch {
      // Handled gracefully
    }
  }, [fromDate, toDate]);

  React.useEffect(() => {
    if (!initialReport) {
      void loadReport();
    }
  }, [initialReport, loadReport]);

  const metrics = report?.metrics;
  const currency = metrics?.currency || 'BDT';

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-neutral-200 bg-white p-4 shadow-2xs">
        <div className="flex flex-wrap items-center gap-3">
          <Input
            type="date"
            aria-label="Report From Date"
            value={fromDate}
            onChange={(e) => setFromDate(e.target.value)}
            className="h-9 w-40 text-xs"
          />
          <Input
            type="date"
            aria-label="Report To Date"
            value={toDate}
            onChange={(e) => setToDate(e.target.value)}
            className="h-9 w-40 text-xs"
          />
          <Button type="button" size="sm" onClick={() => void loadReport()}>
            Generate Report
          </Button>
        </div>

        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => void downloadAdminCsvExport('reports-sales')}
        >
          Export Sales Report CSV
        </Button>
      </div>

      {metrics && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
          <div className="rounded-xl border border-neutral-200 bg-white p-4">
            <p className="text-xs text-neutral-500">Gross Sales</p>
            <p className="mt-1 text-lg font-bold text-neutral-900">
              {formatPrice({ amount: metrics.total_sales, currency })}
            </p>
          </div>
          <div className="rounded-xl border border-neutral-200 bg-white p-4">
            <p className="text-xs text-neutral-500">Settled Revenue</p>
            <p className="mt-1 text-lg font-bold text-neutral-900">
              {formatPrice({ amount: metrics.revenue, currency })}
            </p>
          </div>
          <div className="rounded-xl border border-neutral-200 bg-white p-4">
            <p className="text-xs text-neutral-500">Average Order Value</p>
            <p className="mt-1 text-lg font-bold text-neutral-900">
              {formatPrice({ amount: metrics.average_order_value, currency })}
            </p>
          </div>
          <div className="rounded-xl border border-neutral-200 bg-white p-4">
            <p className="text-xs text-neutral-500">Low-Stock Alerts</p>
            <p className="mt-1 text-lg font-bold text-red-600">
              {metrics.low_stock_count} Variants
            </p>
          </div>
        </div>
      )}

      {/* Daily Sales Trend & Payment Breakdown */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section className="rounded-xl border border-neutral-200 bg-white p-5 shadow-2xs">
          <h3 className="text-sm font-bold text-neutral-900">
            Daily Sales & Revenue Series
          </h3>
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead>
                <tr className="border-b border-neutral-200 text-[11px] uppercase tracking-wider text-neutral-500">
                  <th className="py-2 pr-3">Date</th>
                  <th className="px-3 py-2 text-right">Orders</th>
                  <th className="px-3 py-2 text-right">Gross Sales</th>
                  <th className="pl-3 py-2 text-right">Revenue</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {(report?.daily_sales ?? []).length === 0 ? (
                  <tr>
                    <td colSpan={4} className="py-6 text-center text-neutral-500">
                      No daily sales recorded in this date range.
                    </td>
                  </tr>
                ) : (
                  (report?.daily_sales ?? []).map((day) => (
                    <tr key={day.date}>
                      <td className="py-2 pr-3 font-mono font-medium">{day.date}</td>
                      <td className="px-3 py-2 text-right">{day.orders_count}</td>
                      <td className="px-3 py-2 text-right font-semibold">
                        {formatPrice({ amount: day.total_sales, currency })}
                      </td>
                      <td className="pl-3 py-2 text-right font-semibold text-emerald-700">
                        {formatPrice({ amount: day.revenue, currency })}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </section>

        <section className="rounded-xl border border-neutral-200 bg-white p-5 shadow-2xs">
          <h3 className="text-sm font-bold text-neutral-900">
            Payment Method Breakdown
          </h3>
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead>
                <tr className="border-b border-neutral-200 text-[11px] uppercase tracking-wider text-neutral-500">
                  <th className="py-2 pr-3">Payment Method</th>
                  <th className="px-3 py-2 text-right">Orders</th>
                  <th className="pl-3 py-2 text-right">Volume</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {(report?.payment_method_breakdown ?? []).length === 0 ? (
                  <tr>
                    <td colSpan={3} className="py-6 text-center text-neutral-500">
                      No payment volume recorded yet.
                    </td>
                  </tr>
                ) : (
                  (report?.payment_method_breakdown ?? []).map((pm) => (
                    <tr key={pm.payment_method}>
                      <td className="py-2 pr-3 font-semibold uppercase">
                        {pm.payment_method}
                      </td>
                      <td className="px-3 py-2 text-right">{pm.orders_count}</td>
                      <td className="pl-3 py-2 text-right font-semibold">
                        {formatPrice({ amount: pm.total_amount, currency })}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </div>
  );
}

// ─── 2. Settings Module View ─────────────────────────────────────────────────

export function SettingsAdminView({
  initialSettings = [],
}: {
  initialSettings?: AdminPlatformSetting[];
}) {
  const { hasPermission } = useAdminAuth();
  const canManage = hasPermission('settings.manage');

  const [settings, setSettings] =
    React.useState<AdminPlatformSetting[]>(initialSettings);
  const [draftValues, setDraftValues] = React.useState<
    Record<string, string | number | boolean>
  >(() => {
    const map: Record<string, string | number | boolean> = {};
    for (const s of initialSettings) {
      if (typeof s.value === 'boolean' || typeof s.value === 'number' || typeof s.value === 'string') {
        map[s.key] = s.value;
      }
    }
    return map;
  });
  const [confirmSave, setConfirmSave] = React.useState(false);
  const [savedBanner, setSavedBanner] = React.useState<string | null>(null);

  const loadSettings = React.useCallback(async () => {
    try {
      const res = await fetchAdminSettings();
      setSettings(res.data);
      const map: Record<string, string | number | boolean> = {};
      for (const s of res.data) {
        if (
          typeof s.value === 'boolean' ||
          typeof s.value === 'number' ||
          typeof s.value === 'string'
        ) {
          map[s.key] = s.value;
        }
      }
      setDraftValues(map);
    } catch {
      // Handled gracefully
    }
  }, []);

  React.useEffect(() => {
    if (initialSettings.length === 0) {
      void loadSettings();
    }
  }, [initialSettings.length, loadSettings]);

  const handleConfirmSave = async () => {
    const payload = settings.map((s) => ({
      key: s.key,
      group: s.group,
      type: s.type,
      label: s.label,
      value: (draftValues[s.key] !== undefined ? draftValues[s.key] : s.value) ?? null,
    }));

    const res = await updateAdminSettings(payload);
    setSettings(res.data);
    setConfirmSave(false);
    setSavedBanner('Platform settings updated and recorded in Audit Trail.');
  };

  return (
    <div className="space-y-4">
      {savedBanner && (
        <div
          role="status"
          className="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs font-medium text-emerald-800"
        >
          {savedBanner}
        </div>
      )}

      <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-2xs">
        <div className="flex items-center justify-between border-b border-neutral-100 pb-4">
          <div>
            <h2 className="text-base font-bold text-neutral-900">
              Platform Configuration & Commerce Rules
            </h2>
            <p className="text-xs text-neutral-500">
              Changes are persisted in the database and logged in the Audit Trail.
            </p>
          </div>
          {canManage && (
            <Button type="button" size="sm" onClick={() => setConfirmSave(true)}>
              Save Settings
            </Button>
          )}
        </div>

        <div className="mt-4 divide-y divide-neutral-100">
          {settings.map((setting) => {
            const currentVal = draftValues[setting.key] ?? setting.value;

            return (
              <div
                key={setting.key}
                className="flex flex-col justify-between gap-3 py-4 sm:flex-row sm:items-center"
              >
                <div>
                  <div className="flex items-center gap-2">
                    <span className="text-xs font-bold text-neutral-900">
                      {setting.label}
                    </span>
                    <Badge variant="outline" className="text-[10px]">
                      {setting.group}
                    </Badge>
                  </div>
                  <p className="mt-0.5 text-xs text-neutral-500">
                    {setting.description || setting.key}
                  </p>
                </div>

                <div className="sm:w-64">
                  {setting.type === 'boolean' ? (
                    <label className="inline-flex items-center gap-2 text-xs font-medium">
                      <input
                        type="checkbox"
                        disabled={!canManage}
                        checked={Boolean(currentVal)}
                        onChange={(e) =>
                          setDraftValues((prev) => ({
                            ...prev,
                            [setting.key]: e.target.checked,
                          }))
                        }
                      />
                      <span>{currentVal ? 'Enabled' : 'Disabled'}</span>
                    </label>
                  ) : (
                    <Input
                      type={setting.type === 'integer' ? 'number' : 'text'}
                      disabled={!canManage}
                      value={String(currentVal ?? '')}
                      onChange={(e) =>
                        setDraftValues((prev) => ({
                          ...prev,
                          [setting.key]:
                            setting.type === 'integer'
                              ? Number(e.target.value)
                              : e.target.value,
                        }))
                      }
                    />
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </div>

      <ConfirmDialog
        open={confirmSave}
        title="Apply Platform Configuration Changes?"
        description="Updates store, checkout, shipping, and security settings immediately and writes a diff to the Audit Trail."
        confirmLabel="Save & Apply"
        onConfirm={() => void handleConfirmSave()}
        onCancel={() => setConfirmSave(false)}
      />
    </div>
  );
}

// ─── 3. Users, Roles, Permissions & Audit History Module View ────────────────

export function UsersAndPermissionsAdminView({
  initialUsers = [],
  initialRoles = [],
  initialAuditLogs = [],
}: {
  initialUsers?: AdminStaffUser[];
  initialRoles?: AdminRoleRecord[];
  initialAuditLogs?: AdminAuditLogRecord[];
}) {
  const { hasPermission } = useAdminAuth();
  const canManageUsers = hasPermission('users.manage');
  const canManageRoles = hasPermission('roles.manage');
  const canViewAudit = hasPermission('audit.view');

  const [activeTab, setActiveTab] = React.useState<'users' | 'roles' | 'audit'>(
    'users'
  );
  const [users, setUsers] = React.useState<AdminStaffUser[]>(initialUsers);
  const [roles, setRoles] = React.useState<AdminRoleRecord[]>(initialRoles);
  const [allPermissions, setAllPermissions] = React.useState<string[]>([]);
  const [auditLogs, setAuditLogs] =
    React.useState<AdminAuditLogRecord[]>(initialAuditLogs);

  const [userSearch, setUserSearch] = React.useState('');
  const [roleFilter, setRoleFilter] = React.useState('');
  const [auditSearch, setAuditSearch] = React.useState('');
  const [userMeta, setUserMeta] = React.useState<PaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: initialUsers.length,
  });
  const [auditMeta, setAuditMeta] = React.useState<PaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: 25,
    total: initialAuditLogs.length,
  });

  const [showCreateUser, setShowCreateUser] = React.useState(false);
  const [newUserName, setNewUserName] = React.useState('');
  const [newUserEmail, setNewUserEmail] = React.useState('');
  const [newUserPassword, setNewUserPassword] = React.useState('');
  const [newUserRole, setNewUserRole] = React.useState('order_manager');
  const [confirmDeleteUser, setConfirmDeleteUser] =
    React.useState<AdminStaffUser | null>(null);

  const loadGovernanceData = React.useCallback(async () => {
    try {
      const [uRes, rRes, pRes] = await Promise.all([
        fetchAdminUsers({
          search: userSearch || undefined,
          role: roleFilter || undefined,
        }),
        fetchAdminRoles(),
        fetchAdminPermissions(),
      ]);
      setUsers(uRes.data);
      if (uRes.meta) {
        setUserMeta(uRes.meta);
      }
      setRoles(rRes.data);
      setAllPermissions(pRes.data.map((p) => p.name));

      if (canViewAudit) {
        const aRes = await fetchAdminAuditLogs({
          search: auditSearch || undefined,
        });
        setAuditLogs(aRes.data);
        if (aRes.meta) {
          setAuditMeta(aRes.meta);
        }
      }
    } catch {
      // Handled gracefully
    }
  }, [userSearch, roleFilter, auditSearch, canViewAudit]);

  React.useEffect(() => {
    if (
      initialUsers.length === 0 &&
      initialRoles.length === 0 &&
      initialAuditLogs.length === 0
    ) {
      void loadGovernanceData();
    }
  }, [
    initialUsers.length,
    initialRoles.length,
    initialAuditLogs.length,
    loadGovernanceData,
  ]);

  const handleCreateUser = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newUserName.trim() || !newUserEmail.trim() || !newUserPassword) return;
    await createAdminUser({
      name: newUserName.trim(),
      email: newUserEmail.trim(),
      password: newUserPassword,
      role: newUserRole,
      status: 'active',
      verified: true,
    });
    setNewUserName('');
    setNewUserEmail('');
    setNewUserPassword('');
    setShowCreateUser(false);
    await loadGovernanceData();
  };

  const handleToggleUserStatus = async (u: AdminStaffUser) => {
    const nextStatus = u.status === 'active' ? 'suspended' : 'active';
    await toggleAdminUserStatus(u.id, nextStatus);
    await loadGovernanceData();
  };

  const handleConfirmDeleteUser = async () => {
    if (!confirmDeleteUser) return;
    await deleteAdminUser(confirmDeleteUser.id);
    setConfirmDeleteUser(null);
    await loadGovernanceData();
  };

  const handleToggleRolePermission = async (
    role: AdminRoleRecord,
    permName: string
  ) => {
    const existingNames = role.permissions.map((p) =>
      typeof p === 'string' ? p : p.name
    );
    const nextPerms = existingNames.includes(permName)
      ? existingNames.filter((n) => n !== permName)
      : [...existingNames, permName];
    await updateAdminRolePermissions(role.id, nextPerms);
    await loadGovernanceData();
  };

  return (
    <div className="space-y-6">
      {/* Sub-navigation Tabs: Users | Roles & Permissions | Audit History */}
      <div className="flex flex-wrap items-center gap-2 border-b border-neutral-200 pb-3">
        <Button
          type="button"
          size="sm"
          variant={activeTab === 'users' ? 'default' : 'outline'}
          onClick={() => setActiveTab('users')}
        >
          Staff & User Accounts ({userMeta.total || users.length})
        </Button>
        <Button
          type="button"
          size="sm"
          variant={activeTab === 'roles' ? 'default' : 'outline'}
          onClick={() => setActiveTab('roles')}
        >
          Roles & Permissions Matrix ({roles.length})
        </Button>
        {canViewAudit && (
          <Button
            type="button"
            size="sm"
            variant={activeTab === 'audit' ? 'default' : 'outline'}
            onClick={() => setActiveTab('audit')}
          >
            Audit History ({auditMeta.total || auditLogs.length})
          </Button>
        )}
      </div>

      {/* Tab 1: Staff & Users */}
      {activeTab === 'users' && (
        <div className="space-y-4">
          <AdminDataToolbar
            searchPlaceholder="Search users by name or email..."
            searchValue={userSearch}
            onSearchChange={setUserSearch}
            filters={[
              {
                key: 'role',
                label: 'Role',
                value: roleFilter,
                options: [
                  { label: 'Super Admin', value: 'super_admin' },
                  { label: 'Product Manager', value: 'product_manager' },
                  { label: 'Inventory Manager', value: 'inventory_manager' },
                  { label: 'Order Manager', value: 'order_manager' },
                  { label: 'Customer Support', value: 'customer_support' },
                  { label: 'Marketing Manager', value: 'marketing_manager' },
                  { label: 'Report Viewer', value: 'report_viewer' },
                ],
                onChange: setRoleFilter,
              },
            ]}
            totalCount={userMeta.total || users.length}
            primaryAction={
              canManageUsers
                ? {
                    label: showCreateUser ? 'Close Form' : '+ Add Staff Member',
                    onClick: () => setShowCreateUser((v) => !v),
                  }
                : undefined
            }
          />

          {showCreateUser && canManageUsers && (
            <form
              onSubmit={(e) => void handleCreateUser(e)}
              className="rounded-xl border border-neutral-200 bg-white p-5 shadow-2xs"
            >
              <h3 className="text-sm font-bold text-neutral-900">
                Create Staff Account
              </h3>
              <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-4">
                <Input
                  placeholder="Full Name *"
                  value={newUserName}
                  onChange={(e) => setNewUserName(e.target.value)}
                  required
                />
                <Input
                  type="email"
                  placeholder="Staff Email *"
                  value={newUserEmail}
                  onChange={(e) => setNewUserEmail(e.target.value)}
                  required
                />
                <Input
                  type="password"
                  placeholder="Initial Password *"
                  value={newUserPassword}
                  onChange={(e) => setNewUserPassword(e.target.value)}
                  required
                />
                <select
                  value={newUserRole}
                  onChange={(e) => setNewUserRole(e.target.value)}
                  className="h-9 rounded-md border border-neutral-300 bg-white px-3 text-xs"
                >
                  <option value="product_manager">product_manager</option>
                  <option value="inventory_manager">inventory_manager</option>
                  <option value="order_manager">order_manager</option>
                  <option value="customer_support">customer_support</option>
                  <option value="marketing_manager">marketing_manager</option>
                  <option value="report_viewer">report_viewer</option>
                </select>
              </div>
              <div className="mt-3 flex justify-end">
                <Button type="submit" size="sm">
                  Create Staff Account
                </Button>
              </div>
            </form>
          )}

          <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-2xs">
            <table className="w-full text-left text-xs">
              <thead>
                <tr className="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500">
                  <th className="px-4 py-3">User</th>
                  <th className="px-4 py-3">Role</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {users.map((u) => (
                  <tr key={u.id}>
                    <td className="px-4 py-3">
                      <div className="font-semibold text-neutral-900">{u.name}</div>
                      <div className="text-[11px] text-neutral-500">{u.email}</div>
                    </td>
                    <td className="px-4 py-3">
                      <Badge variant="outline">{u.role}</Badge>
                    </td>
                    <td className="px-4 py-3">
                      <Badge
                        variant={u.status === 'active' ? 'default' : 'destructive'}
                      >
                        {u.status}
                      </Badge>
                    </td>
                    <td className="px-4 py-3 text-right">
                      {canManageUsers && u.role !== 'super_admin' && (
                        <div className="inline-flex items-center gap-2">
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => void handleToggleUserStatus(u)}
                          >
                            {u.status === 'active' ? 'Suspend' : 'Activate'}
                          </Button>
                          <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            onClick={() => setConfirmDeleteUser(u)}
                          >
                            Delete
                          </Button>
                        </div>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Tab 2: Roles & Permissions Matrix */}
      {activeTab === 'roles' && (
        <div className="space-y-4">
          {roles.map((role) => {
            const assignedNames = role.permissions.map((p) =>
              typeof p === 'string' ? p : p.name
            );

            return (
              <div
                key={role.id}
                className="rounded-xl border border-neutral-200 bg-white p-5 shadow-2xs"
              >
                <div className="flex items-center justify-between">
                  <div>
                    <h3 className="text-sm font-bold text-neutral-900">
                      Role: {role.name}
                    </h3>
                    <p className="text-xs text-neutral-500">
                      {assignedNames.length} permissions granted
                    </p>
                  </div>
                  <Badge variant="secondary">{role.name}</Badge>
                </div>

                <div className="mt-3 flex flex-wrap gap-2">
                  {(allPermissions.length > 0 ? allPermissions : assignedNames).map(
                    (permName) => {
                      const hasIt = assignedNames.includes(permName);
                      return (
                        <button
                          key={permName}
                          type="button"
                          disabled={!canManageRoles || role.name === 'super_admin'}
                          onClick={() => void handleToggleRolePermission(role, permName)}
                          className={`rounded-md border px-2.5 py-1 text-[11px] font-medium transition ${
                            hasIt
                              ? 'border-neutral-900 bg-neutral-900 text-white'
                              : 'border-neutral-200 bg-neutral-50 text-neutral-500 hover:border-neutral-400'
                          }`}
                        >
                          {hasIt ? '✓ ' : ''}
                          {permName}
                        </button>
                      );
                    }
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Tab 3: Audit History Trail */}
      {activeTab === 'audit' && canViewAudit && (
        <div className="space-y-4">
          <AdminDataToolbar
            searchPlaceholder="Search audit history by action (e.g. coupon.created) or staff email..."
            searchValue={auditSearch}
            onSearchChange={setAuditSearch}
            totalCount={auditMeta.total || auditLogs.length}
            onExportCsv={() => void downloadAdminCsvExport('audit-logs')}
          />

          <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-2xs">
            <table
              className="w-full text-left text-xs"
              aria-label="Audit History Table"
            >
              <thead>
                <tr className="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500">
                  <th className="px-4 py-3">Timestamp</th>
                  <th className="px-4 py-3">Action</th>
                  <th className="px-4 py-3">Actor</th>
                  <th className="px-4 py-3">Changes / Target</th>
                  <th className="px-4 py-3">IP Address</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {auditLogs.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-4 py-8 text-center text-neutral-500">
                      No audit history records match the current search.
                    </td>
                  </tr>
                ) : (
                  auditLogs.map((log) => (
                    <tr key={log.id}>
                      <td className="px-4 py-3 font-mono text-[11px] text-neutral-500">
                        {log.created_at}
                      </td>
                      <td className="px-4 py-3">
                        <Badge variant="outline" className="font-mono">
                          {log.action}
                        </Badge>
                      </td>
                      <td className="px-4 py-3 font-medium text-neutral-800">
                        {log.user_email || `User #${log.user_id ?? 'system'}`}
                      </td>
                      <td className="max-w-md truncate px-4 py-3 font-mono text-[11px] text-neutral-600">
                        {log.new_values
                          ? JSON.stringify(log.new_values)
                          : log.old_values
                            ? JSON.stringify(log.old_values)
                            : log.auditable_type
                              ? `${log.auditable_type}#${log.auditable_id ?? ''}`
                              : '—'}
                      </td>
                      <td className="px-4 py-3 font-mono text-[11px] text-neutral-500">
                        {log.ip_address || '127.0.0.1'}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          <AdminPaginationBar
            currentPage={auditMeta.current_page}
            lastPage={auditMeta.last_page}
            total={auditMeta.total}
            onPageChange={() => void loadGovernanceData()}
          />
        </div>
      )}

      <ConfirmDialog
        open={confirmDeleteUser !== null}
        title={`Delete User Account "${confirmDeleteUser?.name ?? ''}"?`}
        description="Revokes all active Sanctum tokens, deletes the account, and logs user.deleted in the Audit Trail."
        confirmLabel="Delete Account"
        variant="destructive"
        onConfirm={() => void handleConfirmDeleteUser()}
        onCancel={() => setConfirmDeleteUser(null)}
      />
    </div>
  );
}
