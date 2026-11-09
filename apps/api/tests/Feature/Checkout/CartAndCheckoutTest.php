<?php

namespace Tests\Feature\Checkout;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Models\CheckoutQuote;
use App\Modules\Checkout\Services\CheckoutService;
use App\Modules\Identity\Models\Address;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Models\Order;
use App\Modules\Promotions\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CartAndCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected Warehouse $warehouse;
    protected Product $product;
    protected ProductVariant $variantA;
    protected ProductVariant $variantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::query()->create([
            'name'       => 'Dhaka Central Fulfillment Hub',
            'code'       => 'DHK-CENTRAL',
            'city'       => 'Dhaka',
            'country'    => 'BD',
            'is_active'  => true,
            'is_default' => true,
            'priority'   => 1,
        ]);

        $brand = Brand::query()->create([
            'name'      => 'Atelier Véra',
            'slug'      => 'atelier-vera',
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'brand_id'     => $brand->id,
            'name'         => 'Handloom Jamdani Overshirt',
            'slug'         => 'handloom-jamdani-overshirt',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->variantA = ProductVariant::factory()->create([
            'product_id'       => $this->product->id,
            'sku'              => 'JMD-BLK-M',
            'color'            => 'Obsidian Black',
            'size'             => 'M',
            'price_amount'     => 250000, // 2,500.00 BDT in paisa
            'compare_at_price' => 300000,
            'currency'         => 'BDT',
            'status'           => 'active',
        ]);

        $this->variantB = ProductVariant::factory()->create([
            'product_id'   => $this->product->id,
            'sku'          => 'JMD-IVR-L',
            'color'        => 'Raw Ivory',
            'size'         => 'L',
            'price_amount' => 320000, // 3,200.00 BDT in paisa
            'currency'     => 'BDT',
            'status'       => 'active',
        ]);

        InventoryItem::query()->create([
            'product_variant_id'  => $this->variantA->id,
            'warehouse_id'        => $this->warehouse->id,
            'quantity_on_hand'    => 10,
            'quantity_reserved'   => 0,
            'low_stock_threshold' => 2,
        ]);

        InventoryItem::query()->create([
            'product_variant_id'  => $this->variantB->id,
            'warehouse_id'        => $this->warehouse->id,
            'quantity_on_hand'    => 5,
            'quantity_reserved'   => 0,
            'low_stock_threshold' => 2,
        ]);
    }

    private function validBangladeshAddress(array $overrides = []): array
    {
        return array_merge([
            'name'                  => 'Tariqul Islam',
            'email'                 => 'tariq@example.com',
            'phone'                 => '01712345678',
            'division'              => 'Dhaka',
            'district'              => 'Dhaka',
            'upazila_thana'         => 'Gulshan',
            'street_address'        => 'House 14, Road 55, Gulshan-2',
            'postal_code'           => '1212',
            'delivery_instructions' => 'Leave with building security if unavailable.',
        ], $overrides);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Guest Cart, Customer Cart & Idempotent Cart Merging
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_cart_add_update_remove_and_merge_into_customer_cart(): void
    {
        $guestToken = 'guest-session-bd-001';

        // 1. Add item to guest cart
        $addRes = $this->withHeaders(['X-Guest-Token' => $guestToken])
            ->postJson('/api/cart/items', [
                'variant_id' => $this->variantA->id,
                'quantity'   => 2,
            ]);

        $addRes->assertCreated()
            ->assertJsonPath('data.guest_token', $guestToken)
            ->assertJsonPath('data.currency', 'BDT')
            ->assertJsonPath('data.item_count', 2)
            ->assertJsonPath('data.subtotal.amount', 500000); // 2 * 250000 paisa

        $cartItemId = $addRes->json('data.items.0.id');

        // 2. Update item quantity on guest cart
        $updateRes = $this->withHeaders(['X-Guest-Token' => $guestToken])
            ->patchJson("/api/cart/items/{$cartItemId}", [
                'quantity' => 3,
            ]);

        $updateRes->assertOk()
            ->assertJsonPath('data.item_count', 3)
            ->assertJsonPath('data.subtotal.amount', 750000);

        // 3. Add second variant to guest cart
        $this->withHeaders(['X-Guest-Token' => $guestToken])
            ->postJson('/api/cart/items', [
                'variant_id' => $this->variantB->id,
                'quantity'   => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.item_count', 4);

        // 4. Remove second variant from guest cart
        $this->withHeaders(['X-Guest-Token' => $guestToken])
            ->deleteJson("/api/cart/items/{$this->variantB->id}")
            ->assertOk()
            ->assertJsonPath('data.item_count', 3);

        // 5. Customer already has 1 unit of Variant A and 2 units of Variant B in their customer cart
        $customer = User::factory()->create();
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', [
                'variant_id' => $this->variantA->id,
                'quantity'   => 1,
            ])
            ->assertCreated();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', [
                'variant_id' => $this->variantB->id,
                'quantity'   => 2,
            ])
            ->assertCreated();

        // 6. Merge guest cart (3 units of Variant A) into customer cart (1 unit of Variant A + 2 units of Variant B)
        $mergeRes = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/merge', [
                'guest_token' => $guestToken,
            ]);

        // Expect Variant A = 4 (1 + 3) and Variant B = 2 -> total 6 items
        // Subtotal = (4 * 250000) + (2 * 320000) = 1000000 + 640000 = 1640000 paisa
        $mergeRes->assertOk()
            ->assertJsonPath('data.user_id', $customer->id)
            ->assertJsonPath('data.item_count', 6)
            ->assertJsonPath('data.subtotal.amount', 1640000);

        // 7. Re-merging the same guest_token is idempotent (does not double-add items)
        $secondMerge = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/merge', [
                'guest_token' => $guestToken,
            ]);

        $secondMerge->assertOk()
            ->assertJsonPath('data.item_count', 6)
            ->assertJsonPath('data.subtotal.amount', 1640000);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Bangladesh Phone, Division/District/Upazila & Shipping Calculation
    // ─────────────────────────────────────────────────────────────────────────

    public function test_bangladesh_phone_and_division_district_upazila_validation_and_shipping_rates(): void
    {
        // 1. Invalid phone number rejected
        $this->postJson('/api/checkout/orders', [
            'idempotency_key'  => 'bd-invalid-phone-1',
            'items'            => [['variant_id' => $this->variantA->id, 'quantity' => 1]],
            'payment_method'   => 'cod',
            'shipping_address' => $this->validBangladeshAddress([
                'phone' => '01199999999', // 011 is not a valid BD mobile operator prefix (must be 013-019)
            ]),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_address.phone']);

        // 2. Invalid division rejected
        $this->postJson('/api/checkout/orders', [
            'idempotency_key'  => 'bd-invalid-div-1',
            'items'            => [['variant_id' => $this->variantA->id, 'quantity' => 1]],
            'payment_method'   => 'cod',
            'shipping_address' => $this->validBangladeshAddress([
                'division' => 'Ontario',
            ]),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_address.division']);

        // 3. Missing upazila_thana rejected
        $addrWithoutUpazila = $this->validBangladeshAddress();
        unset($addrWithoutUpazila['upazila_thana']);

        $this->postJson('/api/checkout/orders', [
            'idempotency_key'  => 'bd-missing-upazila-1',
            'items'            => [['variant_id' => $this->variantA->id, 'quantity' => 1]],
            'payment_method'   => 'cod',
            'shipping_address' => $addrWithoutUpazila,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_address.upazila_thana']);

        // 4. Shipping calculation across Bangladesh zones (1 unit of Variant A = 2500.00 BDT = 250000 paisa, below 5000 BDT free threshold)
        // 4a. Inside Dhaka Metro (Dhaka Division, Dhaka District) -> Standard = 70.00 BDT (7000 paisa)
        $dhakaQuote = $this->postJson('/api/checkout/quote', [
            'items'            => [['variant_id' => $this->variantA->id, 'quantity' => 1]],
            'shipping_method'  => 'standard',
            'shipping_address' => $this->validBangladeshAddress([
                'division' => 'Dhaka',
                'district' => 'Dhaka',
            ]),
        ]);
        $dhakaQuote->assertOk()
            ->assertJsonPath('data.shipping.amount', 7000);

        // 4b. Dhaka Sub-Area (Dhaka Division, Gazipur District) -> Standard = 100.00 BDT (10000 paisa)
        $subAreaQuote = $this->postJson('/api/checkout/quote', [
            'items'            => [['variant_id' => $this->variantA->id, 'quantity' => 1]],
            'shipping_method'  => 'standard',
            'shipping_address' => $this->validBangladeshAddress([
                'division'      => 'Dhaka',
                'district'      => 'Gazipur',
                'upazila_thana' => 'Tongi',
            ]),
        ]);
        $subAreaQuote->assertOk()
            ->assertJsonPath('data.shipping.amount', 10000);

        // 4c. Outside Dhaka Division (Chattogram Division, Chattogram District) -> Standard = 130.00 BDT (13000 paisa), Express = 250.00 BDT (25000 paisa)
        $ctgStandardQuote = $this->postJson('/api/checkout/quote', [
            'items'            => [['variant_id' => $this->variantA->id, 'quantity' => 1]],
            'shipping_method'  => 'standard',
            'shipping_address' => $this->validBangladeshAddress([
                'division'      => 'Chattogram',
                'district'      => 'Chattogram',
                'upazila_thana' => 'Panchlaish',
            ]),
        ]);
        $ctgStandardQuote->assertOk()
            ->assertJsonPath('data.shipping.amount', 13000);

        // 5. Customer saved Address selection on Cart
        $customer = User::factory()->create();
        $savedAddress = Address::query()->create([
            'user_id'        => $customer->id,
            'name'           => 'Farhana Ahmed',
            'phone'          => '01812345678',
            'division'       => 'Sylhet',
            'district'       => 'Sylhet',
            'upazila_thana'  => 'Kotwali',
            'street_address' => 'Zindabazar Main Road',
            'postal_code'    => '3100',
            'type'           => 'shipping',
            'is_default'     => true,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', [
                'variant_id' => $this->variantA->id,
                'quantity'   => 1,
            ])->assertCreated();

        $addrRes = $this->actingAs($customer, 'sanctum')
            ->putJson('/api/cart/address', [
                'address_id'      => $savedAddress->id,
                'shipping_method' => 'standard',
            ]);

        $addrRes->assertOk()
            ->assertJsonPath('data.shipping_address.division', 'Sylhet')
            ->assertJsonPath('data.shipping.amount', 13000);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Invalid Quantities & Revalidating Deactivated Variants
    // ─────────────────────────────────────────────────────────────────────────

    public function test_invalid_quantities_and_inactive_variants_are_rejected(): void
    {
        // Quantity 0 rejected
        $this->postJson('/api/cart/items', [
            'variant_id' => $this->variantA->id,
            'quantity'   => 0,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);

        // Negative quantity rejected
        $this->postJson('/api/checkout/quote', [
            'items' => [['variant_id' => $this->variantA->id, 'quantity' => -2]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);

        // Quantity > 50 rejected
        $this->postJson('/api/cart/items', [
            'variant_id' => $this->variantA->id,
            'quantity'   => 51,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);

        // Variant deactivated after quote -> order placement revalidates and rejects with 422
        $quoteRes = $this->postJson('/api/checkout/quote', [
            'items' => [['variant_id' => $this->variantA->id, 'quantity' => 1]],
        ])->assertOk();

        $quoteToken = $quoteRes->json('data.quote_token');

        $this->variantA->update(['status' => 'inactive']);

        $this->postJson('/api/checkout/orders', [
            'idempotency_key'  => 'deactivated-variant-order-1',
            'quote_token'      => $quoteToken,
            'payment_method'   => 'cod',
            'shipping_address' => $this->validBangladeshAddress(),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['variant_id']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Out-of-Stock Variants & Stock Revalidation During Order Placement
    // ─────────────────────────────────────────────────────────────────────────

    public function test_out_of_stock_variants_are_rejected_at_cart_quote_and_order_placement(): void
    {
        // Set Variant B stock to 0
        InventoryItem::query()
            ->where('product_variant_id', $this->variantB->id)
            ->update(['quantity_on_hand' => 0, 'quantity_reserved' => 0]);

        // 1. Adding out-of-stock variant to cart fails with 422
        $this->postJson('/api/cart/items', [
            'variant_id' => $this->variantB->id,
            'quantity'   => 1,
        ])->assertStatus(422);

        // 2. Generating quote for out-of-stock variant fails with 422
        $this->postJson('/api/checkout/quote', [
            'items' => [['variant_id' => $this->variantB->id, 'quantity' => 1]],
        ])->assertStatus(422);

        // 3. Variant A has 10 in stock when quote is created, but drops to 1 before order placement
        $quoteRes = $this->postJson('/api/checkout/quote', [
            'items'            => [['variant_id' => $this->variantA->id, 'quantity' => 2]],
            'shipping_address' => $this->validBangladeshAddress(),
        ])->assertOk();

        $quoteToken = $quoteRes->json('data.quote_token');

        // Another buyer takes 9 units, leaving only 1 unit available
        InventoryItem::query()
            ->where('product_variant_id', $this->variantA->id)
            ->update(['quantity_on_hand' => 10, 'quantity_reserved' => 9]);

        // Order placement for 2 units must revalidate stock under lock and fail with 422
        $this->postJson('/api/checkout/orders', [
            'idempotency_key'  => 'oos-during-placement-1',
            'quote_token'      => $quoteToken,
            'payment_method'   => 'cod',
            'shipping_address' => $this->validBangladeshAddress(),
        ])->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. Coupon Validation & Coupon Expiry
    // ─────────────────────────────────────────────────────────────────────────

    public function test_coupon_validation_usage_limits_and_coupon_expiry(): void
    {
        // Create an active coupon and an already-expired coupon
        Coupon::query()->create([
            'code'             => 'POHELA500',
            'description'      => 'Pohela Boishakh 500 BDT off',
            'type'             => 'fixed_amount',
            'value'            => 50000, // 500.00 BDT in paisa
            'min_order_amount' => 400000, // 4,000.00 BDT minimum
            'usage_limit'      => 1,
            'currency'         => 'BDT',
            'starts_at'        => now()->subDay(),
            'expires_at'       => now()->addHour(),
            'is_active'        => true,
        ]);

        Coupon::query()->create([
            'code'             => 'EXPIRED2025',
            'description'      => 'Expired campaign',
            'type'             => 'percentage',
            'value'            => 20,
            'min_order_amount' => 0,
            'currency'         => 'BDT',
            'starts_at'        => now()->subDays(10),
            'expires_at'       => now()->subMinute(),
            'is_active'        => true,
        ]);

        // 1. Expired coupon rejected with 422
        $this->postJson('/api/checkout/validate-coupon', [
            'code'            => 'EXPIRED2025',
            'subtotal_amount' => 500000,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['coupon_code']);

        // 2. Minimum order amount enforced (1 unit of Variant A = 250000 < 400000 min_order_amount)
        $this->postJson('/api/checkout/quote', [
            'items'       => [['variant_id' => $this->variantA->id, 'quantity' => 1]],
            'coupon_code' => 'POHELA500',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['coupon_code']);

        // 3. Valid order of 2 units (500000 paisa) applies 50000 paisa discount
        $quoteRes = $this->postJson('/api/checkout/quote', [
            'items'            => [['variant_id' => $this->variantA->id, 'quantity' => 2]],
            'coupon_code'      => 'POHELA500',
            'shipping_address' => $this->validBangladeshAddress(),
        ])->assertOk()
            ->assertJsonPath('data.subtotal.amount', 500000)
            ->assertJsonPath('data.discount.amount', 50000);

        $quoteToken = $quoteRes->json('data.quote_token');

        // 4. Coupon expires BEFORE order is placed -> order placement revalidates coupon and rejects with 422!
        Coupon::query()->where('code', 'POHELA500')->update([
            'expires_at' => now()->subSecond(),
        ]);

        $this->postJson('/api/checkout/orders', [
            'idempotency_key'  => 'expired-coupon-at-checkout-1',
            'quote_token'      => $quoteToken,
            'payment_method'   => 'cod',
            'shipping_address' => $this->validBangladeshAddress(),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['coupon_code']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. Duplicate Requests & Idempotency Key Protection
    // ─────────────────────────────────────────────────────────────────────────

    public function test_duplicate_order_requests_with_same_idempotency_key_create_only_one_order(): void
    {
        $payload = [
            'idempotency_key'  => 'idem-bd-order-999',
            'items'            => [['variant_id' => $this->variantA->id, 'quantity' => 2]],
            'shipping_method'  => 'standard',
            'payment_method'   => 'cod',
            'coupon_code'      => 'WELCOME10',
            'shipping_address' => $this->validBangladeshAddress(),
        ];

        $firstResponse = $this->withHeaders(['Idempotency-Key' => 'idem-bd-order-999'])
            ->postJson('/api/checkout/orders', $payload);

        $firstResponse->assertCreated()
            ->assertJsonPath('idempotent_replay', false);

        $orderNumber = $firstResponse->json('data.order_number');
        $orderId     = $firstResponse->json('data.order_id');

        // Duplicate submission with same Idempotency-Key
        $secondResponse = $this->withHeaders(['Idempotency-Key' => 'idem-bd-order-999'])
            ->postJson('/api/checkout/orders', $payload);

        $secondResponse->assertOk()
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('data.order_number', $orderNumber)
            ->assertJsonPath('data.order_id', $orderId);

        // Assert only 1 order was created and stock was reserved only once (2 units, not 4)
        $this->assertDatabaseCount('orders', 1);

        $inventoryItem = InventoryItem::query()
            ->where('product_variant_id', $this->variantA->id)
            ->firstOrFail();

        $this->assertSame(2, (int) $inventoryItem->quantity_reserved);
        $this->assertSame(8, $inventoryItem->available_quantity);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. Price Changes & Never Trusting Frontend Totals
    // ─────────────────────────────────────────────────────────────────────────

    public function test_price_changes_and_tampered_frontend_totals_are_detected_and_rejected(): void
    {
        // 1. Client sends forged/tampered low price and total in request body -> backend ignores forged fields and computes authoritative total
        $quoteRes = $this->postJson('/api/checkout/quote', [
            'items' => [
                [
                    'variant_id' => $this->variantA->id,
                    'quantity'   => 1,
                    'price'      => 100, // Forged frontend price (1 BDT)
                ],
            ],
            'subtotal'         => 100,
            'total'            => 100,
            'shipping_address' => $this->validBangladeshAddress(),
        ]);

        // Authoritative price of Variant A is 250,000 paisa (2,500.00 BDT)
        // Shipping inside Dhaka = 7,000 paisa (70.00 BDT)
        // Tax (5% of 250,000) = 12,500 paisa (125.00 BDT)
        // Total = 269,500 paisa (2,695.00 BDT)
        $quoteRes->assertOk()
            ->assertJsonPath('data.subtotal.amount', 250000)
            ->assertJsonPath('data.total.amount', 269500);

        $quoteToken = $quoteRes->json('data.quote_token');

        // 2. Variant price changes in database after quote generation -> order placement rejects with 409 Conflict!
        $this->variantA->update(['price_amount' => 280000]);

        $this->postJson('/api/checkout/orders', [
            'idempotency_key'  => 'price-changed-order-1',
            'quote_token'      => $quoteToken,
            'payment_method'   => 'cod',
            'shipping_address' => $this->validBangladeshAddress(),
        ])->assertStatus(409)
            ->assertJsonValidationErrors(['price']);

        $this->assertDatabaseCount('orders', 0);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. Expired Quotes
    // ─────────────────────────────────────────────────────────────────────────

    public function test_expired_checkout_quotes_are_rejected_during_order_placement(): void
    {
        $quoteRes = $this->postJson('/api/checkout/quote', [
            'items'            => [['variant_id' => $this->variantA->id, 'quantity' => 1]],
            'shipping_address' => $this->validBangladeshAddress(),
        ])->assertOk();

        $quoteToken = $quoteRes->json('data.quote_token');

        // Fast-forward quote expiration into the past
        CheckoutQuote::query()
            ->where('quote_token', $quoteToken)
            ->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/checkout/orders', [
            'idempotency_key'  => 'expired-quote-order-1',
            'quote_token'      => $quoteToken,
            'payment_method'   => 'cod',
            'shipping_address' => $this->validBangladeshAddress(),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['quote_token']);

        $this->assertSame(
            'expired',
            CheckoutQuote::query()->where('quote_token', $quoteToken)->value('status')
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 9. Concurrent Checkout & Inventory Row Locking
    // ─────────────────────────────────────────────────────────────────────────

    public function test_concurrent_checkout_locks_inventory_and_prevents_overselling(): void
    {
        // Set Variant B stock to exactly 3 units
        InventoryItem::query()
            ->where('product_variant_id', $this->variantB->id)
            ->update(['quantity_on_hand' => 3, 'quantity_reserved' => 0]);

        // Two shoppers each attempt to checkout 2 units of Variant B (total 4 > 3 available)
        $firstAttempt = $this->postJson('/api/checkout/orders', [
            'idempotency_key'  => 'concurrent-buyer-1',
            'items'            => [['variant_id' => $this->variantB->id, 'quantity' => 2]],
            'payment_method'   => 'cod',
            'shipping_address' => $this->validBangladeshAddress(['phone' => '01711111111']),
        ]);

        $secondAttempt = $this->postJson('/api/checkout/orders', [
            'idempotency_key'  => 'concurrent-buyer-2',
            'items'            => [['variant_id' => $this->variantB->id, 'quantity' => 2]],
            'payment_method'   => 'cod',
            'shipping_address' => $this->validBangladeshAddress(['phone' => '01722222222']),
        ]);

        $firstAttempt->assertCreated();
        $secondAttempt->assertStatus(422);

        // Verify exactly 1 order exists and remaining available stock is 1 (never negative)
        $this->assertDatabaseCount('orders', 1);

        $item = InventoryItem::query()
            ->where('product_variant_id', $this->variantB->id)
            ->firstOrFail();

        $this->assertSame(3, (int) $item->quantity_on_hand);
        $this->assertSame(2, (int) $item->quantity_reserved);
        $this->assertSame(1, $item->available_quantity);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 10. Order Snapshots, Online Payment Preparation & Zero Locks During External Hook
    // ─────────────────────────────────────────────────────────────────────────

    public function test_order_snapshots_online_payment_preparation_and_no_db_locks_held_during_external_hook(): void
    {
        /** @var CheckoutService $checkoutService */
        $checkoutService = app(CheckoutService::class);

        $baselineTransactionLevel = DB::transactionLevel();
        $transactionLevelDuringExternalCall = null;

        $result = $checkoutService->placeOrder(
            input: [
                'items'            => [['variant_id' => $this->variantA->id, 'quantity' => 2]],
                'shipping_method'  => 'express',
                'payment_method'   => 'bkash',
                'shipping_address' => $this->validBangladeshAddress([
                    'division'      => 'Rajshahi',
                    'district'      => 'Bogra',
                    'upazila_thana' => 'Sadar',
                ]),
            ],
            idempotencyKey: 'online-prep-order-001',
            userId: null,
            guestToken: 'guest-online-001',
            externalPaymentPreparationHook: function (Order $order) use (&$transactionLevelDuringExternalCall) {
                $transactionLevelDuringExternalCall = DB::transactionLevel();
            }
        );

        // Rule 10: CheckoutService's DB transaction MUST have already committed before external hook executes
        $this->assertSame($baselineTransactionLevel, $transactionLevelDuringExternalCall);

        $order = $result['order'];
        $this->assertSame('pending_payment', $order->status);
        $this->assertSame('awaiting_payment', $order->payment_status);
        $this->assertSame('bkash', $order->payment_method);
        $this->assertSame('ready_for_gateway', $order->payment_preparation_data['status'] ?? null);
        $this->assertSame('BDT', $order->payment_preparation_data['currency'] ?? null);

        // Mutate product and variant in Catalog AFTER order is placed
        $this->product->update(['name' => 'Renamed Future Product']);
        $this->variantA->update([
            'sku'          => 'RENAMED-SKU-999',
            'price_amount' => 999900,
        ]);

        // Rule 7: Saved Order & OrderItem snapshots remain 100% immutable
        $freshOrder = Order::query()->with('items')->findOrFail($order->id);
        $orderItem = $freshOrder->items->firstOrFail();

        $this->assertSame('Handloom Jamdani Overshirt', $orderItem->product_name_snapshot);
        $this->assertSame('JMD-BLK-M', $orderItem->sku_snapshot);
        $this->assertSame(250000, (int) $orderItem->unit_price_amount);
        $this->assertSame('Rajshahi', $freshOrder->shipping_address_snapshot['division']);
        $this->assertSame('Bogra', $freshOrder->shipping_address_snapshot['district']);
        $this->assertSame('Sadar', $freshOrder->shipping_address_snapshot['upazila_thana']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 11. Authorization & Cross-Tenant Isolation
    // ─────────────────────────────────────────────────────────────────────────

    public function test_unauthorized_order_and_quote_access_is_blocked(): void
    {
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();

        // Customer A generates a quote
        $quoteRes = $this->actingAs($customerA, 'sanctum')
            ->postJson('/api/checkout/quote', [
                'items'            => [['variant_id' => $this->variantA->id, 'quantity' => 1]],
                'shipping_address' => $this->validBangladeshAddress(),
            ])->assertOk();

        $quoteToken = $quoteRes->json('data.quote_token');

        // Customer B attempts to place an order using Customer A's quote_token -> 403 Forbidden
        $this->actingAs($customerB, 'sanctum')
            ->postJson('/api/checkout/orders', [
                'idempotency_key'  => 'cross-user-quote-hijack',
                'quote_token'      => $quoteToken,
                'payment_method'   => 'cod',
                'shipping_address' => $this->validBangladeshAddress(),
            ])->assertStatus(403);

        // Customer A places the order legitimately
        $orderRes = $this->actingAs($customerA, 'sanctum')
            ->postJson('/api/checkout/orders', [
                'idempotency_key'  => 'legit-customer-a-order',
                'quote_token'      => $quoteToken,
                'payment_method'   => 'cod',
                'shipping_address' => $this->validBangladeshAddress(),
            ])->assertCreated();

        $orderNumber = $orderRes->json('data.order_number');

        // Customer B cannot view Customer A's order -> 403 Forbidden
        $this->actingAs($customerB, 'sanctum')
            ->getJson("/api/checkout/orders/{$orderNumber}")
            ->assertStatus(403);

        // Customer A can view their own order -> 200 OK
        $this->actingAs($customerA, 'sanctum')
            ->getJson("/api/checkout/orders/{$orderNumber}")
            ->assertOk()
            ->assertJsonPath('data.order_number', $orderNumber);
    }
}
