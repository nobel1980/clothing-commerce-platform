'use client';

import * as React from 'react';
import { Package } from 'lucide-react';
import type { Order, OrderStatus } from '@clothing-commerce/types';
import { useQuery } from '@/lib/query';
import { fetchCustomerOrders } from '@/features/orders/api/ordersApi';
import { formatMoney } from '@/lib/utils/format';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { EmptyState } from '@/components/shared/EmptyState';

function getOrderStatusVariant(
  status: OrderStatus
): 'default' | 'secondary' | 'success' | 'warning' | 'destructive' {
  switch (status) {
    case 'paid':
    case 'delivered':
      return 'success';
    case 'processing':
    case 'shipped':
    case 'payment_processing':
      return 'warning';
    case 'cancelled':
    case 'refunded':
      return 'destructive';
    default:
      return 'secondary';
  }
}

export function OrderHistoryView({ initialOrders }: { initialOrders?: Order[] }) {
  const { data: orders = initialOrders ?? [], isLoading } = useQuery<Order[]>({
    queryKey: ['customer-orders'],
    queryFn: ({ signal }) => fetchCustomerOrders(signal),
    initialData: initialOrders,
    staleTime: 0,
  });

  if (isLoading && orders.length === 0) {
    return (
      <div aria-busy="true" aria-label="Loading order history" className="flex flex-col gap-4">
        <Skeleton style={{ width: '100%', height: '9rem' }} />
        <Skeleton style={{ width: '100%', height: '9rem' }} />
      </div>
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
        <Card key={order.id}>
          <CardHeader>
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <CardTitle>Order #{order.orderNumber}</CardTitle>
                <p className="text-xs text-muted">
                  Placed on {new Date(order.createdAt).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                  })}
                </p>
              </div>
              <div className="flex items-center gap-3">
                <Badge variant={getOrderStatusVariant(order.status)}>
                  {order.status.replace('_', ' ').toUpperCase()}
                </Badge>
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
                <div
                  key={item.id}
                  className="flex items-center justify-between text-sm"
                >
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
            <div className="mt-2 rounded-md bg-muted p-3 text-xs text-muted">
              <strong>Ship to:</strong> {order.shippingAddress.fullName},{' '}
              {order.shippingAddress.addressLine1}, {order.shippingAddress.city}{' '}
              {order.shippingAddress.postalCode} ({order.shippingAddress.phone})
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}

