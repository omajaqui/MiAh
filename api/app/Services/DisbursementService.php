<?php

namespace App\Services;

use App\Enums\DisbursementStatus;
use App\Enums\TurnStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\SavingsDisbursement;
use App\Models\SavingsTurn;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class DisbursementService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function register(
        SavingsTurn $turn,
        User $actor,
        ?string $deliveredAmount = null,
        ?string $deliveredAt = null,
        ?string $observations = null,
    ): SavingsDisbursement {
        $cycle = $turn->cycle;

        if (! $cycle->isInProgress()) {
            throw new BusinessRuleException('Solo se pueden registrar entregas en un ciclo en proceso.');
        }

        if ($turn->status === TurnStatus::Completado) {
            throw new BusinessRuleException('Este turno ya tiene una entrega completada.');
        }

        if ($turn->status === TurnStatus::Cancelado) {
            throw new BusinessRuleException('No se puede registrar una entrega en un turno cancelado.');
        }

        if ($turn->activeDisbursement()->exists()) {
            throw new BusinessRuleException('Ya existe una entrega activa para este turno.');
        }

        $amount = $deliveredAmount !== null ? Money::of($deliveredAmount) : Money::of($turn->collected_amount);

        if (Money::compare($amount, 0) < 0) {
            throw new BusinessRuleException('El monto entregado no puede ser negativo.');
        }

        return DB::transaction(function () use ($turn, $actor, $amount, $deliveredAt, $observations) {
            $locked = SavingsTurn::query()->whereKey($turn->id)->lockForUpdate()->first();

            if ($locked->activeDisbursement()->exists()) {
                throw new BusinessRuleException('Ya existe una entrega activa para este turno.');
            }

            $disbursement = SavingsDisbursement::query()->create([
                'savings_cycle_id' => $locked->savings_cycle_id,
                'savings_turn_id' => $locked->id,
                'beneficiary_person_id' => $locked->beneficiary_person_id,
                'expected_amount' => $locked->expected_gross_amount,
                'collected_amount' => $locked->collected_amount,
                'delivered_amount' => $amount,
                'delivered_at' => $deliveredAt ? \Illuminate\Support\Carbon::parse($deliveredAt) : now(),
                'status' => DisbursementStatus::Completado,
                'registered_by' => $actor->id,
                'observations' => $observations,
            ]);

            $locked->update([
                'status' => TurnStatus::Completado,
                'actual_disbursement_date' => $disbursement->delivered_at->toDateString(),
            ]);

            $this->activateNextTurn($locked);

            $this->audit->log('disbursement.registered', $disbursement, 'Entrega registrada.', null, [
                'turn_id' => $locked->id,
                'delivered_amount' => $amount,
                'collected_amount' => $locked->collected_amount,
                'expected_amount' => $locked->expected_gross_amount,
            ], $actor);

            return $disbursement->fresh(['beneficiary', 'registrar', 'turn']);
        });
    }

    public function annul(SavingsDisbursement $disbursement, User $actor, string $reason): SavingsDisbursement
    {
        if ($disbursement->status === DisbursementStatus::Anulado) {
            throw new BusinessRuleException('Esta entrega ya fue anulada.');
        }

        $cycle = $disbursement->cycle;

        if (! $cycle->isInProgress()) {
            throw new BusinessRuleException('Solo se pueden anular entregas de un ciclo en proceso.');
        }

        return DB::transaction(function () use ($disbursement, $actor, $reason) {
            $disbursement->update([
                'status' => DisbursementStatus::Anulado,
                'annul_reason' => $reason,
                'annulled_at' => now(),
                'annulled_by' => $actor->id,
            ]);

            $turn = $disbursement->turn;
            $turn->update([
                'status' => TurnStatus::EnCurso,
                'actual_disbursement_date' => null,
            ]);

            $this->audit->log('disbursement.annulled', $disbursement, 'Entrega anulada.', [
                'delivered_amount' => $disbursement->delivered_amount,
            ], [
                'reason' => $reason,
            ], $actor);

            return $disbursement->fresh();
        });
    }

    private function activateNextTurn(SavingsTurn $completed): void
    {
        $next = SavingsTurn::query()
            ->where('savings_cycle_id', $completed->savings_cycle_id)
            ->where('turn_number', $completed->turn_number + 1)
            ->where('status', TurnStatus::Pendiente)
            ->first();

        $next?->update(['status' => TurnStatus::EnCurso]);
    }
}
