<?php

namespace App\Modules\Catalog\Controllers\Store;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Resources\CategoryResource;
use App\Modules\Catalog\Services\CategoryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class StoreCategoryController extends Controller
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    /**
     * GET /catalog/categories
     * Returns only active categories (tree).
     */
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->categoryService->tree());
    }

    /**
     * GET /catalog/categories/{category:slug}
     * Show a single active category.
     */
    public function show(Category $category): CategoryResource
    {
        abort_unless($category->is_active, 404);

        return new CategoryResource($category->load(['parent', 'children']));
    }
}
