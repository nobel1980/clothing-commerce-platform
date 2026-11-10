<?php

namespace Tests\Feature\Shipping;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Models\Order;
use App\Modules\Shipping\Models\CourierApiLog;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShipmentTrackingEvent;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShippingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $orderManager;
    protected User $customerA;
    protected User $customerB;
    protected Warehouse $warehouse;
    protected Product $product;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->orderManager = User::factory()->staff('order_manager')->create([
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $this->customerA = User::factory()->create([
            'name'              => 'Farhana Rahman',
            'email'             => 'farhana@example.com',
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $this->customerB = User::factory()->create([
            'name'              => 'Rashed Karim',
            'email'             => 'rashed@example.com',
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $this->warehouse = Warehouse::query()->create([
            'name'       => 'Dhaka Central Warehouse',
            'code'       => 'WH-DHK-01',
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
            'name'         => 'Structured Linen Panjabi',
            'slug'         => 'structured-linen-panjabi',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->variant = ProductVariant::factory()->create([
            'product_id'       => $this->product->id,
            'sku'              => 'PNJ-LIN-NVY-L',
            'color'            => 'Midnight Navy',
            'size'             => 'L',
            'price_amount'     => 320000, // 3,200.00 BDT in paisa
            'compare_at_price' => 380000,
            'currency'         => 'BDT',
            'status'           => 'active',
        ]);

        InventoryItem::query()->create([
            'product_variant_id'  => $this->variant->id,
            'warehouse_id'        => $this->warehouse->id,
            'quantity_on_hand'    => 20,
            'quantity_reserved'   => 0,
            'low_stock_threshold' => 2,
        ]);

        config([
            'shipping.couriers.pathao.base_url'       => 'https://courier-api-sandbox.pathao.com',
            'shipping.couriers.pathao.client_id'      => 'pathao-client-id',
            'shipping.couriers.pathao.client_secret'  => 'pathao-TOP-SECRET-client-secret',
            'shipping.couriers.pathao.username'       => 'merchant@example.com',
            'shipping.couriers.pathao.password'       => 'pathao-SUPER-SECRET-password',
            'shipping.couriers.pathao.store_id'       => 101,
            'shipping.couriers.pathao.webhook_secret' => 'pathao_webhook_secret_key_123',
        ]);
    }

    private function sampleAddress(): array
    {
        return [
            'name'           => 'Farhana Rahman',
            'phone'          => '01711223344',
            'email'          => 'farhana@example.com',
            'division'       => 'Dhaka',
            'district'       => 'Dhaka',
            'upazila_thana'  => 'Banani',
            'street_address' => 'House 18, Road 11, Block F, Banani',
            'postal_code'    => '1213',
        ];
    }

    private function placeCodOrder(int $quantity = 1): array
    {
        $response = $this->actingAs($this->customerA, 'sanctum')
            ->postJson('/api/checkout/orders', [
                'idempotency_key'  => 'ord-ship-' . uniqid(),
                'items'            => [
                    [
                        'variant_id' => $this->variant->id,
                        'quantity'   => $quantity,
                    ],
                ],
                'shipping_address' => $this->sampleAddress(),
                'shipping_method'  => 'standard',
                'payment_method'   => 'cod',
            ]);

        $response->assertStatus(201);

        return $response->json('data');
    }

    // ─── 1. Pathao Delivery Charge Calculation & Secret Redaction ────────

    public function test_pathao_calculates_delivery_charge_and_redacts_oauth_credentials(): void
    {
        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response([
                'token_type'    => 'Bearer',
                'expires_in'    => 3600,
                'access_token'  => 'pathao-secret-jwt-access-token',
                'refresh_token' => 'pathao-secret-refresh-token',
            ], 200),

            '*/aladdin/api/v1/merchant/price-plan' => Http::response([
                'message' => 'price successfully calculated',
                'type'    => 'success',
                'code'    => 200,
                'data'    => [
                    'price'             => 80,
                    'discount'          => 0,
                    'promo_discount'    => 0,
                    'plan_id'           => 1,
                    'cod_enabled'       => 1,
                    'cod_percentage'    => 0.01,
                    'additional_charge' => 0,
                    'final_price'       => 80,
                ],
            ], 200),
        ]);

        $resp = $this->actingAs($this->orderManager, 'sanctum')
            ->postJson('/api/admin/shipments/calculate-charge', [
                'courier'       => 'pathao',
                'division'      => 'Dhaka',
                'district'      => 'Dhaka',
                'upazila_thana' => 'Banani',
                'weight_grams'  => 500,
                'cod_amount'    => 320000, // 3,200.00 BDT
                'delivery_type' => 'standard',
            ]);

        $resp->assertStatus(200)
            ->assertJsonPath('data.courier', 'pathao')
            ->assertJsonPath('data.delivery_charge_amount', 8000) // 80.00 BDT in paisa
            ->assertJsonPath('data.cod_fee_amount', 3200)         // 1% of 320000 = 3200 paisa
            ->assertJsonPath('data.total_charge_amount', 11200)
            ->assertJsonPath('data.currency', 'BDT');

        // Verify credentials and tokens are redacted in courier_api_logs
        $logs = CourierApiLog::query()->where('courier', 'pathao')->get();
        $this->assertGreaterThanOrEqual(2, $logs->count());
        foreach ($logs as $log) {
            $encoded = (string) json_encode([$log->request_payload, $log->response_payload]);
            $this->assertStringNotContainsString('pathao-TOP-SECRET-client-secret', $encoded);
            $this->assertStringNotContainsString('pathao-SUPER-SECRET-password', $encoded);
            $this->assertStringNotContainsString('pathao-secret-jwt-access-token', $encoded);
        }
    }

    // ─── 2. Consignment Creation, Duplicate Guard & Stock Commit ─────────

    public function test_create_shipment_stores_consignment_commits_stock_and_prevents_duplicates(): void
    {
        $orderData = $this->placeCodOrder(2);

        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response([
                'access_token' => 'pathao-token-1',
                'expires_in'   => 3600,
            ], 200),
            '*/aladdin/api/v1/orders' => Http::response([
                'message' => 'Order Created Successfully',
                'type'    => 'success',
                'code'    => 200,
                'data'    => [
                    'consignment_id'    => 'DC261111998877',
                    'merchant_order_id' => $orderData['order_number'],
                    'order_status'      => 'Pending',
                    'delivery_fee'      => 80,
                ],
            ], 200),
        ]);

        // 1. Create shipment via Pathao
        $createResp = $this->actingAs($this->orderManager, 'sanctum')
            ->postJson('/api/admin/shipments', [
                'order_id'        => $orderData['id'],
                'courier'         => 'pathao',
                'idempotency_key' => 'ship-key-001',
                'weight_grams'    => 800,
            ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('idempotent_replay', false)
            ->assertJsonPath('data.courier', 'pathao')
            ->assertJsonPath('data.consignment_id', 'DC261111998877')
            ->assertJsonPath('data.status', 'created')
            ->assertJsonPath('data.shipping_charge_amount', 8000)
            ->assertJsonPath('data.cod_amount_paisa', (int) $orderData['total_amount'])
            ->assertJsonPath('data.cod_reconciliation_status', 'pending');

        // Verify order status became 'shipped' and stock reservation was committed (20 -> 18 on hand, 0 reserved)
        $order = Order::query()->findOrFail($orderData['id']);
        $this->assertSame('shipped', $order->status);
        $this->assertSame('fulfilled', $order->fulfilment_status);

        $invItem = InventoryItem::query()->where('product_variant_id', $this->variant->id)->firstOrFail();
        $this->assertSame(18, (int) $invItem->quantity_on_hand);
        $this->assertSame(0, (int) $invItem->quantity_reserved);

        // 2. Replaying same idempotency_key returns existing shipment (200 OK)
        $replayResp = $this->actingAs($this->orderManager, 'sanctum')
            ->postJson('/api/admin/shipments', [
                'order_id'        => $orderData['id'],
                'courier'         => 'pathao',
                'idempotency_key' => 'ship-key-001',
            ]);

        $replayResp->assertStatus(200)
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('data.consignment_id', 'DC261111998877');

        // 3. Attempting to create a second shipment with a new idempotency_key fails with 409 Conflict
        $dupResp = $this->actingAs($this->orderManager, 'sanctum')
            ->postJson('/api/admin/shipments', [
                'order_id'        => $orderData['id'],
                'courier'         => 'pathao',
                'idempotency_key' => 'ship-key-002-different',
            ]);

        $dupResp->assertStatus(409);
        $this->assertSame(1, Shipment::query()->where('order_id', $orderData['id'])->count());
    }

    // ─── 3. Safe Error Logging & Retry Recovery on Courier Failure ───────

    public function test_failed_courier_api_call_logs_error_safely_and_can_be_retried(): void
    {
        $orderData = $this->placeCodOrder(1);

        $callCount = 0;
        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response([
                'access_token' => 'pathao-token-retry',
                'expires_in'   => 3600,
            ], 200),
            '*/aladdin/api/v1/orders' => function () use (&$callCount, $orderData) {
                $callCount++;
                if ($callCount === 1) {
                    return Http::response([
                        'message' => 'Recipient address is too short or invalid zone',
                        'type'    => 'error',
                        'code'    => 422,
                    ], 422);
                }

                return Http::response([
                    'message' => 'Order Created Successfully',
                    'type'    => 'success',
                    'code'    => 200,
                    'data'    => [
                        'consignment_id'    => 'DC261111RETRYOK',
                        'merchant_order_id' => $orderData['order_number'],
                        'order_status'      => 'Pending',
                        'delivery_fee'      => 70,
                    ],
                ], 200);
            },
        ]);

        // 1. First attempt fails with 502 CourierApiException
        $failResp = $this->actingAs($this->orderManager, 'sanctum')
            ->postJson('/api/admin/shipments', [
                'order_id'        => $orderData['id'],
                'courier'         => 'pathao',
                'idempotency_key' => 'ship-retry-key-1',
            ]);

        $failResp->assertStatus(502);

        $errorLog = CourierApiLog::query()
            ->where('courier', 'pathao')
            ->where('operation', 'create_shipment')
            ->where('is_error', true)
            ->first();
        $this->assertNotNull($errorLog);
        $this->assertSame(422, $errorLog->response_status);
        $this->assertStringContainsString('Recipient address', (string) $errorLog->error_message);

        // 2. Retry with the same idempotency_key recovers and creates consignment
        $retryResp = $this->actingAs($this->orderManager, 'sanctum')
            ->postJson('/api/admin/shipments', [
                'order_id'        => $orderData['id'],
                'courier'         => 'pathao',
                'idempotency_key' => 'ship-retry-key-1',
            ]);

        $retryResp->assertStatus(201)
            ->assertJsonPath('data.consignment_id', 'DC261111RETRYOK')
            ->assertJsonPath('data.status', 'created');
    }

    // ─── 4. Steadfast & REDX Adapters + Consignment Cancellation ─────────

    public function test_steadfast_and_redx_adapters_create_shipments_and_cancellation_works(): void
    {
        $order1 = $this->placeCodOrder(1);
        $order2 = $this->placeCodOrder(1);

        Http::fake([
            '*/create_order' => Http::response([
                'status'      => 200,
                'message'     => 'Consignment has been created successfully.',
                'consignment' => [
                    'consignment_id' => 99441122,
                    'invoice'        => $order1['order_number'],
                    'tracking_code'  => 'SF-TRK-99441122',
                    'status'         => 'in_review',
                ],
            ], 200),
            '*/parcel' => Http::response([
                'tracking_id' => 'REDX-BD-778899',
            ], 201),
        ]);

        // Steadfast shipment creation
        $sfResp = $this->actingAs($this->orderManager, 'sanctum')
            ->postJson('/api/admin/shipments', [
                'order_id'        => $order1['id'],
                'courier'         => 'steadfast',
                'idempotency_key' => 'sf-key-1',
            ]);

        $sfResp->assertStatus(201)
            ->assertJsonPath('data.courier', 'steadfast')
            ->assertJsonPath('data.consignment_id', '99441122')
            ->assertJsonPath('data.tracking_number', 'SF-TRK-99441122')
            ->assertJsonPath('data.status', 'created');

        $sfShipmentId = $sfResp->json('data.id');

        // Cancel the Steadfast shipment before pickup
        $cancelResp = $this->actingAs($this->orderManager, 'sanctum')
            ->postJson("/api/admin/shipments/{$sfShipmentId}/cancel", [
                'reason' => 'Customer requested address change before dispatch',
            ]);

        $cancelResp->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cod_reconciliation_status', 'not_applicable');

        // REDX shipment creation
        $redxResp = $this->actingAs($this->orderManager, 'sanctum')
            ->postJson('/api/admin/shipments', [
                'order_id'        => $order2['id'],
                'courier'         => 'redx',
                'idempotency_key' => 'redx-key-1',
            ]);

        $redxResp->assertStatus(201)
            ->assertJsonPath('data.courier', 'redx')
            ->assertJsonPath('data.consignment_id', 'REDX-BD-778899')
            ->assertJsonPath('data.status', 'created');
    }

    // ─── 5. Tracking Sync, Signed Webhooks & Customer Tracking View ──────

    public function test_tracking_sync_and_webhooks_update_status_idempotently_and_customer_can_track(): void
    {
        $orderData = $this->placeCodOrder(1);
        $orderNumber = $orderData['order_number'];

        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response([
                'access_token' => 'pathao-token-trk',
                'expires_in'   => 3600,
            ], 200),
            '*/aladdin/api/v1/orders' => Http::response([
                'code' => 200,
                'data' => [
                    'consignment_id'    => 'DC-TRK-556677',
                    'merchant_order_id' => $orderNumber,
                    'order_status'      => 'Pending',
                    'delivery_fee'      => 70,
                ],
            ], 200),
            '*/aladdin/api/v1/orders/DC-TRK-556677' => Http::response([
                'code' => 200,
                'data' => [
                    'consignment_id'    => 'DC-TRK-556677',
                    'order_status'      => 'Picked_Up',
                    'order_status_text' => 'Parcel picked up by Pathao rider',
                    'city_name'         => 'Dhaka Hub',
                    'updated_at'        => '2026-11-11T10:00:00Z',
                ],
            ], 200),
        ]);

        $createResp = $this->actingAs($this->orderManager, 'sanctum')
            ->postJson('/api/admin/shipments', [
                'order_id'        => $orderData['id'],
                'courier'         => 'pathao',
                'idempotency_key' => 'trk-ship-1',
            ]);
        $shipmentId = $createResp->json('data.id');

        // 1. Poll tracking via admin endpoint
        $pollResp = $this->actingAs($this->orderManager, 'sanctum')
            ->postJson("/api/admin/shipments/{$shipmentId}/track");

        $pollResp->assertStatus(200)
            ->assertJsonPath('data.status', 'picked_up');

        // 2. Forged webhook signature is rejected with 400
        $webhookPayload = [
            'consignment_id'    => 'DC-TRK-556677',
            'merchant_order_id' => $orderNumber,
            'order_status'      => 'Delivered',
            'collected_amount'  => $orderData['total_amount'] / 100,
            'updated_at'        => '2026-11-11T15:30:00Z',
        ];
        $rawBody = (string) json_encode($webhookPayload);

        $this->call('POST', '/api/webhooks/shipping/pathao', [], [], [], [
            'CONTENT_TYPE'            => 'application/json',
            'HTTP_ACCEPT'             => 'application/json',
            'HTTP_X_PATHAO_SIGNATURE' => 'forged-bad-signature',
        ], $rawBody)->assertStatus(400);

        // 3. Valid signed Pathao webhook marks shipment and order as delivered
        $wh1 = $this->call('POST', '/api/webhooks/shipping/pathao', [], [], [], [
            'CONTENT_TYPE'            => 'application/json',
            'HTTP_ACCEPT'             => 'application/json',
            'HTTP_X_PATHAO_SIGNATURE' => 'pathao_webhook_secret_key_123',
        ], $rawBody);

        $wh1->assertStatus(200)
            ->assertJsonPath('idempotent_replay', false)
            ->assertJsonPath('data.shipment.status', 'delivered')
            ->assertJsonPath('data.shipment.cod_reconciliation_status', 'reconciled');

        // Replay identical webhook -> idempotent_replay: true
        $wh2 = $this->call('POST', '/api/webhooks/shipping/pathao', [], [], [], [
            'CONTENT_TYPE'            => 'application/json',
            'HTTP_ACCEPT'             => 'application/json',
            'HTTP_X_PATHAO_SIGNATURE' => 'pathao_webhook_secret_key_123',
        ], $rawBody);
        $wh2->assertStatus(200)->assertJsonPath('idempotent_replay', true);

        // 4. Customer A can view tracking history; Customer B receives 403
        $this->actingAs($this->customerA, 'sanctum')
            ->getJson("/api/orders/{$orderNumber}/tracking")
            ->assertStatus(200)
            ->assertJsonPath('data.order_status', 'delivered')
            ->assertJsonPath('data.shipments.0.consignment_id', 'DC-TRK-556677')
            ->assertJsonCount(3, 'data.shipments.0.tracking_events');

        $this->actingAs($this->customerB, 'sanctum')
            ->getJson("/api/orders/{$orderNumber}/tracking")
            ->assertStatus(403);
    }

    // ─── 6. COD Collection Reconciliation & Discrepancy Flagging ─────────

    public function test_cod_collection_reconciliation_matches_exact_amounts_and_flags_discrepancies(): void
    {
        $order1 = $this->placeCodOrder(1);
        $order2 = $this->placeCodOrder(2);

        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            '*/aladdin/api/v1/orders' => Http::sequence()
                ->push([
                    'code' => 200,
                    'data' => [
                        'consignment_id'    => 'DC-COD-EXACT',
                        'merchant_order_id' => $order1['order_number'],
                        'order_status'      => 'Pending',
                        'delivery_fee'      => 70,
                    ],
                ], 200)
                ->push([
                    'code' => 200,
                    'data' => [
                        'consignment_id'    => 'DC-COD-SHORT',
                        'merchant_order_id' => $order2['order_number'],
                        'order_status'      => 'Pending',
                        'delivery_fee'      => 70,
                    ],
                ], 200),
        ]);

        $this->actingAs($this->orderManager, 'sanctum')
            ->postJson('/api/admin/shipments', ['order_id' => $order1['id'], 'courier' => 'pathao', 'idempotency_key' => 'cod-k1'])
            ->assertStatus(201);

        $this->actingAs($this->orderManager, 'sanctum')
            ->postJson('/api/admin/shipments', ['order_id' => $order2['id'], 'courier' => 'pathao', 'idempotency_key' => 'cod-k2'])
            ->assertStatus(201);

        $reconcileResp = $this->actingAs($this->orderManager, 'sanctum')
            ->postJson('/api/admin/shipments/cod-reconciliation', [
                'courier' => 'pathao',
                'items'   => [
                    [
                        'consignment_id'   => 'DC-COD-EXACT',
                        'collected_amount' => (int) $order1['total_amount'],
                    ],
                    [
                        'consignment_id'   => 'DC-COD-SHORT',
                        'collected_amount' => (int) $order2['total_amount'] - 5000, // 50.00 BDT short
                    ],
                ],
            ]);

        $reconcileResp->assertStatus(200)
            ->assertJsonPath('data.reconciled_count', 1)
            ->assertJsonPath('data.discrepancy_count', 1)
            ->assertJsonPath('data.items.0.cod_reconciliation_status', 'reconciled')
            ->assertJsonPath('data.items.1.cod_reconciliation_status', 'discrepancy')
            ->assertJsonPath('data.items.1.difference_amount', -5000);

        // Order 1 payment_status is now 'paid', while Order 2 remains 'cod_pending' due to discrepancy
        $this->assertSame('paid', Order::query()->findOrFail($order1['id'])->payment_status);
        $this->assertSame('cod_pending', Order::query()->findOrFail($order2['id'])->payment_status);
    }

    // ─── 7. Returned Parcel Handling & Idempotent Stock Restoration ──────

    public function test_returned_parcel_webhook_restores_inventory_once_and_marks_order_returned(): void
    {
        $orderData = $this->placeCodOrder(3);
        $orderNumber = $orderData['order_number'];

        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            '*/aladdin/api/v1/orders' => Http::response([
                'code' => 200,
                'data' => [
                    'consignment_id'    => 'DC-RTN-9988',
                    'merchant_order_id' => $orderNumber,
                    'order_status'      => 'Pending',
                    'delivery_fee'      => 70,
                ],
            ], 200),
        ]);

        // 1. Dispatch order -> commits reservation (20 -> 17 on hand, 0 reserved)
        $this->actingAs($this->orderManager, 'sanctum')
            ->postJson('/api/admin/shipments', [
                'order_id'        => $orderData['id'],
                'courier'         => 'pathao',
                'idempotency_key' => 'rtn-ship-1',
            ])
            ->assertStatus(201);

        $invItem = InventoryItem::query()->where('product_variant_id', $this->variant->id)->firstOrFail();
        $this->assertSame(17, (int) $invItem->quantity_on_hand);
        $this->assertSame(0, (int) $invItem->quantity_reserved);

        // 2. Pathao sends 'Returned' webhook
        $returnPayload = [
            'consignment_id'    => 'DC-RTN-9988',
            'merchant_order_id' => $orderNumber,
            'order_status'      => 'Returned',
            'reason'            => 'Customer unreachable after 3 attempts',
            'updated_at'        => '2026-11-12T12:00:00Z',
        ];
        $rawBody = (string) json_encode($returnPayload);

        $rtnResp = $this->call('POST', '/api/webhooks/shipping/pathao', [], [], [], [
            'CONTENT_TYPE'            => 'application/json',
            'HTTP_ACCEPT'             => 'application/json',
            'HTTP_X_PATHAO_SIGNATURE' => 'pathao_webhook_secret_key_123',
        ], $rawBody);

        $rtnResp->assertStatus(200)
            ->assertJsonPath('data.shipment.status', 'returned')
            ->assertJsonPath('idempotent_replay', false);

        // Verify inventory was restored (17 + 3 = 20 on hand) and a 'return' InventoryMovement was recorded
        $invItem->refresh();
        $this->assertSame(20, (int) $invItem->quantity_on_hand);

        $returnMovement = InventoryMovement::query()
            ->where('inventory_item_id', $invItem->id)
            ->where('type', 'return')
            ->first();
        $this->assertNotNull($returnMovement);
        $this->assertSame(3, (int) $returnMovement->quantity_change);
        $this->assertSame(3, (int) $returnMovement->on_hand_delta);

        // Verify Order status is now 'returned'
        $order = Order::query()->findOrFail($orderData['id']);
        $this->assertSame('returned', $order->status);
        $this->assertSame('returned', $order->fulfilment_status);

        // 3. Even if a second 'Returned' event with a new timestamp arrives, stock is NOT double-restored
        $secondReturnPayload = [
            'consignment_id'    => 'DC-RTN-9988',
            'merchant_order_id' => $orderNumber,
            'order_status'      => 'Returned',
            'reason'            => 'RTM completed at hub',
            'updated_at'        => '2026-11-12T14:00:00Z',
        ];
        $this->call('POST', '/api/webhooks/shipping/pathao', [], [], [], [
            'CONTENT_TYPE'            => 'application/json',
            'HTTP_ACCEPT'             => 'application/json',
            'HTTP_X_PATHAO_SIGNATURE' => 'pathao_webhook_secret_key_123',
        ], (string) json_encode($secondReturnPayload))->assertStatus(200);

        $invItem->refresh();
        $this->assertSame(20, (int) $invItem->quantity_on_hand);
    }
}
