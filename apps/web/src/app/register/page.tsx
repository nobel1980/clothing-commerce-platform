import * as React from 'react';
import type { Metadata } from 'next';
import { RegisterForm } from '@/features/customers/components/AuthForms';
import { StoreHeader } from '@/components/layout/StoreHeader';
import { StoreFooter } from '@/components/layout/StoreFooter';
import { buildCanonicalUrl } from '@/lib/utils/format';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: 'Create Account',
  description: 'Create an Atelier Véra customer account.',
  alternates: {
    canonical: buildCanonicalUrl('/register'),
  },
  robots: {
    index: false,
    follow: false,
  },
};

export default function RegisterPage() {
  return (
    <div className="flex min-h-screen flex-col">
      <StoreHeader />
      <main className="store-container flex-1 py-12">
        <RegisterForm />
      </main>
      <StoreFooter />
    </div>
  );
}

