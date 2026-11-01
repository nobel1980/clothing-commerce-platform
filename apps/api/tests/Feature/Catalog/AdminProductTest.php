<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed required roles/permissions via Spatie
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);

        $this->staff = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->staff->assignRole('super_admin');
    }

    // ─── Product CRUD ──────────────────────────────────────────────────

    public function test_admin_can_create_product(): void
    {
        $category = Category::factory()->create();
        $brand    = Brand::factory()->create();

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/admin/catalog/products', [
                'name'        => 'Test T-Shirt',
                'brand_id'    => $brand->id,
                'category_id' => $category->id,
                'description' => 'A great t-shirt',
                'status'      => 'draft',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Test T-Shirt');
        $response->assertJsonPath('data.status', 'draft');
        $this->assertDatabaseHas('products', ['slug' => 'test-t-shirt']);
    }

    public function test_admin_can_update_product(): void
    {
        $product = Product::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/admin/catalog/products/{$product->id}", [
                'name' => 'Updated T-Shirt',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Updated T-Shirt');
    }

    public function test_admin_can_publish_product(): void
    {
        $product = Product::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/admin/catalog/products/{$product->id}/publish");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'published');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'published']);
    }

    public function test_admin_can_unpublish_product(): void
    {
        $product = Product::factory()->create(['status' => 'published', 'published_at' => now()]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/admin/catalog/products/{$product->id}/unpublish");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'draft');
    }

    public function test_admin_can_delete_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->staff, 'sanctum')
            ->deleteJson("/api/admin/catalog/products/{$product->id}");

        $response->assertOk();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    // ─── Variants ──────────────────────────────────────────────────────

    public function test_admin_can_add_variant_to_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/admin/catalog/products/{$product->id}/variants", [
                'sku'          => 'SKU-RED-M-001',
                'color'        => 'Red',
                'size'         => 'M',
                'price_amount' => 150000, // BDT 1500.00 in paisa
                'currency'     => 'BDT',
                'status'       => 'active',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.sku', 'SKU-RED-M-001');
        $response->assertJsonPath('data.price.amount', 150000);
        $this->assertDatabaseHas('product_variants', ['sku' => 'SKU-RED-M-001', 'price_amount' => 150000]);
    }

    public function test_variant_sku_must_be_unique(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'DUPE-SKU']);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/admin/catalog/products/{$product->id}/variants", [
                'sku'          => 'DUPE-SKU',
                'price_amount' => 100000,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('sku');
    }

    public function test_variant_price_must_be_integer(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/admin/catalog/products/{$product->id}/variants", [
                'sku'          => 'FLOAT-PRICE',
                'price_amount' => 15.99, // must fail — must be integer
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('price_amount');
    }

    // ─── Images ────────────────────────────────────────────────────────

    public function test_admin_can_upload_product_image(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/admin/catalog/products/{$product->id}/images", [
                'image'    => UploadedFile::fake()->image('shirt.jpg', 800, 800),
                'alt_text' => 'Front view',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['data' => ['id', 'url', 'alt_text', 'is_primary']]);

        // Uploaded via storage abstraction — not a hardcoded path
        $this->assertDatabaseHas('product_images', ['product_id' => $product->id]);
        $image = ProductImage::where('product_id', $product->id)->first();
        $this->assertNotEmpty($image->disk);
        $this->assertNotEmpty($image->path);
    }

    public function test_admin_can_reorder_images(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $img1 = ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 0]);
        $img2 = ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 1]);
        $img3 = ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 2]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/admin/catalog/products/{$product->id}/images/reorder", [
                'order' => [$img3->id, $img1->id, $img2->id],
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('product_images', ['id' => $img3->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('product_images', ['id' => $img1->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('product_images', ['id' => $img2->id, 'sort_order' => 2]);
    }

    // ─── Auth Guard ────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_access_admin_catalog(): void
    {
        $response = $this->getJson('/api/admin/catalog/products');
        $response->assertUnauthorized();
    }
}
