import type { CatalogProduct, Money, VariantPrice } from '@clothing-commerce/types';

export function cn(...classes: Array<string | false | null | undefined>): string {
  return classes.filter(Boolean).join(' ');
}

/**
 * Formats an authoritative Money or VariantPrice object provided by the backend.
 * Never performs price calculations on the frontend.
 */
export function formatMoney(
  price?: Money | VariantPrice | null,
  fallbackCurrency = 'BDT'
): string {
  if (!price) {
    return fallbackCurrency === 'BDT' ? '৳0.00' : `${fallbackCurrency} 0.00`;
  }

  if ('formatted' in price && typeof price.formatted === 'string' && price.formatted.length > 0) {
    return price.formatted;
  }

  const currency = (price.currency || fallbackCurrency).toUpperCase();
  const majorUnits =
    'display' in price && typeof price.display === 'number'
      ? price.display
      : price.amount / 100;

  const symbol =
    currency === 'BDT'
      ? '৳'
      : currency === 'USD'
        ? '$'
        : currency === 'EUR'
          ? '€'
          : `${currency} `;

  return `${symbol}${majorUnits.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

export function getAppBaseUrl(): string {
  const raw = process.env.NEXT_PUBLIC_APP_URL || 'http://localhost:3000';
  return raw.replace(/\/+$/, '');
}

/**
 * Builds a clean canonical URL for SEO metadata.
 * Strips default page=1 and empty query parameters.
 */
export function buildCanonicalUrl(
  path: string,
  params?: Record<string, string | number | boolean | null | undefined>
): string {
  const base = getAppBaseUrl();
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;

  if (!params) {
    return `${base}${normalizedPath}`;
  }

  const searchParams = new URLSearchParams();
  const sortedKeys = Object.keys(params).sort();

  for (const key of sortedKeys) {
    const val = params[key];
    if (val === undefined || val === null || val === '') {
      continue;
    }
    if (key === 'page' && Number(val) <= 1) {
      continue;
    }
    searchParams.set(key, String(val));
  }

  const qs = searchParams.toString();
  return qs ? `${base}${normalizedPath}?${qs}` : `${base}${normalizedPath}`;
}

/**
 * Builds Schema.org Product JSON-LD structured data for SEO.
 */
export function buildProductJsonLd(
  product: CatalogProduct,
  canonicalUrl: string
): Record<string, unknown> {
  const variants = product.variants ?? [];
  const images = (product.images ?? []).map((img) => img.url);
  const firstVariant = variants[0];

  const offers = variants.map((variant) => ({
    '@type': 'Offer',
    sku: variant.sku,
    priceCurrency: variant.price.currency || 'BDT',
    price: (variant.price.amount / 100).toFixed(2),
    itemCondition: 'https://schema.org/NewCondition',
    availability:
      variant.status === 'active'
        ? 'https://schema.org/InStock'
        : 'https://schema.org/OutOfStock',
    url: canonicalUrl,
  }));

  return {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: product.name,
    description: product.description || product.meta_description || product.name,
    sku: firstVariant?.sku || `PROD-${product.id}`,
    image: images.length > 0 ? images : undefined,
    url: canonicalUrl,
    brand: product.brand
      ? {
          '@type': 'Brand',
          name: product.brand.name,
        }
      : {
          '@type': 'Brand',
          name: 'Atelier Véra',
        },
    category: product.category?.name,
    offers:
      offers.length > 1
        ? {
            '@type': 'AggregateOffer',
            priceCurrency: firstVariant?.price.currency || 'BDT',
            lowPrice: (
              Math.min(...variants.map((v) => v.price.amount)) / 100
            ).toFixed(2),
            highPrice: (
              Math.max(...variants.map((v) => v.price.amount)) / 100
            ).toFixed(2),
            offerCount: offers.length,
            offers,
          }
        : offers[0] ?? {
            '@type': 'Offer',
            priceCurrency: 'BDT',
            price: '0.00',
            availability: 'https://schema.org/OutOfStock',
            url: canonicalUrl,
          },
  };
}

