<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key'              => ['required', 'string', 'max:191'],
            'ttl_minutes'                  => ['nullable', 'integer', 'min:1', 'max:10080'],
            'reference_type'               => ['nullable', 'string', 'max:100'],
            'reference_id'                 => ['nullable', 'string', 'max:191'],
            'metadata'                     => ['nullable', 'array'],
            'items'                        => ['required', 'array', 'min:1'],
            'items.*.product_variant_id'   => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity'             => ['required', 'integer', 'min:1'],
            'items.*.warehouse_id'         => ['nullable', 'integer', 'exists:warehouses,id'],
        ];
    }
}

