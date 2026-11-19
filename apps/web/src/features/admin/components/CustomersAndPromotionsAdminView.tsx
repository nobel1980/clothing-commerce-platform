'use client';

import * as React from 'react';
import type {
  AdminCouponRecord,
  AdminCustomerRecord,
  PaginationMeta,
} from '@clothing-commerce/types';
import {
  createAdminCoupon,
  deleteAdminCoupon,
  downloadAdminCsvExport,
  fetchAdminCoupons,
  fetchAdminCustomers,
  toggleAdminCoupon,
  updateAdminCustomerStatus,
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

// ─── 1. Customers Module View ────────────────────────────────────────────────

export function CustomersAdminView({
  initialCustomers = [],
}: {
  initialCustomers?: AdminCustomerRecord[];
}) {
  const { hasPermission } = useAdminAuth();
  const canManage = hasPermission('customers.manage');

  const [customers, setCustomers] =
    React.useState<AdminCustomerRecord[]>(initialCustomers);
  const [search, setSearch] = React.useState('');
  const [statusFilter, setStatusFilter] = React.useState('');
  const [page, setPage] = React.useState(1);
  const [meta, setMeta] = React.useState<PaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: initialCustomers.length,
  });

  const [statusToggleTarget, setStatusToggleTarget] = React.useState<{
    customer: AdminCustomerRecord;
    nextStatus: 'active' | 'suspended';
  } | null>(null);

  const loadCustomers = React.useCallback(async () => {
    try {
      const res = await fetchAdminCustomers({
        search: search || undefined,
        status: statusFilter || undefined,
        page,
      });
      setCustomers(res.data);
      if (res.meta) {
        setMeta(res.meta);
      }
    } catch {
      // Handled gracefully
    }
  }, [search, statusFilter, page]);

  React.useEffect(() => {
    if (initialCustomers.length === 0) {
      void loadCustomers();
    }
  }, [initialCustomers.length, loadCustomers]);

  const handleConfirmStatusChange = async () => {
    if (!statusToggleTarget) return;
    await updateAdminCustomerStatus(
      statusToggleTarget.customer.id,
      statusToggleTarget.nextStatus
    );
    setStatusToggleTarget(null);
    await loadCustomers();
  };

  return (
    <div className="space-y-4">
      <AdminDataToolbar
        searchPlaceholder="Search customers by name, email, or phone..."
        searchValue={search}
        onSearchChange={(val) => {
          setSearch(val);
          setPage(1);
        }}
        filters={[
          {
            key: 'status',
            label: 'Account Status',
            value: statusFilter,
            options: [
              { label: 'Active', value: 'active' },
              { label: 'Suspended', value: 'suspended' },
              { label: 'Inactive', value: 'inactive' },
            ],
            onChange: setStatusFilter,
          },
        ]}
        totalCount={meta.total}
        onExportCsv={() => void downloadAdminCsvExport('customers')}
      />

      <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-2xs">
        <table className="w-full text-left text-xs">
          <thead>
            <tr className="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500">
              <th className="px-4 py-3">Customer</th>
              <th className="px-4 py-3">Phone</th>
              <th className="px-4 py-3 text-right">Orders</th>
              <th className="px-4 py-3 text-right">Lifetime Spend</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {customers.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                  No customer accounts found.
                </td>
              </tr>
            ) : (
              customers.map((customer) => (
                <tr key={customer.id}>
                  <td className="px-4 py-3">
                    <div className="font-semibold text-neutral-900">{customer.name}</div>
                    <div className="text-[11px] text-neutral-500">{customer.email}</div>
                  </td>
                  <td className="px-4 py-3 text-neutral-600">
                    {customer.phone || '—'}
                  </td>
                  <td className="px-4 py-3 text-right font-medium text-neutral-900">
                    {customer.orders_count}
                  </td>
                  <td className="px-4 py-3 text-right font-semibold text-neutral-900">
                    {formatPrice({
                      amount: customer.total_spent,
                      currency: customer.currency || 'BDT',
                    })}
                  </td>
                  <td className="px-4 py-3">
                    <Badge
                      variant={
                        customer.status === 'active'
                          ? 'default'
                          : customer.status === 'suspended'
                            ? 'destructive'
                            : 'secondary'
                      }
                    >
                      {customer.status}
                    </Badge>
                  </td>
                  <td className="px-4 py-3 text-right">
                    {canManage && (
                      <Button
                        type="button"
                        size="sm"
                        variant={
                          customer.status === 'active' ? 'destructive' : 'outline'
                        }
                        onClick={() =>
                          setStatusToggleTarget({
                            customer,
                            nextStatus:
                              customer.status === 'active' ? 'suspended' : 'active',
                          })
                        }
                      >
                        {customer.status === 'active' ? 'Suspend' : 'Activate'}
                      </Button>
                    )}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <AdminPaginationBar
        currentPage={meta.current_page}
        lastPage={meta.last_page}
        total={meta.total}
        onPageChange={setPage}
      />

      <ConfirmDialog
        open={statusToggleTarget !== null}
        title={
          statusToggleTarget
            ? `${statusToggleTarget.nextStatus === 'suspended' ? 'Suspend' : 'Activate'} Customer "${statusToggleTarget.customer.name}"?`
            : ''
        }
        description="Suspending a customer revokes active session tokens and logs the action in the Audit Trail."
        confirmLabel={
          statusToggleTarget?.nextStatus === 'suspended'
            ? 'Suspend Account'
            : 'Activate Account'
        }
        variant={
          statusToggleTarget?.nextStatus === 'suspended' ? 'destructive' : 'default'
        }
        onConfirm={() => void handleConfirmStatusChange()}
        onCancel={() => setStatusToggleTarget(null)}
      />
    </div>
  );
}

// ─── 2. Promotions (Coupons) Module View ─────────────────────────────────────

export function PromotionsAdminView({
  initialCoupons = [],
}: {
  initialCoupons?: AdminCouponRecord[];
}) {
  const { hasPermission } = useAdminAuth();
  const canManage = hasPermission('promotions.manage');

  const [coupons, setCoupons] = React.useState<AdminCouponRecord[]>(initialCoupons);
  const [search, setSearch] = React.useState('');
  const [typeFilter, setTypeFilter] = React.useState('');
  const [page, setPage] = React.useState(1);
  const [meta, setMeta] = React.useState<PaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: initialCoupons.length,
  });

  const [showCreateForm, setShowCreateForm] = React.useState(false);
  const [codeInput, setCodeInput] = React.useState('');
  const [descInput, setDescInput] = React.useState('');
  const [couponType, setCouponType] = React.useState<'percentage' | 'fixed_amount'>(
    'fixed_amount'
  );
  const [valueInput, setValueInput] = React.useState('50000');
  const [minOrderInput, setMinOrderInput] = React.useState('200000');
  const [confirmDelete, setConfirmDelete] = React.useState<AdminCouponRecord | null>(
    null
  );

  const loadCoupons = React.useCallback(async () => {
    try {
      const res = await fetchAdminCoupons({
        search: search || undefined,
        type: typeFilter || undefined,
        page,
      });
      setCoupons(res.data);
      if (res.meta) {
        setMeta(res.meta);
      }
    } catch {
      // Handled gracefully
    }
  }, [search, typeFilter, page]);

  React.useEffect(() => {
    if (initialCoupons.length === 0) {
      void loadCoupons();
    }
  }, [initialCoupons.length, loadCoupons]);

  const handleCreateCoupon = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!codeInput.trim()) return;
    await createAdminCoupon({
      code: codeInput.trim().toUpperCase(),
      description: descInput.trim() || undefined,
      type: couponType,
      value: Math.round(Number(valueInput)),
      min_order_amount: Math.round(Number(minOrderInput || 0)),
      is_active: true,
    });
    setCodeInput('');
    setDescInput('');
    setShowCreateForm(false);
    await loadCoupons();
  };

  const handleToggle = async (coupon: AdminCouponRecord) => {
    await toggleAdminCoupon(coupon.id);
    await loadCoupons();
  };

  const handleConfirmDelete = async () => {
    if (!confirmDelete) return;
    await deleteAdminCoupon(confirmDelete.id);
    setConfirmDelete(null);
    await loadCoupons();
  };

  return (
    <div className="space-y-4">
      <AdminDataToolbar
        searchPlaceholder="Search coupons by code or description..."
        searchValue={search}
        onSearchChange={(val) => {
          setSearch(val);
          setPage(1);
        }}
        filters={[
          {
            key: 'type',
            label: 'Discount Type',
            value: typeFilter,
            options: [
              { label: 'Fixed Amount (Paisa)', value: 'fixed_amount' },
              { label: 'Percentage', value: 'percentage' },
            ],
            onChange: setTypeFilter,
          },
        ]}
        totalCount={meta.total}
        onExportCsv={() => void downloadAdminCsvExport('promotions')}
        primaryAction={
          canManage
            ? {
                label: showCreateForm ? 'Close Form' : '+ New Coupon',
                onClick: () => setShowCreateForm((v) => !v),
              }
            : undefined
        }
      />

      {showCreateForm && canManage && (
        <form
          onSubmit={(e) => void handleCreateCoupon(e)}
          className="rounded-xl border border-neutral-200 bg-white p-5 shadow-2xs"
        >
          <h3 className="text-sm font-bold text-neutral-900">Create Promotional Coupon</h3>
          <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-5">
            <Input
              placeholder="Coupon Code (e.g. EID2026) *"
              value={codeInput}
              onChange={(e) => setCodeInput(e.target.value)}
              required
            />
            <Input
              placeholder="Description"
              value={descInput}
              onChange={(e) => setDescInput(e.target.value)}
            />
            <select
              value={couponType}
              onChange={(e) =>
                setCouponType(e.target.value as 'percentage' | 'fixed_amount')
              }
              className="h-9 rounded-md border border-neutral-300 bg-white px-3 text-xs"
            >
              <option value="fixed_amount">Fixed Amount (Paisa)</option>
              <option value="percentage">Percentage (%)</option>
            </select>
            <Input
              type="number"
              placeholder="Value (Paisa or %)"
              value={valueInput}
              onChange={(e) => setValueInput(e.target.value)}
              required
            />
            <Input
              type="number"
              placeholder="Min Order (Paisa)"
              value={minOrderInput}
              onChange={(e) => setMinOrderInput(e.target.value)}
            />
          </div>
          <div className="mt-3 flex justify-end">
            <Button type="submit" size="sm">
              Create Coupon
            </Button>
          </div>
        </form>
      )}

      <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-2xs">
        <table className="w-full text-left text-xs">
          <thead>
            <tr className="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500">
              <th className="px-4 py-3">Code</th>
              <th className="px-4 py-3">Discount Value</th>
              <th className="px-4 py-3 text-right">Min Order</th>
              <th className="px-4 py-3 text-right">Redemptions</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {coupons.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                  No promotional coupons found.
                </td>
              </tr>
            ) : (
              coupons.map((coupon) => (
                <tr key={coupon.id}>
                  <td className="px-4 py-3">
                    <div className="font-mono font-bold text-neutral-900">
                      {coupon.code}
                    </div>
                    <div className="text-[11px] text-neutral-500">
                      {coupon.description || 'Storewide promotion'}
                    </div>
                  </td>
                  <td className="px-4 py-3 font-semibold text-neutral-900">
                    {coupon.type === 'percentage'
                      ? `${coupon.value}%`
                      : formatPrice({
                          amount: coupon.value,
                          currency: coupon.currency || 'BDT',
                        })}
                  </td>
                  <td className="px-4 py-3 text-right text-neutral-600">
                    {formatPrice({
                      amount: coupon.min_order_amount,
                      currency: coupon.currency || 'BDT',
                    })}
                  </td>
                  <td className="px-4 py-3 text-right font-medium text-neutral-800">
                    {coupon.used_count}
                    {coupon.usage_limit ? ` / ${coupon.usage_limit}` : ''}
                  </td>
                  <td className="px-4 py-3">
                    <Badge variant={coupon.is_active && !coupon.is_expired ? 'default' : 'secondary'}>
                      {coupon.is_expired
                        ? 'Expired'
                        : coupon.is_active
                          ? 'Active'
                          : 'Disabled'}
                    </Badge>
                  </td>
                  <td className="px-4 py-3 text-right">
                    {canManage && (
                      <div className="inline-flex items-center gap-2">
                        <Button
                          type="button"
                          size="sm"
                          variant="outline"
                          onClick={() => void handleToggle(coupon)}
                        >
                          {coupon.is_active ? 'Disable' : 'Enable'}
                        </Button>
                        <Button
                          type="button"
                          size="sm"
                          variant="destructive"
                          onClick={() => setConfirmDelete(coupon)}
                        >
                          Delete
                        </Button>
                      </div>
                    )}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <AdminPaginationBar
        currentPage={meta.current_page}
        lastPage={meta.last_page}
        total={meta.total}
        onPageChange={setPage}
      />

      <ConfirmDialog
        open={confirmDelete !== null}
        title={`Delete Coupon "${confirmDelete?.code ?? ''}"?`}
        description="This permanently removes the coupon code and logs the deletion in the Audit Trail."
        confirmLabel="Delete Coupon"
        variant="destructive"
        onConfirm={() => void handleConfirmDelete()}
        onCancel={() => setConfirmDelete(null)}
      />
    </div>
  );
}

