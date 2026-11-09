import * as React from 'react';
import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { fetchStoreProductBySlug } from '@/features/catalog/api/catalogApi';
import { ProductImageGallery } from '@/features/catalog/components/ProductImageGallery';
import { ProductVariantSelector } from '@/features/catalog/components/ProductVariantSelector';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { ApiClientError } from '@/lib/api/client';
import { buildCanonicalUrl, buildProductJsonLd } from '@/lib/utils/format';

interface ProductDetailPageProps {
  params: Promise<{ slug: string }>;
}

export async function generateMetadata({ params }: ProductDetailPageProps): Promise<Metadata> {
  const { slug } = await params;
  const canonicalUrl = buildCanonicalUrl(`/products/${slug}`);

  try {
    const product = await fetchStoreProductBySlug(slug);
    if (!product) {
      return { title: 'Product Not Found' };
    }

    const title = product.meta_title || product.name;
    const description =
      product.meta_description ||
      product.description ||
      `Shop ${product.name} at Atelier Véra.`;
    const primaryImage =
      product.images?.find((img) => img.is_primary)?.url ?? product.images?.[0]?.url;

    return {
      title,
      description,
      keywords: product.meta_keywords || undefined,
      alternates: {
        canonical: canonicalUrl,
      },
      openGraph: {
        title,
        description,
        url: canonicalUrl,
        images: primaryImage ? [{ url: primaryImage, alt: product.name }] : undefined,
      },
    };
  } catch {
    return {
      title: slug.replace(/-/g, ' '),
      alternates: {
        canonical: canonicalUrl,
      },
    };
  }
}

export default async function ProductDetailPage({ params }: ProductDetailPageProps) {
  const { slug } = await params;

  let product = null;
  try {
    product = await fetchStoreProductBySlug(slug);
  } catch (err) {
    if (err instanceof ApiClientError && err.status === 404) {
      notFound();
    }
  }

  if (!product) {
    notFound();
  }

  const canonicalUrl = buildCanonicalUrl(`/products/${product.slug}`);
  const jsonLd = buildProductJsonLd(product, canonicalUrl);

  return (
    <div className="store-container py-8">
      {/* Schema.org Product Structured Data */}
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />

      {/* Breadcrumbs */}
      <nav aria-label="Breadcrumb" className="mb-6 text-xs text-muted">
        <Link href="/">Home</Link> / <Link href="/products">Collection</Link>
        {product.category && (
          <>
            {' '}
            / <Link href={`/categories/${product.category.slug}`}>{product.category.name}</Link>
          </>
        )}{' '}
        / <span className="font-semibold">{product.name}</span>
      </nav>

      <div className="two-col-layout">
        {/* Left: Interactive Image Gallery */}
        <ProductImageGallery
          images={product.images ?? []}
          productName={product.name}
        />

        {/* Right: Details & Variant Selection */}
        <div className="flex flex-col gap-4">
          <div className="flex flex-wrap items-center gap-2">
            {product.brand && (
              <Badge variant="secondary">{product.brand.name}</Badge>
            )}
            {product.category && (
              <Link href={`/categories/${product.category.slug}`}>
                <Badge variant="outline">{product.category.name}</Badge>
              </Link>
            )}
          </div>

          <h1 className="text-3xl font-bold tracking-tight">{product.name}</h1>

          {product.description && (
            <p className="text-sm text-muted">{product.description}</p>
          )}

          <ProductVariantSelector product={product} />

          {product.body && (
            <>
              <Separator />
              <section aria-label="Garment specifications">
                <h2 className="mb-2 text-sm font-semibold uppercase tracking-wider">
                  Fabric & Construction
                </h2>
                <div className="text-sm text-muted" style={{ whiteSpace: 'pre-line' }}>
                  {product.body}
                </div>
              </section>
            </>
          )}
        </div>
      </div>
    </div>
  );
}

