'use client';

import * as React from 'react';
import type {
  AdminOrderRecord,
  AdminPaymentSummary,
  AdminPaymentTransaction,
  PaginationMeta,
} from '@clothing-commerce/types';
import {
  downloadAdminCsvExport,
  fetchAdminOrders,
  fetchAdminPayments,
  initiateAdminPaymentRefund,
  transitionAdminOrder,
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

// ─── 1. Orders Module View ───────────────────────────────────────────────────

export function OrdersAdminView({
  initialOrders = [],
}: {
  initialOrders?: AdminOrderRecord[];
}) {
  const { hasPermission } = useAdminAuth();
  const canManage = hasPermission('orders.manage');

  const [orders, setOrders] = React.useState<AdminOrderRecord[]>(initialOrders);
  const [search, setSearch] = React.useState('');
  const [statusFilter, setStatusFilter] = React.useState('');
  const [paymentFilter, setPaymentFilter] = React.useState('');
  const [page, setPage] = React.useState(1);
  const [meta, setMeta] = React.useState<PaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: initialOrders.length,
  });

  const [pendingAction, setPendingAction] = React.useState<{
    order: AdminOrderRecord;
    action: 'confirm' | 'process' | 'ship' | 'deliver' | 'cancel';
  } | null>(null);

  const loadOrders = React.useCallback(async () => {
    try {
      const res = await fetchAdminOrders({
        search: search || undefined,
        status: statusFilter || undefined,
        payment_status: paymentFilter || undefined,
        page,
      });
      setOrders(res.data);
      if (res.meta) {
        setMeta(res.meta);
      }
    } catch {
      // Handled gracefully
    }
  }, [search, statusFilter, paymentFilter, page]);

  React.useEffect(() => {
    if (initialOrders.length === 0) {
      void loadOrders();
    }
  }, [initialOrders.length, loadOrders]);

  const handleConfirmTransition = async (reason?: string) => {
    if (!pendingAction) return;
    await transitionAdminOrder(pendingAction.order.id, pendingAction.action, {
      reason: reason || 'Cancelled by staff',
      notes: reason || `Order transitioned via Admin Portal (${pendingAction.action})`,
    });
    setPendingAction(null);
    await loadOrders();
  };

  return (
    <div className="space-y-4">
      <AdminDataToolbar
        searchPlaceholder="Search by order number, recipient name, or phone..."
        searchValue={search}
        onSearchChange={(val) => {
          setSearch(val);
          setPage(1);
        }}
        filters={[
          {
            key: 'status',
            label: 'Order Status',
            value: statusFilter,
            options: [
              { label: 'Pending', value: 'pending' },
              { label: 'Confirmed', value: 'confirmed' },
              { label: 'Processing', value: 'processing' },
              { label: 'Shipped', value: 'shipped' },
              { label: 'Delivered', value: 'delivered' },
              { label: 'Cancelled', value: 'cancelled' },
              { label: 'Returned', value: 'returned' },
            ],
            onChange: setStatusFilter,
          },
          {
            key: 'payment_status',
            label: 'Payment',
            value: paymentFilter,
            options: [
              { label: 'Paid', value: 'paid' },
              { label: 'COD Pending', value: 'cod_pending' },
              { label: 'Pending', value: 'pending' },
              { label: 'Refunded', value: 'refunded' },
            ],
            onChange: setPaymentFilter,
          },
        ]}
        totalCount={meta.total}
        onExportCsv={() => void downloadAdminCsvExport('orders')}
      />

      <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-2xs">
        <table className="w-full text-left text-xs">
          <thead>
            <tr className="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500">
              <th className="px-4 py-3">Order #</th>
              <th className="px-4 py-3">Customer / Destination</th>
              <th className="px-4 py-3">Order Status</th>
              <th className="px-4 py-3">Payment Status</th>
              <th className="px-4 py-3 text-right">Total (BDT)</th>
              <th className="px-4 py-3 text-right">Lifecycle Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {orders.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                  No orders match the current filters.
                </td>
              </tr>
            ) : (
              orders.map((order) => {
                const statusLower = order.status.toLowerCase();
                return (
                  <tr key={order.id}>
                    <td className="px-4 py-3 font-mono font-semibold text-neutral-900">
                      {order.order_number}
                    </td>
                    <td className="px-4 py-3">
                      <div className="font-medium text-neutral-900">
                        {order.shipping_address?.recipient_name || 'Customer'}
                      </div>
                      <div className="text-[11px] text-neutral-500">
                        {[
                          order.shipping_address?.upazila,
                          order.shipping_address?.district,
                          order.shipping_address?.division,
                        ]
                          .filter(Boolean)
                          .join(', ') || 'Bangladesh'}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <Badge variant="outline">{statusLower}</Badge>
                    </td>
                    <td className="px-4 py-3">
                      <Badge variant="secondary">
                        {order.payment_method.toUpperCase()} ·{' '}
                        {order.payment_status.toLowerCase()}
                      </Badge>
                    </td>
                    <td className="px-4 py-3 text-right font-semibold text-neutral-900">
                      {formatPrice({
                        amount: order.total_amount,
                        currency: order.currency || 'BDT',
                      })}
                    </td>
                    <td className="px-4 py-3 text-right">
                      {canManage && (
                        <div className="inline-flex flex-wrap justify-end gap-1.5">
                          {statusLower === 'pending' && (
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              onClick={() =>
                                setPendingAction({ order, action: 'confirm' })
                              }
                            >
                              Confirm
                            </Button>
                          )}
                          {statusLower === 'confirmed' && (
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              onClick={() =>
                                setPendingAction({ order, action: 'process' })
                              }
                            >
                              Process
                            </Button>
                          )}
                          {(statusLower === 'confirmed' || statusLower === 'processing') && (
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              onClick={() => setPendingAction({ order, action: 'ship' })}
                            >
                              Ship
                            </Button>
                          )}
                          {statusLower === 'shipped' && (
                            <Button
                              type="button"
                              size="sm"
                              variant="default"
                              onClick={() =>
                                setPendingAction({ order, action: 'deliver' })
                              }
                            >
                              Deliver
                            </Button>
                          )}
                          {!['shipped', 'delivered', 'cancelled', 'returned'].includes(
                            statusLower
                          ) && (
                            <Button
                              type="button"
                              size="sm"
                              variant="destructive"
                              onClick={() =>
                                setPendingAction({ order, action: 'cancel' })
                              }
                            >
                              Cancel
                            </Button>
                          )}
                        </div>
                      )}
                    </td>
                  </tr>
                );
              })
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
        open={pendingAction !== null}
        title={
          pendingAction
            ? `${pendingAction.action.toUpperCase()} Order ${pendingAction.order.order_number}?`
            : ''
        }
        description={
          pendingAction?.action === 'cancel'
            ? 'Cancelling this order releases reserved stock back to the warehouse and writes an immutable OrderStatusHistory record.'
            : 'This transition updates the order fulfilment state and records an audit entry.'
        }
        confirmLabel={
          pendingAction ? `Confirm ${pendingAction.action.toUpperCase()}` : 'Confirm'
        }
        variant={pendingAction?.action === 'cancel' ? 'destructive' : 'default'}
        requireReason={pendingAction?.action === 'cancel'}
        reasonLabel="Cancellation Reason"
        reasonPlaceholder="e.g. Customer requested cancellation before dispatch"
        onConfirm={(reason) => void handleConfirmTransition(reason)}
        onCancel={() => setPendingAction(null)}
      />
    </div>
  );
}

