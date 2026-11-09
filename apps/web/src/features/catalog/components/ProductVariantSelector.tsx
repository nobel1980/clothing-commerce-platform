'use client';

import * as React from 'react';
import { Check, ShoppingBag, ShieldCheck, Truck } from 'lucide-react';
import type { CatalogProduct, CatalogVariant } from '@clothing-commerce/types';
import { formatMoney } from '@/lib/utils/format';
import { useVariantAvailability } from '@/features/catalog/hooks/useVariantAvailability';
import { useCart } from '@/features/cart/hooks/useCart';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';

export interface ProductVariantSelectorProps {
  product: CatalogProduct;
}

export function ProductVariantSelector({ product }: ProductVariantSelectorProps) {
  const variants = React.useMemo(
    () => (product.variants ?? []).filter((v) => v.status === 'active'),
    [product.variants]
  );

  const colors = React.useMemo(
    () =>
      Array.from(
        new Set(variants.map((v) => v.color).filter((c): c is string => Boolean(c)))
      ),
    [variants]
  );

  const sizes = React.useMemo(
    () =>
      Array.from(
        new Set(variants.map((v) => v.size).filter((s): s is string => Boolean(s)))
      ),
    [variants]
  );

  const initialVariant: CatalogVariant | undefined = variants[0];
  const [selectedColor, setSelectedColor] = React.useState<string | null>(
    initialVariant?.color ?? colors[0] ?? null
  );
  const [selectedSize, setSelectedSize] = React.useState<string | null>(
    initialVariant?.size ?? sizes[0] ?? null
  );
  const [quantity, setQuantity] = React.useState<number>(1);
  const [addedFeedback, setAddedFeedback] = React.useState<boolean>(false);

  const { addItem } = useCart();

  // Resolve matching variant for the chosen color & size
  const selectedVariant = React.useMemo<CatalogVariant | null>(() => {
    const exactMatch = variants.find(
      (v) =>
        (selectedColor === null || v.color === selectedColor) &&
        (selectedSize === null || v.size === selectedSize)
    );
    if (exactMatch) return exactMatch;

    const colorFallback = variants.find((v) => v.color === selectedColor);
    return colorFallback ?? variants[0] ?? null;
  }, [variants, selectedColor, selectedSize]);

  // Query live stock availability from Laravel Inventory API
  const { data: availability, isLoading: isCheckingStock } = useVariantAvailability(
    selectedVariant?.id
  );

  const isOutOfStock =
    !selectedVariant ||
    (availability !== undefined && (!availability.in_stock || availability.available_quantity <= 0));

  const maxQuantity =
    availability && availability.available_quantity > 0
      ? Math.min(10, availability.available_quantity)
      : 10;

  const isSizeAvailableForColor = (size: string): boolean => {
    return variants.some(
      (v) => (selectedColor === null || v.color === selectedColor) && v.size === size
    );
  };

  const handleSelectColor = (color: string) => {
    setSelectedColor(color);
    setAddedFeedback(false);
    // If current size doesn't exist for this color, switch to first valid size for that color
    const validForColor = variants.find((v) => v.color === color && v.size === selectedSize);
    if (!validForColor) {
      const firstForColor = variants.find((v) => v.color === color);
      if (firstForColor?.size) {
        setSelectedSize(firstForColor.size);
      }
    }
  };

  const handleAddToCart = () => {
    if (!selectedVariant || isOutOfStock) return;

    const primaryImage =
      (product.images ?? []).find((img) => img.is_primary)?.url ??
      product.images?.[0]?.url ??
      null;

    addItem({
      variantId: selectedVariant.id,
      productId: product.id,
      slug: product.slug,
      sku: selectedVariant.sku,
      name: product.name,
      color: selectedVariant.color,
      size: selectedVariant.size,
      quantity,
      image: primaryImage,
    });

    setAddedFeedback(true);
  };

  return (
    <div className="flex flex-col gap-6">
      {/* Authoritative Variant Price Display (never calculated on frontend) */}
      <div>
        <div className="flex items-center gap-3">
          <span
            className="text-2xl font-bold tracking-tight"
            data-testid="selected-variant-price"
          >
            {selectedVariant ? formatMoney(selectedVariant.price) : 'Unavailable'}
          </span>
          {selectedVariant?.compare_at_price &&
            selectedVariant.compare_at_price.amount > selectedVariant.price.amount && (
              <span
                className="text-base line-through text-muted"
                data-testid="selected-variant-compare-price"
              >
                {formatMoney(selectedVariant.compare_at_price)}
              </span>
            )}
        </div>

        <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted">
          {selectedVariant && <span data-testid="selected-variant-sku">SKU: {selectedVariant.sku}</span>}
          {isCheckingStock ? (
            <Badge variant="secondary">Checking stock…</Badge>
          ) : availability ? (
            availability.in_stock ? (
              <Badge variant={availability.is_low_stock ? 'warning' : 'success'}>
                {availability.is_low_stock
                  ? `Low Stock (${availability.available_quantity} left)`
                  : `In Stock (${availability.available_quantity} available)`}
              </Badge>
            ) : (
              <Badge variant="destructive">Out of Stock</Badge>
            )
          ) : selectedVariant ? (
            <Badge variant="outline">Ready to ship</Badge>
          ) : null}
        </div>
      </div>

      <Separator />

      {/* Color Selection */}
      {colors.length > 0 && (
        <fieldset style={{ border: 0 }}>
          <legend className="mb-2 text-sm font-medium">
            Color: <span className="font-semibold">{selectedColor ?? 'Select'}</span>
          </legend>
          <div role="radiogroup" aria-label="Select color" className="flex flex-wrap gap-2">
            {colors.map((color) => {
              const isSelected = selectedColor === color;
              return (
                <button
                  key={color}
                  type="button"
                  role="radio"
                  aria-checked={isSelected}
                  aria-label={`Color ${color}`}
                  onClick={() => handleSelectColor(color)}
                  className="rounded-md px-4 py-2 text-sm font-medium"
                  style={{
                    border: isSelected ? '2px solid #18181b' : '1px solid #d6d3cd',
                    backgroundColor: isSelected ? '#18181b' : '#ffffff',
                    color: isSelected ? '#fafafa' : '#18181b',
                    cursor: 'pointer',
                  }}
                >
                  {color}
                </button>
              );
            })}
          </div>
        </fieldset>
      )}

      {/* Size Selection */}
      {sizes.length > 0 && (
        <fieldset style={{ border: 0 }}>
          <div className="mb-2 flex items-center justify-between">
            <legend className="text-sm font-medium">
              Size: <span className="font-semibold">{selectedSize ?? 'Select'}</span>
            </legend>
            <span className="text-xs text-muted">Tailored standard fit</span>
          </div>
          <div role="radiogroup" aria-label="Select size" className="flex flex-wrap gap-2">
            {sizes.map((size) => {
              const isSelected = selectedSize === size;
              const existsForColor = isSizeAvailableForColor(size);
              return (
                <button
                  key={size}
                  type="button"
                  role="radio"
                  aria-checked={isSelected}
                  aria-label={`Size ${size}`}
                  disabled={!existsForColor}
                  onClick={() => {
                    setSelectedSize(size);
                    setAddedFeedback(false);
                  }}
                  className="rounded-md px-4 py-2 text-sm font-medium"
                  style={{
                    minWidth: '3.25rem',
                    border: isSelected ? '2px solid #18181b' : '1px solid #d6d3cd',
                    backgroundColor: isSelected ? '#18181b' : '#ffffff',
                    color: isSelected ? '#fafafa' : '#18181b',
                    opacity: existsForColor ? 1 : 0.4,
                    cursor: existsForColor ? 'pointer' : 'not-allowed',
                  }}
                >
                  {size}
                </button>
              );
            })}
          </div>
        </fieldset>
      )}

      {/* Quantity & Add to Bag */}
      <div className="flex flex-col gap-3">
        <div className="flex items-center gap-3">
          <div
            className="inline-flex items-center rounded-md border bg-surface"
            style={{ border: '1px solid #d6d3cd', borderRadius: '0.5rem' }}
          >
            <button
              type="button"
              aria-label="Decrease quantity"
              onClick={() => setQuantity((q) => Math.max(1, q - 1))}
              style={{
                width: '2.5rem',
                height: '2.875rem',
                border: 0,
                background: 'transparent',
                cursor: 'pointer',
              }}
            >
              −
            </button>
            <span
              aria-label="Selected quantity"
              data-testid="selected-quantity"
              className="px-3 text-sm font-semibold"
            >
              {quantity}
            </span>
            <button
              type="button"
              aria-label="Increase quantity"
              onClick={() => setQuantity((q) => Math.min(maxQuantity, q + 1))}
              style={{
                width: '2.5rem',
                height: '2.875rem',
                border: 0,
                background: 'transparent',
                cursor: 'pointer',
              }}
            >
              +
            </button>
          </div>

          <Button
            type="button"
            size="lg"
            className="flex-1"
            disabled={isOutOfStock}
            onClick={handleAddToCart}
            data-testid="add-to-bag-button"
          >
            {addedFeedback ? (
              <>
                <Check size={18} />
                Added to Bag
              </>
            ) : isOutOfStock ? (
              'Out of Stock'
            ) : (
              <>
                <ShoppingBag size={18} />
                Add to Bag
              </>
            )}
          </Button>
        </div>

        {addedFeedback && (
          <p role="status" className="text-xs font-medium text-success">
            Added {quantity} × {product.name} ({selectedVariant?.color ?? ''}{' '}
            {selectedVariant?.size ?? ''}) to your bag.
          </p>
        )}
      </div>

      {/* Editorial Assurance Pillars */}
      <div className="mt-2 grid gap-3 rounded-lg bg-muted p-4 text-xs text-muted">
        <div className="flex items-center gap-2">
          <Truck size={15} />
          <span>Complimentary standard delivery on orders above ৳5,000.00</span>
        </div>
        <div className="flex items-center gap-2">
          <ShieldCheck size={15} />
          <span>Real-time multi-warehouse stock verification & 14-day returns</span>
        </div>
      </div>
    </div>
  );
}

