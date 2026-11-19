<?php

namespace Tests\Feature\Admin;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Payments\Models\PaymentTransaction;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $supportStaff;
    protected User $customer;
    protected Product $product;
    protected ProductVariant $variant;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::where('email', 'admin@clothing-commerce.test')->firstOrFail();

        $this->supportStaff = User::factory()->staff('customer_support')->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->customer = User::factory()->customer()->create([
            'name' => 'Nadia Rahman',
            'email' => 'nadia@example.com',
            'phone' => '+8801711223344',
            'status' => 'active',
        ]);

        $brand = Brand::factory()->create(['name' => 'Aarong Heritage', 'slug' => 'aarong-heritage']);
        $category = Category::factory()->create(['name' => 'Panjabi', 'slug' => 'panjabi', 'is_active' => true]);

        $this->product = Product::factory()->create([
            'brand_id'     => $brand->id,
            'category_id'  => $category->id,
            'name'         => 'Embroidered Cotton Panjabi',
            'slug'         => 'embroidered-cotton-panjabi',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->variant = ProductVariant::factory()->create([
            'product_id'   => $this->product->id,
            'sku'          => 'PNJ-BLK-L',
            'color'        => 'Black',
            'size'         => 'L',
            'price_amount' => 320000, // BDT 3,200.00
            'currency'     => 'BDT',
            'status'       => 'active',
        ]);

        $this->warehouse = Warehouse::factory()->create([
            'name'       => 'Dhaka Central Hub',
            'code'       => 'DHK-CENTRAL',
            'is_active'  => true,
            'is_default' => true,
        ]);

        InventoryItem::factory()->create([
            'product_variant_id'  => $this->variant->id,
            'warehouse_id'        => $this->warehouse->id,
            'quantity_on_hand'    => 3, // Low stock! (<= 5)
            'quantity_reserved'   => 0,
            'low_stock_threshold' => 5,
        ]);
    }

    private function seedSampleOrders(): array
    {
        // Order 1: Delivered & Paid (BDT 6,400 = 640000 paisa, qty 2)
        $order1 = Order::create([
            'order_number'              => 'ORD-20261115-AAA111',
            'idempotency_key'           => (string) Str::uuid(),
            'user_id'                   => $this->customer->id,
            'status'                    => 'delivered',
            'payment_method'            => 'bkash',
            'payment_status'            => 'paid',
            'fulfilment_status'         => 'delivered',
            'currency'                  => 'BDT',
            'subtotal_amount'           => 640000,
            'discount_amount'           => 0,
            'shipping_amount'           => 0,
            'tax_amount'                => 0,
            'total_amount'              => 640000,
            'shipping_method'           => 'inside_dhaka',
            'shipping_address_snapshot' => [
                'recipient_name' => 'Nadia Rahman',
                'phone'          => '+8801711223344',
                'division'       => 'Dhaka',
                'district'       => 'Dhaka',
                'upazila'        => 'Gulshan',
                'address_line_1' => 'House 12, Road 5',
            ],
            'placed_at'                 => now(),
            'delivered_at'              => now(),
        ]);

        OrderItem::create([
            'order_id'              => $order1->id,
            'product_id'            => $this->product->id,
            'product_variant_id'    => $this->variant->id,
            'sku_snapshot'          => $this->variant->sku,
            'product_name_snapshot' => $this->product->name,
            'color_snapshot'        => 'Black',
            'size_snapshot'         => 'L',
            'quantity'              => 2,
            'unit_price_amount'     => 320000,
            'discount_amount'       => 0,
            'tax_amount'            => 0,
            'line_total_amount'     => 640000,
            'currency'              => 'BDT',
        ]);

        $tx1 = PaymentTransaction::create([
            'order_id'               => $order1->id,
            'idempotency_key'        => 'tx-key-1',
            'merchant_reference'     => 'MREF-AAA111',
            'gateway'                => 'bkash',
            'gateway_payment_id'     => 'BK-PAY-111',
            'gateway_transaction_id' => 'BK-TRX-111',
            'amount'                 => 640000,
            'refunded_amount'        => 0,
            'currency'               => 'BDT',
            'status'                 => 'paid',
            'paid_at'                => now(),
        ]);

        // Order 2: Pending COD Order (BDT 3,270 = 327000 paisa, qty 1)
        $order2 = Order::create([
            'order_number'              => 'ORD-20261115-BBB222',
            'idempotency_key'           => (string) Str::uuid(),
            'user_id'                   => $this->customer->id,
            'status'                    => 'pending',
            'payment_method'            => 'cod',
            'payment_status'            => 'cod_pending',
            'fulfilment_status'         => 'unfulfilled',
            'currency'                  => 'BDT',
            'subtotal_amount'           => 320000,
            'discount_amount'           => 0,
            'shipping_amount'           => 7000,
            'tax_amount'                => 0,
            'total_amount'              => 327000,
            'shipping_method'           => 'inside_dhaka',
            'shipping_address_snapshot' => [
                'recipient_name' => 'Nadia Rahman',
                'phone'          => '+8801711223344',
                'division'       => 'Dhaka',
                'district'       => 'Dhaka',
                'upazila'        => 'Gulshan',
                'address_line_1' => 'House 12, Road 5',
            ],
            'placed_at'                 => now(),
        ]);

        OrderItem::create([
            'order_id'              => $order2->id,
            'product_id'            => $this->product->id,
            'product_variant_id'    => $this->variant->id,
            'sku_snapshot'          => $this->variant->sku,
            'product_name_snapshot' => $this->product->name,
            'color_snapshot'        => 'Black',
            'size_snapshot'         => 'L',
            'quantity'              => 1,
            'unit_price_amount'     => 320000,
            'discount_amount'       => 0,
            'tax_amount'            => 0,
            'line_total_amount'     => 320000,
            'currency'              => 'BDT',
        ]);

        return [$order1, $order2, $tx1];
    }

    public function test_dashboard_metrics_are_calculated_dynamically_from_database(): void
    {
        $this->seedSampleOrders();

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/admin/dashboard/metrics');

        $response->assertOk()
            ->assertJsonPath('data.total_sales', 967000)         // 640000 + 327000 paisa
            ->assertJsonPath('data.total_sales_count', 2)
            ->assertJsonPath('data.orders_today', 2)
            ->assertJsonPath('data.pending_orders', 1)
            ->assertJsonPath('data.delivered_orders', 1)
            ->assertJsonPath('data.revenue', 640000)             // Paid/delivered order
            ->assertJsonPath('data.average_order_value', 483500) // 967000 / 2
            ->assertJsonPath('data.low_stock_count', 1)
            ->assertJsonPath('data.low_stock_products.0.sku', 'PNJ-BLK-L')
            ->assertJsonPath('data.best_selling_products.0.product_id', $this->product->id)
            ->assertJsonPath('data.best_selling_products.0.total_quantity_sold', 3)
            ->assertJsonPath('data.best_selling_products.0.total_revenue', 960000);
    }

    public function test_every_admin_endpoint_enforces_granular_permissions(): void
    {
        // Customer cannot access any admin endpoint
        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/admin/dashboard/metrics')
            ->assertStatus(403);

        // Customer support has orders.view, customers.view, reviews.view ONLY.
        // They MUST be forbidden (403) from catalog, promotions, payments, shipping, reports, settings, audit logs:
        $this->actingAs($this->supportStaff, 'sanctum')
            ->getJson('/api/admin/catalog/products')
            ->assertStatus(403);

        $this->actingAs($this->supportStaff, 'sanctum')
            ->getJson('/api/admin/catalog/variants')
            ->assertStatus(403);

        $this->actingAs($this->supportStaff, 'sanctum')
            ->getJson('/api/admin/promotions/coupons')
            ->assertStatus(403);

        $this->actingAs($this->supportStaff, 'sanctum')
            ->getJson('/api/admin/payments')
            ->assertStatus(403);

        $this->actingAs($this->supportStaff, 'sanctum')
            ->getJson('/api/admin/reports/overview')
            ->assertStatus(403);

        $this->actingAs($this->supportStaff, 'sanctum')
            ->getJson('/api/admin/settings')
            ->assertStatus(403);

        $this->actingAs($this->supportStaff, 'sanctum')
            ->getJson('/api/admin/audit-logs')
            ->assertStatus(403);

        // Support staff CAN view customers and reviews, but CANNOT manage/mutate them:
        $this->actingAs($this->supportStaff, 'sanctum')
            ->getJson('/api/admin/customers')
            ->assertOk();

        $this->actingAs($this->supportStaff, 'sanctum')
            ->postJson("/api/admin/customers/{$this->customer->id}/status", ['status' => 'suspended'])
            ->assertStatus(403);
    }

    public function test_admin_variants_listing_and_filtering(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/admin/catalog/variants?search=PNJ-BLK-L&status=active');

        $response->assertOk()
            ->assertJsonPath('data.0.sku', 'PNJ-BLK-L')
            ->assertJsonPath('data.0.product.name', 'Embroidered Cotton Panjabi');
    }

    public function test_admin_customers_management_and_audit_trail(): void
    {
        $this->seedSampleOrders();

        $listResp = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/admin/customers?search=Nadia');

        $listResp->assertOk()
            ->assertJsonPath('data.0.email', 'nadia@example.com')
            ->assertJsonPath('data.0.orders_count', 2)
            ->assertJsonPath('data.0.total_spent', 967000);

        $detailResp = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/admin/customers/{$this->customer->id}");

        $detailResp->assertOk()
            ->assertJsonCount(2, 'data.recent_orders');

        $statusResp = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/admin/customers/{$this->customer->id}/status", [
                'status' => 'suspended',
            ]);

        $statusResp->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'customer.status_suspended',
        ]);
    }

    public function test_admin_promotions_coupon_crud_and_audit_logging(): void
    {
        // Create
        $createResp = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/admin/promotions/coupons', [
                'code'             => 'winter26',
                'description'      => 'Winter 2026 Collection Discount',
                'type'             => 'fixed_amount',
                'value'            => 50000, // BDT 500
                'min_order_amount' => 200000,
                'usage_limit'      => 100,
                'is_active'        => true,
            ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('data.code', 'WINTER26')
            ->assertJsonPath('data.value', 50000);

        $couponId = $createResp->json('data.id');

        // Toggle
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/admin/promotions/coupons/{$couponId}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        // Update
        $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/admin/promotions/coupons/{$couponId}", [
                'value' => 60000,
            ])
            ->assertOk()
            ->assertJsonPath('data.value', 60000);

        // Delete
        $this->actingAs($this->superAdmin, 'sanctum')
            ->deleteJson("/api/admin/promotions/coupons/{$couponId}")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'coupon.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'coupon.deleted']);
    }

    public function test_product_reviews_submission_and_admin_moderation(): void
    {
        $this->seedSampleOrders();

        // Customer submits review
        $submitResp = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/catalog/products/{$this->product->slug}/reviews", [
                'reviewer_name' => 'Nadia Rahman',
                'rating'        => 5,
                'title'         => 'Exquisite stitching',
                'comment'       => 'Fabric quality and embroidery are outstanding.',
            ]);

        $submitResp->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.is_verified_purchase', true);

        $reviewId = $submitResp->json('data.id');

        // Pending review is not publicly listed yet
        $this->getJson("/api/catalog/products/{$this->product->slug}/reviews")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Admin moderates & approves review
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/admin/reviews/{$reviewId}/moderate", [
                'status'           => 'approved',
                'moderation_notes' => 'Verified buyer review approved.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        // Now publicly visible
        $this->getJson("/api/catalog/products/{$this->product->slug}/reviews")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertDatabaseHas('audit_logs', ['action' => 'review.approved']);
    }

    public function test_admin_payments_and_reports_and_settings_and_csv_export(): void
    {
        [, , $tx1] = $this->seedSampleOrders();

        // Payments index
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/admin/payments?gateway=bkash')
            ->assertOk()
            ->assertJsonPath('data.0.id', $tx1->id)
            ->assertJsonPath('summary.total_paid_amount', 640000);

        // Reports overview
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/admin/reports/overview')
            ->assertOk()
            ->assertJsonPath('data.metrics.total_sales', 967000);

        // Platform settings
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/admin/settings')
            ->assertOk();

        $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson('/api/admin/settings', [
                'settings' => [
                    [
                        'key'   => 'store.name',
                        'value' => 'Atelier Dhaka Flagship',
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);

        // CSV Export
        $exportResp = $this->actingAs($this->superAdmin, 'sanctum')
            ->get('/api/admin/export/orders');

        $exportResp->assertStatus(200);
        $this->assertStringContainsString('ORD-20261115-AAA111', $exportResp->getContent());
        $this->assertDatabaseHas('audit_logs', ['action' => 'export.orders']);
    }
}

