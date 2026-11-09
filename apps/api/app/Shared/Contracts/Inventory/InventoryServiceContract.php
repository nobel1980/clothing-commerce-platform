<?php

namespace App\Shared\Contracts\Inventory;

/**
 * Public Application Interface for the Inventory Module.
 *
 * All external modules (Catalog, Cart, Checkout, Orders, Reporting) MUST
 * interact with inventory exclusively through this contract or shared events,
 * and NEVER directly modify inventory tables.
 */
interface InventoryServiceContract
{
    /**
     * Get available stock (quantity_on_hand - quantity_reserved) for a variant.
     * If $warehouseId is null, sums across all active warehouses.
     */
    public function getAvailableStock(int $productVariantId, ?int $warehouseId = null): int;

    /**
     * Check whether at least $quantity units are available for a variant.
     */
    public function hasAvailableStock(int $productVariantId, int $quantity = 1, ?int $warehouseId = null): bool;

    /**
     * Get complete stock summary (on hand, reserved, available, warehouse breakdown) for a single variant.
     */
    public function getStockSummaryForVariant(int $productVariantId): VariantStockSummaryData;

    /**
     * Get stock summaries for multiple variants in batch (prevents N+1 queries in Catalog/Cart).
     *
     * @param array<int> $productVariantIds
     * @return array<int, VariantStockSummaryData> Keyed by product_variant_id
     */
    public function getStockSummaryForVariants(array $productVariantIds): array;

    /**
     * Perform a transactional, row-locked stock adjustment and record an InventoryMovement.
     */
    public function adjustStock(StockAdjustmentData $data): InventoryMovementData;

    /**
     * Create an idempotent stock reservation across one or more variants/warehouses.
     */
    public function reserveStock(ReserveStockData $data): StockReservationData;

    /**
     * Commit an active reservation (deducts quantity_on_hand and quantity_reserved idempotently).
     */
    public function commitReservation(int|string $reservationIdOrKey, ?int $performedBy = null): StockReservationData;

    /**
     * Release an active reservation (restores available stock by decrementing quantity_reserved idempotently).
     */
    public function releaseReservation(
        int|string $reservationIdOrKey,
        ?string $reason = null,
        ?int $performedBy = null
    ): StockReservationData;

    /**
     * Retrieve a stock reservation by ID or idempotency key.
     */
    public function getReservation(int|string $reservationIdOrKey): ?StockReservationData;

    /**
     * Expire all overdue active reservations and release their reserved stock.
     *
     * @return int Number of expired reservations processed
     */
    public function expireOverdueReservations(): int;

    /**
     * Check whether inventory tracking is active (at least one active warehouse or inventory item exists).
     */
    public function isInventoryTrackingActive(?int $productVariantId = null): bool;
}

