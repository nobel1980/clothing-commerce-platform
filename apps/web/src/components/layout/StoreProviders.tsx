'use client';

import * as React from 'react';
import { QueryClientProvider } from '@/lib/query';
import { CartProvider } from '@/features/cart/hooks/useCart';

export function StoreProviders({ children }: { children: React.ReactNode }) {
  return (
    <QueryClientProvider>
      <CartProvider>{children}</CartProvider>
    </QueryClientProvider>
  );
}

