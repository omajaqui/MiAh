<?php

namespace App\Services;

use App\Enums\ContributionStatus;
use App\Enums\PaymentStatus;
use App\Enums\TurnStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\SavingsContribution;
use App\Models\SavingsContributionPayment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ContributionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function registerPayment(
        SavingsContribution $contribution,
        User $actor,
        string $amount,
        ?string $paidAt = null,
        ?string $observations = null,
        ?int $reportId = null,
    ): SavingsContribution {
        $cycle = $contribution->cycle;
        $turn = $contribution->turn;

        if (! $cycle->isInProgress()) {
            throw new BusinessRuleException('Solo se pueden registrar aportes en un ciclo en proceso.');
        }

        if (! in_array($turn->status, [TurnStatus::Pendiente, TurnStatus::EnCurso], true)) {
            throw new BusinessRuleException('No se pueden registrar aportes en un turno completado o cancelado.');
        }

        if (in_array($contribution->status, [ContributionStatus::Anulado, ContributionStatus::Pagado], true)) {
            throw new BusinessRuleException('Este aporte ya no admite nuevos abonos.');
        }

        if (! Money::isPositive($amount)) {
            throw new BusinessRuleException('El monto del aporte debe ser mayor a cero.');
        }

        $projected = Money::add($contribution->received_amount, $amount);

        if (Money::compare($projected, $contribution->expected_amount) > 0) {
            throw new BusinessRuleException('El monto recibido no puede superar el monto esperado del aporte.');
        }

        return DB::transaction(function () use ($contribution, $actor, $amount, $paidAt, $observations, $projected, $reportId) {
            $payment = SavingsContributionPayment::query()->create([
                'savings_contribution_id' => $contribution->id,
                'amount' => Money::of($amount),
                'paid_at' => $paidAt ? \Illuminate\Support\Carbon::parse($paidAt) : now(),
                'status' => PaymentStatus::Registrado,
                'registered_by' => $actor->id,
                'observations' => $observations,
                'member_payment_report_id' => $reportId,
            ]);

            $status = Money::compare($projected, $contribution->expected_amount) >= 0
                ? ContributionStatus::Pagado
                : ContributionStatus::Parcial;

            $contribution->update([
                'received_amount' => $projected,
                'received_at' => $payment->paid_at,
                'status' => $status,
            ]);

            $this->refreshTurnCollected($contribution->fresh()->turn);

            $this->audit->log('contribution.payment.registered', $contribution, 'Abono registrado.', null, [
                'payment_id' => $payment->id,
                'amount' => Money::of($amount),
                'status' => $status->value,
            ], $actor);

            return $contribution->fresh(['payments.registrar', 'person', 'turn']);
        });
    }

    public function annulPayment(
        SavingsContributionPayment $payment,
        User $actor,
        string $reason,
    ): SavingsContribution {
        if ($payment->status === PaymentStatus::Anulado) {
            throw new BusinessRuleException('Este pago ya fue anulado.');
        }

        $contribution = $payment->contribution;
        $cycle = $contribution->cycle;

        if (! $cycle->isInProgress()) {
            throw new BusinessRuleException('Solo se pueden anular pagos de un ciclo en proceso.');
        }

        if ($contribution->turn->status === TurnStatus::Completado) {
            throw new BusinessRuleException('No se puede anular un pago de un turno cuya entrega ya fue completada. Anule primero la entrega.');
        }

        return DB::transaction(function () use ($payment, $actor, $reason, $contribution) {
            $payment->update([
                'status' => PaymentStatus::Anulado,
                'annul_reason' => $reason,
                'annulled_at' => now(),
                'annulled_by' => $actor->id,
            ]);

            $received = $contribution->payments()
                ->where('status', PaymentStatus::Registrado)
                ->get()
                ->reduce(fn (string $carry, $item) => Money::add($carry, $item->amount), Money::of(0));

            $status = match (true) {
                Money::compare($received, 0) === 0 => ContributionStatus::Pendiente,
                Money::compare($received, $contribution->expected_amount) >= 0 => ContributionStatus::Pagado,
                default => ContributionStatus::Parcial,
            };

            $lastPayment = $contribution->payments()
                ->where('status', PaymentStatus::Registrado)
                ->orderByDesc('paid_at')
                ->first();

            $contribution->update([
                'received_amount' => $received,
                'received_at' => $lastPayment?->paid_at,
                'status' => $status,
            ]);

            $this->refreshTurnCollected($contribution->fresh()->turn);

            $this->audit->log('contribution.payment.annulled', $contribution, 'Pago anulado.', [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
            ], [
                'reason' => $reason,
                'status' => $status->value,
            ], $actor);

            return $contribution->fresh(['payments', 'person', 'turn']);
        });
    }

    private function refreshTurnCollected($turn): void
    {
        $collected = $turn->contributions()
            ->get()
            ->reduce(fn (string $carry, $item) => Money::add($carry, $item->received_amount), Money::of(0));

        $turn->update(['collected_amount' => $collected]);
    }
}
