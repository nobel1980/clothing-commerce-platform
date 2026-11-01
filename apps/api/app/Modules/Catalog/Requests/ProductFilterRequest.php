<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    public function rules(): array
    {
        return [
            'search'      => ['sometimes', 'string', 'max:191'],
            'category'    => ['sometimes', 'string', 'max:191'],    // slug
            'brand'       => ['sometimes', 'string', 'max:191'],    // slug
            'featured'    => ['sometimes', 'boolean'],
            'min_price'   => ['sometimes', 'integer', 'min:0'],
            'max_price'   => ['sometimes', 'integer', 'min:0'],
            'color'       => ['sometimes', 'string', 'max:100'],
            'size'        => ['sometimes', 'string', 'max:50'],
            'sort'        => ['sometimes', 'in:newest,oldest,price_asc,price_desc,name_asc'],
            'per_page'    => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
