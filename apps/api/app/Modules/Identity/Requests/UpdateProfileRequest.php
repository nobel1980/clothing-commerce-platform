<?php

namespace App\Modules\Identity\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'phone' => [
                'nullable',
                'string',
                'regex:/^(?:\+8801|01)[3-9]\d{8}$/',
                Rule::unique('users', 'phone')->ignore($this->user()?->id),
            ],
            'avatar_url' => ['nullable', 'string', 'url', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Please provide a valid Bangladesh mobile number (e.g. 017XXXXXXXX or +88017XXXXXXXX).',
        ];
    }
}
