<?php

namespace App\Http\Controllers\Api;

use App\Enums\CycleStatus;
use App\Enums\MemberPaymentReportStatus;
use App\Enums\TurnStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SavingsTurnResource;
use App\Models\AuditLog;
use App\Models\MemberPaymentReport;
use App\Models\Person;
use App\Models\SavingsCycle;
use App\Models\SavingsTurn;

class AdminDashboardController extends Controller
{
    public function __invoke()
    {
        $this->authorize('viewAdmin', SavingsCycle::class);

        $nextTurn = SavingsTurn::query()
            ->with(['beneficiary', 'cycle'])
            ->whereHas('cycle', fn ($query) => $query->where('status', CycleStatus::EnProceso))
            ->whereIn('status', [TurnStatus::EnCurso, TurnStatus::Pendiente])
            ->orderBy('expected_disbursement_date')
            ->first();

        $recent = AuditLog::query()
            ->with('user.person')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'created_at' => $log->created_at?->toIso8601String(),
                'user' => $log->user?->person?->full_name ?? $log->user?->email,
            ]);

        return $this->success([
            'active_people' => Person::query()->where('is_active', true)->count(),
            'open_cycles' => SavingsCycle::query()->where('status', CycleStatus::Abierto)->count(),
            'in_progress_cycles' => SavingsCycle::query()->where('status', CycleStatus::EnProceso)->count(),
            'closed_cycles' => SavingsCycle::query()->where('status', CycleStatus::Cerrado)->count(),
            'finalized_cycles' => SavingsCycle::query()->where('status', CycleStatus::Finalizado)->count(),
            'pending_payment_reports' => MemberPaymentReport::query()
                ->where('status', MemberPaymentReportStatus::PendienteValidar)
                ->count(),
            'next_turn' => $nextTurn ? new SavingsTurnResource($nextTurn) : null,
            'recent_activity' => $recent,
        ]);
    }
}
