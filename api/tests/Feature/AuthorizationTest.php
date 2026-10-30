<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_socio_no_puede_acceder_a_endpoints_administrativos(): void
    {
        $socio = $this->makeSocio();

        $this->withHeaders($this->bearer($socio))->getJson('/api/people')->assertForbidden();
        $this->withHeaders($this->bearer($socio))->postJson('/api/people', [])->assertForbidden();
        $this->withHeaders($this->bearer($socio))->postJson('/api/savings-cycles', [])->assertForbidden();
        $this->withHeaders($this->bearer($socio))->getJson('/api/admin/dashboard')->assertForbidden();
        $this->withHeaders($this->bearer($socio))->getJson('/api/payment-reports')->assertForbidden();
    }

    public function test_el_socio_no_puede_consultar_un_ciclo_ajeno(): void
    {
        $admin = $this->makeAdmin();
        [$cycle] = $this->createCycleWithMembers($admin, 2);
        $outsider = $this->makeSocio();

        $this->withHeaders($this->bearer($outsider))
            ->getJson("/api/savings-cycles/{$cycle->id}")
            ->assertForbidden();

        $this->withHeaders($this->bearer($outsider))
            ->getJson("/api/member/cycles/{$cycle->id}")
            ->assertForbidden();
    }

    public function test_el_admin_puede_consultar_el_dashboard(): void
    {
        $admin = $this->makeAdmin();

        $this->withHeaders($this->bearer($admin))
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
