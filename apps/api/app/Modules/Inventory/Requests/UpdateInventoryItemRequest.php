<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'reorder_point'       => ['nullable', 'integer', 'min:0'],
            'reorder_quantity'    => ['nullable', 'integer', 'min:1'],
            'bin_location'        => ['nullable', 'string', 'max:100'],
        ];
    }
}

