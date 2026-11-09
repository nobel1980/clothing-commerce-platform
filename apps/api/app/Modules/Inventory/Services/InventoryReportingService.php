<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InventoryReportingService
{
    /**
     * List inventory items with filtering for Admin backoffice.
     */
    public function listInventoryItems(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = InventoryItem::query()
            ->with(['productVariant.product', 'warehouse']);

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        if (!empty($filters['product_variant_id'])) {
            $query->where('product_variant_id', (int) $filters['product_variant_id']);
        }

        if (!empty($filters['sku'])) {
            $sku = trim($filters['sku']);
            $query->whereHas('productVariant', fn ($q) => $q->where('sku', $sku));
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->whereHas('productVariant', function ($q) use ($search) {
                $q->where('sku', 'like', $search)
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', $search));
            });
        }

        if (!empty($filters['low_stock']) && filter_var($filters['low_stock'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereRaw('(quantity_on_hand - quantity_reserved) <= low_stock_threshold');
        }

        if (!empty($filters['out_of_stock']) && filter_var($filters['out_of_stock'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereRaw('(quantity_on_hand - quantity_reserved) <= 0');
        }

        return $query->orderBy('warehouse_id')
            ->orderBy('product_variant_id')
            ->paginate($perPage);
    }

    /**
     * Comprehensive inventory summary report.
     * All monetary valuations are calculated in integer sub-units (never floats).
     */
    public function getSummaryReport(?int $warehouseId = null): array
    {
        $itemQuery = InventoryItem::query();
        if ($warehouseId !== null) {
            $itemQuery->where('inventory_items.warehouse_id', $warehouseId);
        }

        $totals = (clone $itemQuery)
            ->selectRaw('
                COUNT(*) as total_items,
                COALESCE(SUM(quantity_on_hand), 0) as total_on_hand,
                COALESCE(SUM(quantity_reserved), 0) as total_reserved,
                COALESCE(SUM(quantity_on_hand - quantity_reserved), 0) as total_available,
                SUM(CASE WHEN (quantity_on_hand - quantity_reserved) <= low_stock_threshold THEN 1 ELSE 0 END) as low_stock_count,
                SUM(CASE WHEN (quantity_on_hand - quantity_reserved) <= 0 THEN 1 ELSE 0 END) as out_of_stock_count
            ')
            ->first();

        $valuation = (clone $itemQuery)
            ->join('product_variants', 'product_variants.id', '=', 'inventory_items.product_variant_id')
            ->selectRaw('
                COALESCE(SUM(inventory_items.quantity_on_hand * COALESCE(product_variants.cost_price, 0)), 0) as cost_amount,
                COALESCE(SUM(inventory_items.quantity_on_hand * product_variants.price_amount), 0) as retail_amount
            ')
            ->first();

        $activeReservationsQuery = StockReservation::query()->active();
        if ($warehouseId !== null) {
            $activeReservationsQuery->whereHas('items', fn ($q) => $q->where('warehouse_id', $warehouseId));
        }

        return [
            'warehouse_id'             => $warehouseId,
            'total_warehouses'         => Warehouse::query()->count(),
            'active_warehouses'        => Warehouse::query()->active()->count(),
            'total_inventory_items'    => (int) ($totals->total_items ?? 0),
            'total_quantity_on_hand'   => (int) ($totals->total_on_hand ?? 0),
            'total_quantity_reserved'  => (int) ($totals->total_reserved ?? 0),
            'total_available_quantity' => (int) ($totals->total_available ?? 0),
            'low_stock_items_count'    => (int) ($totals->low_stock_count ?? 0),
            'out_of_stock_items_count' => (int) ($totals->out_of_stock_count ?? 0),
            'active_reservations_count'=> (int) $activeReservationsQuery->count(),
            'valuation'                => [
                'cost_amount'   => (int) ($valuation->cost_amount ?? 0),
                'retail_amount' => (int) ($valuation->retail_amount ?? 0),
                'currency'      => 'BDT',
            ],
        ];
    }

    /**
     * Low-stock and out-of-stock report.
     */
    public function getLowStockReport(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = InventoryItem::query()
            ->with(['productVariant.product', 'warehouse'])
            ->whereRaw('(quantity_on_hand - quantity_reserved) <= low_stock_threshold');

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        if (!empty($filters['out_of_stock_only']) && filter_var($filters['out_of_stock_only'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereRaw('(quantity_on_hand - quantity_reserved) <= 0');
        }

        return $query
            ->orderByRaw('(quantity_on_hand - quantity_reserved) ASC')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * Per-warehouse stock breakdown report.
     */
    public function getWarehouseBreakdownReport(): array
    {
        $warehouses = Warehouse::query()
            ->orderedByAllocationPriority()
            ->get();

        $statsByWarehouse = InventoryItem::query()
            ->leftJoin('product_variants', 'product_variants.id', '=', 'inventory_items.product_variant_id')
            ->groupBy('inventory_items.warehouse_id')
            ->selectRaw('
                inventory_items.warehouse_id,
                COUNT(inventory_items.id) as sku_count,
                COALESCE(SUM(inventory_items.quantity_on_hand), 0) as total_on_hand,
                COALESCE(SUM(inventory_items.quantity_reserved), 0) as total_reserved,
                COALESCE(SUM(inventory_items.quantity_on_hand - inventory_items.quantity_reserved), 0) as total_available,
                SUM(CASE WHEN (inventory_items.quantity_on_hand - inventory_items.quantity_reserved) <= inventory_items.low_stock_threshold THEN 1 ELSE 0 END) as low_stock_count,
                SUM(CASE WHEN (inventory_items.quantity_on_hand - inventory_items.quantity_reserved) <= 0 THEN 1 ELSE 0 END) as out_of_stock_count,
                COALESCE(SUM(inventory_items.quantity_on_hand * COALESCE(product_variants.cost_price, 0)), 0) as cost_amount,
                COALESCE(SUM(inventory_items.quantity_on_hand * COALESCE(product_variants.price_amount, 0)), 0) as retail_amount
            ')
            ->get()
            ->keyBy('warehouse_id');

        $report = [];

        foreach ($warehouses as $warehouse) {
            $stat = $statsByWarehouse->get($warehouse->id);

            $report[] = [
                'warehouse_id'             => $warehouse->id,
                'warehouse_code'           => $warehouse->code,
                'warehouse_name'           => $warehouse->name,
                'is_active'                => (bool) $warehouse->is_active,
                'is_default'               => (bool) $warehouse->is_default,
                'priority'                 => (int) $warehouse->priority,
                'sku_count'                => (int) ($stat->sku_count ?? 0),
                'total_quantity_on_hand'   => (int) ($stat->total_on_hand ?? 0),
                'total_quantity_reserved'  => (int) ($stat->total_reserved ?? 0),
                'total_available_quantity' => (int) ($stat->total_available ?? 0),
                'low_stock_count'          => (int) ($stat->low_stock_count ?? 0),
                'out_of_stock_count'       => (int) ($stat->out_of_stock_count ?? 0),
                'valuation'                => [
                    'cost_amount'   => (int) ($stat->cost_amount ?? 0),
                    'retail_amount' => (int) ($stat->retail_amount ?? 0),
                    'currency'      => 'BDT',
                ],
            ];
        }

        return $report;
    }

    /**
     * Aggregated movement summary report by movement type.
     */
    public function getMovementSummaryReport(array $filters = []): array
    {
        $query = InventoryMovement::query();

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        if (!empty($filters['product_variant_id'])) {
            $query->where('product_variant_id', (int) $filters['product_variant_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $rows = $query
            ->groupBy('type')
            ->selectRaw('
                type,
                COUNT(*) as movements_count,
                COALESCE(SUM(quantity_change), 0) as total_quantity_change,
                COALESCE(SUM(on_hand_delta), 0) as total_on_hand_delta,
                COALESCE(SUM(reserved_delta), 0) as total_reserved_delta
            ')
            ->orderBy('type')
            ->get();

        return $rows->map(fn ($row) => [
            'type'                  => $row->type,
            'movements_count'       => (int) $row->movements_count,
            'total_quantity_change' => (int) $row->total_quantity_change,
            'total_on_hand_delta'   => (int) $row->total_on_hand_delta,
            'total_reserved_delta'  => (int) $row->total_reserved_delta,
        ])->all();
    }

    /**
     * Paginated stock movement history.
     */
    public function listMovements(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = InventoryMovement::query()
            ->with(['productVariant', 'warehouse', 'actor']);

        if (!empty($filters['inventory_item_id'])) {
            $query->where('inventory_item_id', (int) $filters['inventory_item_id']);
        }

        if (!empty($filters['product_variant_id'])) {
            $query->where('product_variant_id', (int) $filters['product_variant_id']);
        }

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['reference_type'])) {
            $query->where('reference_type', $filters['reference_type']);
        }

        if (!empty($filters['reference_id'])) {
            $query->where('reference_id', $filters['reference_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }
}

