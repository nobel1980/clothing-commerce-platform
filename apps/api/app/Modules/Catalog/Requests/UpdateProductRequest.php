<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('catalog.manage') || $this->user()?->isStaff();
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id ?? $this->route('id');

        return [
            'brand_id'         => ['nullable', 'integer', 'exists:brands,id'],
            'category_id'      => ['nullable', 'integer', 'exists:categories,id'],
            'name'             => ['sometimes', 'string', 'max:191'],
            'slug'             => ['sometimes', 'string', 'max:191', "unique:products,slug,{$productId}", 'regex:/^[a-z0-9-]+$/'],
            'description'      => ['nullable', 'string'],
            'body'             => ['nullable', 'string'],
            'status'           => ['sometimes', 'in:draft,published,archived'],
            'is_featured'      => ['sometimes', 'boolean'],
            'published_at'     => ['nullable', 'date'],
            'meta_title'       => ['nullable', 'string', 'max:191'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'meta_keywords'    => ['nullable', 'string', 'max:500'],
        ];
    }
}
