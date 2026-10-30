<?php

namespace App\Modules\Identity\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
{
    public const BD_DIVISIONS = [
        'Dhaka',
        'Chattogram',
        'Rajshahi',
        'Khulna',
        'Barishal',
        'Sylhet',
        'Rangpur',
        'Mymensingh',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^(?:\+8801|01)[3-9]\d{8}$/'],
            'division' => ['required', 'string', 'in:' . implode(',', self::BD_DIVISIONS)],
            'district' => ['required', 'string', 'max:100'],
            'upazila_thana' => ['required', 'string', 'max:100'],
            'street_address' => ['required', 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{4}$/'],
            'type' => ['nullable', 'in:shipping,billing,both'],
            'is_default' => ['sometimes', 'boolean'],
            'delivery_instructions' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Please provide a valid Bangladesh mobile number (e.g. 017XXXXXXXX).',
            'division.in' => 'Division must be one of: ' . implode(', ', self::BD_DIVISIONS),
            'postal_code.regex' => 'Bangladesh postal codes must be 4 digits (e.g. 1205).',
        ];
    }
}
