import * as React from 'react';
import type { Metadata } from 'next';
import Link from 'next/link';
import { ArrowRight, Sparkles, Layers } from 'lucide-react';
import type { CatalogCategory, CatalogProduct } from '@clothing-commerce/types';
import { fetchStoreCategories, fetchStoreProducts } from '@/features/catalog/api/catalogApi';
import { ProductCard } from '@/features/catalog/components/ProductCard';
import { buildCanonicalUrl } from '@/lib/utils/format';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { EmptyState } from '@/components/shared/EmptyState';

export const metadata: Metadata = {
  title: 'Atelier Véra | Contemporary Ready-to-Wear & Tailored Essentials',
  description:
    'Explore modern ready-to-wear silhouettes, organic heavyweight jersey, and structured outerwear.',
  alternates: {
    canonical: buildCanonicalUrl('/'),
  },
};

export default async function StoreHomePage() {
  let featuredProducts: CatalogProduct[] = [];
  let latestProducts: CatalogProduct[] = [];
  let categories: CatalogCategory[] = [];
  let apiErrorMessage: string | null = null;

  try {
    const [featuredRes, latestRes, categoriesRes] = await Promise.all([
      fetchStoreProducts({ featured: true, per_page: 4 }),
      fetchStoreProducts({ sort: 'newest', per_page: 8 }),
      fetchStoreCategories(),
    ]);
    featuredProducts = featuredRes.data ?? [];
    latestProducts = latestRes.data ?? [];
    categories = categoriesRes ?? [];
  } catch (err) {
    apiErrorMessage =
      err instanceof Error ? err.message : 'Unable to reach the catalog service.';
  }

  return (
    <div>
      {/* Editorial Hero Banner */}
      <section
        aria-label="Seasonal collection hero"
        className="border-b"
        style={{
          background: 'linear-gradient(135deg, #18181b 0%, #27272a 100%)',
          color: '#fafafa',
          padding: '4.5rem 0',
        }}
      >
        <div className="store-container">
          <div style={{ maxWidth: '42rem' }}>
            <Badge
              variant="secondary"
              style={{
                backgroundColor: 'rgba(255, 255, 255, 0.14)',
                color: '#fafafa',
                marginBottom: '1rem',
              }}
            >
              Autumn / Winter Archive
            </Badge>
            <h1
              className="text-4xl font-bold tracking-tight"
              style={{
                fontSize: 'clamp(2.25rem, 4vw, 3.5rem)',
                lineHeight: 1.1,
                marginBottom: '1.25rem',
              }}
            >
              Architectural silhouettes for everyday movement.
            </h1>
            <p
              style={{
                color: '#d4d4d8',
                fontSize: '1.0625rem',
                marginBottom: '2rem',
                maxWidth: '36rem',
              }}
            >
              Thoughtfully constructed garments milled from 280–450 GSM organic cotton,
              merino wool, and Japanese technical twill.
            </p>
            <div className="flex flex-wrap gap-3">
              <Link href="/products">
                <Button
                  size="lg"
                  style={{
                    backgroundColor: '#fafafa',
                    color: '#18181b',
                    borderColor: '#fafafa',
                  }}
                >
                  Shop Collection
                  <ArrowRight size={16} />
                </Button>
              </Link>
              <Link href="/categories">
                <Button
                  variant="outline"
                  size="lg"
                  style={{
                    borderColor: 'rgba(255, 255, 255, 0.35)',
                    color: '#fafafa',
                  }}
                >
                  Explore Categories
                </Button>
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* Curated Categories */}
      <section aria-labelledby="home-categories-heading" className="store-container py-12">
        <div className="mb-6 flex items-end justify-between gap-4">
          <div>
            <p className="text-xs font-semibold uppercase tracking-wider text-muted">
              Wardrobe Pillars
            </p>
            <h2 id="home-categories-heading" className="text-2xl font-bold tracking-tight">
              Shop by Category
            </h2>
          </div>
          <Link href="/categories" className="text-sm font-semibold">
            View All Categories →
          </Link>
        </div>

        {categories.length === 0 ? (
          <EmptyState
            icon={<Layers size={24} />}
            title="Categories are being curated"
            description={
              apiErrorMessage ||
              'Our seasonal categories will appear here as soon as they are published in the catalog.'
            }
            actionLabel="Browse All Products"
            actionHref="/products"
          />
        ) : (
          <div className="category-grid">
            {categories.slice(0, 6).map((cat) => (
              <Link
                key={cat.id}
                href={`/categories/${cat.slug}`}
                className="flex flex-col justify-between rounded-xl border bg-surface p-6"
                style={{
                  backgroundColor: '#ffffff',
                  border: '1px solid #e5e2dc',
                  borderRadius: '0.875rem',
                  minHeight: '9.5rem',
                  padding: '1.5rem',
                }}
              >
                <div>
                  <span className="text-xs uppercase tracking-wider text-muted">Collection</span>
                  <h3 className="mt-1 text-xl font-bold">{cat.name}</h3>
                  {cat.description && (
                    <p className="mt-1 text-xs text-muted">{cat.description}</p>
                  )}
                </div>
                <span className="mt-4 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wider">
                  Explore {cat.name} <ArrowRight size={13} />
                </span>
              </Link>
            ))}
          </div>
        )}
      </section>

      {/* Featured Essentials */}
      {featuredProducts.length > 0 && (
        <section aria-labelledby="home-featured-heading" className="store-container py-8">
          <div className="mb-6 flex items-end justify-between gap-4">
            <div>
              <p className="text-xs font-semibold uppercase tracking-wider text-muted">
                Curated Selection
              </p>
              <h2 id="home-featured-heading" className="text-2xl font-bold tracking-tight">
                Featured Essentials
              </h2>
            </div>
            <Link href="/products?featured=1" className="text-sm font-semibold">
              View Featured →
            </Link>
          </div>

          <div className="product-grid">
            {featuredProducts.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        </section>
      )}

      {/* New Arrivals */}
      <section aria-labelledby="home-new-arrivals-heading" className="store-container py-12">
        <div className="mb-6 flex items-end justify-between gap-4">
          <div>
            <p className="text-xs font-semibold uppercase tracking-wider text-muted">
              Latest Release
            </p>
            <h2 id="home-new-arrivals-heading" className="text-2xl font-bold tracking-tight">
              New Arrivals
            </h2>
          </div>
          <Link href="/products" className="text-sm font-semibold">
            View Full Catalog →
          </Link>
        </div>

        {latestProducts.length === 0 ? (
          <EmptyState
            icon={<Sparkles size={24} />}
            title="No published garments found"
            description={
              apiErrorMessage ||
              'Published products from the Laravel Catalog API will appear here automatically.'
            }
            actionLabel="Search Archive"
            actionHref="/search"
          />
        ) : (
          <div className="product-grid">
            {latestProducts.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        )}
      </section>
    </div>
  );
}
