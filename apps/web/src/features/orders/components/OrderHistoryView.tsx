'use client';

import * as React from 'react';
import { Package } from 'lucide-react';
import type { Order, OrderStatus, PaymentStatus } from '@clothing-commerce/types';
import { useMutation, useQuery, useQueryClient } from '@/lib/query';
import {
  cancelCustomerOrder,
  fetchCustomerOrders,
  fetchOrderTracking,
  isCancellableByCustomer,
  normalizeOrder,
  type OrderTrackingPayload,
} from '@/features/orders/api/ordersApi';
import { formatMoney } from '@/lib/utils/format';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { EmptyState } from '@/components/shared/EmptyState';
import { ErrorState } from '@/components/shared/ErrorState';

type BadgeVariant = 'default' | 'secondary' | 'success' | 'warning' | 'destructive';

export function getOrderStatusVariant(status: OrderStatus): BadgeVariant {
  switch (status) {
    case 'paid':
    case 'confirmed':
    case 'delivered':
      return 'success';
    case 'processing':
    case 'shipped':
    case 'pending_payment':
    case 'awaiting_payment':
    case 'payment_processing':
      return 'warning';
    case 'cancelled':
    case 'refunded':
    case 'returned':
    case 'payment_failed':
      return 'destructive';
    default:
      return 'secondary';
  }
}

export function getPaymentStatusVariant(status: PaymentStatus): BadgeVariant {
  switch (status) {
    case 'paid':
      return 'success';
    case 'failed':
      return 'destructive';
    case 'refunded':
    case 'partially_refunded':
      return 'warning';
    default:
      return 'secondary';
  }
}

export function humanizeStatus(value: string): string {
  return value.replace(/_/g, ' ').toUpperCase();
}

