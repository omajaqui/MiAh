<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => RoleName::Socio,
            'display_name' => RoleName::Socio->label(),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'name' => RoleName::Admin,
            'display_name' => RoleName::Admin->label(),
        ]);
    }
}
