import * as React from 'react';
import { Skeleton } from '@/components/ui/skeleton';

export function ProductGridSkeleton({ count = 8 }: { count?: number }) {
  return (
    <div
      aria-busy="true"
      aria-label="Loading products"
      className="product-grid"
    >
      {Array.from({ length: count }).map((_, index) => (
        <div
          key={index}
          className="flex flex-col gap-3 rounded-xl border bg-surface p-3"
          style={{
            backgroundColor: '#ffffff',
            border: '1px solid #e5e2dc',
            borderRadius: '0.75rem',
            padding: '0.75rem',
          }}
        >
          <Skeleton style={{ width: '100%', aspectRatio: '3 / 4', borderRadius: '0.5rem' }} />
          <Skeleton style={{ width: '40%', height: '0.875rem' }} />
          <Skeleton style={{ width: '80%', height: '1.125rem' }} />
          <Skeleton style={{ width: '35%', height: '1rem' }} />
        </div>
      ))}
    </div>
  );
}

export function ProductDetailSkeleton() {
  return (
    <div
      aria-busy="true"
      aria-label="Loading product details"
      className="two-col-layout py-8"
    >
      <div className="flex flex-col gap-4">
        <Skeleton style={{ width: '100%', aspectRatio: '4 / 5', borderRadius: '0.875rem' }} />
        <div className="flex gap-3">
          {Array.from({ length: 4 }).map((_, idx) => (
            <Skeleton key={idx} style={{ width: '5rem', height: '6rem', borderRadius: '0.5rem' }} />
          ))}
        </div>
      </div>
      <div className="flex flex-col gap-4">
        <Skeleton style={{ width: '30%', height: '1rem' }} />
        <Skeleton style={{ width: '85%', height: '2.25rem' }} />
        <Skeleton style={{ width: '40%', height: '1.5rem' }} />
        <Skeleton style={{ width: '100%', height: '5rem' }} />
        <Skeleton style={{ width: '60%', height: '2.5rem' }} />
        <Skeleton style={{ width: '100%', height: '3.25rem' }} />
      </div>
    </div>
  );
}

