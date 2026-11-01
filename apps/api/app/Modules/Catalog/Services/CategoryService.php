<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Category;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryService
{
    /**
     * List all categories, optionally filtered and paginated.
     */
    public function list(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = Category::with('parent')
            ->withCount('products');

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where('name', 'like', $search);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (!empty($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
        }

        return $query->orderBy('sort_order')->orderBy('name')->paginate($perPage);
    }

    /**
     * Return the full category tree (root categories with nested children).
     */
    public function tree(): \Illuminate\Database\Eloquent\Collection
    {
        return Category::with('children.children')
            ->active()
            ->roots()
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Create a new category inside a DB transaction.
     */
    public function create(array $data): Category
    {
        return DB::transaction(function () use ($data) {
            return Category::create([
                'parent_id'        => $data['parent_id'] ?? null,
                'name'             => $data['name'],
                'slug'             => $data['slug'] ?? Str::slug($data['name']),
                'description'      => $data['description'] ?? null,
                'image_url'        => $data['image_url'] ?? null,
                'sort_order'       => $data['sort_order'] ?? 0,
                'is_active'        => $data['is_active'] ?? true,
                'meta_title'       => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
            ]);
        });
    }

    /**
     * Update an existing category.
     */
    public function update(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            $category->update(array_filter([
                'parent_id'        => array_key_exists('parent_id', $data) ? $data['parent_id'] : $category->parent_id,
                'name'             => $data['name'] ?? $category->name,
                'slug'             => $data['slug'] ?? $category->slug,
                'description'      => array_key_exists('description', $data) ? $data['description'] : $category->description,
                'image_url'        => array_key_exists('image_url', $data) ? $data['image_url'] : $category->image_url,
                'sort_order'       => $data['sort_order'] ?? $category->sort_order,
                'is_active'        => $data['is_active'] ?? $category->is_active,
                'meta_title'       => array_key_exists('meta_title', $data) ? $data['meta_title'] : $category->meta_title,
                'meta_description' => array_key_exists('meta_description', $data) ? $data['meta_description'] : $category->meta_description,
            ], fn($v) => $v !== null) + [
                // Always propagate explicit nulls for nullable fields
                'parent_id'        => array_key_exists('parent_id', $data) ? $data['parent_id'] : $category->parent_id,
                'description'      => array_key_exists('description', $data) ? $data['description'] : $category->description,
                'image_url'        => array_key_exists('image_url', $data) ? $data['image_url'] : $category->image_url,
                'meta_title'       => array_key_exists('meta_title', $data) ? $data['meta_title'] : $category->meta_title,
                'meta_description' => array_key_exists('meta_description', $data) ? $data['meta_description'] : $category->meta_description,
            ]);

            return $category->fresh('parent');
        });
    }

    /**
     * Delete a category. Orphan its children by setting their parent_id to null.
     */
    public function delete(Category $category): void
    {
        DB::transaction(function () use ($category) {
            // Orphan children rather than cascade deleting the whole sub-tree
            $category->children()->update(['parent_id' => null]);
            $category->delete();
        });
    }
}
