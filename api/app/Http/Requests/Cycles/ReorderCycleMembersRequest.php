<?php

namespace App\Http\Requests\Cycles;

use Illuminate\Foundation\Http\FormRequest;

class ReorderCycleMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'person_ids' => ['required', 'array', 'min:1'],
            'person_ids.*' => ['required', 'integer', 'distinct', 'exists:people,id'],
        ];
    }
}
