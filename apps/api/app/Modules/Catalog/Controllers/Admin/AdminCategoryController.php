<?php

namespace App\Modules\Catalog\Controllers\Admin;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Requests\StoreCategoryRequest;
use App\Modules\Catalog\Requests\UpdateCategoryRequest;
use App\Modules\Catalog\Resources\CategoryResource;
use App\Modules\Catalog\Services\CategoryService;
use App\Modules\Identity\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class AdminCategoryController extends Controller
{
    public function __construct(
        protected CategoryService $categoryService,
        protected AuditService $auditService,
    ) {}

    private function authorizePermission(Request $request, string $permission): void
    {
        $user = $request->user('sanctum') ?? $request->user();

        abort_unless(
            $user !== null && ($user->can($permission) || $user->isSuperAdmin()),
            403,
            'You do not have permission to perform this category action.'
        );
    }

    /**
     * GET /admin/catalog/categories
     * List all categories (paginated) with optional search/filter.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizePermission($request, 'catalog.view');

        $categories = $this->categoryService->list(
            filters: $request->only(['search', 'is_active', 'parent_id']),
            perPage: min(max((int) $request->get('per_page', 50), 1), 100)
        );

        return CategoryResource::collection($categories);
    }

    /**
     * GET /admin/catalog/categories/tree
     * Return the full nested category tree.
     */
    public function tree(Request $request): AnonymousResourceCollection
    {
        $this->authorizePermission($request, 'catalog.view');

        return CategoryResource::collection($this->categoryService->tree());
    }

    /**
     * POST /admin/catalog/categories
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorizePermission($request, 'catalog.manage');

        $category = $this->categoryService->create($request->validated());
        $this->auditService->log('category.created', $request->user(), $category, null, [
            'name' => $category->name,
            'slug' => $category->slug,
        ]);

        return (new CategoryResource($category->load('parent')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /admin/catalog/categories/{category}
     */
    public function show(Request $request, Category $category): CategoryResource
    {
        $this->authorizePermission($request, 'catalog.view');

        return new CategoryResource($category->load(['parent', 'children']));
    }

    /**
     * PUT /admin/catalog/categories/{category}
     */
    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $this->authorizePermission($request, 'catalog.manage');

        $oldValues = $category->only(['name', 'slug', 'parent_id', 'is_active']);
        $updated = $this->categoryService->update($category, $request->validated());
        $this->auditService->log('category.updated', $request->user(), $updated, $oldValues, $request->validated());

        return new CategoryResource($updated->load(['parent', 'children']));
    }

    /**
     * DELETE /admin/catalog/categories/{category}
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->authorizePermission($request, 'catalog.manage');

        $oldValues = $category->only(['id', 'name', 'slug']);
        $this->categoryService->delete($category);
        $this->auditService->log('category.deleted', $request->user(), null, $oldValues, null);

        return response()->json(['message' => 'Category deleted successfully.']);
    }
}
