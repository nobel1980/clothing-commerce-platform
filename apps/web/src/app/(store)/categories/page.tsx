import * as React from 'react';
import type { Metadata } from 'next';
import Link from 'next/link';
import { ArrowRight, Layers } from 'lucide-react';
import type { CatalogCategory } from '@clothing-commerce/types';
import { fetchStoreCategories } from '@/features/catalog/api/catalogApi';
import { buildCanonicalUrl } from '@/lib/utils/format';
import { Badge } from '@/components/ui/badge';
import { EmptyState } from '@/components/shared/EmptyState';

export const metadata: Metadata = {
  title: 'Categories & Collections',
  description: 'Browse Atelier Véra ready-to-wear categories, outerwear, knitwear, and essentials.',
  alternates: {
    canonical: buildCanonicalUrl('/categories'),
  },
};

export default async function CategoriesListingPage() {
  let categories: CatalogCategory[] = [];
  let errorMessage: string | null = null;

  try {
    categories = await fetchStoreCategories();
  } catch (err) {
    errorMessage = err instanceof Error ? err.message : 'Unable to load categories.';
  }

  return (
    <div className="store-container py-8">
      <header className="mb-8">
        <p className="text-xs font-semibold uppercase tracking-wider text-muted">
          Atelier Directory
        </p>
        <h1 className="mt-1 text-3xl font-bold tracking-tight">Categories & Collections</h1>
        <p className="mt-2 text-sm text-muted" style={{ maxWidth: '38rem' }}>
          Explore our modular wardrobe collections organized by silhouette and garment type.
        </p>
      </header>

      {categories.length === 0 ? (
        <EmptyState
          icon={<Layers size={28} />}
          title="No active categories available"
          description={
            errorMessage ||
            'Once categories are activated in the catalog backoffice, they will appear here.'
          }
          actionLabel="Browse All Products"
          actionHref="/products"
        />
      ) : (
        <div className="category-grid">
          {categories.map((category) => (
            <article
              key={category.id}
              className="flex flex-col justify-between rounded-xl border bg-surface p-6"
              style={{
                backgroundColor: '#ffffff',
                border: '1px solid #e5e2dc',
                borderRadius: '0.875rem',
                padding: '1.5rem',
              }}
            >
              <div>
                <div className="flex items-center justify-between gap-2">
                  <Badge variant="secondary">Collection</Badge>
                  {category.children && category.children.length > 0 && (
                    <span className="text-xs text-muted">
                      {category.children.length} subcategories
                    </span>
                  )}
                </div>

                <h2 className="mt-3 text-xl font-bold">
                  <Link href={`/categories/${category.slug}`}>{category.name}</Link>
                </h2>

                {category.description && (
                  <p className="mt-2 text-sm text-muted">{category.description}</p>
                )}

                {category.children && category.children.length > 0 && (
                  <div className="mt-4 flex flex-wrap gap-2">
                    {category.children.map((sub) => (
                      <Link key={sub.id} href={`/categories/${sub.slug}`}>
                        <Badge variant="outline">{sub.name}</Badge>
                      </Link>
                    ))}
                  </div>
                )}
              </div>

              <div className="mt-6 border-t pt-4">
                <Link
                  href={`/categories/${category.slug}`}
                  className="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wider"
                >
                  Shop {category.name} <ArrowRight size={14} />
                </Link>
              </div>
            </article>
          ))}
        </div>
      )}
    </div>
  );
}
