import type { Order, ShippingAddress } from '@clothing-commerce/types';
import { apiRequest } from '@/lib/api/client';

export interface PlaceOrderPayload {
  idempotency_key: string;
  items: Array<{ variant_id: number; quantity: number }>;
  shipping_address: ShippingAddress;
  shipping_method: 'standard' | 'express' | 'pickup';
  payment_method: 'cod' | 'card' | 'bkash';
  promo_code?: string | null;
}

export async function placeStoreOrder(payload: PlaceOrderPayload): Promise<Order> {
  const response = await apiRequest<{ data: Order }>('/checkout/orders', {
    method: 'POST',
    body: payload,
    isPrivate: true,
  });
  return response.data;
}

export async function fetchCustomerOrders(signal?: AbortSignal): Promise<Order[]> {
  try {
    const response = await apiRequest<{ data: Order[] }>('/account/orders', {
      isPrivate: true,
      signal,
    });
    return response.data ?? [];
  } catch {
    return [];
  }
}

