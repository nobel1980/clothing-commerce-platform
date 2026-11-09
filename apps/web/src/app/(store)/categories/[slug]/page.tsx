import * as React from 'react';
import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import type {
  CatalogCategory,
  CatalogProduct,
  PaginatedResponse,
  ProductFilterParams,
  ProductSortOption,
} from '@clothing-commerce/types';
import {
  fetchStoreCategories,
  fetchStoreCategoryBySlug,
  fetchStoreProducts,
} from '@/features/catalog/api/catalogApi';
import { ProductCard } from '@/features/catalog/components/ProductCard';
import { ProductFiltersBar } from '@/features/catalog/components/ProductFiltersBar';
import { PaginationNav } from '@/components/shared/PaginationNav';
import { EmptyState } from '@/components/shared/EmptyState';
import { ApiClientError } from '@/lib/api/client';
import { buildCanonicalUrl } from '@/lib/utils/format';

interface CategoryPageProps {
  params: Promise<{ slug: string }>;
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}

export async function generateMetadata({ params, searchParams }: CategoryPageProps): Promise<Metadata> {
  const { slug } = await params;
  const resolvedSearch = await searchParams;
  const page = typeof resolvedSearch.page === 'string' ? Number(resolvedSearch.page) : undefined;

  try {
    const category = await fetchStoreCategoryBySlug(slug);
    if (!category) {
      return { title: 'Category Not Found' };
    }

    const title = category.meta_title || `${category.name} Collection`;
    const description =
      category.meta_description ||
      category.description ||
      `Shop ${category.name} garments and tailored essentials at Atelier Véra.`;

    return {
      title,
      description,
      alternates: {
        canonical: buildCanonicalUrl(`/categories/${slug}`, { page }),
      },
      openGraph: {
        title,
        description,
        url: buildCanonicalUrl(`/categories/${slug}`),
      },
    };
  } catch {
    return {
      title: `${slug} Collection`,
      alternates: {
        canonical: buildCanonicalUrl(`/categories/${slug}`, { page }),
      },
    };
  }
}

export default async function CategoryDetailPage({ params, searchParams }: CategoryPageProps) {
  const { slug } = await params;
  const rawQuery = await searchParams;

  let category = null;
  try {
    category = await fetchStoreCategoryBySlug(slug);
  } catch (err) {
    if (err instanceof ApiClientError && err.status === 404) {
      notFound();
    }
  }

  const filters: ProductFilterParams = {
    category: slug,
    search: typeof rawQuery.search === 'string' ? rawQuery.search : undefined,
    color: typeof rawQuery.color === 'string' ? rawQuery.color : undefined,
    size: typeof rawQuery.size === 'string' ? rawQuery.size : undefined,
    sort: (typeof rawQuery.sort === 'string' ? rawQuery.sort : 'newest') as ProductSortOption,
    page: typeof rawQuery.page === 'string' ? Math.max(1, Number(rawQuery.page)) : 1,
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
    // Fallback to empty state when backend is unreachable
  }

  const products = productsResponse.data ?? [];

  return (
    <div className="store-container py-8">
      <nav aria-label="Breadcrumb" className="mb-4 text-xs text-muted">
        <Link href="/">Home</Link> / <Link href="/categories">Categories</Link> /{' '}
        <span className="font-semibold">{category?.name || slug}</span>
      </nav>

      <header className="mb-8">
        <h1 className="text-3xl font-bold tracking-tight">
          {category?.name || slug.replace(/-/g, ' ')}
        </h1>
        {category?.description && (
          <p className="mt-2 text-sm text-muted" style={{ maxWidth: '42rem' }}>
            {category.description}
          </p>
        )}
      </header>

      <ProductFiltersBar
        categories={categories}
        currentFilters={filters}
        totalResults={productsResponse.meta?.total ?? products.length}
      />

      {products.length === 0 ? (
        <EmptyState
          title="No garments match these filters"
          description="Try clearing your color or size filters, or browse all ready-to-wear pieces."
          actionLabel="View All Products"
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
            basePath={`/categories/${slug}`}
            searchParams={{
              search: filters.search,
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
