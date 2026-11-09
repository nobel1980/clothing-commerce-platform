<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Support\InventoryWriteGuard;
use App\Shared\Contracts\Inventory\StockAdjustmentData;
use App\Shared\Events\Inventory\StockAdjusted;
use App\Shared\Exceptions\Inventory\InsufficientStockException;
use App\Shared\Exceptions\Inventory\InventoryException;
use App\Shared\Exceptions\Inventory\NegativeStockException;
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    public function __construct(
        protected LowStockMonitorService $lowStockMonitor
    ) {}

    /**
     * Perform a relative stock adjustment (+ or -) using SELECT FOR UPDATE inside a DB transaction.
     * Guarantees non-negative available stock and creates an InventoryMovement record.
     */
    public function adjustStock(StockAdjustmentData $data): InventoryMovement
    {
        return DB::transaction(function () use ($data) {
            return InventoryWriteGuard::authorizeStockMutation(function () use ($data) {
                $item = $this->findOrCreateLockedItem(
                    productVariantId: $data->productVariantId,
                    warehouseId: $data->warehouseId
                );

                $beforeOnHand   = (int) $item->quantity_on_hand;
                $beforeReserved = (int) $item->quantity_reserved;
                $beforeAvail    = $beforeOnHand - $beforeReserved;

                $afterOnHand = $beforeOnHand + $data->quantityDelta;
                $afterAvail  = $afterOnHand - $beforeReserved;

                if ($afterOnHand < 0 || $afterAvail < 0) {
                    throw new InsufficientStockException(
                        productVariantId: $data->productVariantId,
                        requestedQuantity: abs($data->quantityDelta),
                        availableQuantity: max(0, $beforeAvail),
                        warehouseId: $data->warehouseId,
                        message: "Cannot adjust stock by {$data->quantityDelta} for variant #{$data->productVariantId} in warehouse #{$data->warehouseId}: available stock is {$beforeAvail} (on_hand: {$beforeOnHand}, reserved: {$beforeReserved})."
                    );
                }

                $item->quantity_on_hand = $afterOnHand;
                $item->save();

                $type = in_array($data->type, InventoryMovement::ALLOWED_TYPES, true)
                    ? $data->type
                    : InventoryMovement::TYPE_ADJUSTMENT;

                $movement = InventoryMovement::create([
                    'inventory_item_id'  => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id'       => $item->warehouse_id,
                    'type'               => $type,
                    'quantity_change'    => $data->quantityDelta,
                    'on_hand_delta'      => $data->quantityDelta,
                    'reserved_delta'     => 0,
                    'quantity_before'    => $beforeOnHand,
                    'quantity_after'     => $afterOnHand,
                    'reserved_before'    => $beforeReserved,
                    'reserved_after'     => $beforeReserved,
                    'reference_type'     => $data->referenceType ?? 'manual_adjustment',
                    'reference_id'       => $data->referenceId,
                    'reason'             => $data->reason,
                    'notes'              => $data->notes,
                    'performed_by'       => $data->performedBy ?? auth()->id(),
                ]);

                $this->lowStockMonitor->evaluate($item);

                DB::afterCommit(function () use ($movement, $item) {
                    StockAdjusted::dispatch(
                        $movement->id,
                        $item->id,
                        $item->product_variant_id,
                        $item->warehouse_id,
                        $movement->type,
                        $movement->on_hand_delta,
                        $movement->reserved_delta,
                        $item->quantity_on_hand,
                        $item->quantity_reserved,
                        $item->available_quantity
                    );
                });

                return $movement;
            });
        });
    }

    /**
     * Set the exact quantity_on_hand for a variant in a warehouse inside a row-locked transaction.
     * Always creates an InventoryMovement record.
     */
    public function setStockOnHand(
        int $productVariantId,
        int $warehouseId,
        int $newQuantityOnHand,
        ?string $reason = null,
        ?string $notes = null,
        ?int $lowStockThreshold = null,
        ?string $binLocation = null,
        ?int $performedBy = null
    ): InventoryMovement {
        if ($newQuantityOnHand < 0) {
            throw new NegativeStockException('Target quantity_on_hand cannot be negative.');
        }

        return DB::transaction(function () use (
            $productVariantId,
            $warehouseId,
            $newQuantityOnHand,
            $reason,
            $notes,
            $lowStockThreshold,
            $binLocation,
            $performedBy
        ) {
            return InventoryWriteGuard::authorizeStockMutation(function () use (
                $productVariantId,
                $warehouseId,
                $newQuantityOnHand,
                $reason,
                $notes,
                $lowStockThreshold,
                $binLocation,
                $performedBy
            ) {
                $item = $this->findOrCreateLockedItem($productVariantId, $warehouseId);

                $beforeOnHand   = (int) $item->quantity_on_hand;
                $beforeReserved = (int) $item->quantity_reserved;

                if ($newQuantityOnHand < $beforeReserved) {
                    throw new InsufficientStockException(
                        productVariantId: $productVariantId,
                        requestedQuantity: $beforeOnHand - $newQuantityOnHand,
                        availableQuantity: $beforeOnHand - $beforeReserved,
                        warehouseId: $warehouseId,
                        message: "Cannot set quantity_on_hand to {$newQuantityOnHand} because {$beforeReserved} units are currently reserved."
                    );
                }

                $delta = $newQuantityOnHand - $beforeOnHand;

                $item->quantity_on_hand = $newQuantityOnHand;
                if ($lowStockThreshold !== null) {
                    $item->low_stock_threshold = $lowStockThreshold;
                }
                if ($binLocation !== null) {
                    $item->bin_location = $binLocation;
                }
                $item->save();

                $movement = InventoryMovement::create([
                    'inventory_item_id'  => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id'       => $item->warehouse_id,
                    'type'               => $beforeOnHand === 0 && $newQuantityOnHand > 0
                        ? InventoryMovement::TYPE_INITIAL
                        : InventoryMovement::TYPE_ADJUSTMENT,
                    'quantity_change'    => $delta,
                    'on_hand_delta'      => $delta,
                    'reserved_delta'     => 0,
                    'quantity_before'    => $beforeOnHand,
                    'quantity_after'     => $newQuantityOnHand,
                    'reserved_before'    => $beforeReserved,
                    'reserved_after'     => $beforeReserved,
                    'reference_type'     => 'stock_set',
                    'reference_id'       => null,
                    'reason'             => $reason ?? 'Set stock on hand',
                    'notes'              => $notes,
                    'performed_by'       => $performedBy ?? auth()->id(),
                ]);

                $this->lowStockMonitor->evaluate($item);

                return $movement;
            });
        });
    }

    /**
     * Transfer stock between two warehouses atomically using deterministic lock ordering.
     *
     * @return array{out_movement: InventoryMovement, in_movement: InventoryMovement}
     */
    public function transferStock(
        int $productVariantId,
        int $sourceWarehouseId,
        int $destinationWarehouseId,
        int $quantity,
        ?string $reason = null,
        ?string $notes = null,
        ?int $performedBy = null
    ): array {
        if ($quantity <= 0) {
            throw new InventoryException('Transfer quantity must be a positive integer.');
        }

        if ($sourceWarehouseId === $destinationWarehouseId) {
            throw new InventoryException('Source and destination warehouses must be different.');
        }

        return DB::transaction(function () use (
            $productVariantId,
            $sourceWarehouseId,
            $destinationWarehouseId,
            $quantity,
            $reason,
            $notes,
            $performedBy
        ) {
            return InventoryWriteGuard::authorizeStockMutation(function () use (
                $productVariantId,
                $sourceWarehouseId,
                $destinationWarehouseId,
                $quantity,
                $reason,
                $notes,
                $performedBy
            ) {
                // Lock warehouses in ascending ID order to prevent deadlocks
                $whIds = [$sourceWarehouseId, $destinationWarehouseId];
                sort($whIds);
                Warehouse::query()->whereIn('id', $whIds)->orderBy('id')->lockForUpdate()->get();

                // Ensure both inventory items exist and lock them in ascending ID order
                $this->findOrCreateLockedItem($productVariantId, $whIds[0]);
                $this->findOrCreateLockedItem($productVariantId, $whIds[1]);

                $lockedItems = InventoryItem::query()
                    ->where('product_variant_id', $productVariantId)
                    ->whereIn('warehouse_id', $whIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('warehouse_id');

                /** @var InventoryItem $sourceItem */
                $sourceItem = $lockedItems->get($sourceWarehouseId);
                /** @var InventoryItem $destItem */
                $destItem = $lockedItems->get($destinationWarehouseId);

                $sourceBeforeOnHand   = (int) $sourceItem->quantity_on_hand;
                $sourceBeforeReserved = (int) $sourceItem->quantity_reserved;
                $sourceAvailable      = $sourceBeforeOnHand - $sourceBeforeReserved;

                if ($sourceAvailable < $quantity) {
                    throw new InsufficientStockException(
                        productVariantId: $productVariantId,
                        requestedQuantity: $quantity,
                        availableQuantity: max(0, $sourceAvailable),
                        warehouseId: $sourceWarehouseId
                    );
                }

                $destBeforeOnHand   = (int) $destItem->quantity_on_hand;
                $destBeforeReserved = (int) $destItem->quantity_reserved;

                $sourceAfterOnHand = $sourceBeforeOnHand - $quantity;
                $destAfterOnHand   = $destBeforeOnHand + $quantity;

                $sourceItem->quantity_on_hand = $sourceAfterOnHand;
                $sourceItem->save();

                $destItem->quantity_on_hand = $destAfterOnHand;
                $destItem->save();

                $transferRef = "transfer:{$sourceWarehouseId}->{$destinationWarehouseId}:" . now()->timestamp;
                $actorId     = $performedBy ?? auth()->id();

                $outMovement = InventoryMovement::create([
                    'inventory_item_id'  => $sourceItem->id,
                    'product_variant_id' => $productVariantId,
                    'warehouse_id'       => $sourceWarehouseId,
                    'type'               => InventoryMovement::TYPE_TRANSFER_OUT,
                    'quantity_change'    => -$quantity,
                    'on_hand_delta'      => -$quantity,
                    'reserved_delta'     => 0,
                    'quantity_before'    => $sourceBeforeOnHand,
                    'quantity_after'     => $sourceAfterOnHand,
                    'reserved_before'    => $sourceBeforeReserved,
                    'reserved_after'     => $sourceBeforeReserved,
                    'reference_type'     => 'warehouse_transfer',
                    'reference_id'       => $transferRef,
                    'reason'             => $reason ?? "Transfer to warehouse #{$destinationWarehouseId}",
                    'notes'              => $notes,
                    'performed_by'       => $actorId,
                ]);

                $inMovement = InventoryMovement::create([
                    'inventory_item_id'  => $destItem->id,
                    'product_variant_id' => $productVariantId,
                    'warehouse_id'       => $destinationWarehouseId,
                    'type'               => InventoryMovement::TYPE_TRANSFER_IN,
                    'quantity_change'    => $quantity,
                    'on_hand_delta'      => $quantity,
                    'reserved_delta'     => 0,
                    'quantity_before'    => $destBeforeOnHand,
                    'quantity_after'     => $destAfterOnHand,
                    'reserved_before'    => $destBeforeReserved,
                    'reserved_after'     => $destBeforeReserved,
                    'reference_type'     => 'warehouse_transfer',
                    'reference_id'       => $transferRef,
                    'reason'             => $reason ?? "Transfer from warehouse #{$sourceWarehouseId}",
                    'notes'              => $notes,
                    'performed_by'       => $actorId,
                ]);

                $this->lowStockMonitor->evaluate($sourceItem);
                $this->lowStockMonitor->evaluate($destItem);

                return [
                    'out_movement' => $outMovement,
                    'in_movement'  => $inMovement,
                ];
            });
        });
    }

    /**
     * Update non-stock metadata (thresholds, reorder point/quantity, bin_location) for an InventoryItem.
     */
    public function updateItemSettings(InventoryItem $item, array $data): InventoryItem
    {
        return DB::transaction(function () use ($item, $data) {
            return InventoryWriteGuard::authorizeStockMutation(function () use ($item, $data) {
                $locked = InventoryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

                $locked->update([
                    'low_stock_threshold' => array_key_exists('low_stock_threshold', $data)
                        ? (int) $data['low_stock_threshold']
                        : $locked->low_stock_threshold,
                    'reorder_point' => array_key_exists('reorder_point', $data)
                        ? ($data['reorder_point'] !== null ? (int) $data['reorder_point'] : null)
                        : $locked->reorder_point,
                    'reorder_quantity' => array_key_exists('reorder_quantity', $data)
                        ? ($data['reorder_quantity'] !== null ? (int) $data['reorder_quantity'] : null)
                        : $locked->reorder_quantity,
                    'bin_location' => array_key_exists('bin_location', $data)
                        ? $data['bin_location']
                        : $locked->bin_location,
                ]);

                $this->lowStockMonitor->evaluate($locked);

                return $locked->fresh(['productVariant', 'warehouse']);
            });
        });
    }

    /**
     * Find and row-lock an InventoryItem by (product_variant_id, warehouse_id),
     * creating a zero-stock record safely if it does not yet exist.
     */
    public function findOrCreateLockedItem(int $productVariantId, int $warehouseId): InventoryItem
    {
        $item = InventoryItem::query()
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if ($item !== null) {
            return $item;
        }

        // Lock the warehouse to serialize concurrent initializations of the same (variant, warehouse)
        Warehouse::query()->whereKey($warehouseId)->lockForUpdate()->firstOrFail();

        $item = InventoryItem::query()
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if ($item !== null) {
            return $item;
        }

        $created = InventoryItem::create([
            'product_variant_id'  => $productVariantId,
            'warehouse_id'        => $warehouseId,
            'quantity_on_hand'    => 0,
            'quantity_reserved'   => 0,
            'low_stock_threshold' => 5,
            'reorder_point'       => 10,
            'reorder_quantity'    => 50,
        ]);

        return InventoryItem::query()
            ->whereKey($created->id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}

