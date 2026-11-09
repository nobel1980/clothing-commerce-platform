<?php

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'slug'             => $this->slug,
            'description'      => $this->description,
            'body'             => $this->body,
            'status'           => $this->status,
            'is_featured'      => $this->is_featured,
            'published_at'     => $this->published_at?->toIso8601String(),
            'meta_title'       => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords'    => $this->meta_keywords,
            'brand'            => new BrandResource($this->whenLoaded('brand')),
            'category'         => new CategoryResource($this->whenLoaded('category')),
            'images'           => ProductImageResource::collection($this->whenLoaded('images')),
            'variants'         => ProductVariantResource::collection(
                $this->relationLoaded('activeVariants')
                    ? $this->activeVariants
                    : $this->whenLoaded('variants')
            ),
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
