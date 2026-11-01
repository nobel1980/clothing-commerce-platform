<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('catalog.manage') || $this->user()?->isStaff();
    }

    public function rules(): array
    {
        return [
            'brand_id'         => ['nullable', 'integer', 'exists:brands,id'],
            'category_id'      => ['nullable', 'integer', 'exists:categories,id'],
            'name'             => ['required', 'string', 'max:191'],
            'slug'             => ['nullable', 'string', 'max:191', 'unique:products,slug', 'regex:/^[a-z0-9-]+$/'],
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
