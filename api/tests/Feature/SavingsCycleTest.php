<?php

namespace Tests\Feature;

use App\Enums\CycleStatus;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavingsCycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_crear_un_ciclo(): void
    {
        $admin = $this->makeAdmin();

        $this->withHeaders($this->bearer($admin))->postJson('/api/savings-cycles', [
            'name' => 'Ciclo amigos',
            'description' => 'Ahorro semanal',
            'start_date' => '2026-01-05',
            'contribution_weekday' => 1,
            'first_contribution_date' => '2026-01-05',
            'first_disbursement_date' => '2026-01-05',
            'weekly_quota' => 50000,
            'currency' => 'COP',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'abierto')
            ->assertJsonPath('data.weekly_quota', '50000.00');
    }

    public function test_asocia_socios_sin_duplicar_y_ordena_turnos(): void
    {
        $admin = $this->makeAdmin();
        $headers = $this->bearer($admin);
        [$cycle, $users] = $this->createCycleWithMembers($admin, 3);

        $this->withHeaders($headers)
            ->postJson("/api/savings-cycles/{$cycle->id}/members", [
                'person_id' => $users[0]->person_id,
            ])->assertStatus(422);

        $this->withHeaders($headers)
            ->putJson("/api/savings-cycles/{$cycle->id}/members/order", [
                'person_ids' => [$users[2]->person_id, $users[0]->person_id, $users[1]->person_id],
            ])->assertOk();

        $cycle->refresh();
        $this->assertEquals([1, 2, 3], $cycle->members()->orderBy('delivery_position')->pluck('delivery_position')->all());
        $this->assertCount(3, $cycle->turns);
        $this->assertEquals(3, $cycle->turns()->pluck('turn_number')->unique()->count());
    }

    public function test_no_inicia_sin_socios_ni_cuota_valida(): void
    {
        $admin = $this->makeAdmin();
        $headers = $this->bearer($admin);

        $empty = $this->withHeaders($headers)->postJson('/api/savings-cycles', [
            'name' => 'Vacío',
            'start_date' => '2026-01-05',
            'contribution_weekday' => 1,
            'first_contribution_date' => '2026-01-05',
            'first_disbursement_date' => '2026-01-05',
            'weekly_quota' => 10000,
        ])->json('data');

        $this->withHeaders($headers)
            ->postJson("/api/savings-cycles/{$empty['id']}/start")
            ->assertStatus(422);

        [$cycle] = $this->createCycleWithMembers($admin, 2);
        $this->withHeaders($headers)
            ->postJson("/api/savings-cycles/{$cycle->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', CycleStatus::EnProceso->value);

        $this->withHeaders($headers)
            ->postJson("/api/savings-cycles/{$cycle->id}/members", [
                'person_id' => $this->makeSocio()->person_id,
            ])->assertForbidden();
    }

    public function test_calcula_fondo_bruto_y_fechas_semanales(): void
    {
        $admin = $this->makeAdmin();
        [$cycle] = $this->createCycleWithMembers($admin, 10, '50000.00');

        $preview = $this->withHeaders($this->bearer($admin))
            ->getJson("/api/savings-cycles/{$cycle->id}/calendar-preview")
            ->assertOk()
            ->json('data');

        $this->assertSame('500000.00', $preview['expected_gross_amount']);
        $this->assertSame(10, $preview['turns_count']);
        $this->assertSame(Money::multiply('50000', 10), $preview['turns'][0]['expected_gross_amount']);
        $this->assertSame(
            $cycle->first_contribution_date->copy()->addWeeks(9)->toDateString(),
            $preview['turns'][9]['expected_contribution_date']
        );
    }

    public function test_asocia_varios_socios_en_una_solicitud(): void
    {
        $admin = $this->makeAdmin();
        $headers = $this->bearer($admin);
        [$cycle] = $this->createCycleWithMembers($admin, 1);
        $newA = $this->makeSocio();
        $newB = $this->makeSocio();

        $this->withHeaders($headers)
            ->postJson("/api/savings-cycles/{$cycle->id}/members", [
                'person_ids' => [$newA->person_id, $newB->person_id],
            ])
            ->assertCreated();

        $cycle->refresh();
        $this->assertCount(3, $cycle->members);
        $this->assertCount(3, $cycle->turns);
    }

    public function test_retira_un_socio_de_un_ciclo_abierto_y_regenera_turnos(): void
    {
        $admin = $this->makeAdmin();
        [$cycle, $users] = $this->createCycleWithMembers($admin, 3);
        $headers = $this->bearer($admin);
        $member = $cycle->members()->where('person_id', $users[1]->person_id)->first();

        $this->assertCount(3, $cycle->turns);

        $this->withHeaders($headers)
            ->deleteJson("/api/savings-cycles/{$cycle->id}/members/{$member->id}")
            ->assertOk();

        $cycle->refresh();
        $this->assertCount(2, $cycle->members);
        $this->assertCount(2, $cycle->turns);
        $this->assertEquals([1, 2], $cycle->members()->orderBy('delivery_position')->pluck('delivery_position')->all());
        $this->assertFalse($cycle->members()->where('person_id', $users[1]->person_id)->exists());
    }

    public function test_socio_inactivo_no_puede_agregarse_a_ciclo(): void
    {
        $admin = $this->makeAdmin();
        [$cycle] = $this->createCycleWithMembers($admin, 1);
        $inactive = $this->makeSocio(['is_active' => false]);

        $this->withHeaders($this->bearer($admin))
            ->postJson("/api/savings-cycles/{$cycle->id}/members", [
                'person_id' => $inactive->person_id,
            ])->assertStatus(422);
    }
}
