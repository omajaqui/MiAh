<?php

namespace Database\Seeders;

use App\Enums\IdentificationType;
use App\Enums\RoleName;
use App\Models\BankType;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::query()->where('name', RoleName::Admin)->firstOrFail();
        $bankType = BankType::query()->where('name', 'Bancolombia')->first();

        $person = Person::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@ahorro-semanal.test')],
            [
                'first_name' => env('ADMIN_NAME', 'Administrador'),
                'last_name' => env('ADMIN_LAST_NAME', 'Sistema'),
                'identification_type' => IdentificationType::Cc,
                'identification_number' => env('ADMIN_IDENTIFICATION', '1000000000'),
                'phone' => env('ADMIN_PHONE', '3000000000'),
                'address' => 'Oficina principal',
                'bank_type_id' => $bankType?->id,
                'account_number' => env('ADMIN_ACCOUNT_NUMBER', '0000000000'),
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => $person->email],
            [
                'person_id' => $person->id,
                'role_id' => $role->id,
                'password' => env('ADMIN_PASSWORD', 'password'),
                'is_active' => true,
            ]
        );
    }
}
