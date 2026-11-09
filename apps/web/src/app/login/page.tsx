import * as React from 'react';
import type { Metadata } from 'next';
import { LoginForm } from '@/features/customers/components/AuthForms';
import { StoreHeader } from '@/components/layout/StoreHeader';
import { StoreFooter } from '@/components/layout/StoreFooter';
import { buildCanonicalUrl } from '@/lib/utils/format';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: 'Sign In',
  description: 'Sign in to your Atelier Véra customer account.',
  alternates: {
    canonical: buildCanonicalUrl('/login'),
  },
  robots: {
    index: false,
    follow: false,
  },
};

export default function LoginPage() {
  return (
    <div className="flex min-h-screen flex-col">
      <StoreHeader />
      <main className="store-container flex-1 py-12">
        <LoginForm />
      </main>
      <StoreFooter />
    </div>
  );
}

