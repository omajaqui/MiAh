<?php

namespace App\Services;

use App\Enums\ContributionStatus;
use App\Enums\MemberPaymentReportStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\MemberPaymentReport;
use App\Models\MemberPaymentReportItem;
use App\Models\SavingsContribution;
use App\Models\SavingsCycle;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MemberPaymentReportService
{
    public function __construct(
        private readonly ContributionService $contributions,
        private readonly AuditLogger $audit,
    ) {}

    public function submit(SavingsCycle $cycle, User $member, array $contributionIds, UploadedFile $receipt): MemberPaymentReport
    {
        if (! $cycle->isInProgress()) {
            throw new BusinessRuleException('Solo puede reportar pagos en un ciclo en proceso.');
        }

        if (! $member->person_id || ! $cycle->members()->where('person_id', $member->person_id)->exists()) {
            throw new BusinessRuleException('No participa en este ciclo.');
        }

        $contributionIds = array_values(array_unique(array_map('intval', $contributionIds)));

        if ($contributionIds === []) {
            throw new BusinessRuleException('Seleccione al menos una cuota.');
        }

        return DB::transaction(function () use ($cycle, $member, $contributionIds, $receipt) {
            $contributions = SavingsContribution::query()
                ->where('savings_cycle_id', $cycle->id)
                ->where('person_id', $member->person_id)
                ->whereIn('id', $contributionIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($contributions->count() !== count($contributionIds)) {
                throw new BusinessRuleException('Una o más cuotas no pertenecen a este ciclo o a su usuario.');
            }

            $items = [];
            $total = Money::of(0);

            foreach ($contributionIds as $contributionId) {
                $contribution = $contributions->get($contributionId);

                if (in_array($contribution->status, [ContributionStatus::Pagado, ContributionStatus::Anulado], true)) {
                    throw new BusinessRuleException('Hay cuotas que ya no admiten un reporte de pago.');
                }

                $remaining = Money::subtract($contribution->expected_amount, $contribution->received_amount);

                if (! Money::isPositive($remaining)) {
                    throw new BusinessRuleException('Hay cuotas sin saldo pendiente.');
                }

                $hasPending = MemberPaymentReportItem::query()
                    ->where('savings_contribution_id', $contribution->id)
                    ->whereHas('report', fn ($query) => $query->where('status', MemberPaymentReportStatus::PendienteValidar))
                    ->exists();

                if ($hasPending) {
                    throw new BusinessRuleException('Hay cuotas que ya tienen un pago pendiente de validar.');
                }

                $items[] = [
                    'contribution' => $contribution,
                    'amount' => $remaining,
                ];
                $total = Money::add($total, $remaining);
            }

            $report = MemberPaymentReport::query()->create([
                'savings_cycle_id' => $cycle->id,
                'person_id' => $member->person_id,
                'reported_by' => $member->id,
                'amount' => $total,
                'status' => MemberPaymentReportStatus::PendienteValidar,
            ]);

            $extension = $receipt->extension() ?: 'jpg';
            $path = $receipt->storeAs(
                "payment-receipts/{$cycle->id}",
                $report->id.'.'.$extension,
                'local'
            );

            if (! $path) {
                throw new BusinessRuleException('No se pudo guardar la captura de pantalla.');
            }

            $report->update([
                'receipt_path' => $path,
                'receipt_original_name' => $receipt->getClientOriginalName() ?: basename($path),
            ]);

            foreach ($items as $item) {
                MemberPaymentReportItem::query()->create([
                    'member_payment_report_id' => $report->id,
                    'savings_contribution_id' => $item['contribution']->id,
                    'amount' => $item['amount'],
                ]);
            }

            $this->audit->log(
                'member.payment_report.submitted',
                $report,
                'El socio reportó un pago de cuotas.',
                null,
                [
                    'contribution_ids' => $contributionIds,
                    'amount' => $total,
                ],
                $member
            );

            return $report->fresh($this->detailRelations());
        });
    }

    public function review(MemberPaymentReport $report, User $admin, string $status, ?string $observations): MemberPaymentReport
    {
        if ($report->status !== MemberPaymentReportStatus::PendienteValidar) {
            throw new BusinessRuleException('Este pago reportado ya fue gestionado.');
        }

        $newStatus = MemberPaymentReportStatus::from($status);

        if (! in_array($newStatus, [MemberPaymentReportStatus::PagoCorrecto, MemberPaymentReportStatus::PagoRechazado], true)) {
            throw new BusinessRuleException('El estado de revisión no es válido.');
        }

        if ($newStatus === MemberPaymentReportStatus::PagoRechazado && blank($observations)) {
            throw new BusinessRuleException('Indique la observación del rechazo.');
        }

        $report->load(['items.contribution.turn', 'reporter', 'cycle']);

        if ($newStatus === MemberPaymentReportStatus::PagoCorrecto && ! $report->cycle?->isInProgress()) {
            throw new BusinessRuleException('Solo se pueden aprobar pagos de un ciclo en proceso.');
        }

        return DB::transaction(function () use ($report, $admin, $newStatus, $observations) {
            $locked = MemberPaymentReport::query()->whereKey($report->id)->lockForUpdate()->first();

            if ($locked->status !== MemberPaymentReportStatus::PendienteValidar) {
                throw new BusinessRuleException('Este pago reportado ya fue gestionado.');
            }

            $locked->update([
                'status' => $newStatus,
                'observations' => $observations,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            $report = $locked->fresh(['items.contribution.turn', 'reporter', 'cycle']);

            if ($newStatus === MemberPaymentReportStatus::PagoCorrecto) {
                foreach ($report->items as $item) {
                    $this->contributions->registerPayment(
                        $item->contribution->fresh(['cycle', 'turn']),
                        $report->reporter,
                        (string) $item->amount,
                        now()->toDateTimeString(),
                        $observations ?: 'Pago reportado por el socio y validado por el administrador.',
                        $report->id,
                    );
                }
            }

            $this->audit->log(
                'member.payment_report.reviewed',
                $report,
                $newStatus === MemberPaymentReportStatus::PagoCorrecto
                    ? 'Pago reportado marcado como correcto.'
                    : 'Pago reportado rechazado.',
                ['status' => MemberPaymentReportStatus::PendienteValidar->value],
                [
                    'status' => $newStatus->value,
                    'observations' => $observations,
                ],
                $admin
            );

            return $report->fresh($this->detailRelations());
        });
    }

    public function receiptResponse(MemberPaymentReport $report)
    {
        if (! $report->receipt_path || ! Storage::disk('local')->exists($report->receipt_path)) {
            throw new BusinessRuleException('El comprobante no está disponible.', 404);
        }

        $path = Storage::disk('local')->path($report->receipt_path);
        $filename = $report->receipt_original_name ?: basename($report->receipt_path);
        $mime = $this->receiptMime($report->receipt_path, $filename);

        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function receiptMime(string $path, string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => Storage::disk('local')->mimeType($path) ?: 'image/jpeg',
        };
    }

    public function detailRelations(): array
    {
        return [
            'person',
            'cycle',
            'reporter.person',
            'reviewer.person',
            'items.contribution.turn',
        ];
    }
}
