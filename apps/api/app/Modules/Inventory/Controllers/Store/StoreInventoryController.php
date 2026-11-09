<?php

namespace App\Modules\Inventory\Controllers\Store;

use App\Modules\Catalog\Models\ProductVariant;
use App\Shared\Contracts\Inventory\InventoryServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class StoreInventoryController extends Controller
{
    public function __construct(
        protected InventoryServiceContract $inventoryService
    ) {}

    /**
     * GET /api/inventory/variants/{variant}/availability
     * Public storefront endpoint to check variant stock availability.
     */
    public function availability(ProductVariant $variant): JsonResponse
    {
        abort_unless($variant->status === 'active', 404);

        $summary = $this->inventoryService->getStockSummaryForVariant($variant->id);

        return response()->json([
            'data' => [
                'product_variant_id' => $variant->id,
                'sku'                => $variant->sku,
                'in_stock'           => $summary->inStock,
                'available_quantity' => $summary->totalAvailable,
                'is_low_stock'       => $summary->isLowStock,
            ],
        ]);
    }
}

