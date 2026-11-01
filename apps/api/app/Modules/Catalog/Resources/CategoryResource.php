<?php

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'parent_id'        => $this->parent_id,
            'name'             => $this->name,
            'slug'             => $this->slug,
            'description'      => $this->description,
            'image_url'        => $this->image_url,
            'sort_order'       => $this->sort_order,
            'is_active'        => $this->is_active,
            'meta_title'       => $this->meta_title,
            'meta_description' => $this->meta_description,
            'parent'           => new CategoryResource($this->whenLoaded('parent')),
            'children'         => CategoryResource::collection($this->whenLoaded('children')),
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
