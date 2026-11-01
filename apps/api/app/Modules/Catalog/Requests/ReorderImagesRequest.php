<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('catalog.manage') || $this->user()?->isStaff();
    }

    public function rules(): array
    {
        return [
            'order'   => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'integer', 'exists:product_images,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'order.required' => 'An ordered array of image IDs is required.',
            'order.*.exists' => 'One or more image IDs are invalid.',
        ];
    }
}
