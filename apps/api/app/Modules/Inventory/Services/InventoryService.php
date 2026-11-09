<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Models\StockReservationItem;
use App\Shared\Contracts\Inventory\InventoryMovementData;
use App\Shared\Contracts\Inventory\InventoryServiceContract;
use App\Shared\Contracts\Inventory\ReserveStockData;
use App\Shared\Contracts\Inventory\StockAdjustmentData;
use App\Shared\Contracts\Inventory\StockReservationData;
use App\Shared\Contracts\Inventory\StockReservationItemData;
use App\Shared\Contracts\Inventory\VariantStockSummaryData;

class InventoryService implements InventoryServiceContract
{
    public function __construct(
        protected WarehouseService $warehouseService,
        protected StockAdjustmentService $adjustmentService,
        protected ReservationService $reservationService,
        protected InventoryReportingService $reportingService,
    ) {}

    public function getAvailableStock(int $productVariantId, ?int $warehouseId = null): int
    {
        $query = InventoryItem::query()
            ->where('product_variant_id', $productVariantId)
            ->whereHas('warehouse', fn ($q) => $q->where('is_active', true));

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        $result = $query
            ->selectRaw('COALESCE(SUM(quantity_on_hand - quantity_reserved), 0) as available')
            ->value('available');

        return max(0, (int) $result);
    }

    public function hasAvailableStock(int $productVariantId, int $quantity = 1, ?int $warehouseId = null): bool
    {
        if ($quantity <= 0) {
            return true;
        }

        return $this->getAvailableStock($productVariantId, $warehouseId) >= $quantity;
    }

    public function getStockSummaryForVariant(int $productVariantId): VariantStockSummaryData
    {
        $summaries = $this->getStockSummaryForVariants([$productVariantId]);

        return $summaries[$productVariantId] ?? new VariantStockSummaryData(
            productVariantId: $productVariantId,
            totalOnHand: 0,
            totalReserved: 0,
            totalAvailable: 0,
            inStock: false,
            isLowStock: true,
            warehouseBreakdown: []
        );
    }

    public function getStockSummaryForVariants(array $productVariantIds): array
    {
        $variantIds = array_values(array_unique(array_map('intval', $productVariantIds)));
        if (empty($variantIds)) {
            return [];
        }

        $items = InventoryItem::query()
            ->with('warehouse')
            ->whereIn('product_variant_id', $variantIds)
            ->whereHas('warehouse', fn ($q) => $q->where('is_active', true))
            ->get()
            ->groupBy('product_variant_id');

        $result = [];

        foreach ($variantIds as $variantId) {
            $variantItems = $items->get($variantId) ?? collect();

            $totalOnHand    = 0;
            $totalReserved  = 0;
            $totalAvailable = 0;
            $totalThreshold = 0;
            $breakdown      = [];

            foreach ($variantItems as $item) {
                $onHand    = (int) $item->quantity_on_hand;
                $reserved  = (int) $item->quantity_reserved;
                $available = $onHand - $reserved;
                $threshold = (int) $item->low_stock_threshold;

                $totalOnHand    += $onHand;
                $totalReserved  += $reserved;
                $totalAvailable += $available;
                $totalThreshold += $threshold;

                $breakdown[] = [
                    'warehouse_id'       => $item->warehouse_id,
                    'warehouse_code'     => $item->warehouse?->code ?? '',
                    'warehouse_name'     => $item->warehouse?->name ?? '',
                    'quantity_on_hand'   => $onHand,
                    'quantity_reserved'  => $reserved,
                    'available_quantity' => $available,
                    'is_low_stock'       => $available <= $threshold,
                ];
            }

            $effectiveThreshold = $variantItems->isEmpty() ? 5 : $totalThreshold;

            $result[$variantId] = new VariantStockSummaryData(
                productVariantId: $variantId,
                totalOnHand: $totalOnHand,
                totalReserved: $totalReserved,
                totalAvailable: $totalAvailable,
                inStock: $totalAvailable > 0,
                isLowStock: $totalAvailable <= $effectiveThreshold,
                warehouseBreakdown: $breakdown
            );
        }

        return $result;
    }

