<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_variant_id'       => ['required', 'integer', 'exists:product_variants,id'],
            'source_warehouse_id'      => ['required', 'integer', 'exists:warehouses,id'],
            'destination_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:source_warehouse_id'],
            'quantity'                 => ['required', 'integer', 'min:1'],
            'reason'                   => ['nullable', 'string', 'max:191'],
            'notes'                    => ['nullable', 'string', 'max:2000'],
        ];
    }
}

