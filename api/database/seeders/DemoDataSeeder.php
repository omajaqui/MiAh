<?php

namespace Database\Seeders;

use App\Enums\IdentificationType;
use App\Enums\RoleName;
use App\Models\BankType;
use App\Models\Person;
use App\Models\Role;
use App\Models\SavingsCycle;
use App\Models\User;
use App\Services\CycleMemberService;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $socioRole = Role::query()->where('name', RoleName::Socio)->firstOrFail();
        $admin = User::query()->where('email', env('ADMIN_EMAIL', 'admin@ahorro-semanal.test'))->first();
        $bankTypes = BankType::query()->orderBy('id')->get();

        $members = collect();

        foreach (range(1, 5) as $index) {
            $bankType = $bankTypes->isNotEmpty()
                ? $bankTypes[($index - 1) % $bankTypes->count()]
                : null;

            $person = Person::query()->updateOrCreate(
                ['email' => "socio{$index}@ahorro-semanal.test"],
                [
                    'first_name' => "Socio{$index}",
                    'last_name' => 'Demo',
                    'identification_type' => IdentificationType::Cc,
                    'identification_number' => (string) (2000000000 + $index),
                    'phone' => '300000000'.$index,
                    'address' => 'Calle '.$index,
                    'bank_type_id' => $bankType?->id,
                    'account_number' => '300000000'.$index,
                    'is_active' => true,
                ]
            );

            User::query()->updateOrCreate(
                ['email' => $person->email],
                [
                    'person_id' => $person->id,
                    'role_id' => $socioRole->id,
                    'password' => 'password',
                    'is_active' => true,
                ]
            );

            $members->push($person);
        }

        if (! $admin) {
            return;
        }

        $cycle = SavingsCycle::query()->firstOrCreate(
            ['name' => 'Ciclo demo 2026'],
            [
                'description' => 'Ciclo de ejemplo para desarrollo local.',
                'start_date' => now()->startOfWeek()->toDateString(),
                'contribution_weekday' => 1,
                'first_contribution_date' => now()->startOfWeek()->toDateString(),
                'first_disbursement_date' => now()->startOfWeek()->toDateString(),
                'weekly_quota' => '50000.00',
                'currency' => 'COP',
                'status' => 'abierto',
                'created_by' => $admin->id,
            ]
        );

        $service = app(CycleMemberService::class);

        foreach ($members as $person) {
            if (! $cycle->members()->where('person_id', $person->id)->exists() && $cycle->isOpen()) {
                $service->add($cycle, $person);
            }
        }
    }
}
