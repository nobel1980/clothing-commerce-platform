'use client';

import type { VariantAvailability } from '@clothing-commerce/types';
import { useQuery } from '@/lib/query';
import { fetchVariantAvailability } from '@/features/catalog/api/catalogApi';

export function useVariantAvailability(variantId: number | null | undefined) {
  return useQuery<VariantAvailability>({
    queryKey: ['variant-availability', variantId],
    queryFn: ({ signal }) => fetchVariantAvailability(variantId as number, signal),
    enabled: typeof variantId === 'number' && variantId > 0,
    staleTime: 10_000,
  });
}

