<?php

namespace App\Modules\Identity\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'division' => $this->division,
            'district' => $this->district,
            'upazila_thana' => $this->upazila_thana,
            'street_address' => $this->street_address,
            'postal_code' => $this->postal_code,
            'formatted_address' => $this->formatted_address,
            'type' => $this->type,
            'is_default' => (bool) $this->is_default,
            'delivery_instructions' => $this->delivery_instructions,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
