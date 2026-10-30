<?php

namespace App\Services;

use App\Enums\ContributionStatus;
use App\Enums\CycleStatus;
use App\Enums\DisbursementStatus;
use App\Enums\TurnStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\SavingsCycle;
use App\Models\SavingsTurn;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class CycleLifecycleService
{
    public function __construct(
        private readonly CycleCalendarService $calendar,
        private readonly AuditLogger $audit,
    ) {}

    public function start(SavingsCycle $cycle, User $actor): SavingsCycle
    {
        if (! $cycle->isOpen()) {
            throw new BusinessRuleException('Solo se puede iniciar un ciclo que esté abierto.');
        }

        $members = $cycle->activeMembers()->orderBy('delivery_position')->get();

        if ($members->isEmpty()) {
            throw new BusinessRuleException('No se puede iniciar un ciclo sin socios.');
        }

        if (! Money::isPositive($cycle->weekly_quota)) {
            throw new BusinessRuleException('El ciclo debe tener una cuota semanal válida.');
        }

        if (! $cycle->start_date) {
            throw new BusinessRuleException('El ciclo debe tener una fecha de inicio.');
        }

        if (! $cycle->first_contribution_date) {
            throw new BusinessRuleException('El ciclo debe tener una fecha de primer aporte.');
        }

        $positions = $members->pluck('delivery_position')->map(fn ($value) => (int) $value)->sort()->values();
        $expected = collect(range(1, $members->count()));

        if ($positions->all() !== $expected->all()) {
            throw new BusinessRuleException('El orden de entrega debe estar definido de forma consecutiva desde 1.');
        }

        return DB::transaction(function () use ($cycle, $actor, $members) {
            $this->calendar->regenerateDraftTurns($cycle->fresh());
            $cycle->refresh();

            $turns = $cycle->turns()->orderBy('turn_number')->get();

            if ($turns->count() !== $members->count()) {
                throw new BusinessRuleException('La cantidad de turnos debe coincidir con la cantidad de socios.');
            }

            foreach ($turns as $index => $turn) {
                $this->createContributions($cycle, $turn, $members);

                $turn->update([
                    'status' => $index === 0 ? TurnStatus::EnCurso : TurnStatus::Pendiente,
                ]);
            }

            $old = ['status' => $cycle->status->value];

            $cycle->update([
                'status' => CycleStatus::EnProceso,
                'members_count' => $members->count(),
                'turns_count' => $turns->count(),
            ]);

            $this->audit->log('cycle.started', $cycle, 'Ciclo iniciado.', $old, [
                'status' => CycleStatus::EnProceso->value,
                'members_count' => $members->count(),
            ], $actor);

            return $cycle->fresh(['members.person', 'turns.beneficiary']);
        });
    }

    public function close(SavingsCycle $cycle, User $actor, string $reason): SavingsCycle
    {
        if (! in_array($cycle->status, [CycleStatus::Abierto, CycleStatus::EnProceso], true)) {
            throw new BusinessRuleException('Solo se puede cerrar un ciclo abierto o en proceso.');
        }

        return DB::transaction(function () use ($cycle, $actor, $reason) {
            $old = ['status' => $cycle->status->value];

            if ($cycle->isInProgress()) {
                $cycle->turns()
                    ->whereIn('status', [TurnStatus::Pendiente, TurnStatus::EnCurso])
                    ->update(['status' => TurnStatus::Cancelado]);
            }

            $cycle->update([
                'status' => CycleStatus::Cerrado,
                'closed_at' => now(),
                'closed_by' => $actor->id,
                'close_reason' => $reason,
            ]);

            $this->audit->log('cycle.closed', $cycle, 'Ciclo cerrado administrativamente.', $old, [
                'status' => CycleStatus::Cerrado->value,
                'reason' => $reason,
            ], $actor);

            return $cycle->fresh();
        });
    }

    public function finalize(SavingsCycle $cycle, User $actor, bool $force = false, ?string $reason = null): SavingsCycle
    {
        if (! $cycle->isInProgress()) {
            throw new BusinessRuleException('Solo se puede finalizar un ciclo que está en proceso.');
        }

        $turns = $cycle->turns()->with(['contributions', 'disbursement'])->get();
        $members = $cycle->activeMembers()->get();

        if ($turns->count() !== $members->count() || $turns->isEmpty()) {
            throw new BusinessRuleException('El ciclo no tiene la cantidad de turnos requerida para finalizarse.');
        }

        $incompleteTurns = $turns->filter(fn (SavingsTurn $turn) => $turn->status !== TurnStatus::Completado);

        if ($incompleteTurns->isNotEmpty()) {
            throw new BusinessRuleException('No se puede finalizar el ciclo: existen turnos que no están completados.');
        }

        $beneficiaries = $turns->pluck('beneficiary_person_id');

        if ($beneficiaries->unique()->count() !== $members->count()) {
            throw new BusinessRuleException('Cada socio debe haber sido beneficiario exactamente una vez.');
        }

        $missingDisbursements = $turns->filter(function (SavingsTurn $turn) {
            return ! $turn->disbursement || $turn->disbursement->status !== DisbursementStatus::Completado;
        });

        if ($missingDisbursements->isNotEmpty()) {
            throw new BusinessRuleException('No se puede finalizar el ciclo: existen entregas sin registrar.');
        }

        $unresolved = $cycle->contributions()
            ->whereIn('status', [ContributionStatus::Pendiente, ContributionStatus::Parcial])
            ->count();

        if ($unresolved > 0 && ! $force) {
            throw new BusinessRuleException('No se puede finalizar el ciclo: existen aportes pendientes o parciales. Use una excepción administrativa documentada si desea continuar.');
        }

        if ($unresolved > 0 && $force && blank($reason)) {
            throw new BusinessRuleException('Debe documentar la razón de la excepción administrativa para finalizar con aportes pendientes.');
        }

        return DB::transaction(function () use ($cycle, $actor, $force, $reason) {
            $old = ['status' => $cycle->status->value];

            $cycle->update([
                'status' => CycleStatus::Finalizado,
                'finalized_at' => now(),
                'finalized_by' => $actor->id,
                'finalize_reason' => $reason,
            ]);

            $this->audit->log('cycle.finalized', $cycle, $force
                ? 'Ciclo finalizado con excepción administrativa.'
                : 'Ciclo finalizado.', $old, [
                    'status' => CycleStatus::Finalizado->value,
                    'forced' => $force,
                    'reason' => $reason,
                ], $actor);

            return $cycle->fresh();
        });
    }

    public function summary(SavingsCycle $cycle): array
    {
        $cycle->load(['turns.beneficiary', 'contributions', 'disbursements', 'members.person']);

        $expected = Money::multiply($cycle->weekly_quota, (int) $cycle->members_count * max((int) $cycle->turns_count, 0));
        $collected = Money::of($cycle->contributions->sum(fn ($item) => (float) $item->received_amount));
        $delivered = Money::of(
            $cycle->disbursements
                ->where('status', DisbursementStatus::Completado)
                ->sum(fn ($item) => (float) $item->delivered_amount)
        );

        return [
            'cycle_id' => $cycle->id,
            'status' => $cycle->status->value,
            'members_count' => $cycle->members_count,
            'turns_count' => $cycle->turns_count,
            'weekly_quota' => Money::of($cycle->weekly_quota),
            'expected_total_contributions' => $expected,
            'collected_total' => $collected,
            'delivered_total' => $delivered,
            'pending_contributions' => $cycle->contributions->where('status', ContributionStatus::Pendiente)->count(),
            'partial_contributions' => $cycle->contributions->where('status', ContributionStatus::Parcial)->count(),
            'paid_contributions' => $cycle->contributions->where('status', ContributionStatus::Pagado)->count(),
            'completed_turns' => $cycle->turns->where('status', TurnStatus::Completado)->count(),
            'pending_turns' => $cycle->turns->whereIn('status', [TurnStatus::Pendiente, TurnStatus::EnCurso])->count(),
        ];
    }

    private function createContributions(SavingsCycle $cycle, SavingsTurn $turn, $members): void
    {
        foreach ($members as $member) {
            $cycle->contributions()->create([
                'savings_turn_id' => $turn->id,
                'person_id' => $member->person_id,
                'expected_amount' => $cycle->weekly_quota,
                'received_amount' => Money::of(0),
                'expected_date' => $turn->expected_contribution_date,
                'status' => ContributionStatus::Pendiente,
            ]);
        }
    }
}
