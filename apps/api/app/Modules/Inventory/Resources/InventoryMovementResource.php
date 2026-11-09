<?php

namespace App\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'inventory_item_id'  => $this->inventory_item_id,
            'product_variant_id' => $this->product_variant_id,
            'warehouse_id'       => $this->warehouse_id,
            'type'               => $this->type,
            'quantity_change'    => (int) $this->quantity_change,
            'on_hand_delta'      => (int) $this->on_hand_delta,
            'reserved_delta'     => (int) $this->reserved_delta,
            'quantity_before'    => (int) $this->quantity_before,
            'quantity_after'     => (int) $this->quantity_after,
            'reserved_before'    => (int) $this->reserved_before,
            'reserved_after'     => (int) $this->reserved_after,
            'available_before'   => $this->available_before,
            'available_after'    => $this->available_after,
            'reference_type'     => $this->reference_type,
            'reference_id'       => $this->reference_id,
            'reason'             => $this->reason,
            'notes'              => $this->notes,
            'performed_by'       => $this->performed_by,
            'actor'              => $this->whenLoaded('actor', fn () => $this->actor ? [
                'id'    => $this->actor->id,
                'name'  => $this->actor->name,
                'email' => $this->actor->email,
            ] : null),
            'variant'            => $this->whenLoaded('productVariant', fn () => $this->productVariant ? [
                'id'    => $this->productVariant->id,
                'sku'   => $this->productVariant->sku,
                'color' => $this->productVariant->color,
                'size'  => $this->productVariant->size,
            ] : null),
            'warehouse'          => $this->whenLoaded('warehouse', fn () => $this->warehouse ? [
                'id'   => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ] : null),
            'created_at'         => $this->created_at?->toIso8601String(),
        ];
    }
}

