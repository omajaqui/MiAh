<?php

namespace App\Http\Requests\Cycles;

use Illuminate\Foundation\Http\FormRequest;

class StoreSavingsCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'contribution_weekday' => ['required', 'integer', 'between:1,7'],
            'first_contribution_date' => ['required', 'date'],
            'first_disbursement_date' => ['required', 'date'],
            'weekly_quota' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
