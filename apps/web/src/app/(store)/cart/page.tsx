import * as React from 'react';
import type { Metadata } from 'next';
import { CartView } from '@/features/cart/components/CartView';
import { buildCanonicalUrl } from '@/lib/utils/format';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: 'Shopping Bag',
  description: 'Review your selected Atelier Véra garments and authoritative order quote.',
  alternates: {
    canonical: buildCanonicalUrl('/cart'),
  },
  robots: {
    index: false,
    follow: false,
  },
};

export default function StoreCartPage() {
  return (
    <div className="store-container py-8">
      <header className="mb-8">
        <p className="text-xs font-semibold uppercase tracking-wider text-muted">
          Your Selection
        </p>
        <h1 className="mt-1 text-3xl font-bold tracking-tight">Shopping Bag</h1>
      </header>

      <CartView />
    </div>
  );
}

