<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);
    }

    // ─── Public listing ────────────────────────────────────────────────

    public function test_public_only_sees_published_products(): void
    {
        Product::factory()->create(['status' => 'draft',     'name' => 'Draft Product']);
        Product::factory()->create(['status' => 'archived',  'name' => 'Archived Product']);
        Product::factory()->create([
            'status'       => 'published',
            'published_at' => now()->subDay(),
            'name'         => 'Visible Product',
        ]);

        $response = $this->getJson('/api/catalog/products');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Visible Product'));
        $this->assertFalse($names->contains('Draft Product'));
        $this->assertFalse($names->contains('Archived Product'));
    }

    public function test_future_published_at_products_are_not_visible(): void
    {
        Product::factory()->create([
            'status'       => 'published',
            'published_at' => now()->addDay(), // scheduled for the future
            'name'         => 'Scheduled Product',
        ]);

        $response = $this->getJson('/api/catalog/products');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertFalse($names->contains('Scheduled Product'));
    }

    public function test_public_can_filter_products_by_category(): void
    {
        $category = Category::factory()->create(['is_active' => true]);

        Product::factory()->create([
            'status'       => 'published',
            'published_at' => now()->subDay(),
            'category_id'  => $category->id,
            'name'         => 'Category Product',
        ]);
        Product::factory()->create([
            'status'       => 'published',
            'published_at' => now()->subDay(),
            'name'         => 'Other Product',
        ]);

        $response = $this->getJson("/api/catalog/products?category={$category->slug}");

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Category Product'));
        $this->assertFalse($names->contains('Other Product'));
    }

    public function test_public_can_search_products(): void
    {
        Product::factory()->create([
            'status'       => 'published',
            'published_at' => now()->subDay(),
            'name'         => 'Blue Denim Jeans',
        ]);
        Product::factory()->create([
            'status'       => 'published',
            'published_at' => now()->subDay(),
            'name'         => 'Red Polo Shirt',
        ]);

        $response = $this->getJson('/api/catalog/products?search=denim');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Blue Denim Jeans'));
        $this->assertFalse($names->contains('Red Polo Shirt'));
    }

    // ─── Public detail ─────────────────────────────────────────────────

    public function test_public_can_view_published_product_by_slug(): void
    {
        $product = Product::factory()->create([
            'status'       => 'published',
            'published_at' => now()->subDay(),
            'slug'         => 'blue-denim-jeans',
        ]);

        $response = $this->getJson('/api/catalog/products/blue-denim-jeans');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'blue-denim-jeans');
    }

    public function test_public_gets_404_for_draft_product(): void
    {
        Product::factory()->create(['status' => 'draft', 'slug' => 'secret-draft']);

        $response = $this->getJson('/api/catalog/products/secret-draft');

        $response->assertNotFound();
    }

    // ─── Pagination ────────────────────────────────────────────────────

    public function test_public_listing_is_paginated(): void
    {
        Product::factory()->count(30)->create([
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/catalog/products?per_page=10');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'links', 'meta']);
        $this->assertCount(10, $response->json('data'));
    }

    // ─── Public category listing ───────────────────────────────────────

    public function test_public_only_sees_active_categories(): void
    {
        Category::factory()->create(['name' => 'Active Cat', 'is_active' => true]);
        Category::factory()->create(['name' => 'Inactive Cat', 'is_active' => false]);

        $response = $this->getJson('/api/catalog/categories');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Active Cat'));
        $this->assertFalse($names->contains('Inactive Cat'));
    }

    public function test_public_gets_404_for_inactive_category(): void
    {
        $category = Category::factory()->create(['is_active' => false, 'slug' => 'hidden-cat']);

        $response = $this->getJson('/api/catalog/categories/hidden-cat');

        $response->assertNotFound();
    }
}
