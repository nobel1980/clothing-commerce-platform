<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('catalog.manage') || $this->user()?->isStaff();
    }

    public function rules(): array
    {
        $variantId = $this->route('variant')?->id ?? $this->route('id');

        return [
            'sku'              => ['sometimes', 'string', 'max:191', "unique:product_variants,sku,{$variantId}"],
            'color'            => ['nullable', 'string', 'max:100'],
            'size'             => ['nullable', 'string', 'max:50'],
            'price_amount'     => ['sometimes', 'integer', 'min:0'],
            'compare_at_price' => ['nullable', 'integer', 'min:0'],
            'cost_price'       => ['nullable', 'integer', 'min:0'],
            'currency'         => ['sometimes', 'string', 'size:3'],
            'status'           => ['sometimes', 'in:active,inactive,discontinued'],
            'attribute_values' => ['sometimes', 'array'],
            'attribute_values.*.attribute_id'       => ['required_with:attribute_values', 'integer', 'exists:attributes,id'],
            'attribute_values.*.attribute_value_id' => ['required_with:attribute_values', 'integer', 'exists:attribute_values,id'],
        ];
    }
}
