<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('catalog.manage') || $this->user()?->isStaff();
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id ?? $this->route('id');

        return [
            'parent_id'        => ['nullable', 'integer', 'exists:categories,id', "not_in:{$categoryId}"],
            'name'             => ['sometimes', 'string', 'max:191'],
            'slug'             => ['sometimes', 'string', 'max:191', "unique:categories,slug,{$categoryId}", 'regex:/^[a-z0-9-]+$/'],
            'description'      => ['nullable', 'string'],
            'image_url'        => ['nullable', 'url', 'max:500'],
            'sort_order'       => ['sometimes', 'integer', 'min:0'],
            'is_active'        => ['sometimes', 'boolean'],
            'meta_title'       => ['nullable', 'string', 'max:191'],
            'meta_description' => ['nullable', 'string', 'max:320'],
        ];
    }
}
