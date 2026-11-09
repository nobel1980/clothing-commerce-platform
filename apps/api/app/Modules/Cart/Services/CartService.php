<?php

namespace App\Modules\Cart\Services;

use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\Address;
use App\Modules\Promotions\Services\CouponService;
use App\Modules\Shipping\Services\ShippingCalculationService;
use App\Shared\Contracts\Inventory\InventoryServiceContract;
use App\Shared\Events\Checkout\CartMerged;
use App\Shared\Exceptions\Checkout\CheckoutException;
use App\Shared\Exceptions\Checkout\EmptyCartException;
use App\Shared\Exceptions\Checkout\InvalidVariantException;
use App\Shared\Exceptions\Inventory\InsufficientStockException;
use App\Shared\Money\Money;
use App\Shared\Support\BangladeshAddressValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartService
{
    public const MAX_ITEM_QUANTITY = 50;

    public function __construct(
        protected InventoryServiceContract $inventoryService,
        protected CouponService $couponService,
        protected ShippingCalculationService $shippingService,
    ) {}

    /**
     * Resolve active customer or guest cart.
     */
    public function resolveCart(?int $userId, ?string $guestToken, bool $createIfMissing = true): Cart
    {
        if ($userId !== null) {
            $cart = Cart::query()
                ->with(['items.variant.product.images', 'coupon'])
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->latest('id')
                ->first();

            if ($cart !== null) {
                return $cart;
            }

            if (!$createIfMissing) {
                return new Cart([
                    'user_id'         => $userId,
                    'status'          => 'active',
                    'currency'        => 'BDT',
                    'shipping_method' => 'standard',
                ]);
            }

            return Cart::query()->create([
                'user_id'         => $userId,
                'guest_token'     => $guestToken,
                'status'          => 'active',
                'currency'        => 'BDT',
                'shipping_method' => 'standard',
                'expires_at'      => now()->addDays(30),
            ]);
        }

        $normalizedToken = $guestToken !== null && trim($guestToken) !== ''
            ? trim($guestToken)
            : 'guest_' . Str::uuid()->toString();

        $cart = Cart::query()
            ->with(['items.variant.product.images', 'coupon'])
            ->where('guest_token', $normalizedToken)
            ->whereNull('user_id')
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if ($cart !== null) {
            return $cart;
        }

        if (!$createIfMissing) {
            return new Cart([
                'guest_token'     => $normalizedToken,
                'status'          => 'active',
                'currency'        => 'BDT',
                'shipping_method' => 'standard',
            ]);
        }

        return Cart::query()->create([
            'guest_token'     => $normalizedToken,
            'status'          => 'active',
            'currency'        => 'BDT',
            'shipping_method' => 'standard',
            'expires_at'      => now()->addDays(14),
        ]);
    }

    /**
     * Add an item to the cart with strict variant & stock revalidation.
     */
    public function addItem(?int $userId, ?string $guestToken, int $variantId, int $quantity): Cart
    {
        $this->validateQuantity($quantity);

        return DB::transaction(function () use ($userId, $guestToken, $variantId, $quantity) {
            $cart = $this->resolveCart($userId, $guestToken, true);
            $variant = $this->revalidateVariant($variantId);

            /** @var CartItem|null $existingItem */
            $existingItem = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_variant_id', $variant->id)
                ->lockForUpdate()
                ->first();

            $newQuantity = ($existingItem?->quantity ?? 0) + $quantity;
            $this->validateQuantity($newQuantity);
            $this->assertStockAvailable($variant->id, $newQuantity);

            if ($existingItem !== null) {
                $existingItem->update([
                    'quantity'            => $newQuantity,
                    'unit_price_snapshot' => (int) $variant->price_amount,
                    'currency'            => $variant->currency ?? 'BDT',
                ]);
            } else {
                CartItem::query()->create([
                    'cart_id'             => $cart->id,
                    'product_id'          => $variant->product_id,
                    'product_variant_id'  => $variant->id,
                    'quantity'            => $newQuantity,
                    'unit_price_snapshot' => (int) $variant->price_amount,
                    'currency'            => $variant->currency ?? 'BDT',
                ]);
            }

            return $cart->fresh(['items.variant.product.images', 'coupon']) ?? $cart;
        });
    }

    /**
     * Update quantity of an item in the cart (by cart_item.id or product_variant_id).
     */
    public function updateItemQuantity(?int $userId, ?string $guestToken, int $itemOrVariantId, int $quantity): Cart
    {
        $this->validateQuantity($quantity);

        return DB::transaction(function () use ($userId, $guestToken, $itemOrVariantId, $quantity) {
            $cart = $this->resolveCart($userId, $guestToken, false);
            if (!$cart->exists) {
                throw new EmptyCartException();
            }

            /** @var CartItem|null $cartItem */
            $cartItem = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where(function ($q) use ($itemOrVariantId) {
                    $q->where('id', $itemOrVariantId)
                        ->orWhere('product_variant_id', $itemOrVariantId);
                })
                ->lockForUpdate()
                ->first();

            if (!$cartItem) {
                throw new InvalidVariantException('The requested item was not found in your cart.');
            }

            $variant = $this->revalidateVariant($cartItem->product_variant_id);
            $this->assertStockAvailable($variant->id, $quantity);

            $cartItem->update([
                'quantity'            => $quantity,
                'unit_price_snapshot' => (int) $variant->price_amount,
            ]);

            return $cart->fresh(['items.variant.product.images', 'coupon']) ?? $cart;
        });
    }

    /**
     * Remove an item from the cart.
     */
    public function removeItem(?int $userId, ?string $guestToken, int $itemOrVariantId): Cart
    {
        return DB::transaction(function () use ($userId, $guestToken, $itemOrVariantId) {
            $cart = $this->resolveCart($userId, $guestToken, false);
            if (!$cart->exists) {
                return $cart;
            }

            CartItem::query()
                ->where('cart_id', $cart->id)
                ->where(function ($q) use ($itemOrVariantId) {
                    $q->where('id', $itemOrVariantId)
                        ->orWhere('product_variant_id', $itemOrVariantId);
                })
                ->delete();

            return $cart->fresh(['items.variant.product.images', 'coupon']) ?? $cart;
        });
    }

    /**
     * Clear all items from the cart.
     */
    public function clearCart(?int $userId, ?string $guestToken): Cart
    {
        return DB::transaction(function () use ($userId, $guestToken) {
            $cart = $this->resolveCart($userId, $guestToken, false);
            if ($cart->exists) {
                CartItem::query()->where('cart_id', $cart->id)->delete();
                $cart->update([
                    'coupon_id'   => null,
                    'coupon_code' => null,
                ]);
            }

            return $cart->fresh(['items.variant.product.images', 'coupon']) ?? $cart;
        });
    }

    /**
     * Select or set Bangladesh shipping address & optional shipping method on the cart.
     */
    public function selectAddress(
        ?int $userId,
        ?string $guestToken,
        array $payload
    ): Cart {
        return DB::transaction(function () use ($userId, $guestToken, $payload) {
            $cart = $this->resolveCart($userId, $guestToken, true);

            $addressId = isset($payload['address_id']) ? (int) $payload['address_id'] : null;
            $normalizedAddress = null;

            if ($addressId !== null) {
                if ($userId === null) {
                    throw new CheckoutException('Authentication is required to select a saved address.', 401, 'address_id');
                }

                /** @var Address|null $savedAddress */
                $savedAddress = Address::query()
                    ->where('id', $addressId)
                    ->where('user_id', $userId)
                    ->first();

                if (!$savedAddress) {
                    throw new CheckoutException('Selected shipping address was not found in your account.', 422, 'address_id');
                }

                $normalizedAddress = BangladeshAddressValidator::normalizeSnapshot([
                    'name'                  => $savedAddress->name,
                    'phone'                 => $savedAddress->phone,
                    'division'              => $savedAddress->division,
                    'district'              => $savedAddress->district,
                    'upazila_thana'         => $savedAddress->upazila_thana,
                    'street_address'        => $savedAddress->street_address,
                    'postal_code'           => $savedAddress->postal_code,
                    'delivery_instructions' => $savedAddress->delivery_instructions,
                ]);
            } elseif (isset($payload['shipping_address']) && is_array($payload['shipping_address'])) {
                $normalizedAddress = BangladeshAddressValidator::normalizeSnapshot($payload['shipping_address']);
            }

            $updates = [];
            if ($normalizedAddress !== null) {
                $updates['shipping_address_id']   = $addressId;
                $updates['shipping_address_data'] = $normalizedAddress;
            }

            if (isset($payload['shipping_method'])) {
                $updates['shipping_method'] = $payload['shipping_method'];
            }

            if (!empty($updates)) {
                $cart->update($updates);
            }

            return $cart->fresh(['items.variant.product.images', 'coupon']) ?? $cart;
        });
    }

    /**
     * Apply and validate a coupon code on the cart.
     */
    public function applyCoupon(?int $userId, ?string $guestToken, string $couponCode): Cart
    {
        return DB::transaction(function () use ($userId, $guestToken, $couponCode) {
            $cart = $this->resolveCart($userId, $guestToken, false);
            if (!$cart->exists || $cart->items->isEmpty()) {
                throw new EmptyCartException('Cannot apply a coupon to an empty cart.');
            }

            $subtotal = $this->calculateAuthoritativeSubtotal($cart);
            $validated = $this->couponService->validateAndCalculate($couponCode, $subtotal, $userId);

            $cart->update([
                'coupon_id'   => $validated['coupon']->id,
                'coupon_code' => $validated['coupon']->code,
            ]);

            return $cart->fresh(['items.variant.product.images', 'coupon']) ?? $cart;
        });
    }

    /**
     * Remove applied coupon from the cart.
     */
    public function removeCoupon(?int $userId, ?string $guestToken): Cart
    {
        return DB::transaction(function () use ($userId, $guestToken) {
            $cart = $this->resolveCart($userId, $guestToken, false);
            if ($cart->exists) {
                $cart->update([
                    'coupon_id'   => null,
                    'coupon_code' => null,
                ]);
            }

            return $cart->fresh(['items.variant.product.images', 'coupon']) ?? $cart;
        });
    }

    /**
     * Merge a guest cart into an authenticated customer's cart idempotently.
     */
    public function mergeGuestCart(string $guestToken, int $userId): Cart
    {
        $normalizedToken = trim($guestToken);
        if ($normalizedToken === '') {
            return $this->resolveCart($userId, null, true);
        }

        $mergedEvent = null;

        $customerCart = DB::transaction(function () use ($normalizedToken, $userId, &$mergedEvent) {
            /** @var Cart|null $guestCart */
            $guestCart = Cart::query()
                ->with('items')
                ->where('guest_token', $normalizedToken)
                ->whereNull('user_id')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $customerCart = Cart::query()
                ->with('items')
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (!$customerCart) {
                $customerCart = Cart::query()->create([
                    'user_id'         => $userId,
                    'status'          => 'active',
                    'currency'        => 'BDT',
                    'shipping_method' => $guestCart?->shipping_method ?? 'standard',
                    'expires_at'      => now()->addDays(30),
                ]);
            }

            // Idempotent return if guest cart doesn't exist or was already merged
            if (!$guestCart || $guestCart->status === 'merged') {
                return $customerCart->fresh(['items.variant.product.images', 'coupon']) ?? $customerCart;
            }

            if ($guestCart->status !== 'active') {
                return $customerCart->fresh(['items.variant.product.images', 'coupon']) ?? $customerCart;
            }

            foreach ($guestCart->items as $guestItem) {
                try {
                    $variant = $this->revalidateVariant($guestItem->product_variant_id);
                } catch (InvalidVariantException) {
                    continue;
                }

                /** @var CartItem|null $existingCustomerItem */
                $existingCustomerItem = CartItem::query()
                    ->where('cart_id', $customerCart->id)
                    ->where('product_variant_id', $variant->id)
                    ->lockForUpdate()
                    ->first();

                $desiredQuantity = min(
                    self::MAX_ITEM_QUANTITY,
                    ($existingCustomerItem?->quantity ?? 0) + $guestItem->quantity
                );

                if ($this->isInventoryTrackedForVariant($variant->id)) {
                    $available = $this->inventoryService->getAvailableStock($variant->id);
                    if ($available <= 0) {
                        continue;
                    }
                    $desiredQuantity = min($desiredQuantity, $available);
                }

                if ($existingCustomerItem !== null) {
                    $existingCustomerItem->update([
                        'quantity'            => $desiredQuantity,
                        'unit_price_snapshot' => (int) $variant->price_amount,
                    ]);
                } else {
                    CartItem::query()->create([
                        'cart_id'             => $customerCart->id,
                        'product_id'          => $variant->product_id,
                        'product_variant_id'  => $variant->id,
                        'quantity'            => $desiredQuantity,
                        'unit_price_snapshot' => (int) $variant->price_amount,
                        'currency'            => $variant->currency ?? 'BDT',
                    ]);
                }
            }

            // Carry over coupon & shipping address if customer cart does not have them yet
            $cartUpdates = [];
            if ($customerCart->coupon_code === null && $guestCart->coupon_code !== null) {
                $cartUpdates['coupon_id']   = $guestCart->coupon_id;
                $cartUpdates['coupon_code'] = $guestCart->coupon_code;
            }
            if (empty($customerCart->shipping_address_data) && !empty($guestCart->shipping_address_data)) {
                $cartUpdates['shipping_address_data'] = $guestCart->shipping_address_data;
            }
            if (!empty($cartUpdates)) {
                $customerCart->update($cartUpdates);
            }

            $guestCart->update([
                'status'              => 'merged',
                'merged_into_cart_id' => $customerCart->id,
                'merged_at'           => now(),
            ]);

            $mergedEvent = new CartMerged($guestCart->id, $customerCart->id, $userId);

            return $customerCart->fresh(['items.variant.product.images', 'coupon']) ?? $customerCart;
        });

        if ($mergedEvent !== null) {
            event($mergedEvent);
        }

        return $customerCart;
    }

    /**
     * Format cart into an authoritative API payload with backend-computed totals.
     */
    public function formatCart(Cart $cart): array
    {
        $cart->loadMissing(['items.variant.product.images', 'coupon']);

        $currency = 'BDT';
        $subtotal = Money::zero($currency);
        $formattedItems = [];
        $variantIds = $cart->items->pluck('product_variant_id')->map(fn ($id) => (int) $id)->all();
        $stockSummaries = !empty($variantIds)
            ? $this->inventoryService->getStockSummaryForVariants($variantIds)
            : [];
        $inventoryTrackedGlobally = $this->hasActiveWarehouses();

        foreach ($cart->items as $item) {
            /** @var ProductVariant|null $variant */
            $variant = $item->variant;
            $product = $variant?->product;

            $isVariantValid = $variant !== null
                && $variant->status === 'active'
                && $product !== null
                && $product->status === 'published';

            $currentUnitPriceAmount = (int) ($variant?->price_amount ?? $item->unit_price_snapshot);
            $unitPrice = new Money($currentUnitPriceAmount, $currency);
            $lineTotal = $unitPrice->multiply((int) $item->quantity);

            if ($isVariantValid) {
                $subtotal = $subtotal->add($lineTotal);
            }

            $stockSummary = $stockSummaries[$item->product_variant_id] ?? null;
            $availableStock = $stockSummary !== null ? $stockSummary->totalAvailable : null;
            $inStock = !$inventoryTrackedGlobally || ($availableStock !== null && $availableStock >= $item->quantity);

            $formattedItems[] = [
                'id'                    => $item->id,
                'variant_id'            => $item->product_variant_id,
                'variantId'             => (string) $item->product_variant_id,
                'product_id'            => $item->product_id,
                'productId'             => (string) $item->product_id,
                'sku'                   => $variant?->sku ?? '',
                'name'                  => $product?->name ?? 'Unavailable Item',
                'slug'                  => $product?->slug ?? '',
                'color'                 => $variant?->color ?? 'Default',
                'size'                  => $variant?->size ?? 'One Size',
                'quantity'              => (int) $item->quantity,
                'unit_price'            => $unitPrice->toArray(),
                'unitPrice'             => $unitPrice->toArray(),
                'unit_price_snapshot'   => (int) $item->unit_price_snapshot,
                'price_changed'         => $currentUnitPriceAmount !== (int) $item->unit_price_snapshot,
                'total_price'           => $lineTotal->toArray(),
                'totalPrice'            => $lineTotal->toArray(),
                'is_valid'              => $isVariantValid,
                'in_stock'              => $inStock,
                'available_stock'       => $availableStock,
                'image'                 => $product?->images?->first()?->getPublicUrl(),
            ];
        }

        // Coupon validation (non-throwing when formatting cart view; detaches if expired/invalid)
        $discount = Money::zero($currency);
        $appliedCouponCode = $cart->coupon_code;
        $couponError = null;

        if ($appliedCouponCode !== null && $subtotal->amount > 0) {
            try {
                $couponResult = $this->couponService->validateAndCalculate(
                    $appliedCouponCode,
                    $subtotal,
                    $cart->user_id
                );
                $discount = $couponResult['discount'];
            } catch (CheckoutException $e) {
                $couponError = $e->getMessage();
                $appliedCouponCode = null;
            }
        }

        $afterDiscount = $subtotal->subtract($discount);
        $shipping = $subtotal->amount > 0
            ? $this->shippingService->calculate(
                $afterDiscount,
                $cart->shipping_method ?? 'standard',
                $cart->shipping_address_data
            )
            : Money::zero($currency);

        $taxAmount = intdiv($afterDiscount->amount * 5, 100);
        $tax = new Money($taxAmount, $currency);
        $total = $afterDiscount->add($shipping)->add($tax);

        return [
            'id'                => $cart->id,
            'user_id'           => $cart->user_id,
            'guest_token'       => $cart->guest_token,
            'status'            => $cart->status,
            'currency'          => $currency,
            'items'             => $formattedItems,
            'item_count'        => array_sum(array_column($formattedItems, 'quantity')),
            'shipping_method'   => $cart->shipping_method ?? 'standard',
            'shipping_address'  => $cart->shipping_address_data,
            'coupon_code'       => $appliedCouponCode,
            'promoCode'         => $appliedCouponCode,
            'coupon_error'      => $couponError,
            'subtotal'          => $subtotal->toArray(),
            'discount'          => $discount->toArray(),
            'shipping'          => $shipping->toArray(),
            'tax'               => $tax->toArray(),
            'total'             => $total->toArray(),
        ];
    }

    /**
     * Revalidate that a ProductVariant exists, is active, and belongs to a published Product.
     */
    public function revalidateVariant(int $variantId): ProductVariant
    {
        /** @var ProductVariant|null $variant */
        $variant = ProductVariant::query()
            ->with(['product.brand', 'product.images'])
            ->find($variantId);

        if (!$variant) {
            throw new InvalidVariantException("Product variant #{$variantId} does not exist.", [
                'variant_id' => $variantId,
            ]);
        }

        if ($variant->status !== 'active') {
            throw new InvalidVariantException("Product variant '{$variant->sku}' is no longer active.", [
                'variant_id' => $variantId,
                'sku'        => $variant->sku,
            ]);
        }

        if (!$variant->product || $variant->product->status !== 'published') {
            throw new InvalidVariantException("Product for variant '{$variant->sku}' is not available for purchase.", [
                'variant_id' => $variantId,
                'product_id' => $variant->product_id,
            ]);
        }

        return $variant;
    }

    /**
     * Assert that requested quantity is valid (integer between 1 and MAX_ITEM_QUANTITY).
     */
    public function validateQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw new CheckoutException('Quantity must be at least 1.', 422, 'quantity', ['quantity' => $quantity]);
        }

        if ($quantity > self::MAX_ITEM_QUANTITY) {
            throw new CheckoutException(
                'Quantity cannot exceed ' . self::MAX_ITEM_QUANTITY . ' units per variant.',
                422,
                'quantity',
                ['quantity' => $quantity, 'max' => self::MAX_ITEM_QUANTITY]
            );
        }
    }

    /**
     * Assert stock availability via InventoryServiceContract when inventory tracking is active.
     */
    public function assertStockAvailable(int $variantId, int $requestedQuantity): void
    {
        if (!$this->isInventoryTrackedForVariant($variantId)) {
            return;
        }

        $available = $this->inventoryService->getAvailableStock($variantId);
        if ($available < $requestedQuantity) {
            throw new InsufficientStockException(
                productVariantId: $variantId,
                requestedQuantity: $requestedQuantity,
                availableQuantity: $available
            );
        }
    }

    public function isInventoryTrackedForVariant(int $variantId): bool
    {
        return $this->inventoryService->isInventoryTrackingActive($variantId);
    }

    public function hasActiveWarehouses(): bool
    {
        return $this->inventoryService->isInventoryTrackingActive();
    }

    private function calculateAuthoritativeSubtotal(Cart $cart): Money
    {
        $cart->loadMissing('items');
        $subtotal = Money::zero('BDT');

        foreach ($cart->items as $item) {
            $variant = $this->revalidateVariant($item->product_variant_id);
            $unitPrice = new Money((int) $variant->price_amount, 'BDT');
            $subtotal = $subtotal->add($unitPrice->multiply((int) $item->quantity));
        }

        return $subtotal;
    }
}
