<?php

namespace App\Modules\Inventory\Controllers\Admin;

use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Resources\InventoryItemResource;
use App\Modules\Inventory\Services\InventoryReportingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class AdminInventoryReportController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected InventoryReportingService $reportingService
    ) {}

    /**
     * GET /api/admin/inventory/reports/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewReports', InventoryItem::class);

        $warehouseId = $request->filled('warehouse_id') ? (int) $request->input('warehouse_id') : null;

        return response()->json([
            'data' => $this->reportingService->getSummaryReport($warehouseId),
        ]);
    }

    /**
     * GET /api/admin/inventory/reports/low-stock
     */
    public function lowStock(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewReports', InventoryItem::class);

        $items = $this->reportingService->getLowStockReport(
            filters: $request->only(['warehouse_id', 'out_of_stock_only']),
            perPage: min(max((int) $request->input('per_page', 20), 1), 100)
        );

        return InventoryItemResource::collection($items);
    }

    /**
     * GET /api/admin/inventory/reports/warehouses
     */
    public function warehouses(): JsonResponse
    {
        $this->authorize('viewReports', InventoryItem::class);

        return response()->json([
            'data' => $this->reportingService->getWarehouseBreakdownReport(),
        ]);
    }

    /**
     * GET /api/admin/inventory/reports/movements
     */
    public function movements(Request $request): JsonResponse
    {
        $this->authorize('viewReports', InventoryItem::class);

        $summary = $this->reportingService->getMovementSummaryReport(
            $request->only(['warehouse_id', 'product_variant_id', 'date_from', 'date_to'])
        );

        return response()->json([
            'data' => $summary,
        ]);
    }
}

