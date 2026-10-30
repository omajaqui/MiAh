<?php

namespace App\Http\Requests\Cycles;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'force' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'required_if:force,true', 'string', 'max:2000'],
        ];
    }
}
