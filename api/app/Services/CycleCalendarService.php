<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Enums\TurnStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\SavingsCycle;
use App\Models\SavingsTurn;
use App\Support\Money;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CycleCalendarService
{
    public function preview(SavingsCycle $cycle, ?array $overrides = null): array
    {
        $members = $cycle->activeMembers()->with('person')->orderBy('delivery_position')->get();
        $quota = $overrides['weekly_quota'] ?? $cycle->weekly_quota;
        $firstContribution = Carbon::parse($overrides['first_contribution_date'] ?? $cycle->first_contribution_date)->startOfDay();
        $firstDisbursement = Carbon::parse($overrides['first_disbursement_date'] ?? $cycle->first_disbursement_date)->startOfDay();
        $count = $members->count();
        $gross = $count > 0 ? Money::multiply($quota, $count) : Money::of(0);

        $turns = $members->values()->map(function ($member, $index) use ($firstContribution, $firstDisbursement, $quota, $count, $gross) {
            return [
                'turn_number' => $index + 1,
                'delivery_position' => $member->delivery_position,
                'beneficiary_person_id' => $member->person_id,
                'beneficiary' => [
                    'id' => $member->person->id,
                    'first_name' => $member->person->first_name,
                    'last_name' => $member->person->last_name,
                    'full_name' => $member->person->full_name,
                ],
                'expected_contribution_date' => $firstContribution->copy()->addWeeks($index)->toDateString(),
                'expected_disbursement_date' => $firstDisbursement->copy()->addWeeks($index)->toDateString(),
                'weekly_quota' => Money::of($quota),
                'contributors_count' => $count,
                'expected_gross_amount' => $gross,
            ];
        })->all();

        return [
            'members_count' => $count,
            'turns_count' => $count,
            'weekly_quota' => Money::of($quota),
            'expected_gross_amount' => $gross,
            'total_contribution_per_member' => $gross,
            'turns' => $turns,
        ];
    }

    public function regenerateDraftTurns(SavingsCycle $cycle): Collection
    {
        if (! $cycle->isOpen()) {
            throw new BusinessRuleException('Solo se pueden regenerar turnos de un ciclo abierto.');
        }

        $cycle->turns()->delete();

        $preview = $this->preview($cycle);
        $turns = collect();

        foreach ($preview['turns'] as $row) {
            $member = $cycle->activeMembers()->where('person_id', $row['beneficiary_person_id'])->first();

            $turns->push(SavingsTurn::query()->create([
                'savings_cycle_id' => $cycle->id,
                'turn_number' => $row['turn_number'],
                'beneficiary_person_id' => $row['beneficiary_person_id'],
                'savings_cycle_member_id' => $member->id,
                'expected_contribution_date' => $row['expected_contribution_date'],
                'expected_disbursement_date' => $row['expected_disbursement_date'],
                'weekly_quota' => $row['weekly_quota'],
                'contributors_count' => $row['contributors_count'],
                'expected_gross_amount' => $row['expected_gross_amount'],
                'collected_amount' => Money::of(0),
                'status' => TurnStatus::Pendiente,
            ]));
        }

        $cycle->update([
            'members_count' => $preview['members_count'],
            'turns_count' => $preview['turns_count'],
        ]);

        return $turns;
    }

    public function updateTurnDates(SavingsTurn $turn, array $dates): SavingsTurn
    {
        $cycle = $turn->cycle;

        if (! $cycle->isOpen()) {
            throw new BusinessRuleException('Las fechas de los turnos solo se pueden modificar mientras el ciclo esté abierto.');
        }

        $turn->update([
            'expected_contribution_date' => $dates['expected_contribution_date'] ?? $turn->expected_contribution_date,
            'expected_disbursement_date' => $dates['expected_disbursement_date'] ?? $turn->expected_disbursement_date,
        ]);

        return $turn->refresh();
    }

    public function weeklyDates(CarbonInterface $start, int $weeks): array
    {
        $dates = [];

        for ($i = 0; $i < $weeks; $i++) {
            $dates[] = $start->copy()->addWeeks($i)->toDateString();
        }

        return $dates;
    }
}
