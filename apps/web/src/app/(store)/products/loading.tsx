import * as React from 'react';
import { ProductGridSkeleton } from '@/components/shared/LoadingSkeletons';

export default function ProductsLoading() {
  return (
    <div className="store-container py-8">
      <ProductGridSkeleton count={8} />
    </div>
  );
}

