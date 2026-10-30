import * as React from 'react';
import { Button, Badge, Card, StatusIndicator } from '@clothing-commerce/ui';
import type { Product } from '@clothing-commerce/types';

const SAMPLE_FEATURED_PRODUCTS: Product[] = [
  {
    id: 'prod-001',
    name: 'Minimalist Heavyweight Tee',
    slug: 'minimalist-heavyweight-tee',
    description: '280 GSM premium organic cotton, tailored boxy fit.',
    categories: [{ id: 'cat-1', name: 'T-Shirts', slug: 't-shirts', createdAt: '', updatedAt: '' }],
    variants: [],
    basePrice: { amount: 4500, currency: 'USD', formatted: '$45.00' },
    status: 'published',
    images: ['/products/tee-black.jpg'],
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
  },
  {
    id: 'prod-002',
    name: 'Structured French Terry Hoodie',
    slug: 'structured-french-terry-hoodie',
    description: '450 GSM custom knit with double-lined hood.',
    categories: [{ id: 'cat-2', name: 'Outerwear', slug: 'outerwear', createdAt: '', updatedAt: '' }],
    variants: [],
    basePrice: { amount: 11000, currency: 'USD', formatted: '$110.00' },
    status: 'published',
    images: ['/products/hoodie-grey.jpg'],
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
  },
];

export default function StoreHomePage() {
  return (
    <div style={{ maxWidth: '1200px', margin: '0 auto', padding: '40px 24px' }}>
      <header style={{ marginBottom: '48px', textAlign: 'center' }}>
        <div style={{ display: 'inline-flex', gap: '8px', marginBottom: '16px' }}>
          <Badge variant="success">Next.js 16 Storefront</Badge>
          <Badge variant="outline">Strict TypeScript</Badge>
        </div>
        <h1 style={{ fontSize: '3rem', fontWeight: 800, letterSpacing: '-0.025em', marginBottom: '12px' }}>
          Clothing Commerce Platform
        </h1>
        <p style={{ color: '#71717a', fontSize: '1.25rem', maxWidth: '640px', margin: '0 auto 24px' }}>
          Modular headless commerce architecture powered by Next.js 16, Laravel 13, MySQL 8 & Redis.
        </p>
        <div style={{ display: 'flex', gap: '12px', justifyContent: 'center' }}>
          <Button variant="primary" size="lg">Explore Collection</Button>
          <Button variant="secondary" size="lg">View Admin Portal</Button>
        </div>
      </header>

      <section style={{ marginBottom: '48px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
          <h2 style={{ fontSize: '1.5rem', fontWeight: 700 }}>Featured Essentials</h2>
          <StatusIndicator status="healthy" label="Inventory Synced" />
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: '24px' }}>
          {SAMPLE_FEATURED_PRODUCTS.map((product) => (
            <Card
              key={product.id}
              title={product.name}
              subtitle={product.categories[0]?.name}
            >
              <p style={{ color: '#71717a', fontSize: '14px', margin: '12px 0' }}>
                {product.description}
              </p>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '16px' }}>
                <span style={{ fontSize: '18px', fontWeight: 700 }}>
                  {product.basePrice.formatted}
                </span>
                <Button size="sm" variant="outline">Add to Cart</Button>
              </div>
            </Card>
          ))}
        </div>
      </section>
    </div>
  );
}
