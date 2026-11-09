<?php

namespace App\Modules\Inventory\Requests;

use App\Modules\Inventory\Models\InventoryMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'warehouse_id'       => ['required', 'integer', 'exists:warehouses,id'],
            'quantity_delta'     => ['required', 'integer', 'not_in:0'],
            'type'               => ['nullable', 'string', Rule::in(InventoryMovement::ALLOWED_TYPES)],
            'reason'             => ['nullable', 'string', 'max:191'],
            'notes'              => ['nullable', 'string', 'max:2000'],
            'reference_type'     => ['nullable', 'string', 'max:100'],
            'reference_id'       => ['nullable', 'string', 'max:191'],
        ];
    }
}

