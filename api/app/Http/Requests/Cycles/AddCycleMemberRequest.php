<?php

namespace App\Http\Requests\Cycles;

use Illuminate\Foundation\Http\FormRequest;

class AddCycleMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'person_id' => ['required_without:person_ids', 'integer', 'exists:people,id'],
            'person_ids' => ['required_without:person_id', 'array', 'min:1'],
            'person_ids.*' => ['integer', 'distinct', 'exists:people,id'],
            'delivery_position' => ['nullable', 'integer', 'min:1'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
