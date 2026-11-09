import * as React from 'react';
import Link from 'next/link';
import type { CatalogProduct } from '@clothing-commerce/types';
import { formatMoney } from '@/lib/utils/format';
import { Badge } from '@/components/ui/badge';

export interface ProductCardProps {
  product: CatalogProduct;
}

export function ProductCard({ product }: ProductCardProps) {
  const images = product.images ?? [];
  const primaryImage =
    images.find((img) => img.is_primary) ?? images[0] ?? null;

  const variants = product.variants ?? [];
  const sortedByPrice = [...variants].sort(
    (a, b) => a.price.amount - b.price.amount
  );
  const lowestVariant = sortedByPrice[0] ?? null;

  const uniqueColors = Array.from(
    new Set(variants.map((v) => v.color).filter((c): c is string => Boolean(c)))
  );
  const uniqueSizes = Array.from(
    new Set(variants.map((v) => v.size).filter((s): s is string => Boolean(s)))
  );

  return (
    <article
      className="flex flex-col justify-between rounded-xl border bg-surface"
      style={{
        backgroundColor: '#ffffff',
        border: '1px solid #e5e2dc',
        borderRadius: '0.875rem',
        overflow: 'hidden',
        transition: 'transform 0.2s ease, box-shadow 0.2s ease',
      }}
    >
      <Link
        href={`/products/${product.slug}`}
        aria-label={`View ${product.name}`}
        className="block"
      >
        <div
          style={{
            position: 'relative',
            width: '100%',
            aspectRatio: '3 / 4',
            backgroundColor: '#f4f3ef',
            overflow: 'hidden',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
          }}
        >
          {primaryImage ? (
            <img
              src={primaryImage.url}
              alt={primaryImage.alt_text || product.name}
              loading="lazy"
              style={{
                width: '100%',
                height: '100%',
                objectFit: 'cover',
              }}
            />
          ) : (
            <div className="flex flex-col items-center gap-1 p-4 text-center">
              <span
                className="text-xs font-semibold uppercase tracking-wider text-muted"
                style={{ letterSpacing: '0.14em' }}
              >
                {product.brand?.name || 'Atelier Véra'}
              </span>
              <span className="text-sm font-medium">{product.name}</span>
            </div>
          )}

          <div
            style={{
              position: 'absolute',
              top: '0.75rem',
              left: '0.75rem',
              display: 'flex',
              gap: '0.375rem',
              flexWrap: 'wrap',
            }}
          >
            {product.is_featured && <Badge variant="default">Featured</Badge>}
            {lowestVariant?.compare_at_price &&
              lowestVariant.compare_at_price.amount > lowestVariant.price.amount && (
                <Badge variant="warning">Archive Offer</Badge>
              )}
          </div>
        </div>
      </Link>

      <div className="flex flex-1 flex-col justify-between p-4" style={{ padding: '1rem' }}>
        <div>
          <div className="mb-1 flex items-center justify-between gap-2 text-xs text-muted">
            <span className="uppercase tracking-wider">
              {product.brand?.name || product.category?.name || 'Ready-to-Wear'}
            </span>
            {uniqueSizes.length > 0 && (
              <span>{uniqueSizes.slice(0, 4).join(' · ')}</span>
            )}
          </div>

          <h3 className="text-base font-semibold tracking-tight">
            <Link href={`/products/${product.slug}`}>{product.name}</Link>
          </h3>

          {product.description && (
            <p
              className="mt-1 text-xs text-muted"
              style={{
                display: '-webkit-box',
                WebkitLineClamp: 2,
                WebkitBoxOrient: 'vertical',
                overflow: 'hidden',
              }}
            >
              {product.description}
            </p>
          )}
        </div>

        <div className="mt-4 flex items-center justify-between border-t pt-3" style={{ paddingTop: '0.75rem' }}>
          <div>
            {lowestVariant ? (
              <div className="flex items-center gap-2">
                <span className="text-sm font-bold" data-testid="product-card-price">
                  {formatMoney(lowestVariant.price)}
                </span>
                {lowestVariant.compare_at_price &&
                  lowestVariant.compare_at_price.amount > lowestVariant.price.amount && (
                    <span className="text-xs line-through text-muted">
                      {formatMoney(lowestVariant.compare_at_price)}
                    </span>
                  )}
              </div>
            ) : (
              <span className="text-xs text-muted">See options</span>
            )}
          </div>

          {uniqueColors.length > 0 && (
            <span className="text-xs text-muted">
              {uniqueColors.length} {uniqueColors.length === 1 ? 'color' : 'colors'}
            </span>
          )}
        </div>
      </div>
    </article>
  );
}

