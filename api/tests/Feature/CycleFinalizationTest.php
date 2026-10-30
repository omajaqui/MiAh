<?php

namespace Tests\Feature;

use App\Enums\CycleStatus;
use App\Models\SavingsContribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CycleFinalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cierra_un_ciclo_y_conserva_el_historial(): void
    {
        $admin = $this->makeAdmin();
        [$cycle] = $this->createCycleWithMembers($admin, 2);
        $headers = $this->bearer($admin);

        $this->withHeaders($headers)->postJson("/api/savings-cycles/{$cycle->id}/start")->assertOk();
        $contribution = SavingsContribution::query()->where('savings_cycle_id', $cycle->id)->first();
        $this->withHeaders($headers)->postJson("/api/contributions/{$contribution->id}/payments", [
            'amount' => $contribution->expected_amount,
        ])->assertOk();

        $this->withHeaders($headers)->postJson("/api/savings-cycles/{$cycle->id}/close", [
            'reason' => 'Cierre anticipado de prueba',
        ])->assertOk()->assertJsonPath('data.status', CycleStatus::Cerrado->value);

        $this->assertDatabaseHas('savings_contributions', ['id' => $contribution->id]);
        $this->withHeaders($headers)
            ->postJson("/api/contributions/{$contribution->id}/payments", ['amount' => 1])
            ->assertForbidden();
    }

    public function test_no_finaliza_si_faltan_entregas_o_aportes(): void
    {
        $admin = $this->makeAdmin();
        [$cycle] = $this->createCycleWithMembers($admin, 2, '10000.00');
        $headers = $this->bearer($admin);

        $this->withHeaders($headers)->postJson("/api/savings-cycles/{$cycle->id}/start")->assertOk();

        $this->withHeaders($headers)->postJson("/api/savings-cycles/{$cycle->id}/finalize")
            ->assertStatus(422);

        $cycle->refresh();

        foreach ($cycle->turns as $turn) {
            foreach ($turn->contributions as $contribution) {
                $this->withHeaders($headers)->postJson("/api/contributions/{$contribution->id}/payments", [
                    'amount' => 10000,
                ])->assertOk();
            }

            $this->withHeaders($headers)->postJson("/api/turns/{$turn->id}/disbursement")->assertCreated();
        }

        $this->withHeaders($headers)->postJson("/api/savings-cycles/{$cycle->id}/finalize")
            ->assertOk()
            ->assertJsonPath('data.status', CycleStatus::Finalizado->value);

        $this->assertDatabaseHas('savings_cycles', [
            'id' => $cycle->id,
            'status' => CycleStatus::Finalizado->value,
        ]);
    }

    public function test_finalizacion_forzada_requiere_razon(): void
    {
        $admin = $this->makeAdmin();
        [$cycle] = $this->createCycleWithMembers($admin, 2, '10000.00');
        $headers = $this->bearer($admin);
        $this->withHeaders($headers)->postJson("/api/savings-cycles/{$cycle->id}/start")->assertOk();

        $cycle->refresh();
        foreach ($cycle->turns as $turn) {
            $this->withHeaders($headers)->postJson("/api/turns/{$turn->id}/disbursement", [
                'delivered_amount' => 0,
            ])->assertCreated();
        }

        $this->withHeaders($headers)->postJson("/api/savings-cycles/{$cycle->id}/finalize", [
            'force' => true,
        ])->assertStatus(422);

        $this->withHeaders($headers)->postJson("/api/savings-cycles/{$cycle->id}/finalize", [
            'force' => true,
            'reason' => 'Excepción administrativa de prueba',
        ])->assertOk()->assertJsonPath('data.status', 'finalizado');
    }
}
