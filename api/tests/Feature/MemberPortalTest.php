<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_socio_consulta_su_panel_y_sus_ciclos(): void
    {
        $admin = $this->makeAdmin();
        [$cycle, $users] = $this->createCycleWithMembers($admin, 2);
        $socio = $users[0];

        $this->withHeaders($this->bearer($admin))
            ->postJson("/api/savings-cycles/{$cycle->id}/start")
            ->assertOk();

        $this->withHeaders($this->bearer($socio))
            ->getJson('/api/member/dashboard')
            ->assertOk()
            ->assertJsonPath('data.person.id', $socio->person_id);

        $this->withHeaders($this->bearer($socio))
            ->getJson('/api/member/cycles')
            ->assertOk()
            ->assertJsonPath('data.0.id', $cycle->id);

        $this->withHeaders($this->bearer($socio))
            ->getJson("/api/member/cycles/{$cycle->id}")
            ->assertOk()
            ->assertJsonPath('data.membership.delivery_position', 1);

        $this->withHeaders($this->bearer($socio))
            ->getJson('/api/member/current-turn')
            ->assertOk();
    }
}
