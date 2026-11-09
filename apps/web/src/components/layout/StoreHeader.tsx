'use client';

import * as React from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { Search, ShoppingBag, User } from 'lucide-react';
import { useCart } from '@/features/cart/hooks/useCart';
import { Badge } from '@/components/ui/badge';

export function StoreHeader() {
  const router = useRouter();
  const { totalItemCount } = useCart();
  const [searchQuery, setSearchQuery] = React.useState('');

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const trimmed = searchQuery.trim();
    router.push(trimmed ? `/search?q=${encodeURIComponent(trimmed)}` : '/search');
  };

  return (
    <header
      className="border-b bg-surface"
      style={{
        position: 'sticky',
        top: 0,
        zIndex: 40,
        backgroundColor: 'rgba(255, 255, 255, 0.94)',
        backdropFilter: 'blur(8px)',
        borderBottom: '1px solid #e5e2dc',
      }}
    >
      {/* Top Announcement Bar */}
      <div
        className="bg-primary py-2 text-center text-xs tracking-wider uppercase"
        style={{
          backgroundColor: '#18181b',
          color: '#fafafa',
          padding: '0.45rem 1rem',
          fontSize: '0.72rem',
          letterSpacing: '0.1em',
        }}
      >
        Complimentary standard shipping on orders over ৳5,000 · Use code{' '}
        <strong>WELCOME10</strong> for 10% off
      </div>

      {/* Main Navigation */}
      <div className="store-container flex flex-wrap items-center justify-between gap-4 py-4">
        <div className="flex items-center gap-8">
          <Link
            href="/"
            aria-label="Atelier Véra Home"
            className="text-xl font-bold uppercase tracking-wider"
            style={{ letterSpacing: '0.16em' }}
          >
            Atelier Véra
          </Link>

          <nav aria-label="Main storefront navigation" className="flex items-center gap-6 text-sm font-medium">
            <Link href="/products">Collection</Link>
            <Link href="/categories">Categories</Link>
            <Link href="/search">Search</Link>
            <Link href="/account/orders">Orders</Link>
          </nav>
        </div>

        <div className="flex items-center gap-3">
          <form
            role="search"
            aria-label="Quick product search"
            onSubmit={handleSearchSubmit}
            className="flex items-center rounded-full border bg-muted px-3 py-1"
            style={{
              border: '1px solid #d6d3cd',
              borderRadius: '9999px',
              backgroundColor: '#f4f3ef',
              padding: '0.25rem 0.75rem',
            }}
          >
            <Search size={14} style={{ color: '#6b6860', marginRight: '0.375rem' }} />
            <label htmlFor="header-search-input" className="sr-only">
              Search products
            </label>
            <input
              id="header-search-input"
              type="search"
              placeholder="Search garments…"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              style={{
                border: 0,
                background: 'transparent',
                fontSize: '0.8125rem',
                width: '9.5rem',
                outline: 'none',
              }}
            />
          </form>

          <Link
            href="/account"
            aria-label="Customer account"
            className="inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium"
          >
            <User size={17} />
            <span>Account</span>
          </Link>

          <Link
            href="/cart"
            aria-label={`Shopping bag with ${totalItemCount} items`}
            className="inline-flex items-center gap-2 rounded-md bg-primary px-3 py-2 text-sm font-medium"
            style={{
              backgroundColor: '#18181b',
              color: '#fafafa',
              padding: '0.5rem 0.875rem',
              borderRadius: '0.5rem',
            }}
          >
            <ShoppingBag size={16} />
            <span>Bag</span>
            <Badge
              variant="secondary"
              data-testid="header-cart-count"
              style={{
                backgroundColor: '#fafafa',
                color: '#18181b',
                padding: '0.05rem 0.45rem',
              }}
            >
              {totalItemCount}
            </Badge>
          </Link>
        </div>
      </div>
    </header>
  );
}

