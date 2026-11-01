<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * Admin list — all statuses, full filter set.
     */
    public function adminList(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Product::with(['brand', 'category', 'images'])
            ->withCount('variants');

        $this->applyCommonFilters($query, $filters);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $this->applySorting($query, $filters['sort'] ?? 'newest')
            ->paginate($perPage);
    }

    /**
     * Public storefront list — only published products.
     * Never exposes draft or archived products.
     */
    public function publicList(array $filters = [], int $perPage = 24): LengthAwarePaginator
    {
        $query = Product::with(['brand', 'category', 'images', 'activeVariants'])
            ->published(); // enforces published + published_at <= now()

        $this->applyCommonFilters($query, $filters);

        // Price filtering via variants (min/max are integer sub-units)
        if (!empty($filters['min_price'])) {
            $query->whereHas('activeVariants', fn($q) =>
                $q->where('price_amount', '>=', (int) $filters['min_price'])
            );
        }

        if (!empty($filters['max_price'])) {
            $query->whereHas('activeVariants', fn($q) =>
                $q->where('price_amount', '<=', (int) $filters['max_price'])
            );
        }

        // Color / size filtering via variants
        if (!empty($filters['color'])) {
            $query->whereHas('activeVariants', fn($q) =>
                $q->where('color', $filters['color'])
            );
        }

        if (!empty($filters['size'])) {
            $query->whereHas('activeVariants', fn($q) =>
                $q->where('size', $filters['size'])
            );
        }

        return $this->applySorting($query, $filters['sort'] ?? 'newest')
            ->paginate($perPage);
    }

    /**
     * Create a new product in a DB transaction.
     */
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            return Product::create([
                'brand_id'         => $data['brand_id'] ?? null,
                'category_id'      => $data['category_id'] ?? null,
                'name'             => $data['name'],
                'slug'             => $data['slug'] ?? Str::slug($data['name']),
                'description'      => $data['description'] ?? null,
                'body'             => $data['body'] ?? null,
                'status'           => $data['status'] ?? 'draft',
                'is_featured'      => $data['is_featured'] ?? false,
                'published_at'     => $data['published_at'] ?? null,
                'meta_title'       => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'meta_keywords'    => $data['meta_keywords'] ?? null,
            ]);
        });
    }

    /**
     * Update an existing product.
     */
    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $product->update([
                'brand_id'         => array_key_exists('brand_id', $data) ? $data['brand_id'] : $product->brand_id,
                'category_id'      => array_key_exists('category_id', $data) ? $data['category_id'] : $product->category_id,
                'name'             => $data['name'] ?? $product->name,
                'slug'             => $data['slug'] ?? $product->slug,
                'description'      => array_key_exists('description', $data) ? $data['description'] : $product->description,
                'body'             => array_key_exists('body', $data) ? $data['body'] : $product->body,
                'status'           => $data['status'] ?? $product->status,
                'is_featured'      => $data['is_featured'] ?? $product->is_featured,
                'published_at'     => array_key_exists('published_at', $data) ? $data['published_at'] : $product->published_at,
                'meta_title'       => array_key_exists('meta_title', $data) ? $data['meta_title'] : $product->meta_title,
                'meta_description' => array_key_exists('meta_description', $data) ? $data['meta_description'] : $product->meta_description,
                'meta_keywords'    => array_key_exists('meta_keywords', $data) ? $data['meta_keywords'] : $product->meta_keywords,
            ]);

            return $product->fresh(['brand', 'category', 'images', 'variants']);
        });
    }

    /**
     * Publish a product (set status to published + stamp published_at if not set).
     */
    public function publish(Product $product): Product
    {
        $product->update([
            'status'       => 'published',
            'published_at' => $product->published_at ?? now(),
        ]);

        return $product->fresh();
    }

    /**
     * Unpublish a product back to draft.
     */
    public function unpublish(Product $product): Product
    {
        $product->update(['status' => 'draft']);
        return $product->fresh();
    }

    /**
     * Soft-delete a product (and cascade-delete its variants via model events).
     */
    public function delete(Product $product): void
    {
        DB::transaction(fn() => $product->delete());
    }

    // ─── Private helpers ───────────────────────────────────────────────

    private function applyCommonFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('description', 'like', $search)
                  ->orWhereHas('variants', fn($v) => $v->where('sku', 'like', $search));
            });
        }

        if (!empty($filters['category'])) {
            $query->whereHas('category', fn($q) => $q->where('slug', $filters['category']));
        }

        if (!empty($filters['brand'])) {
            $query->whereHas('brand', fn($q) => $q->where('slug', $filters['brand']));
        }

        if (!empty($filters['featured'])) {
            $query->where('is_featured', true);
        }
    }

    private function applySorting(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'oldest'     => $query->orderBy('id'),
            'price_asc'  => $query->orderBy(
                \App\Modules\Catalog\Models\ProductVariant::select('price_amount')
                    ->whereColumn('product_id', 'products.id')
                    ->orderBy('price_amount')
                    ->limit(1)
            ),
            'price_desc' => $query->orderByDesc(
                \App\Modules\Catalog\Models\ProductVariant::select('price_amount')
                    ->whereColumn('product_id', 'products.id')
                    ->orderByDesc('price_amount')
                    ->limit(1)
            ),
            'name_asc'   => $query->orderBy('name'),
            default      => $query->orderByDesc('id'), // newest
        };
    }
}
