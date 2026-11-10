<?php

namespace Tests\Feature\OrdersAndPayments;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderStatusHistory;
use App\Modules\Payments\Models\PaymentGatewayLog;
use App\Modules\Payments\Models\PaymentRefund;
use App\Modules\Payments\Models\PaymentTransaction;
use App\Modules\Payments\Models\PaymentWebhookEvent;
use App\Shared\Contracts\Inventory\InventoryServiceContract;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrdersAndPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected User $staffManager;
    protected User $superAdmin;
    protected User $customerA;
    protected User $customerB;
    protected Warehouse $warehouse;
    protected Product $product;
    protected ProductVariant $variant;
    protected InventoryServiceContract $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->staffManager = User::factory()->staff('order_manager')->create([
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $this->superAdmin = User::factory()->superAdmin()->create([
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $this->customerA = User::factory()->create([
            'name'              => 'Nusrat Jahan',
            'email'             => 'nusrat@example.com',
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $this->customerB = User::factory()->create([
            'name'              => 'Tanvir Ahmed',
            'email'             => 'tanvir@example.com',
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $this->warehouse = Warehouse::query()->create([
            'name'       => 'Dhaka Flagship Fulfillment Center',
            'code'       => 'WH-DHK-MAIN',
            'city'       => 'Dhaka',
            'country'    => 'BD',
            'is_active'  => true,
            'is_default' => true,
            'priority'   => 1,
        ]);

        $brand = Brand::query()->create([
            'name'      => 'Dhakai Looms',
            'slug'      => 'dhakai-looms',
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'brand_id'     => $brand->id,
            'name'         => 'Handloom Dhakai Jamdani Saree',
            'slug'         => 'handloom-dhakai-jamdani-saree',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->variant = ProductVariant::factory()->create([
            'product_id'       => $this->product->id,
            'sku'              => 'JMD-SAREE-CRIMSON-OS',
            'color'            => 'Crimson Gold',
            'size'             => 'OS',
            'price_amount'     => 450000, // 4,500.00 BDT in paisa
            'compare_at_price' => 520000,
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

        $this->inventoryService = $this->app->make(InventoryServiceContract::class);
    }

    private function sampleBangladeshAddress(): array
    {
        return [
            'name'           => 'Nusrat Jahan',
            'phone'          => '01711223344',
            'email'          => 'nusrat@example.com',
            'division'       => 'Dhaka',
            'district'       => 'Dhaka',
            'upazila_thana'  => 'Gulshan',
            'street_address' => 'House 14, Road 55, Gulshan-2',
            'postal_code'    => '1212',
        ];
    }

    private function placeSampleOrder(User $user, string $paymentMethod = 'cod', int $quantity = 1): array
    {
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/checkout/orders', [
                'idempotency_key'  => 'ord-key-' . uniqid(),
                'items'            => [
                    [
                        'variant_id' => $this->variant->id,
                        'quantity'   => $quantity,
                    ],
                ],
                'shipping_address' => $this->sampleBangladeshAddress(),
                'shipping_method'  => 'standard',
                'payment_method'   => $paymentMethod,
            ]);

        $response->assertStatus(201);

        return $response->json('data');
    }

    // ─── 1. Order Creation, Snapshots & Initial Status History ────────────

    public function test_order_creation_generates_unique_order_number_snapshots_and_status_history(): void
    {
        $orderA = $this->placeSampleOrder($this->customerA, 'cod', 2);
        $orderB = $this->placeSampleOrder($this->customerA, 'bkash', 1);

        $this->assertMatchesRegularExpression('/^ORD-\d{8}-[A-Z0-9]{6}$/', $orderA['order_number']);
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-[A-Z0-9]{6}$/', $orderB['order_number']);
        $this->assertNotSame($orderA['order_number'], $orderB['order_number']);

        // Verify immutable order item snapshot persists even if catalog product price later changes
        $this->variant->update(['price_amount' => 999900]);

        $detailResp = $this->actingAs($this->customerA, 'sanctum')
            ->getJson("/api/orders/{$orderA['order_number']}");

        $detailResp->assertStatus(200)
            ->assertJsonPath('data.order_number', $orderA['order_number'])
            ->assertJsonPath('data.status', 'CONFIRMED')
            ->assertJsonPath('data.payment_status', 'cod_pending')
            ->assertJsonPath('data.fulfilment_status', 'unfulfilled')
            ->assertJsonPath('data.items.0.sku_snapshot', 'JMD-SAREE-CRIMSON-OS')
            ->assertJsonPath('data.items.0.unit_price_amount', 450000)
            ->assertJsonPath('data.items.0.line_total_amount', 900000);

        // Verify initial status history and COD payment transaction record
        $this->assertCount(1, $detailResp->json('data.status_histories'));
        $this->assertSame('confirmed', $detailResp->json('data.status_histories.0.to_status'));
        $this->assertCount(1, $detailResp->json('data.payment_transactions'));
        $this->assertSame('cod', $detailResp->json('data.payment_transactions.0.gateway'));
        $this->assertSame('cod_pending', $detailResp->json('data.payment_transactions.0.status'));
    }

    // ─── 2. Customer Order History, Authorization & Cancellation ─────────

    public function test_customer_order_history_authorization_and_cancellation_releases_reserved_stock(): void
    {
        $orderData = $this->placeSampleOrder($this->customerA, 'cod', 3);
        $orderNumber = $orderData['order_number'];

        // Verify 3 units are reserved (20 on hand, 3 reserved, 17 available)
        $invItem = InventoryItem::query()->where('product_variant_id', $this->variant->id)->firstOrFail();
        $this->assertSame(20, (int) $invItem->quantity_on_hand);
        $this->assertSame(3, (int) $invItem->quantity_reserved);

        // Customer A can list their orders
        $listResp = $this->actingAs($this->customerA, 'sanctum')->getJson('/api/orders');
        $listResp->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', $orderNumber);

        // Customer B cannot view or cancel Customer A's order
        $this->actingAs($this->customerB, 'sanctum')
            ->getJson("/api/orders/{$orderNumber}")
            ->assertStatus(403);

        $this->actingAs($this->customerB, 'sanctum')
            ->postJson("/api/orders/{$orderNumber}/cancel", ['reason' => 'Unauthorized attempt'])
            ->assertStatus(403);

        // Customer A cancels their order
        $cancelResp = $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/orders/{$orderNumber}/cancel", [
                'reason' => 'Changed delivery schedule',
                'notes'  => 'Will reorder next week',
            ]);

        $cancelResp->assertStatus(200)
            ->assertJsonPath('data.status', 'CANCELLED')
            ->assertJsonPath('data.cancellation_reason', 'Changed delivery schedule');

        // Verify reserved stock was released back to available inventory
        $invItem->refresh();
        $this->assertSame(20, (int) $invItem->quantity_on_hand);
        $this->assertSame(0, (int) $invItem->quantity_reserved);

        $reservation = StockReservation::query()->find($orderData['stock_reservation_id']);
        $this->assertSame('released', $reservation?->status);

        // Verify status history recorded the cancellation
        $this->assertCount(2, $cancelResp->json('data.status_histories'));
        $this->assertSame('cancelled', $cancelResp->json('data.status_histories.1.to_status'));
    }

    // ─── 3. Admin Order Lifecycle (Confirm -> Process -> Ship -> Deliver) ─

    public function test_admin_order_management_lifecycle_commits_stock_on_ship_and_collects_cod_on_deliver(): void
    {
        $orderData = $this->placeSampleOrder($this->customerA, 'cod', 2);
        $orderId = $orderData['id'];
        $orderNumber = $orderData['order_number'];

        // 1. Admin lists orders with filters
        $adminList = $this->actingAs($this->staffManager, 'sanctum')
            ->getJson("/api/admin/orders?search={$orderNumber}&payment_method=cod");
        $adminList->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.order_number', $orderNumber);

        // 2. Admin confirms order
        $confirmResp = $this->actingAs($this->staffManager, 'sanctum')
            ->postJson("/api/admin/orders/{$orderId}/confirm", ['notes' => 'Verified phone with customer']);
        $confirmResp->assertStatus(200)
            ->assertJsonPath('data.status', 'CONFIRMED');

        // 3. Admin transitions order to processing
        $processResp = $this->actingAs($this->staffManager, 'sanctum')
            ->postJson("/api/admin/orders/{$orderId}/process", ['notes' => 'Packed at Dhaka warehouse']);
        $processResp->assertStatus(200)
            ->assertJsonPath('data.status', 'PROCESSING');

        // 4. Admin ships order -> commits stock reservation (`quantity_on_hand` deducted from 20 to 18)
        $shipResp = $this->actingAs($this->staffManager, 'sanctum')
            ->postJson("/api/admin/orders/{$orderId}/ship", [
                'carrier'         => 'Pathao Courier',
                'tracking_number' => 'PTH-BD-889900',
                'notes'           => 'Dispatched via Pathao Next-Day',
            ]);
        $shipResp->assertStatus(200)
            ->assertJsonPath('data.status', 'SHIPPED')
            ->assertJsonPath('data.fulfilment_status', 'fulfilled');

        $invItem = InventoryItem::query()->where('product_variant_id', $this->variant->id)->firstOrFail();
        $this->assertSame(18, (int) $invItem->quantity_on_hand);
        $this->assertSame(0, (int) $invItem->quantity_reserved);

        // Customer cannot cancel once shipped
        $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/orders/{$orderNumber}/cancel", ['reason' => 'Too late'])
            ->assertStatus(422);

        // 5. Admin marks order as delivered -> COD payment_status becomes 'paid'
        $deliverResp = $this->actingAs($this->staffManager, 'sanctum')
            ->postJson("/api/admin/orders/{$orderId}/deliver", ['notes' => 'Cash collected on delivery']);
        $deliverResp->assertStatus(200)
            ->assertJsonPath('data.status', 'DELIVERED')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.fulfilment_status', 'delivered');

        // COD orders are auto-confirmed at checkout, so admin confirm is an idempotent no-op.
        // History trail: created(confirmed) -> processing -> shipped -> delivered
        $this->assertCount(4, $deliverResp->json('data.status_histories'));
        $this->assertSame('paid', $deliverResp->json('data.payment_transactions.0.status'));
    }

    // ─── 4. bKash Tokenized Checkout Adapter (v1.2.0-beta) Full Flow ─────

    public function test_bkash_gateway_adapter_initiates_verifies_refunds_and_redacts_secrets(): void
    {
        config([
            'payment.gateways.bkash.base_url'   => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'payment.gateways.bkash.app_key'    => 'bkash-test-app-key',
            'payment.gateways.bkash.app_secret' => 'bkash-TOP-SECRET-key-999',
            'payment.gateways.bkash.username'   => 'bkash-merchant-user',
            'payment.gateways.bkash.password'   => 'bkash-SUPER-SECRET-password',
        ]);

        $orderData = $this->placeSampleOrder($this->customerA, 'bkash', 1);
        $orderNumber = $orderData['order_number'];
        $expectedAmountPaisa = (int) $orderData['total_amount'];
        $expectedBdtString = number_format($expectedAmountPaisa / 100, 2, '.', '');

        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response([
                'statusCode'    => '0000',
                'statusMessage' => 'Successful',
                'id_token'      => 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.bkash-secret-jwt',
                'token_type'    => 'Bearer',
                'expires_in'    => 3600,
                'refresh_token' => 'bkash-secret-refresh-token',
            ], 200),

            '*/tokenized/checkout/create' => Http::response([
                'statusCode'            => '0000',
                'statusMessage'         => 'Successful',
                'paymentID'             => 'TR0011BKASH998877',
                'bkashURL'              => 'https://sandbox.payment.bkash.com/redirect/TR0011BKASH998877',
                'transactionStatus'     => 'Initiated',
                'amount'                => $expectedBdtString,
                'currency'              => 'BDT',
                'intent'                => 'sale',
                'merchantInvoiceNumber' => $orderNumber,
            ], 200),

            '*/tokenized/checkout/execute' => Http::response([
                'statusCode'            => '0000',
                'statusMessage'         => 'Successful',
                'paymentID'             => 'TR0011BKASH998877',
                'trxID'                 => 'BKH7766554433',
                'transactionStatus'     => 'Completed',
                'amount'                => $expectedBdtString,
                'currency'              => 'BDT',
                'intent'                => 'sale',
                'merchantInvoiceNumber' => $orderNumber,
            ], 200),

            '*/tokenized/checkout/payment/refund' => Http::response([
                'statusCode'        => '0000',
                'statusMessage'     => 'Successful',
                'originalTrxID'     => 'BKH7766554433',
                'refundTrxID'       => 'BKH-REF-112233',
                'transactionStatus' => 'Completed',
                'amount'            => '1000.00',
                'currency'          => 'BDT',
            ], 200),
        ]);

        // 1. Initiate bKash payment
        $initResp = $this->actingAs($this->customerA, 'sanctum')
            ->postJson('/api/payments/initiate', [
                'order_number'    => $orderNumber,
                'gateway'         => 'bkash',
                'idempotency_key' => 'bkash-init-' . $orderNumber,
            ]);

        $initResp->assertStatus(201)
            ->assertJsonPath('data.transaction.gateway', 'bkash')
            ->assertJsonPath('data.transaction.gateway_payment_id', 'TR0011BKASH998877')
            ->assertJsonPath('data.transaction.status', 'pending')
            ->assertJsonPath('data.redirect_url', 'https://sandbox.payment.bkash.com/redirect/TR0011BKASH998877');

        // 2. Process bKash callback -> triggers server-side execute/verify
        $callbackResp = $this->postJson('/api/payments/callback/bkash', [
            'paymentID' => 'TR0011BKASH998877',
            'status'    => 'success',
        ]);

        $callbackResp->assertStatus(200)
            ->assertJsonPath('data.transaction.status', 'paid')
            ->assertJsonPath('data.transaction.gateway_transaction_id', 'BKH7766554433')
            ->assertJsonPath('data.order.payment_status', 'paid')
            ->assertJsonPath('data.order.status', 'CONFIRMED')
            ->assertJsonPath('idempotent_replay', false);

        // 3. Verify sensitive credentials (app_secret, password, id_token) are redacted in payment_gateway_logs
        $logs = PaymentGatewayLog::query()->where('gateway', 'bkash')->get();
        $this->assertGreaterThanOrEqual(2, $logs->count());
        foreach ($logs as $log) {
            $serialized = json_encode([
                'req_headers' => $log->request_headers,
                'req_body'    => $log->request_payload,
                'res_body'    => $log->response_payload,
            ]);
            $this->assertStringNotContainsString('bkash-TOP-SECRET-key-999', (string) $serialized);
            $this->assertStringNotContainsString('bkash-SUPER-SECRET-password', (string) $serialized);
            $this->assertStringNotContainsString('eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.bkash-secret-jwt', (string) $serialized);
        }

        // 4. Refunds need `payments.manage`; an order_manager only has `payments.view`
        $this->actingAs($this->staffManager, 'sanctum')
            ->postJson("/api/admin/orders/{$orderData['id']}/refund", [
                'amount'          => 100000,
                'reason'          => 'Unauthorized refund attempt',
                'idempotency_key' => 'bkash-refund-denied',
            ])
            ->assertStatus(403);

        // Admin initiates partial refund (1,000.00 BDT = 100000 paisa)
        $refundResp = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/admin/orders/{$orderData['id']}/refund", [
                'amount'          => 100000,
                'reason'          => 'Goodwill partial discount adjustment',
                'idempotency_key' => 'bkash-refund-1',
            ]);

        $refundResp->assertStatus(200)
            ->assertJsonPath('data.refund.gateway_refund_id', 'BKH-REF-112233')
            ->assertJsonPath('data.refund.amount', 100000)
            ->assertJsonPath('data.order.payment_status', 'partially_refunded');
    }

    // ─── 5. SSLCommerz Gateway Adapter (v4) Full Flow ────────────────────

    public function test_sslcommerz_gateway_adapter_initiates_validates_server_side_and_redacts_store_passwd(): void
    {
        config([
            'payment.gateways.sslcommerz.base_url'     => 'https://sandbox.sslcommerz.com',
            'payment.gateways.sslcommerz.store_id'     => 'clothstorelive01',
            'payment.gateways.sslcommerz.store_passwd' => 'sslc-SUPER-SECRET-PASSWD-888',
        ]);

        $orderData = $this->placeSampleOrder($this->customerA, 'sslcommerz', 1);
        $orderNumber = $orderData['order_number'];
        $expectedAmountPaisa = (int) $orderData['total_amount'];
        $expectedBdtString = number_format($expectedAmountPaisa / 100, 2, '.', '');

        $capturedTranId = null;

        Http::fake([
            '*/gwprocess/v4/api.php' => function ($request) use (&$capturedTranId) {
                $capturedTranId = $request['tran_id'] ?? null;
                return Http::response([
                    'status'         => 'SUCCESS',
                    'sessionkey'     => 'SSLC-SESSION-KEY-998877',
                    'GatewayPageURL' => 'https://sandbox.sslcommerz.com/EasyCheckOut/testcde998877',
                ], 200);
            },
            '*/validator/api/validationserverAPI.php*' => function () use (&$capturedTranId, $expectedBdtString) {
                return Http::response([
                    'status'        => 'VALIDATED',
                    'tran_id'       => $capturedTranId,
                    'val_id'        => 'SSLC-VAL-ID-554433',
                    'bank_tran_id'  => 'SSLC-BANK-TRX-991122',
                    'amount'        => $expectedBdtString,
                    'currency_type' => 'BDT',
                    'currency'      => 'BDT',
                    'store_id'      => 'clothstorelive01',
                ], 200);
            },
        ]);

        $initResp = $this->actingAs($this->customerA, 'sanctum')
            ->postJson('/api/payments/initiate', [
                'order_number'    => $orderNumber,
                'gateway'         => 'sslcommerz',
                'idempotency_key' => 'sslc-init-' . $orderNumber,
            ]);

        $initResp->assertStatus(201)
            ->assertJsonPath('data.transaction.gateway', 'sslcommerz')
            ->assertJsonPath('data.redirect_url', 'https://sandbox.sslcommerz.com/EasyCheckOut/testcde998877');

        $merchantRef = $initResp->json('data.transaction.merchant_reference');

        // Verify store_passwd is redacted in PaymentTransaction raw_initiation_request
        $tx = PaymentTransaction::query()->where('merchant_reference', $merchantRef)->firstOrFail();
        $this->assertSame('[REDACTED]', $tx->raw_initiation_request['store_passwd'] ?? null);

        // Process SSLCommerz IPN webhook notification
        $webhookResp = $this->postJson('/api/webhooks/sslcommerz', [
            'tran_id'      => $merchantRef,
            'val_id'       => 'SSLC-VAL-ID-554433',
            'bank_tran_id' => 'SSLC-BANK-TRX-991122',
            'status'       => 'VALID',
            'amount'       => $expectedBdtString,
            'currency'     => 'BDT',
            'store_id'     => 'clothstorelive01',
        ]);

        $webhookResp->assertStatus(200)
            ->assertJsonPath('data.status', 'processed')
            ->assertJsonPath('data.transaction.status', 'paid')
            ->assertJsonPath('data.order.payment_status', 'paid')
            ->assertJsonPath('data.order.status', 'CONFIRMED')
            ->assertJsonPath('idempotent_replay', false);
    }

    // ─── 6. Nagad Merchant Checkout Adapter (v-0.2.0) Verification Flow ──

    public function test_nagad_gateway_adapter_verifies_server_side_payment_status_accurately(): void
    {
        config([
            'payment.gateways.nagad.base_url'    => 'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0',
            'payment.gateways.nagad.merchant_id' => '683002007104225',
        ]);

        $orderData = $this->placeSampleOrder($this->customerA, 'nagad', 1);
        $order = Order::query()->findOrFail($orderData['id']);
        $expectedAmountPaisa = (int) $order->total_amount;
        $expectedBdtString = number_format($expectedAmountPaisa / 100, 2, '.', '');

        // Create a pending Nagad transaction (simulating an initiated Nagad session)
        $tx = PaymentTransaction::query()->create([
            'order_id'           => $order->id,
            'idempotency_key'    => 'nagad-tx-' . $order->order_number,
            'merchant_reference' => 'TXN-' . $order->order_number . '-NGD1',
            'gateway'            => 'nagad',
            'gateway_payment_id' => 'NAGAD-PREF-778899',
            'amount'             => $expectedAmountPaisa,
            'currency'           => 'BDT',
            'status'             => 'awaiting_payment',
        ]);

        Http::fake([
            '*/verify/payment/NAGAD-PREF-778899' => Http::response([
                'merchantId'             => '683002007104225',
                'orderId'                => $tx->merchant_reference,
                'paymentRefId'           => 'NAGAD-PREF-778899',
                'amount'                 => $expectedBdtString,
                'issuerPaymentRefNo'     => 'NGD-ISSUER-TRX-556677',
                'status'                 => 'Success',
                'statusCode'             => '000',
                'additionalMerchantInfo' => json_encode([
                    'order_id'           => $order->id,
                    'order_number'       => $order->order_number,
                    'merchant_reference' => $tx->merchant_reference,
                    'currency'           => 'BDT',
                ]),
            ], 200),
        ]);

        $callbackResp = $this->getJson('/api/payments/callback/nagad?payment_ref_id=NAGAD-PREF-778899&status=Success');

        $callbackResp->assertStatus(200)
            ->assertJsonPath('data.transaction.status', 'paid')
            ->assertJsonPath('data.transaction.gateway_transaction_id', 'NGD-ISSUER-TRX-556677')
            ->assertJsonPath('data.order.payment_status', 'paid')
            ->assertJsonPath('data.order.status', 'CONFIRMED');
    }

    // ─── 7. Idempotent Callbacks, Repeated Webhooks & Duplicate Guard ────

    public function test_callbacks_and_webhooks_are_idempotent_and_duplicate_payments_are_prevented(): void
    {
        $orderData = $this->placeSampleOrder($this->customerA, 'mock', 1);
        $orderNumber = $orderData['order_number'];

        // 1. Initiate payment with idempotency key
        $init1 = $this->actingAs($this->customerA, 'sanctum')
            ->postJson('/api/payments/initiate', [
                'order_number'    => $orderNumber,
                'gateway'         => 'mock',
                'idempotency_key' => 'mock-init-key-1',
            ]);
        $init1->assertStatus(201)->assertJsonPath('idempotent_replay', false);

        // Replaying same idempotency_key returns existing transaction (200 OK)
        $init2 = $this->actingAs($this->customerA, 'sanctum')
            ->postJson('/api/payments/initiate', [
                'order_number'    => $orderNumber,
                'gateway'         => 'mock',
                'idempotency_key' => 'mock-init-key-1',
            ]);
        $init2->assertStatus(200)
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('data.transaction.id', $init1->json('data.transaction.id'));

        $merchantRef = $init1->json('data.transaction.merchant_reference');

        // 2. Process webhook first time
        $webhookPayload = [
            'event_id'           => 'EVT-MOCK-10001',
            'event_type'         => 'payment.completed',
            'merchant_reference' => $merchantRef,
            'trx_id'             => 'MOCK-TRX-10001',
            'status'             => 'paid',
            'amount_paisa'       => $orderData['total_amount'],
            'currency'           => 'BDT',
        ];

        // Webhooks must carry a valid HMAC-SHA256 signature over the raw body
        $rawBody = (string) json_encode($webhookPayload);
        $signature = hash_hmac('sha256', $rawBody, (string) config('payment.gateways.mock.webhook_secret'));

        // Unsigned / forged webhook is rejected before any state change
        $this->call('POST', '/api/webhooks/mock', [], [], [], [
            'CONTENT_TYPE'          => 'application/json',
            'HTTP_ACCEPT'           => 'application/json',
            'HTTP_X_MOCK_SIGNATURE' => 'forged-signature',
        ], $rawBody)->assertStatus(400);
        $this->assertSame(0, PaymentWebhookEvent::query()->count());

        $sendSigned = fn () => $this->call('POST', '/api/webhooks/mock', [], [], [], [
            'CONTENT_TYPE'          => 'application/json',
            'HTTP_ACCEPT'           => 'application/json',
            'HTTP_X_MOCK_SIGNATURE' => $signature,
        ], $rawBody);

        $wh1 = $sendSigned();
        $wh1->assertStatus(200)
            ->assertJsonPath('idempotent_replay', false)
            ->assertJsonPath('data.transaction.status', 'paid');

        $historyCountAfterFirstWebhook = OrderStatusHistory::query()->where('order_id', $orderData['id'])->count();

        // 3. Replay identical webhook -> idempotent replay, no extra history or webhook rows
        $wh2 = $sendSigned();
        $wh2->assertStatus(200)
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('data.webhook_event_id', $wh1->json('data.webhook_event_id'));

        $this->assertSame(1, PaymentWebhookEvent::query()->where('gateway_event_id', 'EVT-MOCK-10001')->count());
        $this->assertSame($historyCountAfterFirstWebhook, OrderStatusHistory::query()->where('order_id', $orderData['id'])->count());

        // 4. Replay callback on already-paid transaction -> idempotent replay
        $cbReplay = $this->postJson('/api/payments/callback/mock', [
            'merchant_reference' => $merchantRef,
        ]);
        $cbReplay->assertStatus(200)
            ->assertJsonPath('idempotent_replay', true);

        // 5. Attempting a new payment initiation on an already-paid order fails with 409 Conflict
        $duplicateInit = $this->actingAs($this->customerA, 'sanctum')
            ->postJson('/api/payments/initiate', [
                'order_number'    => $orderNumber,
                'gateway'         => 'mock',
                'idempotency_key' => 'mock-init-key-NEW',
            ]);
        $duplicateInit->assertStatus(409);
    }

    // ─── 8. Amount, Currency & Merchant Reference Mismatch Guards ────────

    public function test_server_side_verification_rejects_amount_currency_and_reference_mismatches(): void
    {
        $orderData = $this->placeSampleOrder($this->customerA, 'mock', 1);
        $orderNumber = $orderData['order_number'];

        $initResp = $this->actingAs($this->customerA, 'sanctum')
            ->postJson('/api/payments/initiate', [
                'order_number'    => $orderNumber,
                'gateway'         => 'mock',
                'idempotency_key' => 'mismatch-test-init-1',
            ]);
        $merchantRef = $initResp->json('data.transaction.merchant_reference');

        // 1. Tampered amount (10000 paisa instead of actual order total)
        $amountMismatchResp = $this->postJson('/api/payments/verify', [
            'gateway'            => 'mock',
            'merchant_reference' => $merchantRef,
            'payload'            => [
                'simulated_amount_paisa' => 10000,
                'currency'               => 'BDT',
            ],
        ]);
        $amountMismatchResp->assertStatus(422)
            ->assertJsonValidationErrors(['payment_verification']);

        // Order remains unpaid
        $order = Order::query()->findOrFail($orderData['id']);
        $this->assertNotSame('paid', $order->payment_status);

        // 2. Initiate a fresh attempt and test currency mismatch ('USD' instead of 'BDT')
        $initResp2 = $this->actingAs($this->customerA, 'sanctum')
            ->postJson('/api/payments/initiate', [
                'order_number'    => $orderNumber,
                'gateway'         => 'mock',
                'idempotency_key' => 'mismatch-test-init-2',
            ]);
        $merchantRef2 = $initResp2->json('data.transaction.merchant_reference');

        $currencyMismatchResp = $this->postJson('/api/payments/verify', [
            'gateway'            => 'mock',
            'merchant_reference' => $merchantRef2,
            'payload'            => [
                'simulated_amount_paisa' => (int) $orderData['total_amount'],
                'simulated_currency'     => 'USD',
            ],
        ]);
        $currencyMismatchResp->assertStatus(422)
            ->assertJsonValidationErrors(['payment_verification']);

        // 3. Initiate a fresh attempt and test merchant reference mismatch
        $initResp3 = $this->actingAs($this->customerA, 'sanctum')
            ->postJson('/api/payments/initiate', [
                'order_number'    => $orderNumber,
                'gateway'         => 'mock',
                'idempotency_key' => 'mismatch-test-init-3',
            ]);
        $merchantRef3 = $initResp3->json('data.transaction.merchant_reference');

        $refMismatchResp = $this->postJson('/api/payments/verify', [
            'gateway'            => 'mock',
            'merchant_reference' => $merchantRef3,
            'payload'            => [
                'simulated_amount_paisa'       => (int) $orderData['total_amount'],
                'simulated_merchant_reference' => 'TXN-TAMPERED-OTHER-ORDER',
            ],
        ]);
        $refMismatchResp->assertStatus(422)
            ->assertJsonValidationErrors(['payment_verification']);
    }

    // ─── 10. Granular admin authorization & order lookup privacy ─────────

    public function test_admin_order_actions_enforce_granular_permissions_and_customers_are_blocked(): void
    {
        $orderData = $this->placeSampleOrder($this->customerA, 'cod', 1);
        $orderId = $orderData['id'];

        $support = User::factory()->staff('customer_support')->create(['status' => 'active']);
        $reporter = User::factory()->staff('report_viewer')->create(['status' => 'active']);

        // Read-only staff roles can view but cannot change orders
        foreach ([$support, $reporter] as $viewer) {
            $this->actingAs($viewer, 'sanctum')->getJson("/api/admin/orders/{$orderId}")->assertStatus(200);
            $this->actingAs($viewer, 'sanctum')->postJson("/api/admin/orders/{$orderId}/ship")->assertStatus(403);
            $this->actingAs($viewer, 'sanctum')
                ->postJson("/api/admin/orders/{$orderId}/cancel", ['reason' => 'nope'])
                ->assertStatus(403);
        }

        // Stock must be untouched by the rejected requests
        $invItem = InventoryItem::query()->where('product_variant_id', $this->variant->id)->firstOrFail();
        $this->assertSame(20, (int) $invItem->quantity_on_hand);
        $this->assertSame(1, (int) $invItem->quantity_reserved);

        // Customers and anonymous callers cannot reach the admin API at all
        $this->actingAs($this->customerA, 'sanctum')->getJson('/api/admin/orders')->assertStatus(403);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/orders')->assertStatus(401);
    }

    public function test_order_lookup_by_number_does_not_leak_customer_data_to_other_callers(): void
    {
        $owned = $this->placeSampleOrder($this->customerA, 'cod', 1);

        // Another customer, and an anonymous caller, get 404 (existence is not revealed)
        $this->actingAs($this->customerB, 'sanctum')
            ->getJson("/api/checkout/orders/{$owned['order_number']}")
            ->assertStatus(404);
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/checkout/orders/{$owned['order_number']}")->assertStatus(404);

        // The owner can still read it
        $this->actingAs($this->customerA, 'sanctum')
            ->getJson("/api/checkout/orders/{$owned['order_number']}")
            ->assertStatus(200)
            ->assertJsonPath('data.order_number', $owned['order_number']);

        // Guest orders require the matching guest token (reset auth: actingAs() persists)
        $this->app['auth']->forgetGuards();
        $guestToken = 'guest-token-for-lookup-test';
        $guestResp = $this->withHeaders(['X-Guest-Token' => $guestToken])
            ->postJson('/api/checkout/orders', [
                'idempotency_key'  => 'guest-ord-' . uniqid(),
                'items'            => [['variant_id' => $this->variant->id, 'quantity' => 1]],
                'shipping_address' => $this->sampleBangladeshAddress(),
                'shipping_method'  => 'standard',
                'payment_method'   => 'cod',
            ]);
        $guestResp->assertStatus(201);
        $guestNumber = $guestResp->json('data.order_number');

        // withHeaders() persists for the rest of the test, so clear the token for the anonymous call
        $this->withoutHeader('X-Guest-Token');
        $this->getJson("/api/checkout/orders/{$guestNumber}")->assertStatus(404);
        $this->withHeaders(['X-Guest-Token' => 'wrong-token'])
            ->getJson("/api/checkout/orders/{$guestNumber}")
            ->assertStatus(404);
        $this->withHeaders(['X-Guest-Token' => $guestToken])
            ->getJson("/api/checkout/orders/{$guestNumber}")
            ->assertStatus(200)
            ->assertJsonPath('data.order_number', $guestNumber);
    }

    // ─── 9. Delayed Callback Arriving After Order Cancellation ───────────

    public function test_delayed_payment_callback_after_order_cancellation_records_payment_without_reopening_cancelled_order(): void
    {
        $orderData = $this->placeSampleOrder($this->customerA, 'mock', 2);
        $orderNumber = $orderData['order_number'];

        // 1. Customer initiates online payment
        $initResp = $this->actingAs($this->customerA, 'sanctum')
            ->postJson('/api/payments/initiate', [
                'order_number'    => $orderNumber,
                'gateway'         => 'mock',
                'idempotency_key' => 'delayed-cb-init-1',
            ]);
        $merchantRef = $initResp->json('data.transaction.merchant_reference');

        // 2. Customer cancels the order before callback arrives (releasing stock reservation)
        $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/orders/{$orderNumber}/cancel", [
                'reason' => 'Payment page timed out',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'CANCELLED');

        // 3. Delayed gateway callback arrives confirming the payment actually succeeded
        $delayedCb = $this->postJson('/api/payments/callback/mock', [
            'merchant_reference' => $merchantRef,
            'amount_paisa'       => (int) $orderData['total_amount'],
            'currency'           => 'BDT',
        ]);

        // Verify payment_status is recorded as 'paid' and flagged for refund, while order.status stays 'cancelled'!
        $delayedCb->assertStatus(200)
            ->assertJsonPath('data.transaction.status', 'paid')
            ->assertJsonPath('data.transaction.metadata.delayed_callback_on_cancelled_order', true)
            ->assertJsonPath('data.transaction.metadata.requires_manual_refund', true)
            ->assertJsonPath('data.order.status', 'CANCELLED')
            ->assertJsonPath('data.order.payment_status', 'paid');
    }
}

