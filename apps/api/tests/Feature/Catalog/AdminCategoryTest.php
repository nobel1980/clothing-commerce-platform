<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);

        $this->staff = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->staff->assignRole('super_admin');
    }

    public function test_admin_can_list_categories(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/admin/catalog/categories');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'meta']);
    }

    public function test_admin_can_create_category(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/admin/catalog/categories', [
                'name'      => 'Men\'s Wear',
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', "Men's Wear");
        $this->assertDatabaseHas('categories', ['slug' => 'mens-wear']);
    }

    public function test_category_slug_must_be_unique(): void
    {
        Category::factory()->create(['slug' => 'existing-slug']);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/admin/catalog/categories', [
                'name' => 'Anything',
                'slug' => 'existing-slug',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('slug');
    }

    public function test_admin_can_update_category(): void
    {
        $category = Category::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/admin/catalog/categories/{$category->id}", [
                'name' => 'New Name',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New Name');
    }

    public function test_admin_can_delete_category_and_orphan_children(): void
    {
        $parent = Category::factory()->create();
        $child  = Category::factory()->create(['parent_id' => $parent->id]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->deleteJson("/api/admin/catalog/categories/{$parent->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('categories', ['id' => $parent->id]);
        $this->assertDatabaseHas('categories', ['id' => $child->id, 'parent_id' => null]);
    }

    public function test_category_cannot_be_its_own_parent(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/admin/catalog/categories/{$category->id}", [
                'parent_id' => $category->id,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('parent_id');
    }

    public function test_tree_endpoint_returns_nested_structure(): void
    {
        $parent = Category::factory()->create(['is_active' => true]);
        Category::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/admin/catalog/categories/tree');

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'name', 'children']]]);
    }
}
