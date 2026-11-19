'use client';

import * as React from 'react';
import type {
  AdminReviewRecord,
  AdminShipmentRecord,
  PaginationMeta,
} from '@clothing-commerce/types';
import {
  cancelAdminShipment,
  createAdminShipment,
  deleteAdminReview,
  downloadAdminCsvExport,
  fetchAdminReviews,
  fetchAdminShipments,
  moderateAdminReview,
  syncAdminShipmentTracking,
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

// ─── 1. Shipping Module View ─────────────────────────────────────────────────

export function ShippingAdminView({
  initialShipments = [],
}: {
  initialShipments?: AdminShipmentRecord[];
}) {
  const { hasPermission } = useAdminAuth();
  const canManage = hasPermission('shipping.manage');

  const [shipments, setShipments] =
    React.useState<AdminShipmentRecord[]>(initialShipments);
  const [search, setSearch] = React.useState('');
  const [courierFilter, setCourierFilter] = React.useState('');
  const [statusFilter, setStatusFilter] = React.useState('');
  const [codFilter, setCodFilter] = React.useState('');
  const [page, setPage] = React.useState(1);
  const [meta, setMeta] = React.useState<PaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: initialShipments.length,
  });

  const [showDispatchForm, setShowDispatchForm] = React.useState(false);
  const [orderIdInput, setOrderIdInput] = React.useState('');
  const [courierInput, setCourierInput] = React.useState<'pathao' | 'steadfast' | 'redx'>(
    'pathao'
  );
  const [cancelTarget, setCancelTarget] = React.useState<AdminShipmentRecord | null>(
    null
  );

  const loadShipments = React.useCallback(async () => {
    try {
      const res = await fetchAdminShipments({
        search: search || undefined,
        courier: courierFilter || undefined,
        status: statusFilter || undefined,
        cod_reconciliation_status: codFilter || undefined,
        page,
      });
      setShipments(res.data);
      if (res.meta) {
        setMeta(res.meta);
      }
    } catch {
      // Handled gracefully
    }
  }, [search, courierFilter, statusFilter, codFilter, page]);

  React.useEffect(() => {
    if (initialShipments.length === 0) {
      void loadShipments();
    }
  }, [initialShipments.length, loadShipments]);

  const handleDispatch = async (e: React.FormEvent) => {
    e.preventDefault();
    const oid = Number(orderIdInput);
    if (!oid) return;
    await createAdminShipment({
      order_id: oid,
      courier: courierInput,
    });
    setOrderIdInput('');
    setShowDispatchForm(false);
    await loadShipments();
  };

  const handleTrackSync = async (shipmentId: number) => {
    await syncAdminShipmentTracking(shipmentId);
    await loadShipments();
  };

  const handleConfirmCancel = async (reason?: string) => {
    if (!cancelTarget) return;
    await cancelAdminShipment(
      cancelTarget.id,
      reason || 'Cancelled before courier pickup'
    );
    setCancelTarget(null);
    await loadShipments();
  };

  return (
    <div className="space-y-4">
      <AdminDataToolbar
        searchPlaceholder="Search by consignment ID, order #, or recipient phone..."
        searchValue={search}
        onSearchChange={(val) => {
          setSearch(val);
          setPage(1);
        }}
        filters={[
          {
            key: 'courier',
            label: 'Courier',
            value: courierFilter,
            options: [
              { label: 'Pathao Courier', value: 'pathao' },
              { label: 'Steadfast Courier', value: 'steadfast' },
              { label: 'REDX Logistics', value: 'redx' },
            ],
            onChange: setCourierFilter,
          },
          {
            key: 'status',
            label: 'Tracking Status',
            value: statusFilter,
            options: [
              { label: 'Created', value: 'created' },
              { label: 'Picked Up', value: 'picked_up' },
              { label: 'In Transit', value: 'in_transit' },
              { label: 'Out for Delivery', value: 'out_for_delivery' },
              { label: 'Delivered', value: 'delivered' },
              { label: 'Returned', value: 'returned' },
              { label: 'Cancelled', value: 'cancelled' },
            ],
            onChange: setStatusFilter,
          },
          {
            key: 'cod_reconciliation_status',
            label: 'COD Settlement',
            value: codFilter,
            options: [
              { label: 'Pending', value: 'pending' },
              { label: 'Reconciled', value: 'reconciled' },
              { label: 'Discrepancy', value: 'discrepancy' },
            ],
            onChange: setCodFilter,
          },
        ]}
        totalCount={meta.total}
        onExportCsv={() => void downloadAdminCsvExport('shipments')}
        primaryAction={
          canManage
            ? {
                label: showDispatchForm ? 'Close Form' : '+ Dispatch Consignment',
                onClick: () => setShowDispatchForm((v) => !v),
              }
            : undefined
        }
      />

      {showDispatchForm && canManage && (
        <form
          onSubmit={(e) => void handleDispatch(e)}
          className="flex flex-wrap items-center gap-3 rounded-xl border border-neutral-200 bg-white p-4"
        >
          <Input
            type="number"
            placeholder="Order ID *"
            value={orderIdInput}
            onChange={(e) => setOrderIdInput(e.target.value)}
            className="max-w-[180px]"
            required
          />
          <select
            value={courierInput}
            onChange={(e) =>
              setCourierInput(e.target.value as 'pathao' | 'steadfast' | 'redx')
            }
            className="h-9 rounded-md border border-neutral-300 bg-white px-3 text-xs"
          >
            <option value="pathao">Pathao Courier</option>
            <option value="steadfast">Steadfast Courier</option>
            <option value="redx">REDX Logistics</option>
          </select>
          <Button type="submit" size="sm">
            Create Courier Shipment
          </Button>
        </form>
      )}

      <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-2xs">
        <table className="w-full text-left text-xs">
          <thead>
            <tr className="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500">
              <th className="px-4 py-3">Consignment / Order</th>
              <th className="px-4 py-3">Courier</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3 text-right">COD Amount</th>
              <th className="px-4 py-3">COD Reconciliation</th>
              <th className="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {shipments.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                  No courier consignments found.
                </td>
              </tr>
            ) : (
              shipments.map((shipment) => (
                <tr key={shipment.id}>
                  <td className="px-4 py-3">
                    <div className="font-mono font-semibold text-neutral-900">
                      {shipment.consignment_id || `SHIP-${shipment.id}`}
                    </div>
                    <div className="text-[11px] text-neutral-500">
                      Order: {shipment.order_number || `#${shipment.order_id}`}
                    </div>
                  </td>
                  <td className="px-4 py-3 font-semibold uppercase text-neutral-800">
                    {shipment.courier}
                  </td>
                  <td className="px-4 py-3">
                    <Badge variant="outline">{shipment.status}</Badge>
                  </td>
                  <td className="px-4 py-3 text-right font-semibold text-neutral-900">
                    {formatPrice({ amount: shipment.cod_amount, currency: 'BDT' })}
                  </td>
                  <td className="px-4 py-3">
                    <Badge
                      variant={
                        shipment.cod_reconciliation_status === 'reconciled'
                          ? 'default'
                          : shipment.cod_reconciliation_status === 'discrepancy'
                            ? 'destructive'
                            : 'secondary'
                      }
                    >
                      {shipment.cod_reconciliation_status}
                    </Badge>
                  </td>
                  <td className="px-4 py-3 text-right">
                    {canManage && (
                      <div className="inline-flex items-center gap-2">
                        <Button
                          type="button"
                          size="sm"
                          variant="outline"
                          onClick={() => void handleTrackSync(shipment.id)}
                        >
                          Sync Tracking
                        </Button>
                        {shipment.status === 'created' && (
                          <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            onClick={() => setCancelTarget(shipment)}
                          >
                            Cancel
                          </Button>
                        )}
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
        open={cancelTarget !== null}
        title={`Cancel Consignment ${cancelTarget?.consignment_id ?? ''}?`}
        description="Cancels the consignment with the courier before pickup and records a tracking event."
        confirmLabel="Cancel Consignment"
        variant="destructive"
        requireReason={true}
        reasonLabel="Cancellation Reason"
        onConfirm={(reason) => void handleConfirmCancel(reason)}
        onCancel={() => setCancelTarget(null)}
      />
    </div>
  );
}

// ─── 2. Reviews Moderation Module View ───────────────────────────────────────

export function ReviewsAdminView({
  initialReviews = [],
}: {
  initialReviews?: AdminReviewRecord[];
}) {
  const { hasPermission } = useAdminAuth();
  const canManage = hasPermission('reviews.manage');

  const [reviews, setReviews] = React.useState<AdminReviewRecord[]>(initialReviews);
  const [search, setSearch] = React.useState('');
  const [statusFilter, setStatusFilter] = React.useState('');
  const [ratingFilter, setRatingFilter] = React.useState('');
  const [page, setPage] = React.useState(1);
  const [meta, setMeta] = React.useState<PaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: initialReviews.length,
  });

  const [confirmDelete, setConfirmDelete] = React.useState<AdminReviewRecord | null>(
    null
  );

  const loadReviews = React.useCallback(async () => {
    try {
      const res = await fetchAdminReviews({
        search: search || undefined,
        status: statusFilter || undefined,
        rating: ratingFilter || undefined,
        page,
      });
      setReviews(res.data);
      if (res.meta) {
        setMeta(res.meta);
      }
    } catch {
      // Handled gracefully
    }
  }, [search, statusFilter, ratingFilter, page]);

  React.useEffect(() => {
    if (initialReviews.length === 0) {
      void loadReviews();
    }
  }, [initialReviews.length, loadReviews]);

  const handleModerate = async (
    reviewId: number,
    status: 'approved' | 'rejected'
  ) => {
    await moderateAdminReview(reviewId, { status });
    await loadReviews();
  };

  const handleConfirmDelete = async () => {
    if (!confirmDelete) return;
    await deleteAdminReview(confirmDelete.id);
    setConfirmDelete(null);
    await loadReviews();
  };

  return (
    <div className="space-y-4">
      <AdminDataToolbar
        searchPlaceholder="Search reviews by reviewer, product, title, or comment..."
        searchValue={search}
        onSearchChange={(val) => {
          setSearch(val);
          setPage(1);
        }}
        filters={[
          {
            key: 'status',
            label: 'Moderation Status',
            value: statusFilter,
            options: [
              { label: 'Pending Review', value: 'pending' },
              { label: 'Approved', value: 'approved' },
              { label: 'Rejected', value: 'rejected' },
            ],
            onChange: setStatusFilter,
          },
          {
            key: 'rating',
            label: 'Star Rating',
            value: ratingFilter,
            options: [
              { label: '5 Stars', value: '5' },
              { label: '4 Stars', value: '4' },
              { label: '3 Stars', value: '3' },
              { label: '2 Stars', value: '2' },
              { label: '1 Star', value: '1' },
            ],
            onChange: setRatingFilter,
          },
        ]}
        totalCount={meta.total}
        onExportCsv={() => void downloadAdminCsvExport('reviews')}
      />

      <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-2xs">
        <table className="w-full text-left text-xs">
          <thead>
            <tr className="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500">
              <th className="px-4 py-3">Product & Reviewer</th>
              <th className="px-4 py-3">Rating</th>
              <th className="px-4 py-3">Review Content</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3 text-right">Moderation Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {reviews.length === 0 ? (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-neutral-500">
                  No product reviews match the current filters.
                </td>
              </tr>
            ) : (
              reviews.map((review) => (
                <tr key={review.id}>
                  <td className="px-4 py-3">
                    <div className="font-semibold text-neutral-900">
                      {review.product?.name ?? `Product #${review.product_id}`}
                    </div>
                    <div className="text-[11px] text-neutral-500">
                      By {review.reviewer_name}{' '}
                      {review.is_verified_purchase ? '· Verified Buyer' : ''}
                    </div>
                  </td>
                  <td className="px-4 py-3 font-bold text-amber-600">
                    {'★'.repeat(review.rating)}
                    {'☆'.repeat(Math.max(0, 5 - review.rating))} ({review.rating}/5)
                  </td>
                  <td className="max-w-md px-4 py-3">
                    {review.title && (
                      <div className="font-semibold text-neutral-900">{review.title}</div>
                    )}
                    <p className="text-neutral-600">{review.comment}</p>
                  </td>
                  <td className="px-4 py-3">
                    <Badge
                      variant={
                        review.status === 'approved'
                          ? 'default'
                          : review.status === 'rejected'
                            ? 'destructive'
                            : 'secondary'
                      }
                    >
                      {review.status}
                    </Badge>
                  </td>
                  <td className="px-4 py-3 text-right">
                    {canManage && (
                      <div className="inline-flex items-center gap-1.5">
                        {review.status !== 'approved' && (
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => void handleModerate(review.id, 'approved')}
                          >
                            Approve
                          </Button>
                        )}
                        {review.status !== 'rejected' && (
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => void handleModerate(review.id, 'rejected')}
                          >
                            Reject
                          </Button>
                        )}
                        <Button
                          type="button"
                          size="sm"
                          variant="destructive"
                          onClick={() => setConfirmDelete(review)}
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
        title="Delete Product Review?"
        description="Permanently deletes this review and logs the action in the Audit Trail."
        confirmLabel="Delete Review"
        variant="destructive"
        onConfirm={() => void handleConfirmDelete()}
        onCancel={() => setConfirmDelete(null)}
      />
    </div>
  );
}

