import type {
  Order,
  OrderStatus,
  PaymentGatewayId,
  ShippingAddress,
} from '@clothing-commerce/types';
import { apiRequest } from '@/lib/api/client';

/** Payment methods the storefront can submit to `/checkout/orders`. */
export type CheckoutPaymentMethod = PaymentGatewayId;

export const ONLINE_PAYMENT_METHODS: ReadonlyArray<CheckoutPaymentMethod> = [
  'bkash',
  'nagad',
  'sslcommerz',
];

export function isOnlinePaymentMethod(method: string): method is CheckoutPaymentMethod {
  return (ONLINE_PAYMENT_METHODS as ReadonlyArray<string>).includes(method);
}

/** Order states in which the backend lets a customer cancel (mirrors `OrderService`). */
const CUSTOMER_CANCELLABLE_STATUSES: ReadonlyArray<string> = [
  'pending',
  'pending_confirmation',
  'pending_payment',
  'awaiting_payment',
  'payment_failed',
  'confirmed',
];

export interface PlaceOrderPayload {
  idempotency_key: string;
  items: Array<{ variant_id: number; quantity: number }>;
  shipping_address: ShippingAddress;
  shipping_method: 'standard' | 'express' | 'pickup';
  payment_method: CheckoutPaymentMethod;
  promo_code?: string | null;
}

export interface PaymentInitiation {
  redirectUrl: string | null;
  transactionStatus: string;
  merchantReference: string;
  idempotentReplay: boolean;
}

/**
 * The API returns `status` upper-cased (e.g. `CONFIRMED`) for historical reasons.
 * Normalise once at the boundary so the UI only deals with the lower-case contract.
 */
export function normalizeOrder(order: Order): Order {
  return { ...order, status: String(order.status).toLowerCase() as OrderStatus };
}

export function isCancellableByCustomer(order: Pick<Order, 'status'>): boolean {
  return CUSTOMER_CANCELLABLE_STATUSES.includes(String(order.status).toLowerCase());
}

export async function placeStoreOrder(payload: PlaceOrderPayload): Promise<Order> {
  const response = await apiRequest<{ data: Order }>('/checkout/orders', {
    method: 'POST',
    body: payload,
    isPrivate: true,
    headers: { 'Idempotency-Key': payload.idempotency_key },
  });
  return normalizeOrder(response.data);
}

/**
 * Starts an online payment for an already-placed order. The amount is read from the
 * order by the backend; the client never sends a price. Safe to retry with the same key.
 */
export async function initiatePayment(
  orderNumber: string,
  gateway: CheckoutPaymentMethod,
  idempotencyKey: string
): Promise<PaymentInitiation> {
  const response = await apiRequest<{
    data: {
      transaction: { status: string; merchant_reference: string };
      redirect_url: string | null;
    };
    idempotent_replay: boolean;
  }>('/payments/initiate', {
    method: 'POST',
    body: { order_number: orderNumber, gateway },
    isPrivate: true,
    headers: { 'Idempotency-Key': idempotencyKey },
  });

  return {
    redirectUrl: response.data.redirect_url,
    transactionStatus: response.data.transaction.status,
    merchantReference: response.data.transaction.merchant_reference,
    idempotentReplay: response.idempotent_replay,
  };
}

/** Throws on failure so callers can render a real error state (no silent empty list). */
export async function fetchCustomerOrders(signal?: AbortSignal): Promise<Order[]> {
  const response = await apiRequest<{ data: Order[] }>('/orders', {
    isPrivate: true,
    signal,
  });
  return (response.data ?? []).map(normalizeOrder);
}

export async function cancelCustomerOrder(
  orderNumber: string,
  reason: string
): Promise<Order> {
  const response = await apiRequest<{ data: Order }>(
    `/orders/${encodeURIComponent(orderNumber)}/cancel`,
    {
      method: 'POST',
      body: { reason },
      isPrivate: true,
    }
  );
  return normalizeOrder(response.data);
}
