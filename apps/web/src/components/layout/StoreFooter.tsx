import * as React from 'react';
import Link from 'next/link';

export function StoreFooter() {
  return (
    <footer
      aria-label="Storefront footer"
      className="mt-12 border-t bg-surface py-12"
      style={{
        backgroundColor: '#ffffff',
        borderTop: '1px solid #e5e2dc',
        marginTop: '4rem',
        paddingTop: '3rem',
        paddingBottom: '3rem',
      }}
    >
      <div className="store-container grid gap-8" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))' }}>
        <div>
          <p
            className="text-base font-bold uppercase tracking-wider"
            style={{ letterSpacing: '0.16em', marginBottom: '0.75rem' }}
          >
            Atelier Véra
          </p>
          <p className="text-xs text-muted" style={{ maxWidth: '18rem' }}>
            Contemporary essentials crafted from heavyweight organic cotton, structured wool
            blends, and breathable linen.
          </p>
        </div>

        <div>
          <h3 className="mb-2 text-xs font-semibold uppercase tracking-wider">Collections</h3>
          <ul className="flex flex-col gap-2 text-sm text-muted" style={{ listStyle: 'none' }}>
            <li>
              <Link href="/products">All Ready-to-Wear</Link>
            </li>
            <li>
              <Link href="/products?featured=1">Featured Essentials</Link>
            </li>
            <li>
              <Link href="/categories">Browse Categories</Link>
            </li>
            <li>
              <Link href="/search">Archive Search</Link>
            </li>
          </ul>
        </div>

        <div>
          <h3 className="mb-2 text-xs font-semibold uppercase tracking-wider">Client Services</h3>
          <ul className="flex flex-col gap-2 text-sm text-muted" style={{ listStyle: 'none' }}>
            <li>
              <Link href="/account">My Account & Addresses</Link>
            </li>
            <li>
              <Link href="/account/orders">Order History & Tracking</Link>
            </li>
            <li>
              <Link href="/cart">Shopping Bag</Link>
            </li>
            <li>
              <Link href="/checkout">Express Checkout</Link>
            </li>
          </ul>
        </div>
      </div>

      <div className="store-container mt-8 border-t pt-6 text-xs text-muted flex flex-wrap justify-between gap-2">
        <span>© {new Date().getFullYear()} Atelier Véra Clothing Commerce Platform. All rights reserved.</span>
        <span>Powered by Next.js 16 & Laravel 13 Modular API</span>
      </div>
    </footer>
  );
}

