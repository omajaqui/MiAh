<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Person;
use App\Models\SavingsCycle;
use App\Models\SavingsCycleMember;
use Illuminate\Support\Facades\DB;

class CycleMemberService
{
    public function __construct(
        private readonly CycleCalendarService $calendar,
        private readonly AuditLogger $audit,
    ) {}

    public function add(SavingsCycle $cycle, Person $person, ?int $position = null, ?string $observations = null): SavingsCycleMember
    {
        return $this->addMany($cycle, [$person->id], $position, $observations)->first();
    }

    public function addMany(SavingsCycle $cycle, array $personIds, ?int $position = null, ?string $observations = null)
    {
        if (! $cycle->isOpen()) {
            throw new BusinessRuleException('No se pueden agregar socios a un ciclo que no está abierto.');
        }

        $personIds = array_values(array_unique(array_map('intval', $personIds)));

        if ($personIds === []) {
            throw new BusinessRuleException('Debe seleccionar al menos un socio.');
        }

        return DB::transaction(function () use ($cycle, $personIds, $position, $observations) {
            $added = collect();
            $nextPosition = $position ?? ((int) $cycle->members()->max('delivery_position') + 1);

            foreach ($personIds as $personId) {
                if ($cycle->members()->where('person_id', $personId)->exists()) {
                    continue;
                }

                $person = Person::query()->findOrFail($personId);

                if (! $person->is_active) {
                    throw new BusinessRuleException('No se puede agregar a un socio inactivo a un ciclo nuevo.');
                }

                if ($nextPosition < 1) {
                    throw new BusinessRuleException('La posición de entrega debe ser un número positivo.');
                }

                while ($cycle->members()->where('delivery_position', $nextPosition)->exists()) {
                    $nextPosition++;
                }

                $member = SavingsCycleMember::query()->create([
                    'savings_cycle_id' => $cycle->id,
                    'person_id' => $person->id,
                    'joined_at' => now(),
                    'delivery_position' => $nextPosition,
                    'status' => MemberStatus::Activo,
                    'weekly_quota' => $cycle->weekly_quota,
                    'observations' => $observations,
                ]);

                $this->audit->log('cycle.member.added', $cycle, 'Socio asociado al ciclo.', null, [
                    'person_id' => $person->id,
                    'delivery_position' => $nextPosition,
                ]);

                $added->push($member->load('person'));
                $nextPosition++;
            }

            if ($added->isEmpty()) {
                throw new BusinessRuleException('Los socios seleccionados ya están asociados a este ciclo.');
            }

            $this->calendar->regenerateDraftTurns($cycle->fresh());

            return $added;
        });
    }

    public function remove(SavingsCycle $cycle, SavingsCycleMember $member): void
    {
        if ($member->savings_cycle_id !== $cycle->id) {
            throw new BusinessRuleException('El socio no pertenece a este ciclo.');
        }

        if (! $cycle->isOpen()) {
            throw new BusinessRuleException('No se puede retirar un socio de un ciclo iniciado, cerrado o finalizado.');
        }

        DB::transaction(function () use ($cycle, $member) {
            $payload = [
                'person_id' => $member->person_id,
                'delivery_position' => $member->delivery_position,
            ];

            $cycle->turns()->delete();
            $member->delete();
            $this->resequence($cycle->fresh());
            $this->calendar->regenerateDraftTurns($cycle->fresh());
            $this->audit->log('cycle.member.removed', $cycle, 'Socio retirado del ciclo abierto.', $payload);
        });
    }

    public function reorder(SavingsCycle $cycle, array $orderedPersonIds): void
    {
        if (! $cycle->isOpen()) {
            throw new BusinessRuleException('El orden de entrega solo se puede modificar mientras el ciclo esté abierto.');
        }

        $members = $cycle->activeMembers()->get()->keyBy('person_id');

        if (count($orderedPersonIds) !== $members->count()) {
            throw new BusinessRuleException('El orden debe incluir exactamente a todos los socios del ciclo.');
        }

        if (count($orderedPersonIds) !== count(array_unique($orderedPersonIds))) {
            throw new BusinessRuleException('El orden de entrega no puede contener socios duplicados.');
        }

        foreach ($orderedPersonIds as $personId) {
            if (! $members->has($personId)) {
                throw new BusinessRuleException('Uno de los socios indicados no pertenece al ciclo.');
            }
        }

        DB::transaction(function () use ($cycle, $orderedPersonIds, $members) {
            foreach ($orderedPersonIds as $index => $personId) {
                $members[$personId]->update([
                    'delivery_position' => 1000 + $index + 1,
                ]);
            }

            foreach ($orderedPersonIds as $index => $personId) {
                $members[$personId]->update([
                    'delivery_position' => $index + 1,
                ]);
            }

            $this->calendar->regenerateDraftTurns($cycle->fresh());
            $this->audit->log('cycle.members.reordered', $cycle, 'Orden de entrega actualizado.', null, [
                'person_ids' => $orderedPersonIds,
            ]);
        });
    }

    private function resequence(SavingsCycle $cycle): void
    {
        $members = $cycle->members()->orderBy('delivery_position')->get();

        foreach ($members as $index => $member) {
            $member->update(['delivery_position' => 2000 + $index + 1]);
        }

        foreach ($members as $index => $member) {
            $member->update(['delivery_position' => $index + 1]);
        }
    }
}
