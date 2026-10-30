<?php

namespace Tests\Feature;

use App\Models\BankType;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_crear_y_editar_personas(): void
    {
        $admin = $this->makeAdmin();
        $bankTypeId = BankType::query()->where('name', 'Nequi')->value('id');

        $create = $this->withHeaders($this->bearer($admin))->postJson('/api/people', [
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'identification_type' => 'CC',
            'identification_number' => '1234567890',
            'phone' => '3001234567',
            'email' => 'ana@test.com',
            'address' => 'Calle 1',
            'bank_type_id' => $bankTypeId,
            'account_number' => '3001234567',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.email', 'ana@test.com')
            ->assertJsonPath('data.bank_type.name', 'Nequi')
            ->assertJsonPath('data.account_number', '3001234567');
        $id = $create->json('data.id');

        $bancolombiaId = BankType::query()->where('name', 'Bancolombia')->value('id');

        $this->withHeaders($this->bearer($admin))->putJson("/api/people/{$id}", [
            'first_name' => 'Ana María',
            'last_name' => 'Pérez',
            'identification_type' => 'CC',
            'identification_number' => '1234567890',
            'phone' => '3001234567',
            'email' => 'ana@test.com',
            'address' => 'Calle 2',
            'bank_type_id' => $bancolombiaId,
            'account_number' => '12345678901',
        ])->assertOk()
            ->assertJsonPath('data.first_name', 'Ana María')
            ->assertJsonPath('data.bank_type.name', 'Bancolombia')
            ->assertJsonPath('data.account_number', '12345678901');
    }

    public function test_admin_lista_tipos_de_banco(): void
    {
        $admin = $this->makeAdmin();

        $this->withHeaders($this->bearer($admin))
            ->getJson('/api/bank-types')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Bancolombia')
            ->assertJsonPath('data.1.name', 'Nequi')
            ->assertJsonPath('data.2.name', 'DaviPlata');
    }

    public function test_admin_puede_crear_cuenta_y_no_permite_duplicar_identificacion(): void
    {
        $admin = $this->makeAdmin();
        $person = Person::factory()->create();

        $this->withHeaders($this->bearer($admin))->postJson("/api/people/{$person->id}/account", [
            'role' => 'socio',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()->assertJsonPath('data.user.role', 'socio');

        $this->withHeaders($this->bearer($admin))->postJson('/api/people', [
            'first_name' => 'Otro',
            'last_name' => 'Socio',
            'identification_type' => 'CC',
            'identification_number' => $person->identification_number,
            'phone' => '3009999999',
            'email' => 'otro@test.com',
            'bank_type_id' => BankType::query()->value('id'),
            'account_number' => '3009999999',
        ])->assertStatus(422);
    }

    public function test_desactivar_persona_inhabilita_su_cuenta(): void
    {
        $admin = $this->makeAdmin();
        $socio = $this->makeSocio();

        $this->withHeaders($this->bearer($admin))
            ->patchJson("/api/people/{$socio->person_id}/status", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($socio->fresh()->is_active);
    }

    public function test_el_admin_no_puede_desactivar_ni_administrar_su_propia_cuenta(): void
    {
        $admin = $this->makeAdmin();

        $this->withHeaders($this->bearer($admin))
            ->patchJson("/api/people/{$admin->person_id}/status", ['is_active' => false])
            ->assertForbidden();

        $this->withHeaders($this->bearer($admin))
            ->patchJson("/api/people/{$admin->person_id}/account", ['is_active' => false])
            ->assertForbidden();

        $this->withHeaders($this->bearer($admin))
            ->postJson("/api/people/{$admin->person_id}/account/reset-password", [
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue($admin->person->fresh()->is_active);
    }
}
