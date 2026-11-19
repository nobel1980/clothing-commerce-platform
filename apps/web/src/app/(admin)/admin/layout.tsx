import type { Metadata } from 'next';
import { AdminAuthProvider } from '@/features/admin/hooks/useAdminAuth';

export const metadata: Metadata = {
  title: {
    default: 'Admin Backoffice | Atelier Dhaka',
    template: '%s | Admin Backoffice',
  },
  description:
    'Centralized commerce administration portal with role-based permissions, inventory control, order fulfilment, and real-time analytics.',
  robots: {
    index: false,
    follow: false,
  },
};

export default function AdminRootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return <AdminAuthProvider>{children}</AdminAuthProvider>;
}

