<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'No autenticado.');
        }

        if (! $user->is_active) {
            abort(403, 'La cuenta de acceso está desactivada.');
        }

        $user->loadMissing('role');
        $roleName = $user->role?->name instanceof \App\Enums\RoleName
            ? $user->role->name->value
            : $user->role?->name;

        if ($roles !== [] && ! in_array($roleName, $roles, true)) {
            abort(403, 'No tiene permisos para realizar esta acción.');
        }

        return $next($request);
    }
}
