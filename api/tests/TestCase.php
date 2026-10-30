<?php

namespace Tests;

use App\Enums\RoleName;
use App\Models\Person;
use App\Models\Role;
use App\Models\SavingsCycle;
use App\Models\User;
use App\Services\CycleMemberService;
use Database\Seeders\BankTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;

abstract class TestCase extends BaseTestCase
{
    protected function seedRoles(): void
    {
        $this->seed([RoleSeeder::class, BankTypeSeeder::class]);
    }

    protected function makeUser(RoleName $role = RoleName::Socio, array $personAttrs = [], array $userAttrs = []): User
    {
        $this->seedRoles();

        $person = Person::factory()->create($personAttrs);
        $roleId = Role::query()->where('name', $role)->value('id');

        return User::factory()->create(array_merge([
            'person_id' => $person->id,
            'role_id' => $roleId,
            'email' => $person->email,
        ], $userAttrs));
    }

    protected function makeAdmin(array $personAttrs = [], array $userAttrs = []): User
    {
        return $this->makeUser(RoleName::Admin, $personAttrs, $userAttrs);
    }

    protected function makeSocio(array $personAttrs = [], array $userAttrs = []): User
    {
        return $this->makeUser(RoleName::Socio, $personAttrs, $userAttrs);
    }

    protected function bearer(User $user): array
    {
        $this->app['auth']->forgetGuards();
        $token = $user->createToken('test')->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }

    protected function createCycleWithMembers(User $admin, int $members = 2, string $quota = '50000.00'): array
    {
        $cycle = SavingsCycle::factory()->create([
            'created_by' => $admin->id,
            'weekly_quota' => $quota,
        ]);

        $service = app(CycleMemberService::class);
        $users = [];

        for ($i = 0; $i < $members; $i++) {
            $user = $this->makeSocio();
            $service->add($cycle, $user->person);
            $users[] = $user;
        }

        return [$cycle->fresh(['members', 'turns']), $users];
    }

    protected function fakeReceipt(string $name = 'comprobante.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('receipt_', true).'.png';
        file_put_contents($path, $png);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }
}
