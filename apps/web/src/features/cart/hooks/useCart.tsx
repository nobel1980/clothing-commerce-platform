'use client';

import * as React from 'react';
import type { CheckoutQuote } from '@clothing-commerce/types';
import { useQuery, useQueryClient } from '@/lib/query';
import { fetchCheckoutQuote } from '@/features/cart/api/cartApi';

export interface CartEntry {
  variantId: number;
  productId: number;
  slug?: string;
  sku: string;
  name: string;
  color: string | null;
  size: string | null;
  quantity: number;
  image?: string | null;
}

export type ShippingMethodOption = 'standard' | 'express' | 'pickup';

interface CartContextValue {
  entries: CartEntry[];
  totalItemCount: number;
  promoCode: string;
  shippingMethod: ShippingMethodOption;
  quote: CheckoutQuote | undefined;
  isQuoteLoading: boolean;
  quoteError: Error | null;
  addItem: (entry: Omit<CartEntry, 'quantity'> & { quantity?: number }) => void;
  updateQuantity: (variantId: number, quantity: number) => void;
  removeItem: (variantId: number) => void;
  clearCart: () => void;
  setPromoCode: (code: string) => void;
  setShippingMethod: (method: ShippingMethodOption) => void;
  refreshQuote: () => Promise<CheckoutQuote | undefined>;
}

const CartContext = React.createContext<CartContextValue | null>(null);

export function CartProvider({
  children,
  initialEntries = [],
}: {
  children: React.ReactNode;
  initialEntries?: CartEntry[];
}) {
  const [entries, setEntries] = React.useState<CartEntry[]>(initialEntries);
  const [promoCode, setPromoCodeState] = React.useState<string>('');
  const [shippingMethod, setShippingMethodState] =
    React.useState<ShippingMethodOption>('standard');

  const queryClient = useQueryClient();

  const quoteItems = React.useMemo(
    () =>
      entries.map((entry) => ({
        variant_id: entry.variantId,
        quantity: entry.quantity,
      })),
    [entries]
  );

  const {
    data: quote,
    isLoading: isQuoteLoading,
    error: quoteError,
    refetch: refreshQuote,
  } = useQuery<CheckoutQuote>({
    queryKey: ['checkout-quote', quoteItems, promoCode, shippingMethod],
    queryFn: ({ signal }) =>
      fetchCheckoutQuote(
        {
          items: quoteItems,
          promo_code: promoCode.trim() || null,
          shipping_method: shippingMethod,
        },
        signal
      ),
    enabled: entries.length > 0,
    staleTime: 5_000,
  });

  const addItem = React.useCallback(
    (newEntry: Omit<CartEntry, 'quantity'> & { quantity?: number }) => {
      const qtyToAdd = Math.max(1, newEntry.quantity ?? 1);
      setEntries((prev) => {
        const existingIndex = prev.findIndex((item) => item.variantId === newEntry.variantId);
        if (existingIndex === -1) {
          return [...prev, { ...newEntry, quantity: qtyToAdd }];
        }
        const updated = [...prev];
        const current = updated[existingIndex];
        if (current) {
          updated[existingIndex] = {
            ...current,
            quantity: Math.min(50, current.quantity + qtyToAdd),
          };
        }
        return updated;
      });
      queryClient.invalidateQueries({ queryKey: ['checkout-quote'] });
    },
    [queryClient]
  );

  const updateQuantity = React.useCallback(
    (variantId: number, quantity: number) => {
      setEntries((prev) => {
        if (quantity <= 0) {
          return prev.filter((item) => item.variantId !== variantId);
        }
        return prev.map((item) =>
          item.variantId === variantId
            ? { ...item, quantity: Math.min(50, quantity) }
            : item
        );
      });
      queryClient.invalidateQueries({ queryKey: ['checkout-quote'] });
    },
    [queryClient]
  );

  const removeItem = React.useCallback(
    (variantId: number) => {
      setEntries((prev) => prev.filter((item) => item.variantId !== variantId));
      queryClient.invalidateQueries({ queryKey: ['checkout-quote'] });
    },
    [queryClient]
  );

  const clearCart = React.useCallback(() => {
    setEntries([]);
    setPromoCodeState('');
    queryClient.invalidateQueries({ queryKey: ['checkout-quote'] });
  }, [queryClient]);

  const setPromoCode = React.useCallback(
    (code: string) => {
      setPromoCodeState(code);
      queryClient.invalidateQueries({ queryKey: ['checkout-quote'] });
    },
    [queryClient]
  );

  const setShippingMethod = React.useCallback(
    (method: ShippingMethodOption) => {
      setShippingMethodState(method);
      queryClient.invalidateQueries({ queryKey: ['checkout-quote'] });
    },
    [queryClient]
  );

  const totalItemCount = React.useMemo(
    () => entries.reduce((acc, item) => acc + item.quantity, 0),
    [entries]
  );

  const value = React.useMemo<CartContextValue>(
    () => ({
      entries,
      totalItemCount,
      promoCode,
      shippingMethod,
      quote: entries.length > 0 ? quote : undefined,
      isQuoteLoading,
      quoteError,
      addItem,
      updateQuantity,
      removeItem,
      clearCart,
      setPromoCode,
      setShippingMethod,
      refreshQuote,
    }),
    [
      entries,
      totalItemCount,
      promoCode,
      shippingMethod,
      quote,
      isQuoteLoading,
      quoteError,
      addItem,
      updateQuantity,
      removeItem,
      clearCart,
      setPromoCode,
      setShippingMethod,
      refreshQuote,
    ]
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart(): CartContextValue {
  const context = React.useContext(CartContext);
  if (!context) {
    throw new Error('useCart must be used within a CartProvider');
  }
  return context;
}

