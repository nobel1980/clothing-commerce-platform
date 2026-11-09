import * as React from 'react';
import type { Metadata } from 'next';
import type {
  CatalogCategory,
  CatalogProduct,
  PaginatedResponse,
  ProductFilterParams,
  ProductSortOption,
} from '@clothing-commerce/types';
import { fetchStoreCategories, fetchStoreProducts } from '@/features/catalog/api/catalogApi';
import { ProductCard } from '@/features/catalog/components/ProductCard';
import { ProductFiltersBar } from '@/features/catalog/components/ProductFiltersBar';
import { PaginationNav } from '@/components/shared/PaginationNav';
import { EmptyState } from '@/components/shared/EmptyState';
import { buildCanonicalUrl } from '@/lib/utils/format';

interface ProductsPageProps {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}

export async function generateMetadata({ searchParams }: ProductsPageProps): Promise<Metadata> {
  const query = await searchParams;
  const category = typeof query.category === 'string' ? query.category : undefined;
  const page = typeof query.page === 'string' ? Number(query.page) : undefined;

  return {
    title: category ? `${category} — Ready-to-Wear Catalog` : 'All Ready-to-Wear & Essentials',
    description:
      'Filter and sort Atelier Véra garments by category, color, size, and price.',
    alternates: {
      canonical: buildCanonicalUrl('/products', { category, page }),
    },
  };
}

export default async function ProductsListingPage({ searchParams }: ProductsPageProps) {
  const rawQuery = await searchParams;

  const filters: ProductFilterParams = {
    search: typeof rawQuery.search === 'string' ? rawQuery.search : undefined,
    category: typeof rawQuery.category === 'string' ? rawQuery.category : undefined,
    brand: typeof rawQuery.brand === 'string' ? rawQuery.brand : undefined,
    color: typeof rawQuery.color === 'string' ? rawQuery.color : undefined,
    size: typeof rawQuery.size === 'string' ? rawQuery.size : undefined,
    min_price: typeof rawQuery.min_price === 'string' ? Number(rawQuery.min_price) : undefined,
    max_price: typeof rawQuery.max_price === 'string' ? Number(rawQuery.max_price) : undefined,
    featured: rawQuery.featured === '1' || rawQuery.featured === 'true',
    sort: (typeof rawQuery.sort === 'string' ? rawQuery.sort : 'newest') as ProductSortOption,
    page: typeof rawQuery.page === 'string' ? Math.max(1, Number(rawQuery.page)) : 1,
  };

  let productsResponse: PaginatedResponse<CatalogProduct> = { data: [], meta: undefined };
  let categories: CatalogCategory[] = [];
  let errorMessage: string | null = null;

  try {
    const [prodRes, catRes] = await Promise.all([
      fetchStoreProducts(filters),
      fetchStoreCategories(),
    ]);
    productsResponse = prodRes;
    categories = catRes;
  } catch (err) {
    errorMessage = err instanceof Error ? err.message : 'Unable to load catalog products.';
  }

  const products = productsResponse.data ?? [];

  return (
    <div className="store-container py-8">
      <header className="mb-6">
        <p className="text-xs font-semibold uppercase tracking-wider text-muted">
          Atelier Catalog
        </p>
        <h1 className="mt-1 text-3xl font-bold tracking-tight">Ready-to-Wear Collection</h1>
      </header>

      <ProductFiltersBar
        categories={categories}
        currentFilters={filters}
        totalResults={productsResponse.meta?.total ?? products.length}
      />

      {products.length === 0 ? (
        <EmptyState
          title="No matching garments found"
          description={
            errorMessage ||
            'No published products match your selected filters. Try adjusting your color, size, or category criteria.'
          }
          actionLabel="Reset Filters"
          actionHref="/products"
        />
      ) : (
        <>
          <div className="product-grid">
            {products.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>

          <PaginationNav
            meta={productsResponse.meta}
            basePath="/products"
            searchParams={{
              search: filters.search,
              category: filters.category,
              brand: filters.brand,
              color: filters.color,
              size: filters.size,
              sort: filters.sort,
              featured: filters.featured ? '1' : undefined,
            }}
          />
        </>
      )}
    </div>
  );
}
