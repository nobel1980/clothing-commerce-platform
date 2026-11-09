import * as React from 'react';
import type { Metadata } from 'next';
import { CheckoutView } from '@/features/checkout/components/CheckoutView';
import { buildCanonicalUrl } from '@/lib/utils/format';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: 'Secure Checkout',
  description: 'Complete your Atelier Véra order with real-time stock reservation.',
  alternates: {
    canonical: buildCanonicalUrl('/checkout'),
  },
  robots: {
    index: false,
    follow: false,
  },
};

export default function StoreCheckoutPage() {
  return (
    <div className="store-container py-8">
      <header className="mb-8">
        <p className="text-xs font-semibold uppercase tracking-wider text-muted">
          Atelier Checkout
        </p>
        <h1 className="mt-1 text-3xl font-bold tracking-tight">Secure Checkout</h1>
      </header>

      <CheckoutView />
    </div>
  );
}

