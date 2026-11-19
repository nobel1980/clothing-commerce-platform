<?php

namespace App\Modules\Catalog\Controllers\Admin;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Requests\BulkImportProductsRequest;
use App\Modules\Catalog\Requests\ReorderImagesRequest;
use App\Modules\Catalog\Requests\StoreProductImageRequest;
use App\Modules\Catalog\Requests\StoreProductRequest;
use App\Modules\Catalog\Requests\StoreVariantRequest;
use App\Modules\Catalog\Requests\UpdateProductRequest;
use App\Modules\Catalog\Requests\UpdateVariantRequest;
use App\Modules\Catalog\Resources\BrandResource;
use App\Modules\Catalog\Resources\ProductImageResource;
use App\Modules\Catalog\Resources\ProductResource;
use App\Modules\Catalog\Resources\ProductVariantResource;
use App\Modules\Catalog\Services\ImageService;
use App\Modules\Catalog\Services\ImportService;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Catalog\Services\VariantService;
use App\Modules\Identity\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class AdminProductController extends Controller
{
    public function __construct(
        protected ProductService $productService,
        protected VariantService $variantService,
        protected ImageService   $imageService,
        protected ImportService  $importService,
        protected AuditService   $auditService,
    ) {}

    private function authorizePermission(Request $request, string $permission): void
    {
        $user = $request->user('sanctum') ?? $request->user();

        abort_unless(
            $user !== null && ($user->can($permission) || $user->isSuperAdmin()),
            403,
            'You do not have permission to perform this catalog action.'
        );
    }

    // ─── Products ──────────────────────────────────────────────────────

    /**
     * GET /admin/catalog/products
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizePermission($request, 'catalog.view');

        $products = $this->productService->adminList(
            filters: $request->only(['search', 'status', 'category', 'brand', 'featured']),
            perPage: min(max((int) $request->get('per_page', 20), 1), 100)
        );

        return ProductResource::collection($products);
    }

    /**
     * POST /admin/catalog/products
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorizePermission($request, 'catalog.manage');

        $product = $this->productService->create($request->validated());
        $this->auditService->log('product.created', $request->user(), $product, null, [
            'name' => $product->name,
            'slug' => $product->slug,
            'status' => $product->status,
        ]);

        return (new ProductResource($product->load(['brand', 'category', 'images', 'variants'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /admin/catalog/products/{product}
     */
    public function show(Request $request, Product $product): ProductResource
    {
        $this->authorizePermission($request, 'catalog.view');

        return new ProductResource(
            $product->load(['brand', 'category', 'images', 'variants.attributeValues.attribute'])
        );
    }

    /**
     * PUT /admin/catalog/products/{product}
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $this->authorizePermission($request, 'catalog.manage');

        $oldValues = $product->only(['name', 'slug', 'status', 'category_id', 'brand_id', 'is_featured']);
        $updated = $this->productService->update($product, $request->validated());
        $this->auditService->log('product.updated', $request->user(), $updated, $oldValues, $request->validated());

        return new ProductResource($updated);
    }

    /**
     * DELETE /admin/catalog/products/{product}
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorizePermission($request, 'catalog.manage');

        $oldValues = $product->only(['id', 'name', 'slug', 'status']);
        $this->productService->delete($product);
        $this->auditService->log('product.deleted', $request->user(), null, $oldValues, null);

        return response()->json(['message' => 'Product deleted successfully.']);
    }

    /**
     * POST /admin/catalog/products/{product}/publish
     */
    public function publish(Request $request, Product $product): ProductResource
    {
        $this->authorizePermission($request, 'catalog.manage');

        $published = $this->productService->publish($product);
        $this->auditService->log('product.published', $request->user(), $published, ['status' => 'draft'], ['status' => 'published']);

        return new ProductResource($published);
    }

    /**
     * POST /admin/catalog/products/{product}/unpublish
     */
    public function unpublish(Request $request, Product $product): ProductResource
    {
        $this->authorizePermission($request, 'catalog.manage');

        $unpublished = $this->productService->unpublish($product);
        $this->auditService->log('product.unpublished', $request->user(), $unpublished, ['status' => 'published'], ['status' => 'draft']);

        return new ProductResource($unpublished);
    }

    // ─── Variants (Global & Product-Scoped) ────────────────────────────

    /**
     * GET /admin/catalog/variants
     */
    public function listVariants(Request $request): AnonymousResourceCollection
    {
        $this->authorizePermission($request, 'catalog.view');

        $query = ProductVariant::query()
            ->with(['product', 'attributeValues.attribute'])
            ->orderByDesc('updated_at');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                  ->orWhere('color', 'like', "%{$search}%")
                  ->orWhere('size', 'like', "%{$search}%")
                  ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->input('product_id'));
        }

        if ($request->filled('color')) {
            $query->where('color', (string) $request->input('color'));
        }

        if ($request->filled('size')) {
            $query->where('size', (string) $request->input('size'));
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        return ProductVariantResource::collection($query->paginate($perPage));
    }

    /**
     * GET /admin/catalog/brands
     */
    public function listBrands(Request $request): AnonymousResourceCollection
    {
        $this->authorizePermission($request, 'catalog.view');

        $brands = Brand::query()->orderBy('name')->get();

        return BrandResource::collection($brands);
    }

    /**
     * POST /admin/catalog/products/{product}/variants
     */
    public function storeVariant(StoreVariantRequest $request, Product $product): ProductVariantResource
    {
        $this->authorizePermission($request, 'catalog.manage');

        $variant = $this->variantService->create($product, $request->validated());
        $this->auditService->log('variant.created', $request->user(), $variant, null, [
            'product_id' => $product->id,
            'sku' => $variant->sku,
            'price_amount' => $variant->price_amount,
        ]);

        return new ProductVariantResource($variant->load('product'));
    }

    /**
     * PUT /admin/catalog/products/{product}/variants/{variant}
     */
    public function updateVariant(
        UpdateVariantRequest $request,
        Product $product,
        ProductVariant $variant
    ): ProductVariantResource {
        $this->authorizePermission($request, 'catalog.manage');
        $this->authorizeVariantBelongsToProduct($product, $variant);

        $oldValues = $variant->only(['sku', 'color', 'size', 'price_amount', 'status']);
        $updated = $this->variantService->update($variant, $request->validated());
        $this->auditService->log('variant.updated', $request->user(), $updated, $oldValues, $request->validated());

        return new ProductVariantResource($updated->load('product'));
    }

    /**
     * DELETE /admin/catalog/products/{product}/variants/{variant}
     */
    public function destroyVariant(
        Request $request,
        Product $product,
        ProductVariant $variant
    ): JsonResponse {
        $this->authorizePermission($request, 'catalog.manage');
        $this->authorizeVariantBelongsToProduct($product, $variant);

        $oldValues = $variant->only(['id', 'product_id', 'sku', 'price_amount']);
        $this->variantService->delete($variant);
        $this->auditService->log('variant.deleted', $request->user(), null, $oldValues, null);

        return response()->json(['message' => 'Variant deleted successfully.']);
    }

    // ─── Images ────────────────────────────────────────────────────────

    /**
     * POST /admin/catalog/products/{product}/images
     */
    public function uploadImage(StoreProductImageRequest $request, Product $product): ProductImageResource
    {
        $this->authorizePermission($request, 'catalog.manage');

        $image = $this->imageService->upload(
            $product,
            $request->file('image'),
            $request->only(['alt_text', 'sort_order', 'is_primary'])
        );

        return new ProductImageResource($image);
    }

    /**
     * POST /admin/catalog/products/{product}/images/reorder
     */
    public function reorderImages(ReorderImagesRequest $request, Product $product): JsonResponse
    {
        $this->authorizePermission($request, 'catalog.manage');

        $this->imageService->reorder($product, $request->validated('order'));
        return response()->json(['message' => 'Images reordered successfully.']);
    }

    /**
     * DELETE /admin/catalog/products/{product}/images/{image}
     */
    public function destroyImage(Request $request, Product $product, ProductImage $image): JsonResponse
    {
        $this->authorizePermission($request, 'catalog.manage');

        abort_unless($image->product_id === $product->id, 404);
        $this->imageService->delete($image);
        return response()->json(['message' => 'Image deleted successfully.']);
    }

    // ─── Bulk Import ───────────────────────────────────────────────────

    /**
     * POST /admin/catalog/products/import
     */
    public function import(BulkImportProductsRequest $request): JsonResponse
    {
        $this->authorizePermission($request, 'catalog.manage');

        $rows = [];

        if ($request->hasFile('file')) {
            $rows = $this->importService->parseCsv($request->file('file'));
        } elseif ($request->has('rows')) {
            $rows = $request->validated('rows');
        }

        $result = $this->importService->importRows($rows);
        $this->auditService->log('product.bulk_imported', $request->user(), null, null, $result);

        return response()->json([
            'message' => "Import complete: {$result['created']} created, {$result['skipped']} skipped.",
            'summary' => $result,
        ]);
    }

    // ─── Private ───────────────────────────────────────────────────────

    private function authorizeVariantBelongsToProduct(
        Product $product,
        ProductVariant $variant
    ): void {
        abort_unless($variant->product_id === $product->id, 404);
    }
}
