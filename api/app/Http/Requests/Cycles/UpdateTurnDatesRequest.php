<?php

namespace App\Http\Requests\Cycles;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTurnDatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'expected_contribution_date' => ['sometimes', 'date'],
            'expected_disbursement_date' => ['sometimes', 'date'],
        ];
    }
}
