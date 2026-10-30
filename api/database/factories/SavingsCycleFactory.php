<?php

namespace Database\Factories;

use App\Enums\CycleStatus;
use App\Models\SavingsCycle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsCycle>
 */
class SavingsCycleFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->startOfWeek();

        return [
            'name' => 'Ciclo '.fake()->unique()->numerify('###'),
            'description' => fake()->optional()->sentence(),
            'start_date' => $start->toDateString(),
            'contribution_weekday' => 1,
            'first_contribution_date' => $start->toDateString(),
            'first_disbursement_date' => $start->toDateString(),
            'weekly_quota' => '50000.00',
            'currency' => 'COP',
            'members_count' => 0,
            'turns_count' => 0,
            'status' => CycleStatus::Abierto,
            'observations' => null,
            'created_by' => User::factory()->admin(),
        ];
    }
}
