'use client';

import * as React from 'react';
import Link from 'next/link';
import { CheckCircle2, Lock, ShieldCheck, Truck } from 'lucide-react';
import type { Order } from '@clothing-commerce/types';
import { useCart, type ShippingMethodOption } from '@/features/cart/hooks/useCart';
import { useMutation } from '@/lib/query';
import { placeStoreOrder } from '@/features/orders/api/ordersApi';
import { formatMoney } from '@/lib/utils/format';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert } from '@/components/ui/alert';
import { EmptyState } from '@/components/shared/EmptyState';

export function CheckoutView() {
  const {
    entries,
    quote,
    isQuoteLoading,
    quoteError,
    promoCode,
    shippingMethod,
    setShippingMethod,
    clearCart,
    refreshQuote,
  } = useCart();

  const [idempotencyKey] = React.useState(
    () => `chk-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`
  );

  const [fullName, setFullName] = React.useState('');
  const [phone, setPhone] = React.useState('');
  const [addressLine1, setAddressLine1] = React.useState('');
  const [addressLine2, setAddressLine2] = React.useState('');
  const [upazilaThana, setUpazilaThana] = React.useState('Banani');
  const [city, setCity] = React.useState('Dhaka');
  const [state, setState] = React.useState('Dhaka');
  const [postalCode, setPostalCode] = React.useState('1212');
  const [paymentMethod, setPaymentMethod] = React.useState<'cod' | 'card' | 'bkash'>('cod');
  const [confirmedOrder, setConfirmedOrder] = React.useState<Order | null>(null);

  const placeOrderMutation = useMutation({
    mutationFn: () =>
      placeStoreOrder({
        idempotency_key: idempotencyKey,
        items: entries.map((e) => ({ variant_id: e.variantId, quantity: e.quantity })),
        shipping_address: {
          fullName,
          phone,
          addressLine1,
          addressLine2: addressLine2 || undefined,
          division: state,
          district: city,
          upazila_thana: upazilaThana,
          city,
          state,
          postalCode,
          country: 'BD',
        },
        shipping_method: shippingMethod,
        payment_method: paymentMethod,
        promo_code: promoCode || null,
      }),
    onSuccess: (order) => {
      setConfirmedOrder(order);
      clearCart();
    },
  });

  if (confirmedOrder) {
    return (
      <Card style={{ maxWidth: '42rem', margin: '0 auto' }}>
        <CardHeader>
          <div className="flex items-center gap-2 text-success">
            <CheckCircle2 size={24} />
            <CardTitle>Order Confirmed — #{confirmedOrder.orderNumber}</CardTitle>
          </div>
          <CardDescription>
            Thank you, {confirmedOrder.shippingAddress.fullName}. Your inventory reservation has
            been committed and your order is being prepared.
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="rounded-lg bg-muted p-4 text-sm">
            <p className="font-semibold">Shipping Destination</p>
            <p className="mt-1 text-xs text-muted">
              {confirmedOrder.shippingAddress.addressLine1}, {confirmedOrder.shippingAddress.city},{' '}
              {confirmedOrder.shippingAddress.postalCode} ({confirmedOrder.shippingAddress.phone})
            </p>
          </div>

          <div className="flex justify-between text-base font-bold">
            <span>Authoritative Order Total</span>
            <span>{formatMoney(confirmedOrder.total)}</span>
          </div>

          <div className="flex gap-3">
            <Link href="/account/orders">
              <Button variant="default">View Order History</Button>
            </Link>
            <Link href="/products">
              <Button variant="outline">Continue Shopping</Button>
            </Link>
          </div>
        </CardContent>
      </Card>
    );
  }

  if (entries.length === 0) {
    return (
      <EmptyState
        title="No items in checkout"
        description="Add garments to your shopping bag before proceeding to checkout."
        actionLabel="Browse Products"
        actionHref="/products"
      />
    );
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!quote) return;
    placeOrderMutation.mutate();
  };

  return (
    <form onSubmit={handleSubmit} className="two-col-layout">
      {/* Left column: Shipping & Payment */}
      <div className="flex flex-col gap-6">
        <Card>
          <CardHeader>
            <CardTitle>1. Shipping Address</CardTitle>
            <CardDescription>
              Where should we deliver your order?
            </CardDescription>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            <div className="flex flex-wrap gap-3">
              <div className="flex-1" style={{ minWidth: '14rem' }}>
                <label htmlFor="chk-fullname" className="mb-1 block text-xs font-medium">
                  Full Name
                </label>
                <Input
                  id="chk-fullname"
                  required
                  value={fullName}
                  onChange={(e) => setFullName(e.target.value)}
                  placeholder="Nadia Karim"
                />
              </div>
              <div className="flex-1" style={{ minWidth: '14rem' }}>
                <label htmlFor="chk-phone" className="mb-1 block text-xs font-medium">
                  Phone Number
                </label>
                <Input
                  id="chk-phone"
                  type="tel"
                  required
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="+8801711000000"
                />
              </div>
            </div>

            <div>
              <label htmlFor="chk-addr1" className="mb-1 block text-xs font-medium">
                Street Address
              </label>
              <Input
                id="chk-addr1"
                required
                value={addressLine1}
                onChange={(e) => setAddressLine1(e.target.value)}
                placeholder="House 22, Road 7, Banani"
              />
            </div>

            <div>
              <label htmlFor="chk-addr2" className="mb-1 block text-xs font-medium">
                Apartment, Suite, Landmark (Optional)
              </label>
              <Input
                id="chk-addr2"
                value={addressLine2}
                onChange={(e) => setAddressLine2(e.target.value)}
                placeholder="Floor 4, Apt B"
              />
            </div>

            <div className="flex flex-wrap gap-3">
              <div className="flex-1" style={{ minWidth: '9rem' }}>
                <label htmlFor="chk-upazila" className="mb-1 block text-xs font-medium">
                  Upazila / Thana
                </label>
                <Input
                  id="chk-upazila"
                  required
                  value={upazilaThana}
                  onChange={(e) => setUpazilaThana(e.target.value)}
                />
              </div>
              <div className="flex-1" style={{ minWidth: '9rem' }}>
                <label htmlFor="chk-city" className="mb-1 block text-xs font-medium">
                  District
                </label>
                <Input
                  id="chk-city"
                  required
                  value={city}
                  onChange={(e) => setCity(e.target.value)}
                />
              </div>
              <div className="flex-1" style={{ minWidth: '9rem' }}>
                <label htmlFor="chk-state" className="mb-1 block text-xs font-medium">
                  Division
                </label>
                <Input
                  id="chk-state"
                  required
                  value={state}
                  onChange={(e) => setState(e.target.value)}
                />
              </div>
              <div className="flex-1" style={{ minWidth: '7rem' }}>
                <label htmlFor="chk-zip" className="mb-1 block text-xs font-medium">
                  Postal Code
                </label>
                <Input
                  id="chk-zip"
                  required
                  value={postalCode}
                  onChange={(e) => setPostalCode(e.target.value)}
                />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>2. Delivery Method</CardTitle>
            <CardDescription>
              Selecting a delivery method refreshes your authoritative quote from the backend.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div role="radiogroup" aria-label="Delivery method" className="flex flex-col gap-3">
              {(
                [
                  {
                    id: 'standard',
                    title: 'Standard Courier (2–4 Business Days)',
                    detail: 'Complimentary on orders over ৳5,000.00, otherwise ৳120.00',
                  },
                  {
                    id: 'express',
                    title: 'Express Next-Day Air (24 Hours)',
                    detail: 'Priority dispatch from nearest fulfillment warehouse (৳250.00)',
                  },
                  {
                    id: 'pickup',
                    title: 'Atelier Flagship Pickup',
                    detail: 'Collect from Dhaka Flagship Showroom (Complimentary)',
                  },
                ] as const
              ).map((option) => {
                const isSelected = shippingMethod === option.id;
                return (
                  <label
                    key={option.id}
                    className="flex items-start justify-between gap-3 rounded-lg border p-3"
                    style={{
                      cursor: 'pointer',
                      borderColor: isSelected ? '#18181b' : '#d6d3cd',
                      backgroundColor: isSelected ? '#f4f3ef' : '#ffffff',
                    }}
                  >
                    <div className="flex items-start gap-3">
                      <input
                        type="radio"
                        name="shipping_method"
                        value={option.id}
                        checked={isSelected}
                        onChange={() => setShippingMethod(option.id as ShippingMethodOption)}
                        style={{ marginTop: '0.25rem' }}
                      />
                      <div>
                        <p className="text-sm font-semibold">{option.title}</p>
                        <p className="text-xs text-muted">{option.detail}</p>
                      </div>
                    </div>
                    <Truck size={16} />
                  </label>
                );
              })}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>3. Payment Method</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-wrap gap-3">
            {(
              [
                { id: 'cod', label: 'Cash on Delivery (COD)' },
                { id: 'card', label: 'Credit / Debit Card' },
                { id: 'bkash', label: 'bKash Mobile Wallet' },
              ] as const
            ).map((pm) => {
              const active = paymentMethod === pm.id;
              return (
                <button
                  key={pm.id}
                  type="button"
                  role="radio"
                  aria-checked={active}
                  onClick={() => setPaymentMethod(pm.id)}
                  className="rounded-md px-4 py-2 text-sm font-medium"
                  style={{
                    border: active ? '2px solid #18181b' : '1px solid #d6d3cd',
                    backgroundColor: active ? '#18181b' : '#ffffff',
                    color: active ? '#fafafa' : '#18181b',
                    cursor: 'pointer',
                  }}
                >
                  {pm.label}
                </button>
              );
            })}
          </CardContent>
        </Card>
      </div>

      {/* Right column: Authoritative Backend Quote Summary */}
      <Card aria-label="Authoritative checkout summary">
        <CardHeader>
          <CardTitle>Order Review & Authoritative Quote</CardTitle>
          <CardDescription>
            Prices, discounts, VAT, and shipping are computed exclusively by the backend `/checkout/quote` endpoint.
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          {placeOrderMutation.error && (
            <Alert variant="destructive">{placeOrderMutation.error.message}</Alert>
          )}

          {quoteError && (
            <Alert variant="destructive">
              <p>{quoteError.message}</p>
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="mt-2"
                onClick={() => void refreshQuote()}
              >
                Retry Quote
              </Button>
            </Alert>
          )}

          {isQuoteLoading && !quote ? (
            <div className="flex flex-col gap-3" aria-busy="true">
              <Skeleton style={{ width: '100%', height: '3.5rem' }} />
              <Skeleton style={{ width: '100%', height: '1.25rem' }} />
              <Skeleton style={{ width: '100%', height: '1.75rem' }} />
            </div>
          ) : quote ? (
            <>
              <div className="flex flex-col gap-3">
                {quote.items.map((item) => (
                  <div key={item.id} className="flex items-center justify-between text-sm">
                    <div>
                      <p className="font-medium">
                        {item.quantity} × {item.name}
                      </p>
                      <p className="text-xs text-muted">
                        {item.color} · {item.size}
                      </p>
                    </div>
                    <span className="font-semibold">{formatMoney(item.totalPrice)}</span>
                  </div>
                ))}
              </div>

              <Separator />

              <dl className="flex flex-col gap-2 text-sm">
                <div className="flex justify-between">
                  <dt className="text-muted">Subtotal</dt>
                  <dd data-testid="checkout-subtotal">{formatMoney(quote.subtotal)}</dd>
                </div>
                {quote.discount.amount > 0 && (
                  <div className="flex justify-between text-success">
                    <dt>Discount ({quote.promoCode})</dt>
                    <dd data-testid="checkout-discount">−{formatMoney(quote.discount)}</dd>
                  </div>
                )}
                <div className="flex justify-between">
                  <dt className="text-muted">Shipping</dt>
                  <dd data-testid="checkout-shipping">
                    {quote.shipping.amount === 0
                      ? 'Complimentary'
                      : formatMoney(quote.shipping)}
                  </dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-muted">VAT (5%)</dt>
                  <dd data-testid="checkout-tax">{formatMoney(quote.tax)}</dd>
                </div>
                <Separator />
                <div className="flex justify-between text-lg font-bold">
                  <dt>Total Due</dt>
                  <dd data-testid="checkout-total">{formatMoney(quote.total)}</dd>
                </div>
              </dl>
            </>
          ) : null}

          <Button
            type="submit"
            size="lg"
            className="w-full"
            disabled={!quote || isQuoteLoading || placeOrderMutation.isPending}
          >
            <Lock size={16} />
            {placeOrderMutation.isPending ? 'Reserving Stock & Placing Order…' : 'Place Order'}
          </Button>

          <p className="flex items-center justify-center gap-1 text-center text-xs text-muted">
            <ShieldCheck size={14} />
            Idempotent stock reservation key: <code>{idempotencyKey}</code>
          </p>
        </CardContent>
      </Card>
    </form>
  );
}

