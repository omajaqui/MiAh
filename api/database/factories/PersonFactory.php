<?php

namespace Database\Factories;

use App\Enums\IdentificationType;
use App\Models\BankType;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'identification_type' => IdentificationType::Cc,
            'identification_number' => (string) fake()->unique()->numerify('##########'),
            'phone' => fake()->numerify('3#########'),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->optional()->streetAddress(),
            'bank_type_id' => fn () => BankType::query()->inRandomOrder()->value('id')
                ?? BankType::query()->firstOrCreate(['name' => 'Bancolombia'])->id,
            'account_number' => fake()->numerify('##########'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
