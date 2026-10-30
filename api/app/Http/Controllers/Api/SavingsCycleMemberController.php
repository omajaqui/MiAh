<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cycles\AddCycleMemberRequest;
use App\Http\Requests\Cycles\ReorderCycleMembersRequest;
use App\Http\Resources\SavingsCycleMemberResource;
use App\Http\Resources\SavingsCycleResource;
use App\Models\SavingsCycle;
use App\Models\SavingsCycleMember;
use App\Services\CycleMemberService;

class SavingsCycleMemberController extends Controller
{
    public function __construct(private readonly CycleMemberService $members) {}

    public function store(AddCycleMemberRequest $request, SavingsCycle $savingsCycle)
    {
        $this->authorize('manageMembers', $savingsCycle);

        $personIds = $request->validated('person_ids') ?? [$request->validated('person_id')];
        $members = $this->members->addMany(
            $savingsCycle,
            $personIds,
            $request->validated('delivery_position'),
            $request->validated('observations')
        );

        if (count($personIds) === 1) {
            return $this->success(new SavingsCycleMemberResource($members->first()), 'Socio asociado al ciclo.', 201);
        }

        return $this->success(
            SavingsCycleMemberResource::collection($members),
            'Socios asociados al ciclo.',
            201
        );
    }

    public function destroy(SavingsCycle $savingsCycle, SavingsCycleMember $member)
    {
        $this->authorize('manageMembers', $savingsCycle);

        $this->members->remove($savingsCycle, $member);

        return $this->success(null, 'Socio retirado del ciclo.');
    }

    public function reorder(ReorderCycleMembersRequest $request, SavingsCycle $savingsCycle)
    {
        $this->authorize('manageMembers', $savingsCycle);

        $this->members->reorder($savingsCycle, $request->validated('person_ids'));

        return $this->success(
            new SavingsCycleResource($savingsCycle->fresh(['members.person', 'turns.beneficiary'])),
            'Orden de entrega actualizado.'
        );
    }
}
