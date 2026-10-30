<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingsCycleMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isAdmin = $user?->isAdmin();
        $isSelf = $user?->person_id === $this->person_id;

        return [
            'id' => $this->id,
            'savings_cycle_id' => $this->savings_cycle_id,
            'person_id' => $this->person_id,
            'joined_at' => $this->joined_at?->toIso8601String(),
            'delivery_position' => $this->delivery_position,
            'status' => $this->status?->value,
            'weekly_quota' => $this->weekly_quota,
            'observations' => $this->when($isAdmin, $this->observations),
            'person' => $this->whenLoaded('person', function () use ($isAdmin, $isSelf) {
                if (! $this->person) {
                    return null;
                }

                $data = [
                    'id' => $this->person->id,
                    'first_name' => $this->person->first_name,
                    'last_name' => $this->person->last_name,
                    'full_name' => $this->person->full_name,
                ];

                if ($isAdmin || $isSelf) {
                    $data['identification_type'] = $this->person->identification_type?->value;
                    $data['identification_number'] = $this->person->identification_number;
                    $data['phone'] = $this->person->phone;
                    $data['email'] = $this->person->email;
                }

                return $data;
            }),
        ];
    }
}
