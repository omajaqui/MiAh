<?php

namespace App\Http\Requests\People;

use App\Enums\IdentificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $personId = $this->route('person')?->id ?? $this->route('id');

        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'identification_type' => ['required', Rule::enum(IdentificationType::class)],
            'identification_number' => ['required', 'string', 'max:50', Rule::unique('people', 'identification_number')->ignore($personId)],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255', Rule::unique('people', 'email')->ignore($personId)],
            'address' => ['nullable', 'string', 'max:255'],
            'bank_type_id' => ['required', 'integer', 'exists:bank_types,id'],
            'account_number' => ['required', 'string', 'max:40'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('account_number')) {
            $this->merge([
                'account_number' => preg_replace('/\s+/', '', (string) $this->input('account_number')),
            ]);
        }
    }
}
