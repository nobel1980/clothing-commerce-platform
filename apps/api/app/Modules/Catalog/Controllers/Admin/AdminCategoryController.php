<?php

namespace App\Modules\Catalog\Controllers\Admin;

use App\Modules\Catalog\Requests\StoreCategoryRequest;
use App\Modules\Catalog\Requests\UpdateCategoryRequest;
use App\Modules\Catalog\Resources\CategoryResource;
use App\Modules\Catalog\Services\CategoryService;
use App\Modules\Catalog\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class AdminCategoryController extends Controller
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    /**
     * GET /admin/catalog/categories
     * List all categories (paginated) with optional search/filter.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = $this->categoryService->list(
            filters: $request->only(['search', 'is_active', 'parent_id']),
            perPage: (int) $request->get('per_page', 50)
        );

        return CategoryResource::collection($categories);
    }

    /**
     * GET /admin/catalog/categories/tree
     * Return the full nested category tree.
     */
    public function tree(): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->categoryService->tree());
    }

    /**
     * POST /admin/catalog/categories
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->validated());
        return (new CategoryResource($category->load('parent')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /admin/catalog/categories/{category}
     */
    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->load(['parent', 'children']));
    }

    /**
     * PUT /admin/catalog/categories/{category}
     */
    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $updated = $this->categoryService->update($category, $request->validated());
        return new CategoryResource($updated->load(['parent', 'children']));
    }

    /**
     * DELETE /admin/catalog/categories/{category}
     */
    public function destroy(Category $category): JsonResponse
    {
        $this->categoryService->delete($category);
        return response()->json(['message' => 'Category deleted successfully.']);
    }
}
