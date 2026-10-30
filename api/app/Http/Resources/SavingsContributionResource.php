<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingsContributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isAdmin = $user?->isAdmin();
        $isSelf = $user?->person_id === $this->person_id;

        return [
            'id' => $this->id,
            'savings_cycle_id' => $this->savings_cycle_id,
            'savings_turn_id' => $this->savings_turn_id,
            'person_id' => $this->person_id,
            'expected_amount' => $this->expected_amount,
            'received_amount' => $this->received_amount,
            'expected_date' => $this->expected_date?->toDateString(),
            'received_at' => $this->received_at?->toIso8601String(),
            'status' => $this->status?->value,
            'observations' => $this->when($isAdmin || $isSelf, $this->observations),
            'person' => $this->whenLoaded('person', fn () => [
                'id' => $this->person->id,
                'first_name' => $this->person->first_name,
                'last_name' => $this->person->last_name,
                'full_name' => $this->person->full_name,
            ]),
            'payments' => $this->when($isAdmin, SavingsContributionPaymentResource::collection($this->whenLoaded('payments'))),
        ];
    }
}
