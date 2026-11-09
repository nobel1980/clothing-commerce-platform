import type { CheckoutQuote } from '@clothing-commerce/types';
import { apiRequest } from '@/lib/api/client';

export interface QuoteRequestItem {
  variant_id: number;
  quantity: number;
}

export interface FetchCheckoutQuotePayload {
  items: QuoteRequestItem[];
  promo_code?: string | null;
  shipping_method?: 'standard' | 'express' | 'pickup';
  shipping_address?: {
    division?: string;
    district?: string;
    upazila_thana?: string;
  };
}

export interface BackendCartPayload {
  id: number;
  user_id: number | null;
  guest_token: string | null;
  status: string;
  currency: string;
  item_count: number;
  shipping_method: 'standard' | 'express' | 'pickup';
  coupon_code: string | null;
  subtotal: { amount: number; currency: string; formatted: string };
  discount: { amount: number; currency: string; formatted: string };
  shipping: { amount: number; currency: string; formatted: string };
  tax: { amount: number; currency: string; formatted: string };
  total: { amount: number; currency: string; formatted: string };
}

/**
 * Fetches authoritative line-item prices, subtotal, discount, shipping, tax, and total
 * from the Laravel backend `/api/checkout/quote` endpoint.
 */
export async function fetchCheckoutQuote(
  payload: FetchCheckoutQuotePayload,
  signal?: AbortSignal
): Promise<CheckoutQuote> {
  const response = await apiRequest<{ data: CheckoutQuote }>('/checkout/quote', {
    method: 'POST',
    body: payload,
    isPrivate: true,
    signal,
  });

  return response.data;
}

export async function mergeGuestCartWithAccount(guestToken: string): Promise<BackendCartPayload> {
  const response = await apiRequest<{ data: BackendCartPayload }>('/cart/merge', {
    method: 'POST',
    body: { guest_token: guestToken },
    isPrivate: true,
  });

  return response.data;
}

