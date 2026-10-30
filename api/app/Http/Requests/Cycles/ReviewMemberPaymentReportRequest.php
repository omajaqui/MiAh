<?php

namespace App\Http\Requests\Cycles;

use App\Enums\MemberPaymentReportStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewMemberPaymentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                MemberPaymentReportStatus::PagoCorrecto->value,
                MemberPaymentReportStatus::PagoRechazado->value,
            ])],
            'observations' => ['required_if:status,'.MemberPaymentReportStatus::PagoRechazado->value, 'nullable', 'string', 'max:1000'],
        ];
    }
}
