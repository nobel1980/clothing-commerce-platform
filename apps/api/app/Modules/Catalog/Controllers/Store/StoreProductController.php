<?php

namespace App\Modules\Catalog\Controllers\Store;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Requests\ProductFilterRequest;
use App\Modules\Catalog\Resources\ProductResource;
use App\Modules\Catalog\Services\ProductService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class StoreProductController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {}

    /**
     * GET /catalog/products
     * Public listing — only published products with filtering, search, pagination.
     */
    public function index(ProductFilterRequest $request): AnonymousResourceCollection
    {
        $products = $this->productService->publicList(
            filters: $request->validated(),
            perPage: (int) $request->get('per_page', 24)
        );

        return ProductResource::collection($products);
    }

    /**
     * GET /catalog/products/{product:slug}
     * Show a single published product by slug.
     * Returns 404 if the product is not published — never leaks draft products.
     */
    public function show(Product $product): ProductResource
    {
        abort_unless($product->isPublished(), 404);

        return new ProductResource(
            $product->load(['brand', 'category', 'images', 'activeVariants.attributeValues.attribute'])
        );
    }
}
