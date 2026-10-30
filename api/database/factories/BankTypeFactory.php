<?php

namespace Database\Factories;

use App\Models\BankType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankType>
 */
class BankTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
        ];
    }
}
