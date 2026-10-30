<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingsTurnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'savings_cycle_id' => $this->savings_cycle_id,
            'turn_number' => $this->turn_number,
            'beneficiary_person_id' => $this->beneficiary_person_id,
            'expected_contribution_date' => $this->expected_contribution_date?->toDateString(),
            'expected_disbursement_date' => $this->expected_disbursement_date?->toDateString(),
            'weekly_quota' => $this->weekly_quota,
            'contributors_count' => $this->contributors_count,
            'expected_gross_amount' => $this->expected_gross_amount,
            'collected_amount' => $this->collected_amount,
            'status' => $this->status?->value,
            'actual_disbursement_date' => $this->actual_disbursement_date?->toDateString(),
            'observations' => $this->observations,
            'beneficiary' => $this->whenLoaded('beneficiary', fn () => [
                'id' => $this->beneficiary->id,
                'first_name' => $this->beneficiary->first_name,
                'last_name' => $this->beneficiary->last_name,
                'full_name' => $this->beneficiary->full_name,
            ]),
            'contributions' => SavingsContributionResource::collection($this->whenLoaded('contributions')),
            'disbursement' => new SavingsDisbursementResource($this->whenLoaded('disbursement')),
        ];
    }
}