// ─── 2. Payments Module View ─────────────────────────────────────────────────

export function PaymentsAdminView({
  initialTransactions = [],
  initialSummary,
}: {
  initialTransactions?: AdminPaymentTransaction[];
  initialSummary?: AdminPaymentSummary;
}) {
  const { hasPermission } = useAdminAuth();
  const canManage = hasPermission('payments.manage');

  const [transactions, setTransactions] =
    React.useState<AdminPaymentTransaction[]>(initialTransactions);
  const [summary, setSummary] = React.useState<AdminPaymentSummary | undefined>(
    initialSummary
  );
  const [search, setSearch] = React.useState('');
  const [gatewayFilter, setGatewayFilter] = React.useState('');
  const [statusFilter, setStatusFilter] = React.useState('');
  const [page, setPage] = React.useState(1);
  const [meta, setMeta] = React.useState<PaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: initialTransactions.length,
  });

  const [refundTarget, setRefundTarget] = React.useState<AdminPaymentTransaction | null>(
    null
  );

  const loadPayments = React.useCallback(async () => {
    try {
      const res = await fetchAdminPayments({
        search: search || undefined,
        gateway: gatewayFilter || undefined,
        status: statusFilter || undefined,
        page,
      });
      setTransactions(res.data);
      if (res.summary) {
        setSummary(res.summary);
      }
      if (res.meta) {
        setMeta(res.meta);
      }
    } catch {
      // Handled gracefully
    }
  }, [search, gatewayFilter, statusFilter, page]);

  React.useEffect(() => {
    if (initialTransactions.length === 0) {
      void loadPayments();
    }
  }, [initialTransactions.length, loadPayments]);

  const handleConfirmRefund = async (reason?: string) => {
    if (!refundTarget) return;
    const refundable = Math.max(0, refundTarget.amount - refundTarget.refunded_amount);
    if (refundable <= 0) return;

    await initiateAdminPaymentRefund(refundTarget.id, {
      amount: refundable,
      reason: reason || 'Admin refund initiated',
    });
    setRefundTarget(null);
    await loadPayments();
  };

  return (
    <div className="space-y-4">
      {summary && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
          <div className="rounded-xl border border-neutral-200 bg-white p-4">
            <p className="text-xs font-medium text-neutral-500">Total Verified Paid</p>
            <p className="mt-1 text-lg font-bold text-neutral-900">
              {formatPrice({ amount: summary.total_paid_amount, currency: 'BDT' })}
            </p>
          </div>
          <div className="rounded-xl border border-neutral-200 bg-white p-4">
            <p className="text-xs font-medium text-neutral-500">Total Refunded</p>
            <p className="mt-1 text-lg font-bold text-neutral-900">
              {formatPrice({ amount: summary.total_refunded_amount, currency: 'BDT' })}
            </p>
          </div>
          <div className="rounded-xl border border-neutral-200 bg-white p-4">
            <p className="text-xs font-medium text-neutral-500">Paid Transactions</p>
            <p className="mt-1 text-lg font-bold text-neutral-900">{summary.paid_count}</p>
          </div>
          <div className="rounded-xl border border-neutral-200 bg-white p-4">
            <p className="text-xs font-medium text-neutral-500">Failed / Pending</p>
            <p className="mt-1 text-lg font-bold text-neutral-900">
              {summary.failed_count} failed · {summary.pending_count} pending
            </p>
          </div>
        </div>
      )}

      <AdminDataToolbar
        searchPlaceholder="Search by merchant reference, gateway trxID, or order #..."
        searchValue={search}
        onSearchChange={(val) => {
          setSearch(val);
          setPage(1);
        }}
        filters={[
          {
            key: 'gateway',
            label: 'Gateway',
            value: gatewayFilter,
            options: [
              { label: 'bKash', value: 'bkash' },
              { label: 'Nagad', value: 'nagad' },
              { label: 'SSLCommerz', value: 'sslcommerz' },
              { label: 'Cash on Delivery', value: 'cod' },
            ],
            onChange: setGatewayFilter,
          },
          {
            key: 'status',
            label: 'Status',
            value: statusFilter,
            options: [
              { label: 'Paid', value: 'paid' },
              { label: 'Pending', value: 'pending' },
              { label: 'COD Pending', value: 'cod_pending' },
              { label: 'Failed', value: 'failed' },
              { label: 'Refunded', value: 'refunded' },
            ],
            onChange: setStatusFilter,
          },
        ]}
        totalCount={meta.total}
        onExportCsv={() => void downloadAdminCsvExport('payments')}
      />

      <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-2xs">
        <table className="w-full text-left text-xs">
          <thead>
            <tr className="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500">
              <th className="px-4 py-3">Merchant Ref / Order</th>
              <th className="px-4 py-3">Gateway & TrxID</th>
              <th className="px-4 py-3 text-right">Amount</th>
              <th className="px-4 py-3 text-right">Refunded</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {transactions.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                  No payment transactions found.
                </td>
              </tr>
            ) : (
              transactions.map((tx) => (
                <tr key={tx.id}>
                  <td className="px-4 py-3">
                    <div className="font-mono font-semibold text-neutral-900">
                      {tx.merchant_reference}
                    </div>
                    <div className="text-[11px] text-neutral-500">
                      Order: {tx.order_number || `#${tx.order_id}`}
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <div className="font-semibold uppercase text-neutral-800">
                      {tx.gateway}
                    </div>
                    <div className="font-mono text-[11px] text-neutral-500">
                      {tx.gateway_transaction_id || tx.gateway_payment_id || '—'}
                    </div>
                  </td>
                  <td className="px-4 py-3 text-right font-semibold text-neutral-900">
                    {formatPrice({ amount: tx.amount, currency: tx.currency || 'BDT' })}
                  </td>
                  <td className="px-4 py-3 text-right text-neutral-600">
                    {tx.refunded_amount > 0
                      ? formatPrice({
                          amount: tx.refunded_amount,
                          currency: tx.currency || 'BDT',
                        })
                      : '—'}
                  </td>
                  <td className="px-4 py-3">
                    <Badge
                      variant={
                        tx.status === 'paid'
                          ? 'default'
                          : tx.status === 'failed'
                            ? 'destructive'
                            : 'secondary'
                      }
                    >
                      {tx.status}
                    </Badge>
                  </td>
                  <td className="px-4 py-3 text-right">
                    {canManage &&
                      tx.status === 'paid' &&
                      tx.amount > tx.refunded_amount && (
                        <Button
                          type="button"
                          variant="destructive"
                          size="sm"
                          onClick={() => setRefundTarget(tx)}
                        >
                          Issue Refund
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
        open={refundTarget !== null}
        title={`Issue Refund for ${refundTarget?.merchant_reference ?? ''}?`}
        description="This action initiates a server-side gateway refund for the remaining balance and logs the refund in the Audit Trail."
        confirmLabel="Issue Refund"
        variant="destructive"
        requireReason={true}
        reasonLabel="Refund Reason"
        reasonPlaceholder="e.g. Returned parcel / Defective item claim"
        onConfirm={(reason) => void handleConfirmRefund(reason)}
        onCancel={() => setRefundTarget(null)}
      />
    </div>
  );
}

