<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Models\StockReservationItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Support\InventoryWriteGuard;
use App\Shared\Contracts\Inventory\ReserveStockData;
use App\Shared\Contracts\Inventory\ReserveStockItemData;
use App\Shared\Events\Inventory\StockReservationCommitted;
use App\Shared\Events\Inventory\StockReservationExpired;
use App\Shared\Events\Inventory\StockReservationReleased;
use App\Shared\Events\Inventory\StockReserved;
use App\Shared\Exceptions\Inventory\InsufficientStockException;
use App\Shared\Exceptions\Inventory\InvalidReservationStateException;
use App\Shared\Exceptions\Inventory\InventoryException;
use App\Shared\Exceptions\Inventory\ReservationExpiredException;
use App\Shared\Exceptions\Inventory\WarehouseInactiveException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    public function __construct(
        protected LowStockMonitorService $lowStockMonitor
    ) {}

    /**
     * List reservations with filtering for Admin API.
     */
    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = StockReservation::query()
            ->with(['items.productVariant', 'items.warehouse']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['reference_type'])) {
            $query->where('reference_type', $filters['reference_type']);
        }

        if (!empty($filters['reference_id'])) {
            $query->where('reference_id', $filters['reference_id']);
        }

        if (!empty($filters['idempotency_key'])) {
            $query->where('idempotency_key', $filters['idempotency_key']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * Find a reservation by numeric ID or idempotency key.
     */
    public function findByIdOrKey(int|string $idOrKey): ?StockReservation
    {
        $query = StockReservation::query()->with(['items.productVariant', 'items.warehouse']);

        if (is_int($idOrKey) || ctype_digit((string) $idOrKey)) {
            $byId = (clone $query)->whereKey((int) $idOrKey)->first();
            if ($byId !== null) {
                return $byId;
            }
        }

        return $query->where('idempotency_key', (string) $idOrKey)->first();
    }

    /**
     * Create an idempotent stock reservation using DB transactions and SELECT FOR UPDATE.
     */
    public function reserve(ReserveStockData $data): StockReservation
    {
        $idempotencyKey = trim($data->idempotencyKey);
        if ($idempotencyKey === '') {
            throw new InventoryException('An idempotency_key is required for stock reservation.');
        }

        if (empty($data->items)) {
            throw new InventoryException('At least one reservation item is required.');
        }

        // Normalize and consolidate items by (product_variant_id, warehouse_id)
        $normalized = [];
        foreach ($data->items as $rawItem) {
            $itemDto = $rawItem instanceof ReserveStockItemData
                ? $rawItem
                : new ReserveStockItemData(
                    productVariantId: (int) $rawItem['product_variant_id'],
                    quantity: (int) $rawItem['quantity'],
                    warehouseId: isset($rawItem['warehouse_id']) && $rawItem['warehouse_id'] !== null
                        ? (int) $rawItem['warehouse_id']
                        : null
                );

            if ($itemDto->quantity <= 0) {
                throw new InventoryException("Reservation quantity must be greater than zero for variant #{$itemDto->productVariantId}.");
            }

            $key = $itemDto->productVariantId . ':' . ($itemDto->warehouseId ?? 'auto');
            if (!isset($normalized[$key])) {
                $normalized[$key] = [
                    'product_variant_id' => $itemDto->productVariantId,
                    'warehouse_id'       => $itemDto->warehouseId,
                    'quantity'           => 0,
                ];
            }
            $normalized[$key]['quantity'] += $itemDto->quantity;
        }

        // Sort deterministically by product_variant_id ASC, warehouse_id ASC
        $requestedItems = array_values($normalized);
        usort($requestedItems, function (array $a, array $b) {
            if ($a['product_variant_id'] !== $b['product_variant_id']) {
                return $a['product_variant_id'] <=> $b['product_variant_id'];
            }
            return ($a['warehouse_id'] ?? 0) <=> ($b['warehouse_id'] ?? 0);
        });

        try {
            return DB::transaction(function () use ($data, $idempotencyKey, $requestedItems) {
                return InventoryWriteGuard::authorizeStockMutation(function () use ($data, $idempotencyKey, $requestedItems) {
                    // 1. Idempotency check under lock
                    $existing = StockReservation::query()
                        ->where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();

                    if ($existing !== null) {
                        return $existing->load(['items.productVariant', 'items.warehouse']);
                    }

                    // 2. Lock all candidate InventoryItem rows for the requested variants in deterministic ID order
                    $variantIds = array_values(array_unique(array_column($requestedItems, 'product_variant_id')));

                    $lockedItems = InventoryItem::query()
                        ->with('warehouse')
                        ->whereIn('product_variant_id', $variantIds)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    $itemsByVariant = $lockedItems->groupBy('product_variant_id');

                    // Track allocations per inventory_item_id in case both explicit and auto allocations touch the same row
                    $allocationsByItemId = [];
                    /** @var array<int, InventoryItem> $lockedItemsById */
                    $lockedItemsById = $lockedItems->keyBy('id')->all();

                    foreach ($requestedItems as $req) {
                        $variantId   = $req['product_variant_id'];
                        $warehouseId = $req['warehouse_id'];
                        $neededQty   = $req['quantity'];

                        if ($warehouseId !== null) {
                            $warehouse = Warehouse::query()->find($warehouseId);
                            if (!$warehouse || !$warehouse->is_active) {
                                throw new WarehouseInactiveException($warehouseId);
                            }

                            /** @var InventoryItem|null $candidate */
                            $candidate = ($itemsByVariant->get($variantId) ?? collect())
                                ->firstWhere('warehouse_id', $warehouseId);

                            $alreadyAllocated = $candidate ? ($allocationsByItemId[$candidate->id] ?? 0) : 0;
                            $available        = $candidate ? ($candidate->available_quantity - $alreadyAllocated) : 0;

                            if (!$candidate || $available < $neededQty) {
                                throw new InsufficientStockException(
                                    productVariantId: $variantId,
                                    requestedQuantity: $neededQty,
                                    availableQuantity: max(0, $available),
                                    warehouseId: $warehouseId
                                );
                            }

                            $allocationsByItemId[$candidate->id] = $alreadyAllocated + $neededQty;
                        } else {
                            // Automatic allocation across active warehouses ordered by is_default DESC, priority ASC, id ASC
                            $candidates = ($itemsByVariant->get($variantId) ?? collect())
                                ->filter(fn (InventoryItem $item) => $item->warehouse !== null && $item->warehouse->is_active)
                                ->sort(function (InventoryItem $a, InventoryItem $b) {
                                    $whA = $a->warehouse;
                                    $whB = $b->warehouse;
                                    if ($whA->is_default !== $whB->is_default) {
                                        return $whB->is_default <=> $whA->is_default;
                                    }
                                    if ($whA->priority !== $whB->priority) {
                                        return $whA->priority <=> $whB->priority;
                                    }
                                    return $whA->id <=> $whB->id;
                                });

                            $totalAvailable = $candidates->sum(function (InventoryItem $item) use ($allocationsByItemId) {
                                return max(0, $item->available_quantity - ($allocationsByItemId[$item->id] ?? 0));
                            });

                            if ($totalAvailable < $neededQty) {
                                throw new InsufficientStockException(
                                    productVariantId: $variantId,
                                    requestedQuantity: $neededQty,
                                    availableQuantity: max(0, (int) $totalAvailable),
                                    warehouseId: null
                                );
                            }

                            $remaining = $neededQty;
                            foreach ($candidates as $candidate) {
                                if ($remaining <= 0) {
                                    break;
                                }

                                $alreadyAllocated = $allocationsByItemId[$candidate->id] ?? 0;
                                $availInWh        = max(0, $candidate->available_quantity - $alreadyAllocated);

                                if ($availInWh <= 0) {
                                    continue;
                                }

                                $take = min($remaining, $availInWh);
                                $allocationsByItemId[$candidate->id] = $alreadyAllocated + $take;
                                $remaining -= $take;
                            }
                        }
                    }

                    // 3. Create the StockReservation header
                    $expiresAt = $data->expiresAt ?? now()->addMinutes(max(1, $data->ttlMinutes));

                    $reservation = StockReservation::create([
                        'idempotency_key' => $idempotencyKey,
                        'reference_type'  => $data->referenceType ?? 'checkout',
                        'reference_id'    => $data->referenceId,
                        'status'          => StockReservation::STATUS_ACTIVE,
                        'expires_at'      => $expiresAt,
                        'metadata'        => $data->metadata,
                    ]);

                    // 4. Apply reservations in deterministic inventory_item_id order and record InventoryMovements
                    ksort($allocationsByItemId);
                    $actorId = $data->performedBy ?? auth()->id();

                    foreach ($allocationsByItemId as $itemId => $allocatedQty) {
                        $item           = $lockedItemsById[$itemId];
                        $beforeOnHand   = (int) $item->quantity_on_hand;
                        $beforeReserved = (int) $item->quantity_reserved;
                        $afterReserved  = $beforeReserved + $allocatedQty;

                        $item->quantity_reserved = $afterReserved;
                        $item->save();

                        StockReservationItem::create([
                            'stock_reservation_id' => $reservation->id,
                            'inventory_item_id'    => $item->id,
                            'product_variant_id'   => $item->product_variant_id,
                            'warehouse_id'         => $item->warehouse_id,
                            'quantity'             => $allocatedQty,
                        ]);

                        InventoryMovement::create([
                            'inventory_item_id'  => $item->id,
                            'product_variant_id' => $item->product_variant_id,
                            'warehouse_id'       => $item->warehouse_id,
                            'type'               => InventoryMovement::TYPE_RESERVATION,
                            'quantity_change'    => -$allocatedQty,
                            'on_hand_delta'      => 0,
                            'reserved_delta'     => $allocatedQty,
                            'quantity_before'    => $beforeOnHand,
                            'quantity_after'     => $beforeOnHand,
                            'reserved_before'    => $beforeReserved,
                            'reserved_after'     => $afterReserved,
                            'reference_type'     => $reservation->reference_type ?? 'stock_reservation',
                            'reference_id'       => (string) ($reservation->reference_id ?? $reservation->id),
                            'reason'             => "Reserved for {$reservation->idempotency_key}",
                            'notes'              => null,
                            'performed_by'       => $actorId,
                        ]);

                        $this->lowStockMonitor->evaluate($item);
                    }

                    $reservation->load(['items.productVariant', 'items.warehouse']);

                    DB::afterCommit(function () use ($reservation) {
                        StockReserved::dispatch(
                            $reservation->id,
                            $reservation->idempotency_key,
                            $reservation->reference_type,
                            $reservation->reference_id,
                            $reservation->items->map(fn (StockReservationItem $i) => [
                                'inventory_item_id'  => $i->inventory_item_id,
                                'product_variant_id' => $i->product_variant_id,
                                'warehouse_id'       => $i->warehouse_id,
                                'quantity'           => $i->quantity,
                            ])->all(),
                            $reservation->expires_at->toIso8601String()
                        );
                    });

                    return $reservation;
                });
            });
        } catch (UniqueConstraintViolationException $e) {
            // If a concurrent request with the same idempotency_key won the insert race, return the existing reservation
            $existing = $this->findByIdOrKey($idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }
            throw $e;
        }
    }

    /**
     * Commit a reservation (idempotent): permanently deducts quantity_on_hand and quantity_reserved.
     */
    public function commit(int|string $reservationIdOrKey, ?int $performedBy = null): StockReservation
    {
        return DB::transaction(function () use ($reservationIdOrKey, $performedBy) {
            return InventoryWriteGuard::authorizeStockMutation(function () use ($reservationIdOrKey, $performedBy) {
                $reservation = $this->lockReservationByIdOrKey($reservationIdOrKey);

                // Idempotent return if already committed
                if ($reservation->isCommitted()) {
                    return $reservation->load(['items.productVariant', 'items.warehouse']);
                }

                if ($reservation->isReleased()) {
                    throw new InvalidReservationStateException(
                        "Cannot commit reservation #{$reservation->id} because it has already been released."
                    );
                }

                if ($reservation->isExpired() || $reservation->hasPastExpiry()) {
                    throw new ReservationExpiredException(
                        "Cannot commit reservation #{$reservation->id} because it has expired."
                    );
                }

                $reservation->load('items');
                $itemIds = $reservation->items->pluck('inventory_item_id')->unique()->sort()->values()->all();

                $lockedItems = InventoryItem::query()
                    ->whereIn('id', $itemIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $actorId = $performedBy ?? auth()->id();

                foreach ($reservation->items->sortBy('inventory_item_id') as $resItem) {
                    /** @var InventoryItem $item */
                    $item = $lockedItems->get($resItem->inventory_item_id);
                    $qty  = (int) $resItem->quantity;

                    $beforeOnHand   = (int) $item->quantity_on_hand;
                    $beforeReserved = (int) $item->quantity_reserved;

                    if ($beforeOnHand < $qty || $beforeReserved < $qty) {
                        throw new InsufficientStockException(
                            productVariantId: $item->product_variant_id,
                            requestedQuantity: $qty,
                            availableQuantity: max(0, $beforeOnHand),
                            warehouseId: $item->warehouse_id,
                            message: "Cannot commit reservation #{$reservation->id}: inconsistent reserved/on-hand stock on item #{$item->id}."
                        );
                    }

                    $afterOnHand   = $beforeOnHand - $qty;
                    $afterReserved = $beforeReserved - $qty;

                    $item->quantity_on_hand  = $afterOnHand;
                    $item->quantity_reserved = $afterReserved;
                    $item->save();

                    InventoryMovement::create([
                        'inventory_item_id'  => $item->id,
                        'product_variant_id' => $item->product_variant_id,
                        'warehouse_id'       => $item->warehouse_id,
                        'type'               => InventoryMovement::TYPE_RESERVATION_COMMIT,
                        'quantity_change'    => -$qty,
                        'on_hand_delta'      => -$qty,
                        'reserved_delta'     => -$qty,
                        'quantity_before'    => $beforeOnHand,
                        'quantity_after'     => $afterOnHand,
                        'reserved_before'    => $beforeReserved,
                        'reserved_after'     => $afterReserved,
                        'reference_type'     => $reservation->reference_type ?? 'stock_reservation',
                        'reference_id'       => (string) ($reservation->reference_id ?? $reservation->id),
                        'reason'             => "Committed reservation {$reservation->idempotency_key}",
                        'notes'              => null,
                        'performed_by'       => $actorId,
                    ]);

                    $this->lowStockMonitor->evaluate($item);
                }

                $reservation->update([
                    'status'       => StockReservation::STATUS_COMMITTED,
                    'committed_at' => now(),
                ]);

                DB::afterCommit(function () use ($reservation) {
                    StockReservationCommitted::dispatch(
                        $reservation->id,
                        $reservation->idempotency_key,
                        $reservation->reference_type,
                        $reservation->reference_id
                    );
                });

                return $reservation->fresh(['items.productVariant', 'items.warehouse']);
            });
        });
    }

    /**
     * Release an active reservation (idempotent): restores available stock by decrementing quantity_reserved.
     */
    public function release(
        int|string $reservationIdOrKey,
        ?string $reason = null,
        ?int $performedBy = null
    ): StockReservation {
        return DB::transaction(function () use ($reservationIdOrKey, $reason, $performedBy) {
            return InventoryWriteGuard::authorizeStockMutation(function () use ($reservationIdOrKey, $reason, $performedBy) {
                $reservation = $this->lockReservationByIdOrKey($reservationIdOrKey);

                // Idempotent return if already released or expired
                if ($reservation->isReleased() || $reservation->isExpired()) {
                    return $reservation->load(['items.productVariant', 'items.warehouse']);
                }

                if ($reservation->isCommitted()) {
                    throw new InvalidReservationStateException(
                        "Cannot release reservation #{$reservation->id} because it has already been committed."
                    );
                }

                $reservation->load('items');
                $itemIds = $reservation->items->pluck('inventory_item_id')->unique()->sort()->values()->all();

                $lockedItems = InventoryItem::query()
                    ->whereIn('id', $itemIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $actorId = $performedBy ?? auth()->id();

                foreach ($reservation->items->sortBy('inventory_item_id') as $resItem) {
                    /** @var InventoryItem $item */
                    $item = $lockedItems->get($resItem->inventory_item_id);
                    $qty  = (int) $resItem->quantity;

                    $beforeOnHand   = (int) $item->quantity_on_hand;
                    $beforeReserved = (int) $item->quantity_reserved;
                    $afterReserved  = max(0, $beforeReserved - $qty);
                    $releasedDelta  = $beforeReserved - $afterReserved;

                    $item->quantity_reserved = $afterReserved;
                    $item->save();

                    InventoryMovement::create([
                        'inventory_item_id'  => $item->id,
                        'product_variant_id' => $item->product_variant_id,
                        'warehouse_id'       => $item->warehouse_id,
                        'type'               => InventoryMovement::TYPE_RESERVATION_RELEASE,
                        'quantity_change'    => $releasedDelta,
                        'on_hand_delta'      => 0,
                        'reserved_delta'     => -$releasedDelta,
                        'quantity_before'    => $beforeOnHand,
                        'quantity_after'     => $beforeOnHand,
                        'reserved_before'    => $beforeReserved,
                        'reserved_after'     => $afterReserved,
                        'reference_type'     => $reservation->reference_type ?? 'stock_reservation',
                        'reference_id'       => (string) ($reservation->reference_id ?? $reservation->id),
                        'reason'             => $reason ?? "Released reservation {$reservation->idempotency_key}",
                        'notes'              => null,
                        'performed_by'       => $actorId,
                    ]);

                    $this->lowStockMonitor->evaluate($item);
                }

                $reservation->update([
                    'status'      => StockReservation::STATUS_RELEASED,
                    'released_at' => now(),
                ]);

                DB::afterCommit(function () use ($reservation, $reason) {
                    StockReservationReleased::dispatch(
                        $reservation->id,
                        $reservation->idempotency_key,
                        $reservation->reference_type,
                        $reservation->reference_id,
                        $reason
                    );
                });

                return $reservation->fresh(['items.productVariant', 'items.warehouse']);
            });
        });
    }

    /**
     * Expire all overdue active reservations and restore their reserved stock.
     */
    public function expireOverdue(): int
    {
        $overdueIds = StockReservation::query()
            ->overdue()
            ->orderBy('id')
            ->pluck('id');

        $expiredCount = 0;

        foreach ($overdueIds as $reservationId) {
            $wasExpired = DB::transaction(function () use ($reservationId) {
                return InventoryWriteGuard::authorizeStockMutation(function () use ($reservationId) {
                    $reservation = StockReservation::query()
                        ->whereKey($reservationId)
                        ->lockForUpdate()
                        ->first();

                    if (!$reservation || !$reservation->isActive() || !$reservation->hasPastExpiry()) {
                        return false;
                    }

                    $reservation->load('items');
                    $itemIds = $reservation->items->pluck('inventory_item_id')->unique()->sort()->values()->all();

                    $lockedItems = InventoryItem::query()
                        ->whereIn('id', $itemIds)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    foreach ($reservation->items->sortBy('inventory_item_id') as $resItem) {
                        /** @var InventoryItem|null $item */
                        $item = $lockedItems->get($resItem->inventory_item_id);
                        if (!$item) {
                            continue;
                        }

                        $qty            = (int) $resItem->quantity;
                        $beforeOnHand   = (int) $item->quantity_on_hand;
                        $beforeReserved = (int) $item->quantity_reserved;
                        $afterReserved  = max(0, $beforeReserved - $qty);
                        $expiredDelta   = $beforeReserved - $afterReserved;

                        $item->quantity_reserved = $afterReserved;
                        $item->save();

                        InventoryMovement::create([
                            'inventory_item_id'  => $item->id,
                            'product_variant_id' => $item->product_variant_id,
                            'warehouse_id'       => $item->warehouse_id,
                            'type'               => InventoryMovement::TYPE_RESERVATION_EXPIRED,
                            'quantity_change'    => $expiredDelta,
                            'on_hand_delta'      => 0,
                            'reserved_delta'     => -$expiredDelta,
                            'quantity_before'    => $beforeOnHand,
                            'quantity_after'     => $beforeOnHand,
                            'reserved_before'    => $beforeReserved,
                            'reserved_after'     => $afterReserved,
                            'reference_type'     => $reservation->reference_type ?? 'stock_reservation',
                            'reference_id'       => (string) ($reservation->reference_id ?? $reservation->id),
                            'reason'             => "Expired reservation {$reservation->idempotency_key}",
                            'notes'              => null,
                            'performed_by'       => null,
                        ]);

                        $this->lowStockMonitor->evaluate($item);
                    }

                    $reservation->update([
                        'status'     => StockReservation::STATUS_EXPIRED,
                        'expired_at' => now(),
                    ]);

                    DB::afterCommit(function () use ($reservation) {
                        StockReservationExpired::dispatch(
                            $reservation->id,
                            $reservation->idempotency_key,
                            $reservation->reference_type,
                            $reservation->reference_id
                        );
                    });

                    return true;
                });
            });

            if ($wasExpired) {
                $expiredCount++;
            }
        }

        return $expiredCount;
    }

    private function lockReservationByIdOrKey(int|string $idOrKey): StockReservation
    {
        if (is_int($idOrKey) || ctype_digit((string) $idOrKey)) {
            $byId = StockReservation::query()->whereKey((int) $idOrKey)->lockForUpdate()->first();
            if ($byId !== null) {
                return $byId;
            }
        }

        $byKey = StockReservation::query()
            ->where('idempotency_key', (string) $idOrKey)
            ->lockForUpdate()
            ->first();

        if ($byKey === null) {
            throw new InventoryException("Stock reservation '{$idOrKey}' not found.");
        }

        return $byKey;
    }
}

