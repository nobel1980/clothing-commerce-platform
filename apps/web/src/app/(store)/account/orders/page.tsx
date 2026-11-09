import * as React from 'react';
import type { Metadata } from 'next';
import Link from 'next/link';
import { OrderHistoryView } from '@/features/orders/components/OrderHistoryView';
import { buildCanonicalUrl } from '@/lib/utils/format';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: 'Order History',
  description: 'Track your Atelier Véra orders, fulfillment status, and invoices.',
  alternates: {
    canonical: buildCanonicalUrl('/account/orders'),
  },
  robots: {
    index: false,
    follow: false,
  },
};

export default function AccountOrdersPage() {
  return (
    <div className="store-container py-8">
      <nav aria-label="Breadcrumb" className="mb-4 text-xs text-muted">
        <Link href="/account">My Account</Link> /{' '}
        <span className="font-semibold">Order History</span>
      </nav>

      <header className="mb-8">
        <p className="text-xs font-semibold uppercase tracking-wider text-muted">
          Purchases & Fulfillment
        </p>
        <h1 className="mt-1 text-3xl font-bold tracking-tight">Order History</h1>
      </header>

      <OrderHistoryView />
    </div>
  );
}

