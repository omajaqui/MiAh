<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'role' => $this->role?->name->value,
            'role_label' => $this->role?->display_name,
            'person' => new PersonResource($this->whenLoaded('person')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
