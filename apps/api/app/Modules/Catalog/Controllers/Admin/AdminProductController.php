<?php

namespace App\Modules\Catalog\Controllers\Admin;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Requests\BulkImportProductsRequest;
use App\Modules\Catalog\Requests\ReorderImagesRequest;
use App\Modules\Catalog\Requests\StoreProductImageRequest;
use App\Modules\Catalog\Requests\StoreProductRequest;
use App\Modules\Catalog\Requests\StoreVariantRequest;
use App\Modules\Catalog\Requests\UpdateProductRequest;
use App\Modules\Catalog\Requests\UpdateVariantRequest;
use App\Modules\Catalog\Resources\ProductImageResource;
use App\Modules\Catalog\Resources\ProductResource;
use App\Modules\Catalog\Resources\ProductVariantResource;
use App\Modules\Catalog\Services\ImageService;
use App\Modules\Catalog\Services\ImportService;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Catalog\Services\VariantService;
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
    ) {}

    // ─── Products ──────────────────────────────────────────────────────

    /**
     * GET /admin/catalog/products
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = $this->productService->adminList(
            filters: $request->only(['search', 'status', 'category', 'brand', 'featured']),
            perPage: (int) $request->get('per_page', 20)
        );

        return ProductResource::collection($products);
    }

    /**
     * POST /admin/catalog/products
     */
    public function store(StoreProductRequest $request): ProductResource
    {
        $product = $this->productService->create($request->validated());
        return (new ProductResource($product->load(['brand', 'category', 'images', 'variants'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /admin/catalog/products/{product}
     */
    public function show(Product $product): ProductResource
    {
        return new ProductResource(
            $product->load(['brand', 'category', 'images', 'variants.attributeValues.attribute'])
        );
    }

    /**
     * PUT /admin/catalog/products/{product}
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $updated = $this->productService->update($product, $request->validated());
        return new ProductResource($updated);
    }

    /**
     * DELETE /admin/catalog/products/{product}
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->productService->delete($product);
        return response()->json(['message' => 'Product deleted successfully.']);
    }

    /**
     * POST /admin/catalog/products/{product}/publish
     */
    public function publish(Product $product): ProductResource
    {
        return new ProductResource($this->productService->publish($product));
    }

    /**
     * POST /admin/catalog/products/{product}/unpublish
     */
    public function unpublish(Product $product): ProductResource
    {
        return new ProductResource($this->productService->unpublish($product));
    }

    // ─── Variants ──────────────────────────────────────────────────────

    /**
     * POST /admin/catalog/products/{product}/variants
     */
    public function storeVariant(StoreVariantRequest $request, Product $product): ProductVariantResource
    {
        $variant = $this->variantService->create($product, $request->validated());
        return new ProductVariantResource($variant);
    }

    /**
     * PUT /admin/catalog/products/{product}/variants/{variant}
     */
    public function updateVariant(
        UpdateVariantRequest $request,
        Product $product,
        \App\Modules\Catalog\Models\ProductVariant $variant
    ): ProductVariantResource {
        $this->authorizeVariantBelongsToProduct($product, $variant);
        $updated = $this->variantService->update($variant, $request->validated());
        return new ProductVariantResource($updated);
    }

    /**
     * DELETE /admin/catalog/products/{product}/variants/{variant}
     */
    public function destroyVariant(
        Product $product,
        \App\Modules\Catalog\Models\ProductVariant $variant
    ): JsonResponse {
        $this->authorizeVariantBelongsToProduct($product, $variant);
        $this->variantService->delete($variant);
        return response()->json(['message' => 'Variant deleted successfully.']);
    }

    // ─── Images ────────────────────────────────────────────────────────

    /**
     * POST /admin/catalog/products/{product}/images
     */
    public function uploadImage(StoreProductImageRequest $request, Product $product): ProductImageResource
    {
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
        $this->imageService->reorder($product, $request->validated('order'));
        return response()->json(['message' => 'Images reordered successfully.']);
    }

    /**
     * DELETE /admin/catalog/products/{product}/images/{image}
     */
    public function destroyImage(Product $product, ProductImage $image): JsonResponse
    {
        abort_unless($image->product_id === $product->id, 404);
        $this->imageService->delete($image);
        return response()->json(['message' => 'Image deleted successfully.']);
    }

    // ─── Bulk Import ───────────────────────────────────────────────────

    /**
     * POST /admin/catalog/products/import
     * Foundation endpoint — imports up to 500 rows synchronously;
     * larger files should be offloaded to queue (future enhancement).
     */
    public function import(BulkImportProductsRequest $request): JsonResponse
    {
        $rows = [];

        if ($request->hasFile('file')) {
            $rows = $this->importService->parseCsv($request->file('file'));
        } elseif ($request->has('rows')) {
            $rows = $request->validated('rows');
        }

        $result = $this->importService->importRows($rows);

        return response()->json([
            'message' => "Import complete: {$result['created']} created, {$result['skipped']} skipped.",
            'summary' => $result,
        ]);
    }

    // ─── Private ───────────────────────────────────────────────────────

    private function authorizeVariantBelongsToProduct(
        Product $product,
        \App\Modules\Catalog\Models\ProductVariant $variant
    ): void {
        abort_unless($variant->product_id === $product->id, 404);
    }
}
