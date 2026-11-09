<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Jobs\SendLowStockNotificationJob;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Support\InventoryWriteGuard;
use App\Shared\Events\Inventory\LowStockDetected;
use Illuminate\Support\Facades\DB;

class LowStockMonitorService
{
    /**
     * Evaluate whether an InventoryItem has crossed into or out of low-stock territory.
     * If available stock <= low_stock_threshold and not yet notified, dispatches
     * LowStockDetected event and SendLowStockNotificationJob after transaction commit.
     */
    public function evaluate(InventoryItem $item): void
    {
        $available = $item->available_quantity;
        $threshold = (int) $item->low_stock_threshold;

        if ($available <= $threshold) {
            if ($item->low_stock_notified_at === null) {
                InventoryWriteGuard::authorizeStockMutation(function () use ($item) {
                    $item->forceFill(['low_stock_notified_at' => now()])->saveQuietly();
                });

                $item->loadMissing(['productVariant', 'warehouse']);

                $sku           = $item->productVariant?->sku ?? "VARIANT-{$item->product_variant_id}";
                $warehouseCode = $item->warehouse?->code ?? "WH-{$item->warehouse_id}";
                $itemId        = $item->id;
                $variantId     = $item->product_variant_id;
                $warehouseId   = $item->warehouse_id;
                $onHand        = $item->quantity_on_hand;
                $reserved      = $item->quantity_reserved;

                DB::afterCommit(function () use (
                    $itemId,
                    $variantId,
                    $sku,
                    $warehouseId,
                    $warehouseCode,
                    $onHand,
                    $reserved,
                    $available,
                    $threshold
                ) {
                    LowStockDetected::dispatch(
                        $itemId,
                        $variantId,
                        $sku,
                        $warehouseId,
                        $warehouseCode,
                        $onHand,
                        $reserved,
                        $available,
                        $threshold
                    );

                    SendLowStockNotificationJob::dispatch(
                        $itemId,
                        $variantId,
                        $sku,
                        $warehouseId,
                        $warehouseCode,
                        $onHand,
                        $reserved,
                        $available,
                        $threshold
                    );
                });
            }
        } elseif ($item->low_stock_notified_at !== null) {
            // Reset notification marker once stock is replenished above threshold
            InventoryWriteGuard::authorizeStockMutation(function () use ($item) {
                $item->forceFill(['low_stock_notified_at' => null])->saveQuietly();
            });
        }
    }
}

