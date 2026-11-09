<?php

namespace App\Modules\Inventory\Controllers\Admin;

use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Requests\StoreWarehouseRequest;
use App\Modules\Inventory\Requests\UpdateWarehouseRequest;
use App\Modules\Inventory\Resources\WarehouseResource;
use App\Modules\Inventory\Services\WarehouseService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class AdminWarehouseController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected WarehouseService $warehouseService
    ) {}

    /**
     * GET /api/admin/inventory/warehouses
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Warehouse::class);

        $warehouses = $this->warehouseService->list(
            filters: $request->only(['search', 'is_active']),
            perPage: min(max((int) $request->input('per_page', 20), 1), 100)
        );

        return WarehouseResource::collection($warehouses);
    }

    /**
     * POST /api/admin/inventory/warehouses
     */
    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $this->authorize('manage', Warehouse::class);

        $warehouse = $this->warehouseService->create($request->validated());

        return (new WarehouseResource($warehouse))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/admin/inventory/warehouses/{warehouse}
     */
    public function show(Warehouse $warehouse): WarehouseResource
    {
        $this->authorize('viewAny', Warehouse::class);

        return new WarehouseResource($warehouse->loadCount('inventoryItems'));
    }

    /**
     * PUT /api/admin/inventory/warehouses/{warehouse}
     */
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): WarehouseResource
    {
        $this->authorize('manage', Warehouse::class);

        $updated = $this->warehouseService->update($warehouse, $request->validated());

        return new WarehouseResource($updated);
    }

    /**
     * DELETE /api/admin/inventory/warehouses/{warehouse}
     */
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('manage', Warehouse::class);

        $this->warehouseService->delete($warehouse);

        return response()->json([
            'message' => 'Warehouse deleted successfully.',
        ]);
    }
}

