<?php

namespace Tests\Feature;

use App\Enums\ContributionStatus;
use App\Enums\MemberPaymentReportStatus;
use App\Models\MemberPaymentReport;
use App\Models\SavingsContribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemberPaymentReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_socio_reporta_cuotas_y_el_admin_las_aprueba(): void
    {
        Storage::fake('local');

        $admin = $this->makeAdmin();
        [$cycle, $users] = $this->createCycleWithMembers($admin, 2, '10000.00');
        $socio = $users[0];

        $this->withHeaders($this->bearer($admin))
            ->postJson("/api/savings-cycles/{$cycle->id}/start")
            ->assertOk();

        $contributions = SavingsContribution::query()
            ->where('savings_cycle_id', $cycle->id)
            ->where('person_id', $socio->person_id)
            ->orderBy('id')
            ->take(2)
            ->get();

        $this->assertCount(2, $contributions);

        $this->withHeaders($this->bearer($socio))
            ->post("/api/member/cycles/{$cycle->id}/payment-reports", [
                'contribution_ids' => $contributions->pluck('id')->all(),
                'receipt' => $this->fakeReceipt('comprobante.png'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.status', MemberPaymentReportStatus::PendienteValidar->value);

        foreach ($contributions as $contribution) {
            $this->assertSame(ContributionStatus::Pendiente, $contribution->fresh()->status);
            $this->assertSame('0.00', $contribution->fresh()->received_amount);
        }

        $report = MemberPaymentReport::query()->first();
        $this->assertNotNull($report);
        Storage::disk('local')->assertExists($report->receipt_path);

        $this->withHeaders($this->bearer($socio))
            ->get("/api/payment-reports/{$report->id}/receipt")
            ->assertOk();

        $this->withHeaders($this->bearer($admin))
            ->getJson('/api/payment-reports')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $report->id);

        $this->withHeaders($this->bearer($admin))
            ->postJson("/api/payment-reports/{$report->id}/review", [
                'status' => MemberPaymentReportStatus::PagoCorrecto->value,
                'observations' => 'Comprobante válido',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', MemberPaymentReportStatus::PagoCorrecto->value);

        foreach ($contributions as $contribution) {
            $fresh = $contribution->fresh();
            $this->assertSame(ContributionStatus::Pagado, $fresh->status);
            $this->assertSame('10000.00', $fresh->received_amount);
        }
    }

    public function test_el_admin_puede_rechazar_un_pago_reportado(): void
    {
        Storage::fake('local');

        $admin = $this->makeAdmin();
        [$cycle, $users] = $this->createCycleWithMembers($admin, 2, '10000.00');
        $socio = $users[0];

        $this->withHeaders($this->bearer($admin))
            ->postJson("/api/savings-cycles/{$cycle->id}/start")
            ->assertOk();

        $contribution = SavingsContribution::query()
            ->where('savings_cycle_id', $cycle->id)
            ->where('person_id', $socio->person_id)
            ->first();

        $create = $this->withHeaders($this->bearer($socio))
            ->post("/api/member/cycles/{$cycle->id}/payment-reports", [
                'contribution_ids' => [$contribution->id],
                'receipt' => $this->fakeReceipt('soporte.png'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $reportId = $create->json('data.id');

        $this->withHeaders($this->bearer($admin))
            ->postJson("/api/payment-reports/{$reportId}/review", [
                'status' => MemberPaymentReportStatus::PagoRechazado->value,
            ])
            ->assertStatus(422);

        $this->withHeaders($this->bearer($admin))
            ->postJson("/api/payment-reports/{$reportId}/review", [
                'status' => MemberPaymentReportStatus::PagoRechazado->value,
                'observations' => 'La captura no coincide con el valor.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', MemberPaymentReportStatus::PagoRechazado->value);

        $this->assertSame(ContributionStatus::Pendiente, $contribution->fresh()->status);

        $this->withHeaders($this->bearer($socio))
            ->getJson("/api/member/cycles/{$cycle->id}")
            ->assertOk()
            ->assertJsonPath('data.payment_reports.0.status', MemberPaymentReportStatus::PagoRechazado->value)
            ->assertJsonPath('data.own_contributions.0.can_report', true);
    }

    public function test_el_socio_no_puede_volver_a_reportar_una_cuota_pendiente_de_validar(): void
    {
        Storage::fake('local');

        $admin = $this->makeAdmin();
        [$cycle, $users] = $this->createCycleWithMembers($admin, 2, '10000.00');
        $socio = $users[0];

        $this->withHeaders($this->bearer($admin))
            ->postJson("/api/savings-cycles/{$cycle->id}/start")
            ->assertOk();

        $contribution = SavingsContribution::query()
            ->where('savings_cycle_id', $cycle->id)
            ->where('person_id', $socio->person_id)
            ->first();

        $this->withHeaders($this->bearer($socio))
            ->post("/api/member/cycles/{$cycle->id}/payment-reports", [
                'contribution_ids' => [$contribution->id],
                'receipt' => $this->fakeReceipt('uno.png'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->withHeaders($this->bearer($socio))
            ->post("/api/member/cycles/{$cycle->id}/payment-reports", [
                'contribution_ids' => [$contribution->id],
                'receipt' => $this->fakeReceipt('dos.png'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_rechaza_un_comprobante_que_no_es_imagen_con_mensaje_claro(): void
    {
        Storage::fake('local');

        $admin = $this->makeAdmin();
        [$cycle, $users] = $this->createCycleWithMembers($admin, 2, '10000.00');
        $socio = $users[0];

        $this->withHeaders($this->bearer($admin))
            ->postJson("/api/savings-cycles/{$cycle->id}/start")
            ->assertOk();

        $contribution = SavingsContribution::query()
            ->where('savings_cycle_id', $cycle->id)
            ->where('person_id', $socio->person_id)
            ->first();

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('receipt_', true).'.txt';
        file_put_contents($path, 'esto no es una imagen');
        $invalid = new \Illuminate\Http\UploadedFile($path, 'notas.txt', 'text/plain', null, true);

        $this->withHeaders($this->bearer($socio))
            ->post("/api/member/cycles/{$cycle->id}/payment-reports", [
                'contribution_ids' => [$contribution->id],
                'receipt' => $invalid,
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Formato incorrecto. Solo se permiten imágenes JPG, PNG, WEBP o GIF.')
            ->assertJsonPath('errors.receipt.0', 'Formato incorrecto. Solo se permiten imágenes JPG, PNG, WEBP o GIF.');
    }
}
