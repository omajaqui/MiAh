<?php

namespace Tests\Feature;

use App\Enums\ContributionStatus;
use App\Enums\DisbursementStatus;
use App\Models\SavingsContribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributionDisbursementTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_aportes_parciales_y_previene_duplicar_la_obligacion(): void
    {
        $admin = $this->makeAdmin();
        [$cycle] = $this->createCycleWithMembers($admin, 2, '50000.00');
        $headers = $this->bearer($admin);

        $this->withHeaders($headers)->postJson("/api/savings-cycles/{$cycle->id}/start")->assertOk();

        $contribution = SavingsContribution::query()->where('savings_cycle_id', $cycle->id)->first();

        $this->withHeaders($headers)->postJson("/api/contributions/{$contribution->id}/payments", [
            'amount' => 20000,
        ])->assertOk()->assertJsonPath('data.status', ContributionStatus::Parcial->value);

        $this->withHeaders($headers)->postJson("/api/contributions/{$contribution->id}/payments", [
            'amount' => 30000,
        ])->assertOk()->assertJsonPath('data.status', ContributionStatus::Pagado->value);

        $this->withHeaders($headers)->postJson("/api/contributions/{$contribution->id}/payments", [
            'amount' => 1000,
        ])->assertStatus(422);

        $this->assertEquals(1, SavingsContribution::query()->where([
            'savings_turn_id' => $contribution->savings_turn_id,
            'person_id' => $contribution->person_id,
        ])->count());
    }

    public function test_registra_entrega_y_previene_duplicados(): void
    {
        $admin = $this->makeAdmin();
        [$cycle] = $this->createCycleWithMembers($admin, 2, '10000.00');
        $headers = $this->bearer($admin);

        $this->withHeaders($headers)->postJson("/api/savings-cycles/{$cycle->id}/start")->assertOk();
        $cycle->refresh();
        $turn = $cycle->turns()->orderBy('turn_number')->first();

        foreach ($turn->contributions as $contribution) {
            $this->withHeaders($headers)->postJson("/api/contributions/{$contribution->id}/payments", [
                'amount' => 10000,
            ])->assertOk();
        }

        $this->withHeaders($headers)->postJson("/api/turns/{$turn->id}/disbursement", [
            'delivered_amount' => 20000,
        ])->assertCreated()
            ->assertJsonPath('data.status', DisbursementStatus::Completado->value)
            ->assertJsonPath('data.expected_amount', '20000.00')
            ->assertJsonPath('data.collected_amount', '20000.00');

        $this->withHeaders($headers)->postJson("/api/turns/{$turn->id}/disbursement", [
            'delivered_amount' => 20000,
        ])->assertStatus(422);
    }

    public function test_anulacion_de_pago_conserva_historial(): void
    {
        $admin = $this->makeAdmin();
        [$cycle] = $this->createCycleWithMembers($admin, 2, '10000.00');
        $headers = $this->bearer($admin);
        $this->withHeaders($headers)->postJson("/api/savings-cycles/{$cycle->id}/start")->assertOk();

        $contribution = SavingsContribution::query()->where('savings_cycle_id', $cycle->id)->first();
        $this->withHeaders($headers)->postJson("/api/contributions/{$contribution->id}/payments", [
            'amount' => 10000,
        ])->assertOk();

        $paymentId = $contribution->fresh()->payments()->first()->id;

        $this->withHeaders($headers)->postJson("/api/payments/{$paymentId}/annul", [
            'reason' => 'Registro erróneo',
        ])->assertOk()->assertJsonPath('data.status', ContributionStatus::Pendiente->value);

        $this->assertDatabaseHas('savings_contribution_payments', [
            'id' => $paymentId,
            'status' => 'anulado',
        ]);
    }
}
