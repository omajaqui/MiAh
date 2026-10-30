<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\People\CreateAccountRequest;
use App\Http\Requests\People\ResetPasswordRequest;
use App\Http\Requests\People\StorePersonRequest;
use App\Http\Requests\People\UpdateAccountRequest;
use App\Http\Requests\People\UpdatePersonRequest;
use App\Http\Requests\People\UpdatePersonStatusRequest;
use App\Http\Resources\PersonResource;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PersonController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Person::class);

        $query = Person::query()->with(['user.role', 'bankType'])->orderBy('last_name')->orderBy('first_name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($builder) use ($search) {
                $builder->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('identification_number', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('has_account')) {
            $request->boolean('has_account')
                ? $query->whereHas('user')
                : $query->whereDoesntHave('user');
        }

        return $this->success(
            PersonResource::collection($query->paginate($request->integer('per_page', 15)))->response()->getData(true)
        );
    }

    public function store(StorePersonRequest $request)
    {
        $this->authorize('create', Person::class);

        $person = Person::query()->create($request->validated());
        $this->audit->log('person.created', $person, 'Persona creada.', null, $person->toArray());

        return $this->success(new PersonResource($person->load(['user.role', 'bankType'])), 'Persona creada.', 201);
    }

    public function show(Person $person)
    {
        $this->authorize('view', $person);

        return $this->success(new PersonResource($person->load(['user.role', 'bankType'])));
    }

    public function update(UpdatePersonRequest $request, Person $person)
    {
        $this->authorize('update', $person);

        $old = $person->toArray();
        $person->update($request->validated());

        if ($person->user && $person->wasChanged('email')) {
            $person->user->update(['email' => $person->email]);
        }

        $this->audit->log('person.updated', $person, 'Persona actualizada.', $old, $person->fresh()->toArray());

        return $this->success(new PersonResource($person->fresh()->load(['user.role', 'bankType'])), 'Persona actualizada.');
    }

    public function updateStatus(UpdatePersonStatusRequest $request, Person $person)
    {
        $this->denySelfLockout($person, 'No puede desactivar su propia persona.');
        $this->authorize('changeStatus', $person);

        $old = ['is_active' => $person->is_active];
        $person->update(['is_active' => $request->boolean('is_active')]);

        if (! $person->is_active && $person->user) {
            $person->user->update(['is_active' => false]);
            $person->user->tokens()->delete();
        }

        $this->audit->log('person.status.updated', $person, 'Estado de la persona actualizado.', $old, [
            'is_active' => $person->is_active,
        ]);

        return $this->success(new PersonResource($person->fresh()->load(['user.role', 'bankType'])), 'Estado actualizado.');
    }

    public function createAccount(CreateAccountRequest $request, Person $person)
    {
        $this->denySelfLockout($person, 'No puede modificar el acceso de su propia cuenta.');
        $this->authorize('manageAccount', $person);

        if ($person->user) {
            return ApiResponse::error('Esta persona ya tiene una cuenta de acceso.', 422);
        }

        $role = Role::query()->where('name', $request->validated('role'))->firstOrFail();

        $user = DB::transaction(function () use ($request, $person, $role) {
            $user = User::query()->create([
                'person_id' => $person->id,
                'role_id' => $role->id,
                'email' => $person->email,
                'password' => $request->validated('password'),
                'is_active' => $request->boolean('is_active', true),
            ]);

            $this->audit->log('user.created', $user, 'Cuenta de acceso creada.', null, [
                'person_id' => $person->id,
                'role' => $role->name->value,
            ]);

            return $user;
        });

        return $this->success(new PersonResource($person->fresh()->load(['user.role', 'bankType'])), 'Cuenta creada.', 201);
    }

    public function updateAccount(UpdateAccountRequest $request, Person $person)
    {
        $this->denySelfLockout($person, 'No puede modificar el acceso de su propia cuenta.');
        $this->authorize('manageAccount', $person);

        if (! $person->user) {
            return ApiResponse::error('Esta persona no tiene una cuenta de acceso.', 422);
        }

        $data = [];

        if ($request->filled('role')) {
            $role = Role::query()->where('name', $request->validated('role'))->firstOrFail();
            $data['role_id'] = $role->id;
        }

        if ($request->exists('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $old = $person->user->only(['role_id', 'is_active']);
        $person->user->update($data);

        if (array_key_exists('is_active', $data) && ! $data['is_active']) {
            $person->user->tokens()->delete();
        }

        $this->audit->log('user.updated', $person->user, 'Cuenta de acceso actualizada.', $old, $data);

        return $this->success(new PersonResource($person->fresh()->load(['user.role', 'bankType'])), 'Cuenta actualizada.');
    }

    public function resetPassword(ResetPasswordRequest $request, Person $person)
    {
        $this->denySelfLockout($person, 'No puede modificar el acceso de su propia cuenta.');
        $this->authorize('manageAccount', $person);

        if (! $person->user) {
            return ApiResponse::error('Esta persona no tiene una cuenta de acceso.', 422);
        }

        $person->user->update(['password' => $request->validated('password')]);
        $person->user->tokens()->delete();

        $this->audit->log('user.password.reset', $person->user, 'Contraseña restablecida.');

        return $this->success(null, 'Contraseña restablecida.');
    }

    private function denySelfLockout(Person $person, string $message): void
    {
        if ((int) request()->user()?->person_id === (int) $person->id) {
            abort(403, $message);
        }
    }
}
