<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'identification_type' => $this->identification_type?->value,
            'identification_type_label' => $this->identification_type?->label(),
            'identification_number' => $this->identification_number,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'bank_type_id' => $this->bank_type_id,
            'account_number' => $this->account_number,
            'bank_type' => $this->whenLoaded('bankType', function () {
                if (! $this->bankType) {
                    return null;
                }

                return [
                    'id' => $this->bankType->id,
                    'name' => $this->bankType->name,
                ];
            }),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', function () {
                if (! $this->user) {
                    return null;
                }

                return [
                    'id' => $this->user->id,
                    'email' => $this->user->email,
                    'is_active' => $this->user->is_active,
                    'role' => $this->user->role?->name->value,
                    'role_label' => $this->user->role?->display_name,
                ];
            }),
        ];
    }
}
