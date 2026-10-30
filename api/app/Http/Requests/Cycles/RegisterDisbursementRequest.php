<?php

namespace App\Http\Requests\Cycles;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDisbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'delivered_amount' => ['nullable', 'numeric', 'gte:0', 'decimal:0,2'],
            'delivered_at' => ['nullable', 'date'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
