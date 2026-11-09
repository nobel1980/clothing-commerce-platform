<?php

namespace App\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockReservationItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'stock_reservation_id' => $this->stock_reservation_id,
            'inventory_item_id'    => $this->inventory_item_id,
            'product_variant_id'   => $this->product_variant_id,
            'warehouse_id'         => $this->warehouse_id,
            'quantity'             => (int) $this->quantity,
            'variant'              => $this->whenLoaded('productVariant', fn () => $this->productVariant ? [
                'id'    => $this->productVariant->id,
                'sku'   => $this->productVariant->sku,
                'color' => $this->productVariant->color,
                'size'  => $this->productVariant->size,
            ] : null),
            'warehouse'            => $this->whenLoaded('warehouse', fn () => $this->warehouse ? [
                'id'   => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ] : null),
        ];
    }
}

