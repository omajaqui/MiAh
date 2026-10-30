<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContributionStatus;
use App\Enums\MemberPaymentReportStatus;
use App\Enums\TurnStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MemberPaymentReportResource;
use App\Http\Resources\PersonResource;
use App\Http\Resources\SavingsCycleResource;
use App\Models\MemberPaymentReport;
use App\Models\SavingsCycle;
use App\Models\SavingsTurn;
use App\Support\ApiResponse;
use App\Support\Money;
use Illuminate\Http\Request;

class MemberPortalController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user()->load('person');
        $personId = $user->person_id;

        $memberships = $user->person
            ? $user->person->memberships()->with(['cycle.turns' => fn ($query) => $query->orderBy('turn_number')])->get()
            : collect();

        $ownTurn = $memberships
            ->map(function ($membership) use ($personId) {
                $turn = $membership->cycle?->turns->firstWhere('beneficiary_person_id', $personId);

                return [
                    'membership' => $membership,
                    'turn' => $turn,
                    'cycle' => $membership->cycle,
                ];
            })
            ->filter(fn ($item) => $item['cycle']?->isInProgress())
            ->sortBy(fn ($item) => $item['turn']?->expected_disbursement_date)
            ->first();

        $nextContribution = SavingsTurn::query()
            ->whereHas('cycle.members', fn ($query) => $query->where('person_id', $personId))
            ->whereHas('cycle', fn ($query) => $query->where('status', 'en_proceso'))
            ->whereIn('status', [TurnStatus::EnCurso, TurnStatus::Pendiente])
            ->orderBy('expected_contribution_date')
            ->first();

        return $this->success([
            'person' => new PersonResource($user->person),
            'cycles_count' => $memberships->count(),
            'in_progress_cycles' => $memberships->filter(fn ($item) => $item->cycle?->isInProgress())->count(),
            'weekly_quota' => data_get($ownTurn, 'membership.weekly_quota') ?? $memberships->first()?->weekly_quota,
            'next_contribution_date' => $nextContribution?->expected_contribution_date?->toDateString(),
            'next_disbursement_date' => data_get($ownTurn, 'turn.expected_disbursement_date')?->toDateString(),
            'expected_gross_amount' => data_get($ownTurn, 'turn.expected_gross_amount'),
            'turn_number' => data_get($ownTurn, 'turn.turn_number'),
            'turn_status' => data_get($ownTurn, 'turn.status')?->value,
            'current_cycle' => data_get($ownTurn, 'cycle') ? new SavingsCycleResource($ownTurn['cycle']) : null,
        ]);
    }

    public function cycles(Request $request)
    {
        $personId = $request->user()->person_id;

        $cycles = SavingsCycle::query()
            ->whereHas('members', fn ($query) => $query->where('person_id', $personId))
            ->with([
                'members' => fn ($query) => $query->where('person_id', $personId),
                'turns' => fn ($query) => $query->where('beneficiary_person_id', $personId),
            ])
            ->latest()
            ->get()
            ->map(function (SavingsCycle $cycle) use ($personId) {
                $membership = $cycle->members->first();
                $ownTurn = $cycle->turns->first();

                return [
                    'id' => $cycle->id,
                    'name' => $cycle->name,
                    'status' => $cycle->status?->value,
                    'status_label' => $cycle->status?->label(),
                    'start_date' => $cycle->start_date?->toDateString(),
                    'weekly_quota' => $membership?->weekly_quota ?? $cycle->weekly_quota,
                    'delivery_position' => $membership?->delivery_position,
                    'expected_disbursement_date' => $ownTurn?->expected_disbursement_date?->toDateString(),
                    'expected_gross_amount' => $ownTurn?->expected_gross_amount ?? Money::multiply($cycle->weekly_quota, (int) $cycle->members_count),
                    'turn_status' => $ownTurn?->status?->value,
                ];
            });

        return $this->success($cycles);
    }

    public function showCycle(Request $request, SavingsCycle $savingsCycle)
    {
        if (! $request->user()->canAccessCycle($savingsCycle)) {
            return ApiResponse::error('No puede consultar ciclos en los que no participa.', 403);
        }

        $personId = $request->user()->person_id;

        $savingsCycle->load([
            'members.person',
            'turns.beneficiary',
            'turns' => fn ($query) => $query->orderBy('turn_number'),
        ]);

        $membership = $savingsCycle->members->firstWhere('person_id', $personId);
        $ownTurn = $savingsCycle->turns->firstWhere('beneficiary_person_id', $personId);
        $cycleInProgress = $savingsCycle->isInProgress();
        $ownContributions = $savingsCycle->contributions()
            ->where('person_id', $personId)
            ->with(['turn', 'paymentReportItems.report'])
            ->orderBy('expected_date')
            ->get()
            ->map(function ($contribution) use ($cycleInProgress) {
                $pendingItem = $contribution->paymentReportItems
                    ->first(fn ($item) => $item->report?->status === MemberPaymentReportStatus::PendienteValidar);
                $latestReport = $contribution->paymentReportItems
                    ->sortByDesc(fn ($item) => optional($item->report)->created_at)
                    ->first()?->report;
                $remaining = Money::subtract($contribution->expected_amount, $contribution->received_amount);

                return [
                    'id' => $contribution->id,
                    'turn_number' => $contribution->turn?->turn_number,
                    'expected_amount' => $contribution->expected_amount,
                    'received_amount' => $contribution->received_amount,
                    'remaining_amount' => $remaining,
                    'expected_date' => $contribution->expected_date?->toDateString(),
                    'status' => $contribution->status?->value,
                    'can_report' => $cycleInProgress
                        && Money::isPositive($remaining)
                        && ! $pendingItem
                        && in_array($contribution->status, [ContributionStatus::Pendiente, ContributionStatus::Parcial], true),
                    'report_status' => $latestReport?->status?->value,
                    'pending_report_id' => $pendingItem?->member_payment_report_id,
                ];
            });
        $paymentReports = MemberPaymentReport::query()
            ->where('savings_cycle_id', $savingsCycle->id)
            ->where('person_id', $personId)
            ->with(['items.contribution.turn', 'reviewer.person', 'cycle', 'person'])
            ->latest()
            ->get();

        return $this->success([
            'cycle' => new SavingsCycleResource($savingsCycle),
            'membership' => [
                'delivery_position' => $membership?->delivery_position,
                'weekly_quota' => $membership?->weekly_quota,
                'status' => $membership?->status?->value,
            ],
            'own_turn' => $ownTurn ? [
                'turn_number' => $ownTurn->turn_number,
                'status' => $ownTurn->status?->value,
                'expected_contribution_date' => $ownTurn->expected_contribution_date?->toDateString(),
                'expected_disbursement_date' => $ownTurn->expected_disbursement_date?->toDateString(),
                'expected_gross_amount' => $ownTurn->expected_gross_amount,
                'collected_amount' => $ownTurn->collected_amount,
            ] : null,
            'own_contributions' => $ownContributions,
            'payment_reports' => MemberPaymentReportResource::collection($paymentReports)->resolve(),
            'turns' => $savingsCycle->turns->map(fn ($turn) => [
                'turn_number' => $turn->turn_number,
                'status' => $turn->status?->value,
                'expected_contribution_date' => $turn->expected_contribution_date?->toDateString(),
                'expected_disbursement_date' => $turn->expected_disbursement_date?->toDateString(),
                'expected_gross_amount' => $turn->expected_gross_amount,
                'beneficiary' => [
                    'id' => $turn->beneficiary?->id,
                    'full_name' => $turn->beneficiary?->full_name,
                ],
                'is_mine' => $turn->beneficiary_person_id === $personId,
            ]),
        ]);
    }

    public function currentTurn(Request $request)
    {
        $personId = $request->user()->person_id;

        $turn = SavingsTurn::query()
            ->with(['cycle', 'beneficiary'])
            ->whereHas('cycle.members', fn ($query) => $query->where('person_id', $personId))
            ->whereHas('cycle', fn ($query) => $query->where('status', 'en_proceso'))
            ->whereIn('status', [TurnStatus::EnCurso, TurnStatus::Pendiente])
            ->orderBy('expected_contribution_date')
            ->first();

        $ownTurn = SavingsTurn::query()
            ->with('cycle')
            ->where('beneficiary_person_id', $personId)
            ->whereHas('cycle', fn ($query) => $query->where('status', 'en_proceso'))
            ->orderBy('turn_number')
            ->first();

        return $this->success([
            'current_cycle_turn' => $turn ? [
                'turn_number' => $turn->turn_number,
                'status' => $turn->status?->value,
                'expected_contribution_date' => $turn->expected_contribution_date?->toDateString(),
                'expected_disbursement_date' => $turn->expected_disbursement_date?->toDateString(),
                'expected_gross_amount' => $turn->expected_gross_amount,
                'collected_amount' => $turn->collected_amount,
                'cycle' => [
                    'id' => $turn->cycle->id,
                    'name' => $turn->cycle->name,
                    'weekly_quota' => $turn->cycle->weekly_quota,
                ],
                'beneficiary' => [
                    'full_name' => $turn->beneficiary?->full_name,
                ],
            ] : null,
            'own_turn' => $ownTurn ? [
                'turn_number' => $ownTurn->turn_number,
                'status' => $ownTurn->status?->value,
                'expected_disbursement_date' => $ownTurn->expected_disbursement_date?->toDateString(),
                'expected_gross_amount' => $ownTurn->expected_gross_amount,
            ] : null,
        ]);
    }
}
