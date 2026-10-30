<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_correcto_devuelve_token(): void
    {
        $user = $this->makeAdmin(['email' => 'admin@test.com'], ['email' => 'admin@test.com']);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_login_incorrecto_es_rechazado(): void
    {
        $this->makeAdmin(['email' => 'admin@test.com'], ['email' => 'admin@test.com']);

        $this->postJson('/api/login', [
            'email' => 'admin@test.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_me_y_logout_requieren_autenticacion(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
    }

    public function test_usuario_autenticado_puede_consultar_me_y_cerrar_sesion(): void
    {
        $user = $this->makeAdmin();

        $this->withHeaders($this->bearer($user))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->withHeaders($this->bearer($user))
            ->postJson('/api/logout')
            ->assertOk();
    }

    public function test_cuenta_inactiva_no_puede_iniciar_sesion(): void
    {
        $user = $this->makeSocio(['email' => 'socio@test.com'], [
            'email' => 'socio@test.com',
            'is_active' => false,
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertForbidden();
    }
}
