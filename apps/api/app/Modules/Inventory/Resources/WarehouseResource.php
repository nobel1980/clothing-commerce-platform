<?php

namespace App\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'code'                  => $this->code,
            'address_line_1'        => $this->address_line_1,
            'address_line_2'        => $this->address_line_2,
            'city'                  => $this->city,
            'state'                 => $this->state,
            'postal_code'           => $this->postal_code,
            'country'               => $this->country,
            'phone'                 => $this->phone,
            'email'                 => $this->email,
            'is_active'             => (bool) $this->is_active,
            'is_default'            => (bool) $this->is_default,
            'priority'              => (int) $this->priority,
            'inventory_items_count' => $this->whenCounted('inventoryItems'),
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }
}

