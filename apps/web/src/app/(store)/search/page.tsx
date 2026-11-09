import * as React from 'react';
import type { Metadata } from 'next';
import { Search as SearchIcon } from 'lucide-react';
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

interface SearchPageProps {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}

export async function generateMetadata({ searchParams }: SearchPageProps): Promise<Metadata> {
  const raw = await searchParams;
  const query =
    typeof raw.q === 'string'
      ? raw.q
      : typeof raw.search === 'string'
        ? raw.search
        : undefined;

  return {
    title: query ? `Search results for “${query}”` : 'Search Catalog & Archive',
    description: 'Search Atelier Véra garments by name, fabric, category, or SKU.',
    alternates: {
      canonical: buildCanonicalUrl('/search', { q: query }),
    },
  };
}

export default async function StoreSearchPage({ searchParams }: SearchPageProps) {
  const raw = await searchParams;
  const queryText =
    typeof raw.q === 'string'
      ? raw.q
      : typeof raw.search === 'string'
        ? raw.search
        : undefined;

  const filters: ProductFilterParams = {
    search: queryText,
    category: typeof raw.category === 'string' ? raw.category : undefined,
    color: typeof raw.color === 'string' ? raw.color : undefined,
    size: typeof raw.size === 'string' ? raw.size : undefined,
    sort: (typeof raw.sort === 'string' ? raw.sort : 'newest') as ProductSortOption,
    page: typeof raw.page === 'string' ? Math.max(1, Number(raw.page)) : 1,
  };

  let productsResponse: PaginatedResponse<CatalogProduct> = { data: [], meta: undefined };
  let categories: CatalogCategory[] = [];

  try {
    const [prodRes, catRes] = await Promise.all([
      fetchStoreProducts(filters),
      fetchStoreCategories(),
    ]);
    productsResponse = prodRes;
    categories = catRes;
  } catch {
    // Fallback to empty state
  }

  const products = productsResponse.data ?? [];

  return (
    <div className="store-container py-8">
      <header className="mb-6">
        <p className="text-xs font-semibold uppercase tracking-wider text-muted">
          Atelier Search
        </p>
        <h1 className="mt-1 text-3xl font-bold tracking-tight">
          {queryText ? `Results for “${queryText}”` : 'Search the Archive'}
        </h1>
      </header>

      <ProductFiltersBar
        categories={categories}
        currentFilters={filters}
        totalResults={productsResponse.meta?.total ?? products.length}
      />

      {products.length === 0 ? (
        <EmptyState
          icon={<SearchIcon size={28} />}
          title={
            queryText
              ? `No garments matched “${queryText}”`
              : 'No garments matched your search'
          }
          description="Try searching by garment type (e.g., tee, shirt, hoodie, denim), color, or variant SKU."
          actionLabel="Browse Full Collection"
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
            basePath="/search"
            searchParams={{
              search: filters.search,
              category: filters.category,
              color: filters.color,
              size: filters.size,
              sort: filters.sort,
            }}
          />
        </>
      )}
    </div>
  );
}
