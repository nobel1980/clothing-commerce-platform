<?php

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'sku'              => $this->sku,
            'color'            => $this->color,
            'size'             => $this->size,
            // Prices returned in major units for frontend convenience;
            // raw integer sub-units also exposed for precision
            'price'            => [
                'amount'   => $this->price_amount,          // integer paisa/cents
                'currency' => $this->currency,
                'display'  => $this->getPriceInMajorUnits(), // float taka/dollar
            ],
            'compare_at_price' => $this->compare_at_price
                ? [
                    'amount'   => $this->compare_at_price,
                    'currency' => $this->currency,
                    'display'  => $this->compare_at_price / 100,
                ]
                : null,
            'status'           => $this->status,
            'attribute_values' => AttributeValueResource::collection($this->whenLoaded('attributeValues')),
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
