<?php

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttributeValueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'value'      => $this->value,
            'slug'       => $this->slug,
            'color_hex'  => $this->color_hex,
            'sort_order' => $this->sort_order,
        ];
    }
}
