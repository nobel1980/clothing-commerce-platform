<?php

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'url'        => $this->getPublicUrl(),
            'alt_text'   => $this->alt_text,
            'sort_order' => $this->sort_order,
            'is_primary' => $this->is_primary,
        ];
    }
}