function OrderCard({ order }: { order: Order }) {
  const queryClient = useQueryClient();
  const [confirming, setConfirming] = React.useState(false);
  const [trackingOpen, setTrackingOpen] = React.useState(false);
  const [trackingData, setTrackingData] = React.useState<OrderTrackingPayload | null>(null);
  const [trackingLoading, setTrackingLoading] = React.useState(false);

  const handleToggleTracking = async () => {
    const nextOpen = !trackingOpen;
    setTrackingOpen(nextOpen);
    if (nextOpen && !trackingData) {
      setTrackingLoading(true);
      try {
        const res = await fetchOrderTracking(order.orderNumber);
        setTrackingData(res);
      } catch {
        // Handled gracefully
      } finally {
        setTrackingLoading(false);
      }
    }
  };

  const cancelMutation = useMutation({
    mutationFn: () => cancelCustomerOrder(order.orderNumber, 'Cancelled by customer'),
    onSuccess: () => {
      setConfirming(false);
      queryClient.invalidateQueries({ queryKey: ['customer-orders'] });
    },
  });

  return (
    <Card>
      <CardHeader>
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div>
            <CardTitle>Order #{order.orderNumber}</CardTitle>
            <p className="text-xs text-muted">
              Placed on{' '}
              {new Date(order.createdAt).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
              })}
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant={getOrderStatusVariant(order.status)}>
              {humanizeStatus(order.status)}
            </Badge>
            {order.payment_status && (
              <Badge variant={getPaymentStatusVariant(order.payment_status)}>
                PAYMENT: {humanizeStatus(order.payment_status)}
              </Badge>
            )}
            <span className="text-base font-bold" data-testid={`order-total-${order.id}`}>
              {formatMoney(order.total)}
            </span>
          </div>
        </div>
      </CardHeader>
      <CardContent className="flex flex-col gap-3">
        <Separator style={{ margin: '0.25rem 0' }} />
        <div className="flex flex-col gap-2">
          {order.items.map((item) => (
            <div key={item.id} className="flex items-center justify-between text-sm">
              <div>
                <span className="font-medium">
                  {item.quantity} × {item.productName}
                </span>
                <span className="ml-2 text-xs text-muted">
                  ({item.color} / {item.size} · SKU: {item.sku})
                </span>
              </div>
              <span className="font-medium">{formatMoney(item.totalPrice)}</span>
            </div>
          ))}
        </div>
        <div className="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-md bg-muted p-3 text-xs text-muted">
          <div>
            <strong>Ship to:</strong> {order.shippingAddress.fullName},{' '}
            {order.shippingAddress.addressLine1}, {order.shippingAddress.city}{' '}
            {order.shippingAddress.postalCode} ({order.shippingAddress.phone})
          </div>
          {['shipped', 'delivered', 'returned'].includes(order.status) && (
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => void handleToggleTracking()}
            >
              {trackingOpen ? 'Hide Tracking' : 'Track Shipment'}
            </Button>
          )}
        </div>

        {trackingOpen && (
          <div className="rounded-md border border-neutral-200 bg-white p-3 text-xs">
            {trackingLoading ? (
              <p className="text-neutral-500">Loading courier tracking timeline...</p>
            ) : !trackingData || trackingData.shipments.length === 0 ? (
              <p className="text-neutral-500">No courier consignment updates recorded yet.</p>
            ) : (
              trackingData.shipments.map((shipment) => (
                <div key={shipment.id} className="space-y-1.5">
                  <div className="flex flex-wrap items-center justify-between gap-2 font-semibold text-neutral-900">
                    <span>
                      Courier: {shipment.courier.toUpperCase()} · Consignment:{' '}
                      {shipment.consignment_id || shipment.tracking_number || `#${shipment.id}`}
                    </span>
                    <Badge variant="secondary">{humanizeStatus(shipment.status)}</Badge>
                  </div>
                  {(shipment.tracking_events ?? []).map((ev) => (
                    <div
                      key={ev.id}
                      className="flex items-center justify-between border-t border-neutral-100 pt-1 text-[11px] text-neutral-600"
                    >
                      <span>
                        <strong>{humanizeStatus(ev.internal_status)}</strong> —{' '}
                        {ev.description || ev.external_status}
                      </span>
                      <span className="font-mono text-neutral-400">
                        {ev.occurred_at || ''}
                      </span>
                    </div>
                  ))}
                </div>
              ))
            )}
          </div>
        )}

        {cancelMutation.error && (
          <Alert variant="destructive" role="alert">
            {cancelMutation.error.message}
          </Alert>
        )}

        {isCancellableByCustomer(order) && (
          <div className="flex flex-wrap items-center gap-3">
            {confirming ? (
              <>
                <span className="text-sm">
                  Cancel this order? Reserved stock will be released.
                </span>
                <Button
                  type="button"
                  variant="destructive"
                  size="sm"
                  disabled={cancelMutation.isPending}
                  onClick={() => cancelMutation.mutate()}
                >
                  {cancelMutation.isPending ? 'Cancelling…' : 'Yes, cancel order'}
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  disabled={cancelMutation.isPending}
                  onClick={() => setConfirming(false)}
                >
                  Keep order
                </Button>
              </>
            ) : (
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setConfirming(true)}
              >
                Cancel order
              </Button>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

export function OrderHistoryView({ initialOrders }: { initialOrders?: Order[] }) {
  const {
    data,
    isLoading,
    error,
    refetch,
  } = useQuery<Order[]>({
    queryKey: ['customer-orders'],
    queryFn: ({ signal }) => fetchCustomerOrders(signal),
    initialData: initialOrders?.map(normalizeOrder),
    staleTime: 0,
  });

  const orders = data ?? initialOrders?.map(normalizeOrder) ?? [];

  if (isLoading && orders.length === 0) {
    return (
      <div aria-busy="true" aria-label="Loading order history" className="flex flex-col gap-4">
        <Skeleton style={{ width: '100%', height: '9rem' }} />
        <Skeleton style={{ width: '100%', height: '9rem' }} />
      </div>
    );
  }

  if (error && orders.length === 0) {
    return (
      <ErrorState
        title="We couldn't load your orders"
        message={
          error.message.includes('401') || /unauthenticated/i.test(error.message)
            ? 'Please sign in to view your order history.'
            : error.message
        }
        onRetry={() => void refetch()}
      />
    );
  }

  if (orders.length === 0) {
    return (
      <EmptyState
        icon={<Package size={28} />}
        title="No orders placed yet"
        description="Once you place an order, you can track its fulfillment status, warehouse dispatch, and invoice summary here."
        actionLabel="Explore New Arrivals"
        actionHref="/products"
      />
    );
  }

  return (
    <div className="flex flex-col gap-4" aria-label="Customer order history">
      {orders.map((order) => (
        <OrderCard key={order.id} order={order} />
      ))}
    </div>
  );
}
