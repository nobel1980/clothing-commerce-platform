<?php

namespace App\Modules\Identity\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') || $this->user()?->isSuperAdmin();
    }

    public function rules(): array
    {
        $userId = $this->route('user') instanceof \App\Modules\Identity\Models\User
            ? $this->route('user')->id
            : $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => [
                'nullable',
                'string',
                'regex:/^(?:\+8801|01)[3-9]\d{8}$/',
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role' => ['sometimes', 'string', 'exists:roles,name'],
            'status' => ['sometimes', 'in:active,suspended,inactive'],
        ];
    }
}
