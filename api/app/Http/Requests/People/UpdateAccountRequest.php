<?php

namespace App\Http\Requests\People;

use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'role' => ['sometimes', Rule::enum(RoleName::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
