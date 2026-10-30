<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingsContributionPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'status' => $this->status?->value,
            'observations' => $this->observations,
            'annul_reason' => $this->annul_reason,
            'annulled_at' => $this->annulled_at?->toIso8601String(),
            'registered_by' => $this->registered_by,
        ];
    }
}
