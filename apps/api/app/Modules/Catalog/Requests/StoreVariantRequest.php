<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('catalog.manage') || $this->user()?->isStaff();
    }

    public function rules(): array
    {
        return [
            'sku'              => ['required', 'string', 'max:191', 'unique:product_variants,sku'],
            'color'            => ['nullable', 'string', 'max:100'],
            'size'             => ['nullable', 'string', 'max:50'],
            // Prices are integer sub-units (paisa/cents) — never floats
            'price_amount'     => ['required', 'integer', 'min:0'],
            'compare_at_price' => ['nullable', 'integer', 'min:0'],
            'cost_price'       => ['nullable', 'integer', 'min:0'],
            'currency'         => ['sometimes', 'string', 'size:3'],
            'status'           => ['sometimes', 'in:active,inactive,discontinued'],
            // Optional: directly attach attribute values on create
            'attribute_values' => ['sometimes', 'array'],
            'attribute_values.*.attribute_id'       => ['required_with:attribute_values', 'integer', 'exists:attributes,id'],
            'attribute_values.*.attribute_value_id' => ['required_with:attribute_values', 'integer', 'exists:attribute_values,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'price_amount.integer' => 'Price must be an integer (e.g. 150000 for BDT 1,500.00).',
            'sku.unique'           => 'This SKU is already taken by another variant.',
        ];
    }
}
