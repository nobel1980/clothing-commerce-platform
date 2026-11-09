'use client';

import * as React from 'react';
import Link from 'next/link';
import { ShoppingBag, Trash2, ArrowRight, Tag } from 'lucide-react';
import { useCart } from '@/features/cart/hooks/useCart';
import { formatMoney } from '@/lib/utils/format';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { EmptyState } from '@/components/shared/EmptyState';
import { ErrorState } from '@/components/shared/ErrorState';

export function CartView() {
  const {
    entries,
    quote,
    isQuoteLoading,
    quoteError,
    promoCode,
    updateQuantity,
    removeItem,
    setPromoCode,
    refreshQuote,
  } = useCart();

  const [promoInput, setPromoInput] = React.useState(promoCode);

  if (entries.length === 0) {
    return (
      <EmptyState
        icon={<ShoppingBag size={28} />}
        title="Your shopping bag is empty"
        description="Explore our latest seasonal silhouettes, tailored essentials, and organic cotton staples."
        actionLabel="Explore Collection"
        actionHref="/products"
      />
    );
  }

  const quoteItemsMap = new Map(
    (quote?.items ?? []).map((item) => [Number(item.variantId), item])
  );

  const handleApplyPromo = (e: React.FormEvent) => {
    e.preventDefault();
    setPromoCode(promoInput.trim().toUpperCase());
  };

  return (
    <div className="two-col-layout">
      {/* Left column: Bag items */}
      <div className="flex flex-col gap-4" aria-label="Shopping bag items">
        {entries.map((entry) => {
          const quotedItem = quoteItemsMap.get(entry.variantId);

          return (
            <Card key={entry.variantId}>
              <CardContent
                className="flex items-center justify-between gap-4"
                style={{ padding: '1.25rem' }}
              >
                <div className="flex items-center gap-4">
                  <div
                    style={{
                      width: '5rem',
                      height: '6.25rem',
                      borderRadius: '0.5rem',
                      backgroundColor: '#f4f3ef',
                      overflow: 'hidden',
                      flexShrink: 0,
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                    }}
                  >
                    {entry.image ? (
                      <img
                        src={entry.image}
                        alt={entry.name}
                        style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                      />
                    ) : (
                      <span className="text-xs uppercase tracking-wider text-muted">
                        Atelier
                      </span>
                    )}
                  </div>

                  <div className="flex flex-col gap-1">
                    {entry.slug ? (
                      <Link
                        href={`/products/${entry.slug}`}
                        className="text-base font-semibold"
                      >
                        {entry.name}
                      </Link>
                    ) : (
                      <span className="text-base font-semibold">{entry.name}</span>
                    )}

                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted">
                      <span>SKU: {entry.sku}</span>
                      {entry.color && <Badge variant="secondary">Color: {entry.color}</Badge>}
                      {entry.size && <Badge variant="outline">Size: {entry.size}</Badge>}
                    </div>

                    <div className="mt-2 flex items-center gap-2">
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        aria-label={`Decrease quantity for ${entry.name}`}
                        onClick={() => updateQuantity(entry.variantId, entry.quantity - 1)}
                      >
                        −
                      </Button>
                      <span
                        aria-label={`Quantity for ${entry.name}`}
                        className="px-3 text-sm font-medium"
                      >
                        {entry.quantity}
                      </span>
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        aria-label={`Increase quantity for ${entry.name}`}
                        onClick={() => updateQuantity(entry.variantId, entry.quantity + 1)}
                      >
                        +
                      </Button>

                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        aria-label={`Remove ${entry.name} from bag`}
                        onClick={() => removeItem(entry.variantId)}
                      >
                        <Trash2 size={15} />
                      </Button>
                    </div>
                  </div>
                </div>

                {/* Authoritative Backend Price Display */}
                <div className="text-right">
                  {isQuoteLoading && !quotedItem ? (
                    <Skeleton style={{ width: '5.5rem', height: '1.25rem' }} />
                  ) : quotedItem ? (
                    <>
                      <p className="text-base font-semibold">
                        {formatMoney(quotedItem.totalPrice)}
                      </p>
                      <p className="text-xs text-muted">
                        {formatMoney(quotedItem.unitPrice)} each
                      </p>
                    </>
                  ) : (
                    <span className="text-xs text-muted">Quote pending</span>
                  )}
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      {/* Right column: Authoritative Backend Order Summary */}
      <Card aria-label="Order summary">
        <CardHeader>
          <CardTitle>Order Summary</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <form onSubmit={handleApplyPromo} className="flex gap-2">
            <label htmlFor="cart-promo-code" className="sr-only">
              Promo Code
            </label>
            <Input
              id="cart-promo-code"
              placeholder="Promo code (e.g. WELCOME10)"
              value={promoInput}
              onChange={(e) => setPromoInput(e.target.value)}
            />
            <Button type="submit" variant="secondary">
              <Tag size={15} />
              Apply
            </Button>
          </form>

          {quote?.promoCode && (
            <div className="flex items-center justify-between rounded-md bg-muted px-3 py-2 text-xs">
              <span>
                Applied code: <strong>{quote.promoCode}</strong>
              </span>
              <button
                type="button"
                onClick={() => {
                  setPromoInput('');
                  setPromoCode('');
                }}
                style={{
                  background: 'transparent',
                  border: 0,
                  cursor: 'pointer',
                  textDecoration: 'underline',
                }}
              >
                Remove
              </button>
            </div>
          )}

          {quoteError && (
            <ErrorState
              title="Could not fetch authoritative order quote"
              message={quoteError.message}
              onRetry={() => void refreshQuote()}
            />
          )}

          <Separator />

          {isQuoteLoading && !quote ? (
            <div className="flex flex-col gap-2" aria-busy="true">
              <Skeleton style={{ width: '100%', height: '1.125rem' }} />
              <Skeleton style={{ width: '100%', height: '1.125rem' }} />
              <Skeleton style={{ width: '100%', height: '1.5rem' }} />
            </div>
          ) : quote ? (
            <dl className="flex flex-col gap-2 text-sm">
              <div className="flex justify-between">
                <dt className="text-muted">Subtotal</dt>
                <dd className="font-medium" data-testid="quote-subtotal">
                  {formatMoney(quote.subtotal)}
                </dd>
              </div>
              {quote.discount.amount > 0 && (
                <div className="flex justify-between text-success">
                  <dt>Discount</dt>
                  <dd data-testid="quote-discount">−{formatMoney(quote.discount)}</dd>
                </div>
              )}
              <div className="flex justify-between">
                <dt className="text-muted">Estimated Shipping</dt>
                <dd className="font-medium" data-testid="quote-shipping">
                  {quote.shipping.amount === 0 ? 'Complimentary' : formatMoney(quote.shipping)}
                </dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-muted">Estimated VAT (5%)</dt>
                <dd className="font-medium" data-testid="quote-tax">
                  {formatMoney(quote.tax)}
                </dd>
              </div>
              <Separator />
              <div className="flex justify-between text-base font-bold">
                <dt>Authoritative Total</dt>
                <dd data-testid="quote-total">{formatMoney(quote.total)}</dd>
              </div>
            </dl>
          ) : (
            <p className="text-xs text-muted">
              Totals are calculated exclusively by the backend quote service.
            </p>
          )}

          <Link href="/checkout" className="w-full">
            <Button variant="default" size="lg" className="w-full">
              Proceed to Checkout
              <ArrowRight size={16} />
            </Button>
          </Link>
        </CardContent>
      </Card>
    </div>
  );
}

