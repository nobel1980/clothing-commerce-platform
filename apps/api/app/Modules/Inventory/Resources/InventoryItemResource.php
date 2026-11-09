<?php

namespace App\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'product_variant_id'    => $this->product_variant_id,
            'warehouse_id'          => $this->warehouse_id,
            'quantity_on_hand'      => (int) $this->quantity_on_hand,
            'quantity_reserved'     => (int) $this->quantity_reserved,
            'available_quantity'    => $this->available_quantity,
            'low_stock_threshold'   => (int) $this->low_stock_threshold,
            'reorder_point'         => $this->reorder_point !== null ? (int) $this->reorder_point : null,
            'reorder_quantity'      => $this->reorder_quantity !== null ? (int) $this->reorder_quantity : null,
            'bin_location'          => $this->bin_location,
            'is_low_stock'          => $this->isLowStock(),
            'is_out_of_stock'       => $this->isOutOfStock(),
            'low_stock_notified_at' => $this->low_stock_notified_at?->toIso8601String(),
            'variant'               => $this->whenLoaded('productVariant', function () {
                return [
                    'id'           => $this->productVariant->id,
                    'sku'          => $this->productVariant->sku,
                    'color'        => $this->productVariant->color,
                    'size'         => $this->productVariant->size,
                    'price_amount' => $this->productVariant->price_amount,
                    'cost_price'   => $this->productVariant->cost_price,
                    'currency'     => $this->productVariant->currency,
                    'product_name' => $this->productVariant->relationLoaded('product')
                        ? $this->productVariant->product?->name
                        : null,
                ];
            }),
            'warehouse'             => new WarehouseResource($this->whenLoaded('warehouse')),
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }
}

