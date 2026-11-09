<?php

namespace App\Shared\Contracts\Inventory;

final readonly class InventoryMovementData
{
    public function __construct(
        public int $id,
        public int $inventoryItemId,
        public int $productVariantId,
        public int $warehouseId,
        public string $type,
        public int $quantityChange,
        public int $onHandDelta,
        public int $reservedDelta,
        public int $quantityBefore,
        public int $quantityAfter,
        public int $reservedBefore,
        public int $reservedAfter,
        public int $availableAfter,
        public ?string $referenceType,
        public ?string $referenceId,
        public ?string $reason,
        public ?string $notes,
        public ?int $performedBy,
        public string $createdAt,
    ) {}

    public function toArray(): array
    {
        return [
            'id'                 => $this->id,
            'inventory_item_id'  => $this->inventoryItemId,
            'product_variant_id' => $this->productVariantId,
            'warehouse_id'       => $this->warehouseId,
            'type'               => $this->type,
            'quantity_change'    => $this->quantityChange,
            'on_hand_delta'      => $this->onHandDelta,
            'reserved_delta'     => $this->reservedDelta,
            'quantity_before'    => $this->quantityBefore,
            'quantity_after'     => $this->quantityAfter,
            'reserved_before'    => $this->reservedBefore,
            'reserved_after'     => $this->reservedAfter,
            'available_after'    => $this->availableAfter,
            'reference_type'     => $this->referenceType,
            'reference_id'       => $this->referenceId,
            'reason'             => $this->reason,
            'notes'              => $this->notes,
            'performed_by'       => $this->performedBy,
            'created_at'         => $this->createdAt,
        ];
    }
}

