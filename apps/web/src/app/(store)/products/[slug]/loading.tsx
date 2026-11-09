import * as React from 'react';
import { ProductDetailSkeleton } from '@/components/shared/LoadingSkeletons';

export default function ProductDetailLoading() {
  return (
    <div className="store-container py-8">
      <ProductDetailSkeleton />
    </div>
  );
}

