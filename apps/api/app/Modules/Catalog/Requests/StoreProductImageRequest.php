<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('catalog.manage') || $this->user()?->isStaff();
    }

    public function rules(): array
    {
        return [
            'image'      => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'], // 5 MB
            'alt_text'   => ['nullable', 'string', 'max:191'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
