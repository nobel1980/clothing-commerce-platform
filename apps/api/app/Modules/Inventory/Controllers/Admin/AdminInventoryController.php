<?php

namespace App\Modules\Inventory\Controllers\Admin;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Requests\AdjustStockRequest;
use App\Modules\Inventory\Requests\SetStockRequest;
use App\Modules\Inventory\Requests\TransferStockRequest;
use App\Modules\Inventory\Requests\UpdateInventoryItemRequest;
use App\Modules\Inventory\Resources\InventoryItemResource;
use App\Modules\Inventory\Resources\InventoryMovementResource;
use App\Modules\Inventory\Services\InventoryReportingService;
use App\Modules\Inventory\Services\StockAdjustmentService;
use App\Shared\Contracts\Inventory\InventoryServiceContract;
use App\Shared\Contracts\Inventory\StockAdjustmentData;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class AdminInventoryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected StockAdjustmentService $adjustmentService,
        protected InventoryReportingService $reportingService,
        protected InventoryServiceContract $inventoryService,
    ) {}

    /**
     * GET /api/admin/inventory/items
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', InventoryItem::class);

        $items = $this->reportingService->listInventoryItems(
            filters: $request->only([
                'warehouse_id',
                'product_variant_id',
                'sku',
                'search',
                'low_stock',
                'out_of_stock',
            ]),
            perPage: min(max((int) $request->input('per_page', 20), 1), 100)
        );

        return InventoryItemResource::collection($items);
    }

    /**
     * GET /api/admin/inventory/items/{inventoryItem}
     */
    public function show(InventoryItem $inventoryItem): InventoryItemResource
    {
        $this->authorize('viewAny', InventoryItem::class);

        return new InventoryItemResource(
            $inventoryItem->load(['productVariant.product', 'warehouse'])
        );
    }

    /**
     * PUT /api/admin/inventory/items/{inventoryItem}
     */
    public function update(UpdateInventoryItemRequest $request, InventoryItem $inventoryItem): InventoryItemResource
    {
        $this->authorize('manage', InventoryItem::class);

        $updated = $this->adjustmentService->updateItemSettings($inventoryItem, $request->validated());

        return new InventoryItemResource($updated);
    }

    /**
     * GET /api/admin/inventory/variants/{variant}/stock
     */
    public function variantStock(ProductVariant $variant): JsonResponse
    {
        $this->authorize('viewAny', InventoryItem::class);

        $summary = $this->inventoryService->getStockSummaryForVariant($variant->id);

        return response()->json([
            'data' => $summary->toArray(),
        ]);
    }

    /**
     * POST /api/admin/inventory/adjust
     */
    public function adjust(AdjustStockRequest $request): JsonResponse
    {
        $this->authorize('manage', InventoryItem::class);

        $validated = $request->validated();

        $movement = $this->adjustmentService->adjustStock(new StockAdjustmentData(
            productVariantId: (int) $validated['product_variant_id'],
            warehouseId: (int) $validated['warehouse_id'],
            quantityDelta: (int) $validated['quantity_delta'],
            type: $validated['type'] ?? InventoryMovement::TYPE_ADJUSTMENT,
            reason: $validated['reason'] ?? null,
            notes: $validated['notes'] ?? null,
            referenceType: $validated['reference_type'] ?? 'manual_adjustment',
            referenceId: $validated['reference_id'] ?? null,
            performedBy: $request->user()?->id,
        ));

        return (new InventoryMovementResource($movement->load(['productVariant', 'warehouse', 'actor'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /api/admin/inventory/set-stock
     */
    public function setStock(SetStockRequest $request): JsonResponse
    {
        $this->authorize('manage', InventoryItem::class);

        $validated = $request->validated();

        $movement = $this->adjustmentService->setStockOnHand(
            productVariantId: (int) $validated['product_variant_id'],
            warehouseId: (int) $validated['warehouse_id'],
            newQuantityOnHand: (int) $validated['quantity_on_hand'],
            reason: $validated['reason'] ?? null,
            notes: $validated['notes'] ?? null,
            lowStockThreshold: isset($validated['low_stock_threshold']) ? (int) $validated['low_stock_threshold'] : null,
            binLocation: $validated['bin_location'] ?? null,
            performedBy: $request->user()?->id,
        );

        return (new InventoryMovementResource($movement->load(['productVariant', 'warehouse', 'actor'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /api/admin/inventory/transfer
     */
    public function transfer(TransferStockRequest $request): JsonResponse
    {
        $this->authorize('manage', InventoryItem::class);

        $validated = $request->validated();

        $result = $this->adjustmentService->transferStock(
            productVariantId: (int) $validated['product_variant_id'],
            sourceWarehouseId: (int) $validated['source_warehouse_id'],
            destinationWarehouseId: (int) $validated['destination_warehouse_id'],
            quantity: (int) $validated['quantity'],
            reason: $validated['reason'] ?? null,
            notes: $validated['notes'] ?? null,
            performedBy: $request->user()?->id,
        );

        return response()->json([
            'message' => 'Stock transferred successfully.',
            'data'    => [
                'out_movement' => new InventoryMovementResource($result['out_movement']->load(['productVariant', 'warehouse'])),
                'in_movement'  => new InventoryMovementResource($result['in_movement']->load(['productVariant', 'warehouse'])),
            ],
        ], 201);
    }

    /**
     * GET /api/admin/inventory/movements
     */
    public function movements(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', InventoryItem::class);

        $movements = $this->reportingService->listMovements(
            filters: $request->only([
                'inventory_item_id',
                'product_variant_id',
                'warehouse_id',
                'type',
                'reference_type',
                'reference_id',
                'date_from',
                'date_to',
            ]),
            perPage: min(max((int) $request->input('per_page', 25), 1), 100)
        );

        return InventoryMovementResource::collection($movements);
    }
}

