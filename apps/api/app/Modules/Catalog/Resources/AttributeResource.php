<?php

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttributeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'slug'       => $this->slug,
            'type'       => $this->type,
            'sort_order' => $this->sort_order,
            'is_active'  => $this->is_active,
            'values'     => AttributeValueResource::collection($this->whenLoaded('values')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
