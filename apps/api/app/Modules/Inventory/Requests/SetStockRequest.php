<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_variant_id'  => ['required', 'integer', 'exists:product_variants,id'],
            'warehouse_id'        => ['required', 'integer', 'exists:warehouses,id'],
            'quantity_on_hand'    => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'bin_location'        => ['nullable', 'string', 'max:100'],
            'reason'              => ['nullable', 'string', 'max:191'],
            'notes'               => ['nullable', 'string', 'max:2000'],
        ];
    }
}

