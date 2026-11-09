import type {
  CatalogCategory,
  CatalogProduct,
  PaginatedResponse,
  ProductFilterParams,
  VariantAvailability,
} from '@clothing-commerce/types';
import { apiRequest } from '@/lib/api/client';

export async function fetchStoreCategories(): Promise<CatalogCategory[]> {
  const response = await apiRequest<{ data: CatalogCategory[] }>('/catalog/categories', {
    tags: ['catalog', 'categories'],
    revalidate: 120,
  });
  return response.data ?? [];
}

export async function fetchStoreCategoryBySlug(slug: string): Promise<CatalogCategory | null> {
  const response = await apiRequest<{ data: CatalogCategory }>(
    `/catalog/categories/${encodeURIComponent(slug)}`,
    {
      tags: ['catalog', 'categories', `category-${slug}`],
      revalidate: 120,
    }
  );
  return response.data ?? null;
}

export async function fetchStoreProducts(
  filters: ProductFilterParams = {}
): Promise<PaginatedResponse<CatalogProduct>> {
  const params = new URLSearchParams();

  if (filters.search) params.set('search', filters.search);
  if (filters.category) params.set('category', filters.category);
  if (filters.brand) params.set('brand', filters.brand);
  if (filters.color) params.set('color', filters.color);
  if (filters.size) params.set('size', filters.size);
  if (filters.min_price !== undefined) params.set('min_price', String(filters.min_price));
  if (filters.max_price !== undefined) params.set('max_price', String(filters.max_price));
  if (filters.featured) params.set('featured', '1');
  if (filters.sort) params.set('sort', filters.sort);
  if (filters.page && filters.page > 1) params.set('page', String(filters.page));
  if (filters.per_page) params.set('per_page', String(filters.per_page));

  const qs = params.toString();
  const endpoint = qs ? `/catalog/products?${qs}` : '/catalog/products';

  return apiRequest<PaginatedResponse<CatalogProduct>>(endpoint, {
    tags: ['catalog', 'products'],
    revalidate: 60,
  });
}

export async function fetchStoreProductBySlug(slug: string): Promise<CatalogProduct | null> {
  const response = await apiRequest<{ data: CatalogProduct }>(
    `/catalog/products/${encodeURIComponent(slug)}`,
    {
      tags: ['catalog', 'products', `product-${slug}`],
      revalidate: 60,
    }
  );
  return response.data ?? null;
}

export async function fetchVariantAvailability(
  variantId: number,
  signal?: AbortSignal
): Promise<VariantAvailability> {
  const response = await apiRequest<{ data: VariantAvailability }>(
    `/inventory/variants/${variantId}/availability`,
    {
      isPrivate: true,
      signal,
    }
  );
  return response.data;
}

