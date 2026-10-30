<?php

namespace App\Http\Controllers\Api;

use App\Enums\CycleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cycles\CloseCycleRequest;
use App\Http\Requests\Cycles\FinalizeCycleRequest;
use App\Http\Requests\Cycles\StoreSavingsCycleRequest;
use App\Http\Requests\Cycles\UpdateSavingsCycleRequest;
use App\Http\Requests\Cycles\UpdateTurnDatesRequest;
use App\Http\Resources\SavingsCycleResource;
use App\Http\Resources\SavingsTurnResource;
use App\Models\SavingsCycle;
use App\Models\SavingsTurn;
use App\Services\AuditLogger;
use App\Services\CycleCalendarService;
use App\Services\CycleLifecycleService;
use App\Support\Money;
use Illuminate\Http\Request;

class SavingsCycleController extends Controller
{
    public function __construct(
        private readonly CycleCalendarService $calendar,
        private readonly CycleLifecycleService $lifecycle,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', SavingsCycle::class);

        $query = SavingsCycle::query()->withCount('members')->latest();

        if (! $request->user()->isAdmin()) {
            $query->whereHas('members', fn ($builder) => $builder->where('person_id', $request->user()->person_id));
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $this->success(
            SavingsCycleResource::collection($query->paginate($request->integer('per_page', 15)))->response()->getData(true)
        );
    }

    public function store(StoreSavingsCycleRequest $request)
    {
        $this->authorize('create', SavingsCycle::class);

        $data = $request->validated();
        $data['currency'] = strtoupper($data['currency'] ?? 'COP');
        $data['weekly_quota'] = Money::of($data['weekly_quota']);
        $data['status'] = CycleStatus::Abierto;
        $data['created_by'] = $request->user()->id;

        $cycle = SavingsCycle::query()->create($data);
        $this->audit->log('cycle.created', $cycle, 'Ciclo creado.', null, $cycle->toArray());

        return $this->success(new SavingsCycleResource($cycle), 'Ciclo creado.', 201);
    }

    public function show(SavingsCycle $savingsCycle)
    {
        $this->authorize('view', $savingsCycle);

        $savingsCycle->load([
            'members.person',
            'turns.beneficiary',
            'turns.contributions.person',
            'turns.contributions.payments',
            'turns.disbursement',
        ]);

        return $this->success(new SavingsCycleResource($savingsCycle));
    }

    public function update(UpdateSavingsCycleRequest $request, SavingsCycle $savingsCycle)
    {
        $this->authorize('update', $savingsCycle);

        if (! $savingsCycle->isOpen()) {
            abort(422, 'Solo se puede editar un ciclo abierto.');
        }

        $old = $savingsCycle->toArray();
        $data = $request->validated();
        $data['currency'] = strtoupper($data['currency'] ?? $savingsCycle->currency);
        $data['weekly_quota'] = Money::of($data['weekly_quota']);

        $savingsCycle->update($data);

        if ($savingsCycle->members()->exists()) {
            $savingsCycle->members()->update(['weekly_quota' => $savingsCycle->weekly_quota]);
            $this->calendar->regenerateDraftTurns($savingsCycle->fresh());
        }

        $this->audit->log('cycle.updated', $savingsCycle, 'Ciclo actualizado.', $old, $savingsCycle->fresh()->toArray());

        return $this->success(new SavingsCycleResource($savingsCycle->fresh(['members.person', 'turns.beneficiary'])), 'Ciclo actualizado.');
    }

    public function calendarPreview(Request $request, SavingsCycle $savingsCycle)
    {
        $this->authorize('view', $savingsCycle);

        return $this->success($this->calendar->preview($savingsCycle, $request->only([
            'weekly_quota',
            'first_contribution_date',
            'first_disbursement_date',
        ])));
    }

    public function start(SavingsCycle $savingsCycle, Request $request)
    {
        $this->authorize('start', $savingsCycle);

        $cycle = $this->lifecycle->start($savingsCycle, $request->user());

        return $this->success(new SavingsCycleResource($cycle), 'Ciclo iniciado.');
    }

    public function close(CloseCycleRequest $request, SavingsCycle $savingsCycle)
    {
        $this->authorize('close', $savingsCycle);

        $cycle = $this->lifecycle->close($savingsCycle, $request->user(), $request->validated('reason'));

        return $this->success(new SavingsCycleResource($cycle), 'Ciclo cerrado.');
    }

    public function finalize(FinalizeCycleRequest $request, SavingsCycle $savingsCycle)
    {
        $this->authorize('finalize', $savingsCycle);

        $cycle = $this->lifecycle->finalize(
            $savingsCycle,
            $request->user(),
            $request->boolean('force'),
            $request->validated('reason')
        );

        return $this->success(new SavingsCycleResource($cycle), 'Ciclo finalizado.');
    }

    public function summary(SavingsCycle $savingsCycle)
    {
        $this->authorize('view', $savingsCycle);

        return $this->success($this->lifecycle->summary($savingsCycle));
    }

    public function updateTurn(UpdateTurnDatesRequest $request, SavingsCycle $savingsCycle, SavingsTurn $turn)
    {
        $this->authorize('update', $savingsCycle);

        if ($turn->savings_cycle_id !== $savingsCycle->id) {
            abort(404, 'El turno no pertenece a este ciclo.');
        }

        $updated = $this->calendar->updateTurnDates($turn, $request->validated());

        return $this->success(new SavingsTurnResource($updated->load('beneficiary')), 'Fechas del turno actualizadas.');
    }
}