    public function adjustStock(StockAdjustmentData $data): InventoryMovementData
    {
        $movement = $this->adjustmentService->adjustStock($data);

        return $this->mapMovementToDto($movement);
    }

    public function reserveStock(ReserveStockData $data): StockReservationData
    {
        $reservation = $this->reservationService->reserve($data);

        return $this->mapReservationToDto($reservation);
    }

    public function commitReservation(int|string $reservationIdOrKey, ?int $performedBy = null): StockReservationData
    {
        $reservation = $this->reservationService->commit($reservationIdOrKey, $performedBy);

        return $this->mapReservationToDto($reservation);
    }

    public function releaseReservation(
        int|string $reservationIdOrKey,
        ?string $reason = null,
        ?int $performedBy = null
    ): StockReservationData {
        $reservation = $this->reservationService->release($reservationIdOrKey, $reason, $performedBy);

        return $this->mapReservationToDto($reservation);
    }

    public function getReservation(int|string $reservationIdOrKey): ?StockReservationData
    {
        $reservation = $this->reservationService->findByIdOrKey($reservationIdOrKey);

        return $reservation ? $this->mapReservationToDto($reservation) : null;
    }

    public function expireOverdueReservations(): int
    {
        return $this->reservationService->expireOverdue();
    }

    public function isInventoryTrackingActive(?int $productVariantId = null): bool
    {
        if (\App\Modules\Inventory\Models\Warehouse::query()->where('is_active', true)->exists()) {
            return true;
        }

        if ($productVariantId !== null) {
            return InventoryItem::query()->where('product_variant_id', $productVariantId)->exists();
        }

        return InventoryItem::query()->exists();
    }

    private function mapMovementToDto(InventoryMovement $movement): InventoryMovementData
    {
        return new InventoryMovementData(
            id: $movement->id,
            inventoryItemId: $movement->inventory_item_id,
            productVariantId: $movement->product_variant_id,
            warehouseId: $movement->warehouse_id,
            type: $movement->type,
            quantityChange: $movement->quantity_change,
            onHandDelta: $movement->on_hand_delta,
            reservedDelta: $movement->reserved_delta,
            quantityBefore: $movement->quantity_before,
            quantityAfter: $movement->quantity_after,
            reservedBefore: $movement->reserved_before,
            reservedAfter: $movement->reserved_after,
            availableAfter: $movement->available_after,
            referenceType: $movement->reference_type,
            referenceId: $movement->reference_id,
            reason: $movement->reason,
            notes: $movement->notes,
            performedBy: $movement->performed_by,
            createdAt: $movement->created_at?->toIso8601String() ?? now()->toIso8601String()
        );
    }

    private function mapReservationToDto(StockReservation $reservation): StockReservationData
    {
        $reservation->loadMissing('items');

        $items = $reservation->items->map(fn (StockReservationItem $item) => new StockReservationItemData(
            id: $item->id,
            stockReservationId: $item->stock_reservation_id,
            inventoryItemId: $item->inventory_item_id,
            productVariantId: $item->product_variant_id,
            warehouseId: $item->warehouse_id,
            quantity: $item->quantity
        ))->all();

        return new StockReservationData(
            id: $reservation->id,
            idempotencyKey: $reservation->idempotency_key,
            status: $reservation->status,
            expiresAt: $reservation->expires_at->toIso8601String(),
            referenceType: $reservation->reference_type,
            referenceId: $reservation->reference_id,
            committedAt: $reservation->committed_at?->toIso8601String(),
            releasedAt: $reservation->released_at?->toIso8601String(),
            expiredAt: $reservation->expired_at?->toIso8601String(),
            items: $items,
            metadata: $reservation->metadata
        );
    }
}

