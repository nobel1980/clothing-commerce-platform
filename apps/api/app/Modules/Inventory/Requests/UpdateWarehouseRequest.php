<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $warehouseId = $this->route('warehouse')?->id ?? $this->route('warehouse');

        return [
            'name'           => ['sometimes', 'string', 'max:191'],
            'code'           => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('warehouses', 'code')->ignore($warehouseId),
            ],
            'address_line_1' => ['nullable', 'string', 'max:191'],
            'address_line_2' => ['nullable', 'string', 'max:191'],
            'city'           => ['nullable', 'string', 'max:100'],
            'state'          => ['nullable', 'string', 'max:100'],
            'postal_code'    => ['nullable', 'string', 'max:30'],
            'country'        => ['nullable', 'string', 'size:2'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'email'          => ['nullable', 'email', 'max:191'],
            'is_active'      => ['nullable', 'boolean'],
            'is_default'     => ['nullable', 'boolean'],
            'priority'       => ['nullable', 'integer', 'min:0'],
        ];
    }
}

