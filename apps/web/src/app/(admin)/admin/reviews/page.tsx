import type { Metadata } from 'next';
import { AdminShell } from '@/features/admin/components/AdminShell';
import { ReviewsAdminView } from '@/features/admin/components/ShippingAndReviewsAdminView';

export const metadata: Metadata = {
  title: 'Product Reviews Moderation',
};

export default function AdminReviewsPage() {
  return (
    <AdminShell
      requiredPermissions={['reviews.view']}
      title="Product Reviews Moderation"
      subtitle="Moderate verified buyer ratings, approve or reject reviews, and export feedback"
    >
      <ReviewsAdminView />
    </AdminShell>
  );
}

