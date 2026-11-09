<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontQuoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);
    }

    public function test_checkout_quote_calculates_authoritative_totals_in_integer_subunits(): void
    {
        $product = Product::factory()->create([
            'name'         => 'Heavyweight Organic Hoodie',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id'   => $product->id,
            'sku'          => 'HD-BLK-L',
            'color'        => 'Black',
            'size'         => 'L',
            'price_amount' => 300000, // 3000.00 BDT in paisa
            'currency'     => 'BDT',
            'status'       => 'active',
        ]);

        // Request quote for 2 units (6000.00 BDT = 600000 paisa) with WELCOME10 promo code (10% off)
        $response = $this->postJson('/api/checkout/quote', [
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity'   => 2,
                ],
            ],
            'promo_code'      => 'WELCOME10',
            'shipping_method' => 'standard',
        ]);

        // Subtotal = 600000 paisa
        // Discount (10%) = 60000 paisa -> After discount = 540000 paisa (>= 500000 free shipping threshold)
        // Shipping = 0 paisa
        // Tax (5% of 540000) = 27000 paisa
        // Total = 540000 + 0 + 27000 = 567000 paisa
        $response->assertOk()
            ->assertJsonPath('data.subtotal.amount', 600000)
            ->assertJsonPath('data.discount.amount', 60000)
            ->assertJsonPath('data.shipping.amount', 0)
            ->assertJsonPath('data.tax.amount', 27000)
            ->assertJsonPath('data.total.amount', 567000)
            ->assertJsonPath('data.promoCode', 'WELCOME10');
    }

    public function test_public_product_resource_exposes_active_variants_for_storefront(): void
    {
        $product = Product::factory()->create([
            'name'         => 'Structured Overshirt',
            'slug'         => 'structured-overshirt',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);

        ProductVariant::factory()->create([
            'product_id'   => $product->id,
            'sku'          => 'OVR-NVY-M',
            'color'        => 'Navy',
            'size'         => 'M',
            'price_amount' => 420000,
            'status'       => 'active',
        ]);

        $response = $this->getJson('/api/catalog/products/structured-overshirt');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'structured-overshirt')
            ->assertJsonPath('data.variants.0.sku', 'OVR-NVY-M')
            ->assertJsonPath('data.variants.0.price.amount', 420000);
    }

    public function test_checkout_order_placement_returns_authoritative_order_payload(): void
    {
        $product = Product::factory()->create([
            'name'         => 'Pleated Wool Trousers',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id'   => $product->id,
            'sku'          => 'TRS-BLK-M',
            'color'        => 'Black',
            'size'         => 'M',
            'price_amount' => 450000,
            'currency'     => 'BDT',
            'status'       => 'active',
        ]);

        $response = $this->postJson('/api/checkout/orders', [
            'idempotency_key' => 'test-order-key-001',
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity'   => 1,
                ],
            ],
            'shipping_method'  => 'standard',
            'payment_method'   => 'cod',
            'shipping_address' => [
                'firstName'     => 'Nadia',
                'lastName'      => 'Rahman',
                'email'         => 'nadia@example.com',
                'phone'         => '+8801700000000',
                'division'      => 'Dhaka',
                'district'      => 'Dhaka',
                'upazila_thana' => 'Banani',
                'street'        => 'Road 11, Banani',
                'postalCode'    => '1213',
                'country'       => 'Bangladesh',
            ],
        ]);

        // Inside Dhaka District standard shipping = 7000 paisa (70.00 BDT)
        // Subtotal = 450000, Shipping = 7000, Tax (5%) = 22500 -> Total = 479500
        $response->assertCreated()
            ->assertJsonPath('data.status', 'CONFIRMED')
            ->assertJsonPath('data.subtotal.amount', 450000)
            ->assertJsonPath('data.shippingCost.amount', 7000)
            ->assertJsonPath('data.tax.amount', 22500)
            ->assertJsonPath('data.total.amount', 479500);
    }
}
