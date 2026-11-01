<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk product import — accepts a CSV/JSON file or an array payload.
 * This is the foundation layer; full import jobs are dispatched to the queue.
 */
class BulkImportProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('catalog.manage') || $this->user()?->isStaff();
    }

    public function rules(): array
    {
        return [
            'file'   => ['required_without:rows', 'file', 'mimes:csv,txt,json', 'max:20480'], // 20 MB
            'rows'   => ['required_without:file', 'array', 'min:1', 'max:500'],

            // When sending raw JSON rows:
            'rows.*.name'          => ['required_with:rows', 'string', 'max:191'],
            'rows.*.sku'           => ['required_with:rows', 'string', 'max:191'],
            'rows.*.price_amount'  => ['required_with:rows', 'integer', 'min:0'],
            'rows.*.currency'      => ['sometimes', 'string', 'size:3'],
            'rows.*.category_slug' => ['nullable', 'string', 'max:191'],
            'rows.*.brand_slug'    => ['nullable', 'string', 'max:191'],
            'rows.*.status'        => ['sometimes', 'in:draft,published'],
        ];
    }
}
