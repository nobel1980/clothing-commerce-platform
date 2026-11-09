import * as React from 'react';
import type { Metadata } from 'next';
import { AccountDashboard } from '@/features/customers/components/AccountDashboard';
import { buildCanonicalUrl } from '@/lib/utils/format';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: 'My Account',
  description: 'Manage your Atelier Véra customer profile and saved shipping addresses.',
  alternates: {
    canonical: buildCanonicalUrl('/account'),
  },
  robots: {
    index: false,
    follow: false,
  },
};

export default function CustomerAccountPage() {
  return (
    <div className="store-container py-8">
      <header className="mb-8">
        <p className="text-xs font-semibold uppercase tracking-wider text-muted">
          Client Portal
        </p>
        <h1 className="mt-1 text-3xl font-bold tracking-tight">My Account</h1>
      </header>

      <AccountDashboard />
    </div>
  );
}

