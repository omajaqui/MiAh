<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingsCycleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isAdmin = $user?->isAdmin();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'start_date' => $this->start_date?->toDateString(),
            'contribution_weekday' => $this->contribution_weekday,
            'first_contribution_date' => $this->first_contribution_date?->toDateString(),
            'first_disbursement_date' => $this->first_disbursement_date?->toDateString(),
            'weekly_quota' => $this->weekly_quota,
            'currency' => $this->currency,
            'members_count' => $this->members_count,
            'turns_count' => $this->turns_count,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'observations' => $this->when($isAdmin, $this->observations),
            'created_by' => $this->when($isAdmin, $this->created_by),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'close_reason' => $this->when($isAdmin, $this->close_reason),
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'finalize_reason' => $this->when($isAdmin, $this->finalize_reason),
            'created_at' => $this->created_at?->toIso8601String(),
            'members' => SavingsCycleMemberResource::collection($this->whenLoaded('members')),
            'turns' => SavingsTurnResource::collection($this->whenLoaded('turns')),
        ];
    }
}
