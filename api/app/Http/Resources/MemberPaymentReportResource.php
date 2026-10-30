<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberPaymentReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'savings_cycle_id' => $this->savings_cycle_id,
            'person_id' => $this->person_id,
            'amount' => $this->amount,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'observations' => $this->observations,
            'has_receipt' => filled($this->receipt_path),
            'receipt_original_name' => $this->receipt_original_name,
            'created_at' => $this->created_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'person' => $this->whenLoaded('person', fn () => [
                'id' => $this->person->id,
                'full_name' => $this->person->full_name,
                'email' => $this->person->email,
            ]),
            'cycle' => $this->whenLoaded('cycle', fn () => [
                'id' => $this->cycle->id,
                'name' => $this->cycle->name,
                'currency' => $this->cycle->currency,
                'status' => $this->cycle->status?->value,
            ]),
            'reporter' => $this->whenLoaded('reporter', fn () => [
                'id' => $this->reporter->id,
                'full_name' => $this->reporter->person?->full_name ?? $this->reporter->email,
            ]),
            'reviewer' => $this->whenLoaded('reviewer', function () {
                if (! $this->reviewer) {
                    return null;
                }

                return [
                    'id' => $this->reviewer->id,
                    'full_name' => $this->reviewer->person?->full_name ?? $this->reviewer->email,
                ];
            }),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'savings_contribution_id' => $item->savings_contribution_id,
                'amount' => $item->amount,
                'turn_number' => $item->contribution?->turn?->turn_number,
                'expected_date' => $item->contribution?->expected_date?->toDateString(),
                'expected_amount' => $item->contribution?->expected_amount,
            ])->values()),
        ];
    }
}
