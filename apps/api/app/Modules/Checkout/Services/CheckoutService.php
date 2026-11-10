<?php

namespace App\Modules\Checkout\Services;

use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Services\CartService;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Models\CheckoutQuote;
use App\Modules\Identity\Models\Address;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderStatusHistory;
use App\Modules\Payments\Models\PaymentTransaction;
use App\Modules\Promotions\Models\Coupon;
use App\Modules\Promotions\Services\CouponService;
use App\Modules\Shipping\Services\ShippingCalculationService;
use App\Shared\Contracts\Inventory\InventoryServiceContract;
use App\Shared\Contracts\Inventory\ReserveStockData;
use App\Shared\Contracts\Inventory\ReserveStockItemData;
use App\Shared\Events\Checkout\OrderPlaced;
use App\Shared\Exceptions\Checkout\CheckoutException;
use App\Shared\Exceptions\Checkout\EmptyCartException;
use App\Shared\Exceptions\Checkout\PriceChangedException;
use App\Shared\Exceptions\Checkout\QuoteExpiredException;
use App\Shared\Money\Money;
use App\Shared\Support\BangladeshAddressValidator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    public const QUOTE_TTL_MINUTES = 15;

    public function __construct(
        protected CartService $cartService,
        protected InventoryServiceContract $inventoryService,
        protected CouponService $couponService,
        protected ShippingCalculationService $shippingService,
    ) {}

    /**
     * Generate and persist an authoritative CheckoutQuote in BDT integer paisa.
     * Never trusts frontend prices or totals.
     */
    public function createQuote(
        array $input,
        ?int $userId = null,
        ?string $guestToken = null
    ): array {
        $currency = BangladeshAddressValidator::CURRENCY;
        $cart = null;

        if (!empty($input['cart_id'])) {
            $cart = Cart::query()->with('items')->find((int) $input['cart_id']);
        } elseif (!isset($input['items'])) {
            $cart = $this->cartService->resolveCart($userId, $guestToken, false);
        }

        // Resolve requested line items
        $rawItems = $input['items'] ?? null;
        if ($rawItems === null && $cart !== null && $cart->exists) {
            $rawItems = $cart->items->map(fn ($ci) => [
                'variant_id'           => $ci->product_variant_id,
                'quantity'             => $ci->quantity,
                'expected_unit_price'  => $ci->unit_price_snapshot,
            ])->all();
        }

        if (empty($rawItems)) {
            $zero = Money::zero($currency)->toArray();

            return [
                'id'          => 'quote-' . Str::uuid()->toString(),
                'quote_token' => null,
                'currency'    => $currency,
                'items'       => [],
                'subtotal'    => $zero,
                'discount'    => $zero,
                'shipping'    => $zero,
                'tax'         => $zero,
                'total'       => $zero,
                'promoCode'   => null,
                'coupon_code' => null,
            ];
        }

        $consolidated = $this->consolidateRequestedItems($rawItems);
        $lineItems = [];
        $itemsSnapshot = [];
        $subtotal = Money::zero($currency);

        foreach ($consolidated as $variantId => $reqItem) {
            $quantity = $reqItem['quantity'];
            $this->cartService->validateQuantity($quantity);

            $variant = $this->cartService->revalidateVariant($variantId);
            $this->cartService->assertStockAvailable($variant->id, $quantity);

            $currentUnitAmount = (int) $variant->price_amount;
            if ($reqItem['expected_unit_price'] !== null && (int) $reqItem['expected_unit_price'] !== $currentUnitAmount) {
                throw new PriceChangedException(
                    variantId: $variant->id,
                    expectedAmount: (int) $reqItem['expected_unit_price'],
                    currentAmount: $currentUnitAmount
                );
            }

            $unitPrice = new Money($currentUnitAmount, $currency);
            $lineTotal = $unitPrice->multiply($quantity);
            $subtotal = $subtotal->add($lineTotal);

            $primaryImg = $variant->product?->images?->first()?->getPublicUrl();

            $lineItems[] = [
                'id'         => "item-{$variant->id}",
                'variant_id' => $variant->id,
                'variantId'  => (string) $variant->id,
                'product_id' => $variant->product_id,
                'productId'  => (string) $variant->product_id,
                'sku'        => $variant->sku,
                'name'       => $variant->product?->name ?? $variant->sku,
                'size'       => $variant->size ?? 'One Size',
                'color'      => $variant->color ?? 'Default',
                'quantity'   => $quantity,
                'unitPrice'  => $unitPrice->toArray(),
                'unit_price' => $unitPrice->toArray(),
                'totalPrice' => $lineTotal->toArray(),
                'total_price'=> $lineTotal->toArray(),
                'image'      => $primaryImg,
            ];

            $itemsSnapshot[] = [
                'product_id'              => $variant->product_id,
                'product_variant_id'      => $variant->id,
                'sku'                     => $variant->sku,
                'product_name'            => $variant->product?->name ?? $variant->sku,
                'brand_name'              => $variant->product?->brand?->name,
                'color'                   => $variant->color,
                'size'                    => $variant->size,
                'image_url'               => $primaryImg,
                'quantity'                => $quantity,
                'unit_price_amount'       => $currentUnitAmount,
                'compare_at_price_amount' => $variant->compare_at_price !== null ? (int) $variant->compare_at_price : null,
                'line_total_amount'       => $lineTotal->amount,
                'currency'                => $currency,
            ];
        }

        // Coupon validation
        $rawCouponCode = $input['coupon_code'] ?? $input['promo_code'] ?? $cart?->coupon_code;
        $discount = Money::zero($currency);
        /** @var Coupon|null $coupon */
        $coupon = null;
        $couponSnapshot = null;

        if ($rawCouponCode !== null && trim((string) $rawCouponCode) !== '') {
            $couponResult = $this->couponService->validateAndCalculate(
                (string) $rawCouponCode,
                $subtotal,
                $userId
            );
            $coupon = $couponResult['coupon'];
            $discount = $couponResult['discount'];
            $couponSnapshot = [
                'id'              => $coupon->id,
                'code'            => $coupon->code,
                'type'            => $coupon->type,
                'value'           => (int) $coupon->value,
                'discount_amount' => $discount->amount,
            ];
        }

        $afterDiscount = $subtotal->subtract($discount);

        // Resolve shipping address snapshot if provided
        $shippingAddressSnapshot = $this->resolveShippingAddressSnapshot($input, $userId, $cart, false);
        $shippingMethod = (string) ($input['shipping_method'] ?? $cart?->shipping_method ?? 'standard');
        $paymentMethod = (string) ($input['payment_method'] ?? 'cod');

        $shipping = $this->shippingService->calculate(
            $afterDiscount,
            $shippingMethod,
            $shippingAddressSnapshot
        );

        $taxAmount = intdiv($afterDiscount->amount * 5, 100);
        $tax = new Money($taxAmount, $currency);
        $total = $afterDiscount->add($shipping)->add($tax);

        if (isset($input['expected_total_amount']) && (int) $input['expected_total_amount'] !== $total->amount) {
            throw new PriceChangedException(
                variantId: 0,
                expectedAmount: (int) $input['expected_total_amount'],
                currentAmount: $total->amount,
                message: 'The calculated checkout total differs from the expected total.'
            );
        }

        $quoteToken = 'quote-' . Str::uuid()->toString();
        $expiresAt = isset($input['ttl_seconds'])
            ? now()->addSeconds((int) $input['ttl_seconds'])
            : now()->addMinutes(self::QUOTE_TTL_MINUTES);

        $pricingHash = $this->computePricingHash(
            $itemsSnapshot,
            $coupon?->code,
            $shippingMethod,
            $shipping->amount,
            $total->amount
        );

        $quote = CheckoutQuote::query()->create([
            'quote_token'               => $quoteToken,
            'cart_id'                   => $cart?->exists ? $cart->id : null,
            'user_id'                   => $userId,
            'guest_token'               => $guestToken ?? $cart?->guest_token,
            'status'                    => 'active',
            'currency'                  => $currency,
            'shipping_method'           => $shippingMethod,
            'payment_method'            => $paymentMethod,
            'coupon_id'                 => $coupon?->id,
            'coupon_code'               => $coupon?->code,
            'subtotal_amount'           => $subtotal->amount,
            'discount_amount'           => $discount->amount,
            'shipping_amount'           => $shipping->amount,
            'tax_amount'                => $tax->amount,
            'total_amount'              => $total->amount,
            'items_snapshot'            => $itemsSnapshot,
            'shipping_address_snapshot' => $shippingAddressSnapshot,
            'coupon_snapshot'           => $couponSnapshot,
            'pricing_hash'              => $pricingHash,
            'expires_at'                => $expiresAt,
        ]);

        return [
            'id'               => $quote->quote_token,
            'quote_id'         => $quote->id,
            'quote_token'      => $quote->quote_token,
            'status'           => $quote->status,
            'currency'         => $currency,
            'items'            => $lineItems,
            'shipping_method'  => $shippingMethod,
            'shipping_address' => $shippingAddressSnapshot,
            'subtotal'         => $subtotal->toArray(),
            'discount'         => $discount->toArray(),
            'shipping'         => $shipping->toArray(),
            'tax'              => $tax->toArray(),
            'total'            => $total->toArray(),
            'promoCode'        => $coupon?->code,
            'coupon_code'      => $coupon?->code,
            'expires_at'       => $quote->expires_at->toIso8601String(),
        ];
    }

    /**
     * Place an order transactionally with idempotency, variant & stock revalidation,
     * row-level inventory locking, price change detection, and full snapshots.
     * Never holds DB locks during external API calls.
     *
     * @return array{order: Order, idempotent_replay: bool}
     */
    public function placeOrder(
        array $input,
        string $idempotencyKey,
        ?int $userId = null,
        ?string $guestToken = null,
        ?callable $externalPaymentPreparationHook = null
    ): array {
        $normalizedKey = trim($idempotencyKey);
        if ($normalizedKey === '') {
            throw new CheckoutException('An idempotency key is required for order placement.', 422, 'idempotency_key');
        }

        // Fast-path idempotency check before entering transaction
        $existingOrder = Order::query()
            ->with('items')
            ->where('idempotency_key', $normalizedKey)
            ->first();

        if ($existingOrder !== null) {
            return [
                'order'             => $existingOrder,
                'idempotent_replay' => true,
            ];
        }

        $idempotentReplay = false;
        $orderPlacedEvent = null;

        try {
            /** @var Order $order */
            $order = DB::transaction(function () use (
                $input,
                $normalizedKey,
                $userId,
                $guestToken,
                &$idempotentReplay,
                &$orderPlacedEvent
            ) {
                // 1. Lock idempotency check inside transaction
                $lockedExisting = Order::query()
                    ->with('items')
                    ->where('idempotency_key', $normalizedKey)
                    ->lockForUpdate()
                    ->first();

                if ($lockedExisting !== null) {
                    $idempotentReplay = true;
                    return $lockedExisting;
                }

                // 2. Lock and validate CheckoutQuote if quote_token is provided
                /** @var CheckoutQuote|null $quote */
                $quote = null;
                if (!empty($input['quote_token'])) {
                    $quoteToken = trim((string) $input['quote_token']);
                    $quote = CheckoutQuote::query()
                        ->where('quote_token', $quoteToken)
                        ->lockForUpdate()
                        ->first();

                    if (!$quote) {
                        throw new QuoteExpiredException($quoteToken, "Checkout quote '{$quoteToken}' was not found.");
                    }

                    if ($quote->status === 'converted' && $quote->converted_order_id !== null) {
                        $convertedOrder = Order::query()->with('items')->find($quote->converted_order_id);
                        if ($convertedOrder !== null) {
                            $idempotentReplay = true;
                            return $convertedOrder;
                        }
                    }

                    if ($quote->isExpired()) {
                        $quote->update(['status' => 'expired']);
                        throw new QuoteExpiredException($quoteToken);
                    }
                }

                // 3. Resolve Cart if applicable
                /** @var Cart|null $cart */
                $cart = null;
                if (!empty($input['cart_id'])) {
                    $cart = Cart::query()
                        ->with('items')
                        ->where('id', (int) $input['cart_id'])
                        ->lockForUpdate()
                        ->first();
                } elseif ($quote?->cart_id !== null) {
                    $cart = Cart::query()
                        ->with('items')
                        ->where('id', $quote->cart_id)
                        ->lockForUpdate()
                        ->first();
                } elseif (empty($input['items']) && $quote === null) {
                    $resolved = $this->cartService->resolveCart($userId, $guestToken, false);
                    if ($resolved->exists) {
                        $cart = Cart::query()
                            ->with('items')
                            ->where('id', $resolved->id)
                            ->lockForUpdate()
                            ->first();
                    }
                }

                if ($cart !== null && $cart->status === 'converted' && $cart->converted_order_id !== null) {
                    $convertedOrder = Order::query()->with('items')->find($cart->converted_order_id);
                    if ($convertedOrder !== null) {
                        $idempotentReplay = true;
                        return $convertedOrder;
                    }
                }

                // 4. Determine requested items & expected unit price snapshots
                $rawItems = $input['items'] ?? null;
                $expectedUnitPricesByVariant = [];

                if ($quote !== null && is_array($quote->items_snapshot)) {
                    foreach ($quote->items_snapshot as $snap) {
                        $vid = (int) ($snap['product_variant_id'] ?? $snap['variant_id'] ?? 0);
                        if ($vid > 0 && isset($snap['unit_price_amount'])) {
                            $expectedUnitPricesByVariant[$vid] = (int) $snap['unit_price_amount'];
                        }
                    }
                    if ($rawItems === null) {
                        $rawItems = array_map(fn (array $snap) => [
                            'variant_id'          => (int) $snap['product_variant_id'],
                            'quantity'            => (int) $snap['quantity'],
                            'expected_unit_price' => (int) $snap['unit_price_amount'],
                        ], $quote->items_snapshot);
                    }
                }

                if ($cart !== null) {
                    foreach ($cart->items as $ci) {
                        if (!isset($expectedUnitPricesByVariant[$ci->product_variant_id])) {
                            $expectedUnitPricesByVariant[$ci->product_variant_id] = (int) $ci->unit_price_snapshot;
                        }
                    }
                    if ($rawItems === null) {
                        $rawItems = $cart->items->map(fn ($ci) => [
                            'variant_id'          => $ci->product_variant_id,
                            'quantity'            => $ci->quantity,
                            'expected_unit_price' => (int) $ci->unit_price_snapshot,
                        ])->all();
                    }
                }

                if (empty($rawItems)) {
                    throw new EmptyCartException('Cannot place an order with no items.');
                }

                $consolidated = $this->consolidateRequestedItems($rawItems);
                if (empty($consolidated)) {
                    throw new EmptyCartException('Cannot place an order with no items.');
                }

                // 5. Revalidate variants, detect price changes, and build authoritative order item snapshots
                $currency = BangladeshAddressValidator::CURRENCY;
                $subtotal = Money::zero($currency);
                $orderItemsPayloads = [];
                $reservationItems = [];

                foreach ($consolidated as $variantId => $reqItem) {
                    $quantity = $reqItem['quantity'];
                    $this->cartService->validateQuantity($quantity);

                    // Lock product variant row to ensure price & status consistency during transaction
                    /** @var ProductVariant|null $lockedVariant */
                    $lockedVariant = ProductVariant::query()
                        ->where('id', $variantId)
                        ->lockForUpdate()
                        ->first();

                    if (!$lockedVariant) {
                        throw new \App\Shared\Exceptions\Checkout\InvalidVariantException(
                            "Product variant #{$variantId} does not exist.",
                            ['variant_id' => $variantId]
                        );
                    }

                    $variant = $this->cartService->revalidateVariant($variantId);
                    $currentUnitAmount = (int) $variant->price_amount;

                    $expectedAmount = $reqItem['expected_unit_price']
                        ?? ($expectedUnitPricesByVariant[$variantId] ?? null);

                    if ($expectedAmount !== null && (int) $expectedAmount !== $currentUnitAmount) {
                        throw new PriceChangedException(
                            variantId: $variant->id,
                            expectedAmount: (int) $expectedAmount,
                            currentAmount: $currentUnitAmount
                        );
                    }

                    $unitPrice = new Money($currentUnitAmount, $currency);
                    $lineTotal = $unitPrice->multiply($quantity);
                    $subtotal = $subtotal->add($lineTotal);

                    $primaryImg = $variant->product?->images?->first()?->getPublicUrl();

                    $orderItemsPayloads[] = [
                        'product_id'              => $variant->product_id,
                        'product_variant_id'      => $variant->id,
                        'sku_snapshot'            => $variant->sku,
                        'product_name_snapshot'   => $variant->product?->name ?? $variant->sku,
                        'brand_name_snapshot'     => $variant->product?->brand?->name,
                        'color_snapshot'          => $variant->color,
                        'size_snapshot'           => $variant->size,
                        'image_url_snapshot'      => $primaryImg,
                        'attributes_snapshot'     => [
                            'color'   => $variant->color,
                            'size'    => $variant->size,
                            'barcode' => $variant->barcode,
                        ],
                        'quantity'                => $quantity,
                        'unit_price_amount'       => $currentUnitAmount,
                        'compare_at_price_amount' => $variant->compare_at_price !== null ? (int) $variant->compare_at_price : null,
                        'discount_amount'         => 0,
                        'tax_amount'              => 0,
                        'line_total_amount'       => $lineTotal->amount,
                        'currency'                => $currency,
                    ];

                    $reservationItems[] = new ReserveStockItemData(
                        productVariantId: $variant->id,
                        quantity: $quantity
                    );
                }

                // 6. Revalidate coupon under row lock
                $rawCouponCode = $input['coupon_code']
                    ?? $input['promo_code']
                    ?? $quote?->coupon_code
                    ?? $cart?->coupon_code;

                $discount = Money::zero($currency);
                /** @var Coupon|null $coupon */
                $coupon = null;
                $couponSnapshot = null;

                if ($rawCouponCode !== null && trim((string) $rawCouponCode) !== '') {
                    $couponResult = $this->couponService->validateAndCalculate(
                        (string) $rawCouponCode,
                        $subtotal,
                        $userId,
                        lockForUpdate: true
                    );
                    $coupon = $couponResult['coupon'];
                    $discount = $couponResult['discount'];
                    $couponSnapshot = [
                        'id'              => $coupon->id,
                        'code'            => $coupon->code,
                        'type'            => $coupon->type,
                        'value'           => (int) $coupon->value,
                        'discount_amount' => $discount->amount,
                    ];
                }

                $afterDiscount = $subtotal->subtract($discount);

                // 7. Resolve & validate Bangladesh shipping address snapshot
                $shippingAddressSnapshot = $this->resolveShippingAddressSnapshot(
                    $input,
                    $userId,
                    $cart,
                    true,
                    $quote?->shipping_address_snapshot
                );

                $shippingMethod = (string) ($input['shipping_method']
                    ?? $quote?->shipping_method
                    ?? $cart?->shipping_method
                    ?? 'standard');

                $shipping = $this->shippingService->calculate(
                    $afterDiscount,
                    $shippingMethod,
                    $shippingAddressSnapshot
                );

                $taxAmount = intdiv($afterDiscount->amount * 5, 100);
                $tax = new Money($taxAmount, $currency);
                $total = $afterDiscount->add($shipping)->add($tax);

                // Verify quote total hasn't drifted or client expected total hasn't drifted
                if ($quote !== null && (int) $quote->total_amount !== $total->amount) {
                    throw new PriceChangedException(
                        variantId: 0,
                        expectedAmount: (int) $quote->total_amount,
                        currentAmount: $total->amount,
                        message: 'Order total has changed since the checkout quote was generated.'
                    );
                }

                if (isset($input['expected_total_amount']) && (int) $input['expected_total_amount'] !== $total->amount) {
                    throw new PriceChangedException(
                        variantId: 0,
                        expectedAmount: (int) $input['expected_total_amount'],
                        currentAmount: $total->amount,
                        message: 'Order total does not match the expected total.'
                    );
                }

                // 8. Revalidate stock and lock inventory rows via InventoryServiceContract::reserveStock
                $stockReservationId = null;
                $shouldReserveInventory = $this->cartService->hasActiveWarehouses();
                if (!$shouldReserveInventory) {
                    foreach (array_keys($consolidated) as $vid) {
                        if ($this->cartService->isInventoryTrackedForVariant((int) $vid)) {
                            $shouldReserveInventory = true;
                            break;
                        }
                    }
                }

                $orderNumber = 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));

                if ($shouldReserveInventory) {
                    $reservationDto = $this->inventoryService->reserveStock(new ReserveStockData(
                        idempotencyKey: 'order-res-' . $normalizedKey,
                        items: $reservationItems,
                        ttlMinutes: 30,
                        referenceType: 'order',
                        referenceId: $orderNumber,
                        metadata: [
                            'order_number' => $orderNumber,
                            'user_id'      => $userId,
                        ],
                        performedBy: $userId
                    ));
                    $stockReservationId = $reservationDto->id;
                }

                // 9. Determine payment method, order status, and online payment preparation metadata
                $paymentMethod = strtolower((string) ($input['payment_method'] ?? $quote?->payment_method ?? 'cod'));
                $isCod = $paymentMethod === 'cod';

                $orderStatus = $isCod ? 'confirmed' : 'pending_payment';
                $paymentStatus = $isCod ? 'cod_pending' : 'awaiting_payment';

                $paymentPreparationData = $this->buildPaymentPreparationMetadata(
                    orderNumber: $orderNumber,
                    paymentMethod: $paymentMethod,
                    total: $total,
                    shippingAddressSnapshot: $shippingAddressSnapshot,
                    idempotencyKey: $normalizedKey
                );

                // 10. Persist Order & OrderItem snapshots
                $createdOrder = Order::query()->create([
                    'order_number'              => $orderNumber,
                    'idempotency_key'           => $normalizedKey,
                    'user_id'                   => $userId,
                    'guest_token'               => $guestToken ?? $quote?->guest_token ?? $cart?->guest_token,
                    'cart_id'                   => $cart?->id,
                    'checkout_quote_id'         => $quote?->id,
                    'stock_reservation_id'      => $stockReservationId,
                    'status'                    => $orderStatus,
                    'payment_method'            => $paymentMethod,
                    'payment_status'            => $paymentStatus,
                    'fulfilment_status'         => 'unfulfilled',
                    'currency'                  => $currency,
                    'subtotal_amount'           => $subtotal->amount,
                    'discount_amount'           => $discount->amount,
                    'shipping_amount'           => $shipping->amount,
                    'tax_amount'                => $tax->amount,
                    'total_amount'              => $total->amount,
                    'coupon_id'                 => $coupon?->id,
                    'coupon_code'               => $coupon?->code,
                    'coupon_snapshot'           => $couponSnapshot,
                    'shipping_method'           => $shippingMethod,
                    'shipping_address_snapshot' => $shippingAddressSnapshot,
                    'billing_address_snapshot'  => isset($input['billing_address']) && is_array($input['billing_address'])
                        ? BangladeshAddressValidator::normalizeSnapshot($input['billing_address'])
                        : $shippingAddressSnapshot,
                    'payment_preparation_data'  => $paymentPreparationData,
                    'notes'                     => $input['notes'] ?? $shippingAddressSnapshot['delivery_instructions'] ?? null,
                    'placed_at'                 => now(),
                    'confirmed_at'              => $isCod ? now() : null,
                ]);

                foreach ($orderItemsPayloads as $itemPayload) {
                    $itemPayload['order_id'] = $createdOrder->id;
                    OrderItem::query()->create($itemPayload);
                }

                // Record initial OrderStatusHistory entry
                OrderStatusHistory::query()->create([
                    'order_id'               => $createdOrder->id,
                    'from_status'            => null,
                    'to_status'              => $orderStatus,
                    'from_payment_status'    => null,
                    'to_payment_status'      => $paymentStatus,
                    'from_fulfilment_status' => null,
                    'to_fulfilment_status'   => 'unfulfilled',
                    'actor_type'             => $userId !== null ? 'customer' : 'system',
                    'actor_id'               => $userId,
                    'reason'                 => 'Order placed',
                    'created_at'             => now(),
                ]);

                // Record initial COD PaymentTransaction if COD
                if ($isCod) {
                    PaymentTransaction::query()->create([
                        'order_id'                => $createdOrder->id,
                        'idempotency_key'         => 'cod-init-' . $normalizedKey,
                        'merchant_reference'      => 'TXN-' . $orderNumber,
                        'gateway'                 => 'cod',
                        'gateway_payment_id'      => 'COD-' . $orderNumber,
                        'amount'                  => $total->amount,
                        'refunded_amount'         => 0,
                        'currency'                => $currency,
                        'status'                  => 'cod_pending',
                        'raw_initiation_request'  => ['order_number' => $orderNumber, 'amount' => $total->amount],
                        'raw_initiation_response' => ['status' => 'cod_pending'],
                    ]);
                }

                // 11. Record coupon redemption if applicable
                if ($coupon !== null) {
                    $this->couponService->recordRedemption($coupon, $createdOrder->id, $userId, $discount);
                }

                // 12. Mark quote and cart as converted
                if ($quote !== null) {
                    $quote->update([
                        'status'             => 'converted',
                        'converted_order_id' => $createdOrder->id,
                    ]);
                }

                if ($cart !== null) {
                    $cart->update([
                        'status'             => 'converted',
                        'converted_order_id' => $createdOrder->id,
                    ]);
                }

                $orderPlacedEvent = new OrderPlaced(
                    orderId: $createdOrder->id,
                    orderNumber: $createdOrder->order_number,
                    userId: $userId,
                    totalAmount: $createdOrder->total_amount,
                    currency: $createdOrder->currency,
                    paymentMethod: $createdOrder->payment_method,
                    status: $createdOrder->status,
                );

                return $createdOrder->load('items');
            });
        } catch (QuoteExpiredException $e) {
            if (!empty($input['quote_token'])) {
                CheckoutQuote::query()
                    ->where('quote_token', trim((string) $input['quote_token']))
                    ->update(['status' => 'expired']);
            }
            throw $e;
        } catch (UniqueConstraintViolationException $e) {
            // Concurrent duplicate request with identical idempotency_key hit DB unique constraint
            $concurrentOrder = Order::query()
                ->with('items')
                ->where('idempotency_key', $normalizedKey)
                ->first();

            if ($concurrentOrder !== null) {
                return [
                    'order'             => $concurrentOrder,
                    'idempotent_replay' => true,
                ];
            }

            throw $e;
        }

        // 13. Post-transaction operations (NO database locks held here!)
        if (!$idempotentReplay && $orderPlacedEvent !== null) {
            event($orderPlacedEvent);

            if ($externalPaymentPreparationHook !== null) {
                $externalPaymentPreparationHook($order);
            }
        }

        return [
            'order'             => $order,
            'idempotent_replay' => $idempotentReplay,
        ];
    }

    /**
     * Format an Order model into a rich API payload with both snake_case and camelCase keys.
     */
    public function formatOrder(Order $order): array
    {
        $order->loadMissing('items');
        $currency = $order->currency ?? 'BDT';

        $subtotal = new Money((int) $order->subtotal_amount, $currency);
        $discount = new Money((int) $order->discount_amount, $currency);
        $shipping = new Money((int) $order->shipping_amount, $currency);
        $tax      = new Money((int) $order->tax_amount, $currency);
        $total    = new Money((int) $order->total_amount, $currency);

        $addr = $order->shipping_address_snapshot ?? [];

        $items = $order->items->map(function (OrderItem $item) use ($currency, $order) {
            $unitPrice = new Money((int) $item->unit_price_amount, $currency);
            $lineTotal = new Money((int) $item->line_total_amount, $currency);

            return [
                'id'                      => (string) $item->id,
                'orderId'                 => (string) $order->id,
                'product_id'              => $item->product_id,
                'productId'               => (string) $item->product_id,
                'variant_id'              => $item->product_variant_id,
                'variantId'               => (string) $item->product_variant_id,
                'sku'                     => $item->sku_snapshot,
                'sku_snapshot'            => $item->sku_snapshot,
                'name'                    => $item->product_name_snapshot,
                'productName'             => $item->product_name_snapshot,
                'product_name_snapshot'   => $item->product_name_snapshot,
                'brand_name_snapshot'     => $item->brand_name_snapshot,
                'color'                   => $item->color_snapshot ?? 'Default',
                'color_snapshot'          => $item->color_snapshot,
                'size'                    => $item->size_snapshot ?? 'One Size',
                'size_snapshot'           => $item->size_snapshot,
                'quantity'                => (int) $item->quantity,
                'unit_price'              => $unitPrice->toArray(),
                'unitPrice'               => $unitPrice->toArray(),
                'unit_price_amount'       => (int) $item->unit_price_amount,
                'compare_at_price_amount' => $item->compare_at_price_amount,
                'total_price'             => $lineTotal->toArray(),
                'totalPrice'              => $lineTotal->toArray(),
                'line_total_amount'       => (int) $item->line_total_amount,
                'image'                   => $item->image_url_snapshot,
            ];
        })->all();

        return [
            'id'                        => (string) $order->id,
            'order_id'                  => $order->id,
            'orderNumber'               => $order->order_number,
            'order_number'              => $order->order_number,
            'idempotency_key'           => $order->idempotency_key,
            'customerId'                => (string) ($order->user_id ?? 'guest'),
            'user_id'                   => $order->user_id,
            'status'                    => strtoupper($order->status),
            'order_status'              => $order->status,
            'payment_method'            => $order->payment_method,
            'payment_status'            => $order->payment_status,
            'currency'                  => $currency,
            'items'                     => $items,
            'subtotal'                  => $subtotal->toArray(),
            'subtotal_amount'           => $subtotal->amount,
            'discount'                  => $discount->toArray(),
            'discount_amount'           => $discount->amount,
            'shippingCost'              => $shipping->toArray(),
            'shipping'                  => $shipping->toArray(),
            'shipping_amount'           => $shipping->amount,
            'tax'                       => $tax->toArray(),
            'tax_amount'                => $tax->amount,
            'total'                     => $total->toArray(),
            'total_amount'              => $total->amount,
            'coupon_code'               => $order->coupon_code,
            'coupon_snapshot'           => $order->coupon_snapshot,
            'shipping_method'           => $order->shipping_method,
            'shipping_address_snapshot' => $addr,
            'shippingAddress'           => [
                'fullName'      => $addr['name'] ?? '',
                'addressLine1'  => $addr['street_address'] ?? '',
                'street'        => $addr['street_address'] ?? '',
                'upazila_thana' => $addr['upazila_thana'] ?? '',
                'district'      => $addr['district'] ?? 'Dhaka',
                'city'          => $addr['district'] ?? 'Dhaka',
                'division'      => $addr['division'] ?? 'Dhaka',
                'state'         => $addr['division'] ?? 'Dhaka',
                'postalCode'    => $addr['postal_code'] ?? '',
                'country'       => $addr['country'] ?? 'BD',
                'phone'         => $addr['phone'] ?? '',
            ],
            'payment_preparation'       => $order->payment_preparation_data,
            'stock_reservation_id'      => $order->stock_reservation_id,
            'createdAt'                 => $order->placed_at?->toIso8601String() ?? $order->created_at?->toIso8601String(),
            'updatedAt'                 => $order->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Consolidate raw requested items by variant_id.
     *
     * @return array<int, array{variant_id: int, quantity: int, expected_unit_price: ?int}>
     */
    private function consolidateRequestedItems(array $rawItems): array
    {
        $consolidated = [];

        foreach ($rawItems as $item) {
            $variantId = (int) ($item['variant_id'] ?? $item['product_variant_id'] ?? 0);
            $quantity  = (int) ($item['quantity'] ?? 0);

            if ($variantId <= 0) {
                throw new \App\Shared\Exceptions\Checkout\InvalidVariantException('A valid variant_id is required.');
            }

            $this->cartService->validateQuantity($quantity);

            if (!isset($consolidated[$variantId])) {
                $consolidated[$variantId] = [
                    'variant_id'          => $variantId,
                    'quantity'            => 0,
                    'expected_unit_price' => isset($item['expected_unit_price'])
                        ? (int) $item['expected_unit_price']
                        : null,
                ];
            }

            $consolidated[$variantId]['quantity'] += $quantity;
            $this->cartService->validateQuantity($consolidated[$variantId]['quantity']);
        }

        ksort($consolidated);

        return $consolidated;
    }

    private function resolveShippingAddressSnapshot(
        array $input,
        ?int $userId,
        ?Cart $cart,
        bool $required,
        ?array $fallbackSnapshot = null
    ): ?array {
        if (!empty($input['address_id'])) {
            $addressId = (int) $input['address_id'];
            $query = Address::query()->where('id', $addressId);
            if ($userId !== null) {
                $query->where('user_id', $userId);
            }
            /** @var Address|null $saved */
            $saved = $query->first();
            if (!$saved) {
                throw new CheckoutException('Selected shipping address was not found.', 422, 'address_id');
            }

            return BangladeshAddressValidator::normalizeSnapshot([
                'name'                  => $saved->name,
                'phone'                 => $saved->phone,
                'division'              => $saved->division,
                'district'              => $saved->district,
                'upazila_thana'         => $saved->upazila_thana,
                'street_address'        => $saved->street_address,
                'postal_code'           => $saved->postal_code,
                'delivery_instructions' => $saved->delivery_instructions,
            ]);
        }

        if (isset($input['shipping_address']) && is_array($input['shipping_address'])) {
            return BangladeshAddressValidator::normalizeSnapshot($input['shipping_address']);
        }

        if (!empty($fallbackSnapshot)) {
            return $fallbackSnapshot;
        }

        if ($cart !== null && !empty($cart->shipping_address_data)) {
            return $cart->shipping_address_data;
        }

        if ($required) {
            throw new CheckoutException('A valid Bangladesh shipping address is required.', 422, 'shipping_address');
        }

        return null;
    }

    private function buildPaymentPreparationMetadata(
        string $orderNumber,
        string $paymentMethod,
        Money $total,
        ?array $shippingAddressSnapshot,
        string $idempotencyKey
    ): array {
        if ($paymentMethod === 'cod') {
            return [
                'mode'               => 'cod',
                'payment_method'     => 'cod',
                'status'             => 'collect_on_delivery',
                'collectable_amount' => $total->amount,
                'currency'           => 'BDT',
                'formatted_amount'   => $total->formatted(),
            ];
        }

        return [
            'mode'                  => 'online',
            'payment_method'        => $paymentMethod,
            'status'                => 'ready_for_gateway',
            'intent_reference'      => 'PAY-' . strtoupper(substr(md5($idempotencyKey), 0, 12)),
            'order_number'          => $orderNumber,
            'payable_amount'        => $total->amount,
            'payable_display'       => $total->toMajorUnits(),
            'formatted_amount'      => $total->formatted(),
            'currency'              => 'BDT',
            'customer_name'         => $shippingAddressSnapshot['name'] ?? null,
            'customer_phone'        => $shippingAddressSnapshot['phone'] ?? null,
            'customer_email'        => $shippingAddressSnapshot['email'] ?? null,
            'shipping_division'     => $shippingAddressSnapshot['division'] ?? 'Dhaka',
            'shipping_district'     => $shippingAddressSnapshot['district'] ?? 'Dhaka',
            'expires_at'            => now()->addMinutes(30)->toIso8601String(),
        ];
    }

    private function computePricingHash(
        array $itemsSnapshot,
        ?string $couponCode,
        string $shippingMethod,
        int $shippingAmount,
        int $totalAmount
    ): string {
        $payload = [
            'items'           => array_map(fn (array $i) => [
                'v' => $i['product_variant_id'],
                'q' => $i['quantity'],
                'p' => $i['unit_price_amount'],
            ], $itemsSnapshot),
            'coupon'          => $couponCode,
            'shipping_method' => $shippingMethod,
            'shipping_amount' => $shippingAmount,
            'total_amount'    => $totalAmount,
        ];

        return hash('sha256', json_encode($payload));
    }
}
