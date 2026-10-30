<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $person = Person::factory()->create();

        return [
            'person_id' => $person->id,
            'role_id' => Role::query()->where('name', 'socio')->value('id') ?? Role::factory(),
            'email' => $person->email,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(function () {
            $roleId = Role::query()->where('name', 'admin')->value('id');

            return ['role_id' => $roleId];
        });
    }

    public function socio(): static
    {
        return $this->state(function () {
            $roleId = Role::query()->where('name', 'socio')->value('id');

            return ['role_id' => $roleId];
        });
    }
}
