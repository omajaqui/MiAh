<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingsDisbursementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'savings_cycle_id' => $this->savings_cycle_id,
            'savings_turn_id' => $this->savings_turn_id,
            'beneficiary_person_id' => $this->beneficiary_person_id,
            'expected_amount' => $this->expected_amount,
            'collected_amount' => $this->collected_amount,
            'delivered_amount' => $this->delivered_amount,
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'status' => $this->status?->value,
            'observations' => $this->observations,
            'annul_reason' => $this->annul_reason,
            'registered_by' => $this->registered_by,
        ];
    }
}
