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

